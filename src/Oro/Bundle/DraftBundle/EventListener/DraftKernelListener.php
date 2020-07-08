<?php

namespace Oro\Bundle\DraftBundle\EventListener;

use Oro\Bundle\DraftBundle\Entity\DraftableInterface;
use Oro\Bundle\DraftBundle\Helper\DraftHelper;
use Oro\Bundle\DraftBundle\Manager\DraftManager;
use Symfony\Component\HttpKernel\Event\FilterControllerArgumentsEvent;

/**
 * This class is part of the basic logic of the draft(Save as draft from update page).
 * Responsible for creating draft, use origin entity or draft, taking into account the changes that are made in form.
 * Listener receives the controller original argument, replaces it with a draft, and inject the argument to the
 * controller again. Changing arguments allows to reuse existing logic to guarantee the integrity of created
 * draft entity (CRUD operation, validation, extensions, etc).
 */
class DraftKernelListener
{
    /**
     * @var DraftManager
     */
    private $draftManager;

    /**
     * @var DraftHelper
     */
    private $draftHelper;

    /**
     * @param DraftManager $draftManager
     * @param DraftHelper $draftHelper
     */
    public function __construct(DraftManager $draftManager, DraftHelper $draftHelper)
    {
        $this->draftManager = $draftManager;
        $this->draftHelper = $draftHelper;
    }

    /**
     * @param FilterControllerArgumentsEvent $event
     */
    public function onKernelControllerArguments(FilterControllerArgumentsEvent $event): void
    {
        if ($event->isMasterRequest() && $this->draftHelper->isSaveAsDraftAction()) {
            $arguments = $this->updateArguments($event->getArguments());
            $event->setArguments($arguments);
        }
    }

    /**
     * @param array $arguments
     *
     * @return array
     */
    private function updateArguments(array $arguments = []): array
    {
        return array_map(function ($argument) {
            return $argument instanceof DraftableInterface ? $this->draftManager->createDraft($argument) : $argument;
        }, $arguments);
    }
}
