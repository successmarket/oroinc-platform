<?php

namespace Oro\Bundle\SecurityBundle\Tests\Unit\Provider\Console;

use Doctrine\Common\Persistence\ManagerRegistry;
use Oro\Bundle\OrganizationBundle\Entity\Organization;
use Oro\Bundle\OrganizationBundle\Entity\Repository\OrganizationRepository;
use Oro\Bundle\SecurityBundle\Authentication\Token\ConsoleToken;
use Oro\Bundle\SecurityBundle\Provider\Console\ConsoleContextGlobalOptionsProvider;
use Oro\Bundle\UserBundle\Entity\Repository\UserRepository;
use Oro\Bundle\UserBundle\Entity\User;
use Oro\Bundle\UserBundle\Entity\UserManager;
use Oro\Component\Testing\Unit\TestContainerBuilder;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\HelperSet;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Role\Role;

class ConsoleContextGlobalOptionsProviderTest extends \PHPUnit\Framework\TestCase
{
    /** @var ManagerRegistry|\PHPUnit\Framework\MockObject\MockObject */
    private $doctrine;

    /** @var TokenStorage */
    private $tokenStorage;

    /** @var UserManager|\PHPUnit\Framework\MockObject\MockObject */
    private $userManager;

    /** @var ConsoleContextGlobalOptionsProvider */
    private $provider;

    protected function setUp(): void
    {
        $this->doctrine = $this->createMock(ManagerRegistry::class);
        $this->tokenStorage = new TokenStorage();
        $this->userManager = $this->createMock(UserManager::class);

        $container = TestContainerBuilder::create()
            ->add('doctrine', $this->doctrine)
            ->add('security.token_storage', $this->tokenStorage)
            ->add('oro_user.manager', $this->userManager)
            ->getContainer($this);

        $this->provider = new ConsoleContextGlobalOptionsProvider($container);
    }

    /**
     * @return UserRepository|\PHPUnit\Framework\MockObject\MockObject
     */
    private function getUserRepository()
    {
        $repository = $this->createMock(UserRepository::class);
        $this->doctrine->expects($this->once())
            ->method('getRepository')
            ->with(User::class)
            ->willReturn($repository);

        return $repository;
    }

    /**
     * @return OrganizationRepository|\PHPUnit\Framework\MockObject\MockObject
     */
    private function getOrganizationRepository()
    {
        $repository = $this->createMock(OrganizationRepository::class);
        $this->doctrine->expects($this->once())
            ->method('getRepository')
            ->with(Organization::class)
            ->willReturn($repository);

        return $repository;
    }

    public function testAddGlobalOptions()
    {
        $inputDefinition = new InputDefinition();
        $application = $this->createMock(Application::class);
        $application->expects($this->any())
            ->method('getDefinition')
            ->willReturn($inputDefinition);
        $application->expects($this->once())
            ->method('getHelperSet')
            ->willReturn(new HelperSet());

        $commandDefinition = new InputDefinition();
        $command = new Command('test');
        $command->setApplication($application);
        $command->setDefinition($commandDefinition);

        $this->provider->addGlobalOptions($command);
        $this->assertEquals(
            [
                ConsoleContextGlobalOptionsProvider::OPTION_USER,
                ConsoleContextGlobalOptionsProvider::OPTION_ORGANIZATION,
            ],
            array_keys($command->getApplication()->getDefinition()->getOptions())
        );
        $this->assertEquals(
            [
                ConsoleContextGlobalOptionsProvider::OPTION_USER,
                ConsoleContextGlobalOptionsProvider::OPTION_ORGANIZATION,
            ],
            array_keys($command->getDefinition()->getOptions())
        );
    }

    public function testResolveGlobalOptionsWhenNoUserAndOrganization()
    {
        /** @var InputInterface|\PHPUnit\Framework\MockObject\MockObject $input */
        $input = $this->createMock(InputInterface::class);
        $input->expects($this->exactly(2))
            ->method('getParameterOption')
            ->withConsecutive(
                ['--' . ConsoleContextGlobalOptionsProvider::OPTION_USER],
                ['--' . ConsoleContextGlobalOptionsProvider::OPTION_ORGANIZATION]
            )
            ->willReturn(null, null);

        $this->provider->resolveGlobalOptions($input);
    }

