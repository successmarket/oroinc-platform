<?php

namespace Oro\Bundle\WsseAuthenticationBundle\Security\Core\Authentication\Provider;

use Doctrine\Common\Collections\Collection;
use Oro\Bundle\SecurityBundle\Exception\BadUserOrganizationException;
use Oro\Bundle\UserBundle\Security\AdvancedApiUserInterface;
use Oro\Bundle\UserBundle\Security\UserApiKeyInterface;
use Oro\Bundle\WsseAuthenticationBundle\Exception\NonceExpiredException;
use Oro\Bundle\WsseAuthenticationBundle\Security\WsseTokenFactoryInterface;
use Psr\SimpleCache\CacheInterface;
use Symfony\Component\Cache\PruneableInterface;
use Symfony\Component\Security\Core\Authentication\Provider\AuthenticationProviderInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken as Token;
use Symfony\Component\Security\Core\Encoder\PasswordEncoderInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Core\Exception\CredentialsExpiredException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

/**
 * Authentication provider for WSSE tokens.
 */
class WsseAuthenticationProvider implements AuthenticationProviderInterface
{
    /** @var UserCheckerInterface */
    private $userChecker;

    /** @var WsseTokenFactoryInterface */
    private $wsseTokenFactory;

    /** @var UserProviderInterface */
    private $userProvider;

    /** @var string */
    private $providerKey;

    /** @var PasswordEncoderInterface */
    private $encoder;

    /** @var CacheInterface */
    private $nonceCache;

    /** @var int */
    private $lifetime;

    /** @var string */
    private $dateFormat;

    /**
     * @param UserCheckerInterface $userChecker A UserChecketerInterface instance
     * @param WsseTokenFactoryInterface $wsseTokenFactory
     * @param UserProviderInterface $userProvider An UserProviderInterface instance
     * @param string $providerKey The provider key
     * @param PasswordEncoderInterface $encoder A PasswordEncoderInterface instance
     * @param CacheInterface $nonceCache The nonce cache
     * @param int $lifetime The lifetime
     * @param string $dateFormat The date format
     */
    public function __construct(
        UserCheckerInterface $userChecker,
        WsseTokenFactoryInterface $wsseTokenFactory,
        UserProviderInterface $userProvider,
        $providerKey,
        PasswordEncoderInterface $encoder,
        CacheInterface $nonceCache,
        $lifetime = 300,
        $dateFormat = '/^([\+-]?\d{4}(?!\d{2}\b))((-?)((0[1-9]|1[0-2])(\3([12]\d|0[1-9]|3[01]))?|W([0-4]\d|5[0-2])'
        . '(-?[1-7])?|(00[1-9]|0[1-9]\d|[12]\d{2}|3([0-5]\d|6[1-6])))([T\s]((([01]\d|2[0-3])((:?)[0-5]\d)?|24\:?0'
        . '0)([\.,]\d+(?!:))?)?(\17[0-5]\d([\.,]\d+)?)?([zZ]|([\+-])([01]\d|2[0-3]):?([0-5]\d)?)?)?)?$/'
    ) {
        if (empty($providerKey)) {
            throw new \InvalidArgumentException('$providerKey must not be empty.');
        }

        $this->userChecker = $userChecker;
        $this->wsseTokenFactory = $wsseTokenFactory;
        $this->userProvider = $userProvider;
        $this->providerKey = $providerKey;
        $this->encoder = $encoder;
        $this->nonceCache = $nonceCache;
        $this->lifetime = $lifetime;
        $this->dateFormat = $dateFormat;
    }

    /**
     * {@inheritdoc}
     */
    public function supports(TokenInterface $token): bool
    {
        return $token instanceof Token &&
            $token->hasAttribute('nonce') &&
            $token->hasAttribute('created') &&
            $this->providerKey === $token->getProviderKey();
    }

