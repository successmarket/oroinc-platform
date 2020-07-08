<?php

namespace Oro\Bundle\ApiBundle\Util;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Oro\Bundle\ActionBundle\Exception\ForbiddenActionGroupException;
use Oro\Bundle\ActionBundle\Model\ActionData;
use Oro\Bundle\ActionBundle\Model\ActionGroupRegistry;
use Oro\Bundle\ApiBundle\Model\Error;
use Oro\Bundle\ApiBundle\Processor\NormalizeResultContext;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Provides functionality to execute action groups from API processors.
 * @see \Oro\Bundle\ActionBundle\Action\RunActionGroup
 */
class ActionGroupExecutor
{
    /** @var ActionGroupRegistry */
    private $actionGroupRegistry;

    /** @var TranslatorInterface */
    private $translator;

    /**
     * @param ActionGroupRegistry $actionGroupRegistry
     * @param TranslatorInterface $translator
     */
    public function __construct(
        ActionGroupRegistry $actionGroupRegistry,
        TranslatorInterface $translator
    ) {
        $this->actionGroupRegistry = $actionGroupRegistry;
        $this->translator = $translator;
    }

    /**
     * Executes the given action group.
     *
     * @param string                 $name
     * @param ActionData             $data
     * @param NormalizeResultContext $context
     * @param string|null            $errorTitle
     *
     * @return bool
     */
    public function execute(
        string $name,
        ActionData $data,
        NormalizeResultContext $context,
        string $errorTitle = null
    ): bool {
        $actionGroup = $this->actionGroupRegistry->get($name);
        $errors = new ArrayCollection();
        try {
            $actionGroup->execute($data, $errors);
        } catch (ForbiddenActionGroupException $e) {
            if ($errors->isEmpty()) {
                $errors->add(['translatedMessage' => $e->getMessage()]);
            }
        }
        if (!$errors->isEmpty()) {
            $this->processErrors($errors, $context, $errorTitle);
        }

        return $errors->isEmpty();
    }

    /**
     * @param Collection             $errors
     * @param NormalizeResultContext $context
     * @param string|null            $errorTitle
     */
    private function processErrors(
        Collection $errors,
        NormalizeResultContext $context,
        string $errorTitle = null
    ): void {
        if (null === $errorTitle) {
            $errorTitle = 'action constraint';
        }
        foreach ($errors as $error) {
            $context->addError(Error::createValidationError(
                $errorTitle,
                $this->getErrorDetail($error)
            ));
        }
    }

    /**
     * @param array $error
     *
     * @return string
     */
    private function getErrorDetail(array $error): string
    {
        return $error['translatedMessage']
            ?? $this->translator->trans($error['message'], $error['parameters']);
    }
}