    public function testResolveGlobalOptionsWhenNoUser()
    {
        $organizationId = 777;
        /** @var InputInterface|\PHPUnit\Framework\MockObject\MockObject $input */
        $input = $this->createMock(InputInterface::class);
        $input->expects($this->exactly(2))
            ->method('getParameterOption')
            ->withConsecutive(
                ['--' . ConsoleContextGlobalOptionsProvider::OPTION_USER],
                ['--' . ConsoleContextGlobalOptionsProvider::OPTION_ORGANIZATION]
            )
            ->willReturn(null, $organizationId);

        $this->provider->resolveGlobalOptions($input);
    }

    public function testResolveGlobalOptionsWhenUserIsNotFound()
    {
        $userId = 777;
        /** @var InputInterface|\PHPUnit\Framework\MockObject\MockObject $input */
        $input = $this->createMock(InputInterface::class);
        $input->expects($this->exactly(2))
            ->method('getParameterOption')
            ->withConsecutive(
                ['--' . ConsoleContextGlobalOptionsProvider::OPTION_USER],
                ['--' . ConsoleContextGlobalOptionsProvider::OPTION_ORGANIZATION]
            )
            ->willReturn($userId, null);

        $repository = $this->getUserRepository();
        $repository->expects($this->once())
            ->method('find')
            ->with($userId)
            ->willReturn(null);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(sprintf('Can\'t find user with identifier %s', $userId));
        $this->provider->resolveGlobalOptions($input);
    }

    public function testResolveGlobalOptionsWhenUserIsIntAndNoOrganization()
    {
        $userId = 777;
        /** @var InputInterface|\PHPUnit\Framework\MockObject\MockObject $input */
        $input = $this->createMock(InputInterface::class);
        $input->expects($this->exactly(2))
            ->method('getParameterOption')
            ->withConsecutive(
                ['--' . ConsoleContextGlobalOptionsProvider::OPTION_USER],
                ['--' . ConsoleContextGlobalOptionsProvider::OPTION_ORGANIZATION]
            )
            ->willReturn($userId, null);

        /** @var Role $role */
        $role = $this->createMock(Role::class);
        $user = new User();
        $user->addRole($role);
        $repository = $this->getUserRepository();
        $repository->expects($this->once())
            ->method('find')
            ->with($userId)
            ->willReturn($user);

        $expectedToken = new ConsoleToken([$role]);
        $expectedToken->setUser($user);

        $this->provider->resolveGlobalOptions($input);
        $this->assertEquals($expectedToken, $this->tokenStorage->getToken());
    }

    public function testResolveGlobalOptionsWhenUserIsStringAndNoOrganization()
    {
        $username = 'username';
        /** @var InputInterface|\PHPUnit\Framework\MockObject\MockObject $input */
        $input = $this->createMock(InputInterface::class);
        $input->expects($this->exactly(2))
            ->method('getParameterOption')
            ->withConsecutive(
                ['--' . ConsoleContextGlobalOptionsProvider::OPTION_USER],
                ['--' . ConsoleContextGlobalOptionsProvider::OPTION_ORGANIZATION]
            )
            ->willReturn($username, null);

        /** @var Role $role */
        $role = $this->createMock(Role::class);
        $user = new User();
        $user->addRole($role);
        $this->userManager->expects($this->once())
            ->method('findUserByUsernameOrEmail')
            ->with($username)
            ->willReturn($user);

        $expectedToken = new ConsoleToken([$role]);
        $expectedToken->setUser($user);

        $this->provider->resolveGlobalOptions($input);
        $this->assertEquals($expectedToken, $this->tokenStorage->getToken());
    }

    public function testResolveGlobalOptionsWhenUserIsStringAndOrganizationIsNotFound()
    {
        $username = 'username';
        $organizationId = 777;
        /** @var InputInterface|\PHPUnit\Framework\MockObject\MockObject $input */
        $input = $this->createMock(InputInterface::class);
        $input->expects($this->exactly(2))
            ->method('getParameterOption')
            ->withConsecutive(
                ['--' . ConsoleContextGlobalOptionsProvider::OPTION_USER],
                ['--' . ConsoleContextGlobalOptionsProvider::OPTION_ORGANIZATION]
            )
            ->willReturn($username, $organizationId);

        $user = new User();
        $this->userManager->expects($this->once())
            ->method('findUserByUsernameOrEmail')
            ->with($username)
            ->willReturn($user);

        $organizationRepository = $this->getOrganizationRepository();
        $organizationRepository->expects($this->once())
            ->method('find')
            ->with($organizationId)
            ->willReturn(null);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(sprintf('Can\'t find organization with identifier %s', $organizationId));
        $this->provider->resolveGlobalOptions($input);
    }