    /**
     * {@inheritdoc}
     */
    public function authenticate(TokenInterface $token)
    {
        $user = $this->userProvider->loadUserByUsername($token->getUsername());
        if ($user) {
            $this->userChecker->checkPreAuth($user);
            $secret = $this->getSecret($user);
            if ($secret instanceof Collection) {
                $validUserApi = $this->getValidUserApi($token, $secret);
                if ($validUserApi) {
                    $authenticatedToken = $this->wsseTokenFactory->create(
                        $user,
                        $token->getCredentials(),
                        $this->providerKey,
                        $user->getRoles()
                    );
                    $authenticatedToken->setOrganization($validUserApi->getOrganization());

                    if ($this->nonceCache instanceof PruneableInterface) {
                        $this->nonceCache->prune();
                    }

                    return $authenticatedToken;
                }
            }
        }

        throw new AuthenticationException('WSSE authentication failed.');
    }

    /**
     * Get valid UserApi for given token
     *
     * @param TokenInterface $token
     * @param Collection $secrets
     *
     * @return UserApiKeyInterface
     */
    private function getValidUserApi(
        TokenInterface $token,
        Collection $secrets
    ): ?UserApiKeyInterface {
        $currentIteration = 0;
        $nonce = $token->getAttribute('nonce');
        $secretsCount = $secrets->count();

        /** @var UserApiKeyInterface $userApi */
        foreach ($secrets as $userApi) {
            $currentIteration++;
            $secret = $userApi->getApiKey();
            $isSecretValid = false;
            if ($secret) {
                $isSecretValid = $this->validateDigest(
                    $token->getCredentials(),
                    $nonce,
                    $token->getAttribute('created'),
                    $secret,
                    ''
                );
            }

            if ($isSecretValid) {
                if (!$userApi->getOrganization()->isEnabled()) {
                    throw new BadUserOrganizationException('Organization is not active.');
                }
                if (!$userApi->isEnabled()) {
                    throw new BadCredentialsException('Wrong API key.');
                }
            }

            // delete nonce from cache because user have another api keys
            if (!$isSecretValid && $secretsCount !== $currentIteration) {
                $this->nonceCache->delete($nonce);
            }

            if ($isSecretValid) {
                return $userApi;
            }
        }

        return null;
    }

    /**
     * @param AdvancedApiUserInterface $user
     *
     * @return Collection|null
     */
    private function getSecret(AdvancedApiUserInterface $user): ?Collection
    {
        return $user->getApiKeys();
    }

    /**
     * @param string $created
     *
     * @return bool
     */
    private function isTokenExpired(string $created): bool
    {
        return ($this->lifetime === -1)
            ? false
            : strtotime($this->getCurrentTime()) - strtotime($created) > $this->lifetime;
    }

    /**
     * @param string $digest
     * @param string $nonce
     * @param string $created
     * @param string $secret
     * @param string $salt
     *
     * @return bool
     */
    private function validateDigest(string $digest, string $nonce, string $created, string $secret, string $salt)
    {
        if (!$this->isFormattedCorrectly($created)) {
            throw new BadCredentialsException('Incorrectly formatted "created" in token.');
        }

        if ($this->isTokenFromFuture($created)) {
            throw new BadCredentialsException('Future token detected.');
        }

        if ($this->isTokenExpired($created)) {
            throw new CredentialsExpiredException('Token has expired.');
        }

        if ($this->nonceCache->has($nonce)) {
            throw new NonceExpiredException('Previously used nonce detected.');
        }

        $this->nonceCache->set($nonce, strtotime($this->getCurrentTime()), $this->lifetime);

        $expected = $this->encoder->encodePassword(sprintf('%s%s%s', base64_decode($nonce), $created, $secret), $salt);

        return hash_equals($expected, $digest);
    }

    /**
     * @return string
     */
    private function getCurrentTime(): string
    {
        return gmdate(DATE_ATOM);
    }

    /**
     * @param string $created
     *
     * @return bool
     */
    private function isTokenFromFuture(string $created): bool
    {
        return strtotime($created) > strtotime($this->getCurrentTime());
    }

    /**
     * @param string $created
     *
     * @return bool
     */
    private function isFormattedCorrectly($created): bool
    {
        return (bool)preg_match($this->dateFormat, $created);
    }
}