    public function testResolveGlobalOptionsWhenUserIsStringAndOrganizationIsNotEnabled()
    {
        $username = 'username';
        $organizationId = 555;
        /** @var InputInterface|\PHPUnit\Framework\MockObject\MockObject $input */
        $input = $this->createMock(InputInterface::class);
        $input->expects($this->exactly(2))
            ->method('getParameterOption')
            ->withConsecutive(
                ['--' . ConsoleContextGlobalOptionsProvider::OPTION_USER],
                ['--' . ConsoleContextGlobalOptionsProvider::OPTION_ORGANIZATION]
            )
            ->willReturn($username, $organizationId);

        $user = new User();
        $this->userManager->expects($this->once())
            ->method('findUserByUsernameOrEmail')
            ->with($username)
            ->willReturn($user);

        $organization = new Organization();
        $organization->setEnabled(false);
        $organization->setName('testorg');
        $organizationRepository = $this->getOrganizationRepository();
        $organizationRepository->expects($this->once())
            ->method('find')
            ->with($organizationId)
            ->willReturn($organization);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(sprintf('Organization %s is not enabled', $organization->getName()));
        $this->provider->resolveGlobalOptions($input);
    }

    public function testResolveGlobalOptionsWhenUserNotFromCurrentOrganization()
    {
        $username = 'username';
        $organizationId = 555;
        /** @var InputInterface|\PHPUnit\Framework\MockObject\MockObject $input */
        $input = $this->createMock(InputInterface::class);
        $input->expects($this->exactly(2))
            ->method('getParameterOption')
            ->withConsecutive(
                ['--' . ConsoleContextGlobalOptionsProvider::OPTION_USER],
                ['--' . ConsoleContextGlobalOptionsProvider::OPTION_ORGANIZATION]
            )
            ->willReturn($username, $organizationId);

        $organization = new Organization();
        $organization->setEnabled(true);
        $organization->setName('testneworg');
        $organizationRepository = $this->getOrganizationRepository();
        $organizationRepository->expects($this->once())
            ->method('find')
            ->with($organizationId)
            ->willReturn($organization);

        $user = new User();
        $user->setUsername('testnewusername');
        $this->userManager->expects($this->once())
            ->method('findUserByUsernameOrEmail')
            ->with($username)
            ->willReturn($user);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(sprintf(
            'User %s is not in organization %s',
            $user->getUsername(),
            $organization->getName()
        ));
        $this->provider->resolveGlobalOptions($input);
    }

    public function testResolveGlobalOptions()
    {
        $username = 'username';
        $organizationId = 555;
        /** @var InputInterface|\PHPUnit\Framework\MockObject\MockObject $input */
        $input = $this->createMock(InputInterface::class);
        $input->expects($this->exactly(2))
            ->method('getParameterOption')
            ->withConsecutive(
                ['--' . ConsoleContextGlobalOptionsProvider::OPTION_USER],
                ['--' . ConsoleContextGlobalOptionsProvider::OPTION_ORGANIZATION]
            )
            ->willReturn($username, $organizationId);

        $organization = new Organization();
        $organization->setEnabled(true);
        $organization->setName('testneworg');
        $organizationRepository = $this->getOrganizationRepository();
        $organizationRepository->expects($this->once())
            ->method('find')
            ->with($organizationId)
            ->willReturn($organization);

        /** @var Role $role */
        $role = $this->createMock(Role::class);
        $user = new User();
        $user->setUsername('testnewusername');
        $user->addRole($role);
        $user->addOrganization($organization);
        $this->userManager->expects($this->once())
            ->method('findUserByUsernameOrEmail')
            ->with($username)
            ->willReturn($user);

        $expectedToken = new ConsoleToken([$role]);
        $expectedToken->setUser($user);
        $expectedToken->setOrganization($organization);

        $this->provider->resolveGlobalOptions($input);
        $this->assertEquals($expectedToken, $this->tokenStorage->getToken());
    }
}
