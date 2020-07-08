<?php

namespace Oro\Bundle\DraftBundle\Helper;

use Oro\Bundle\DraftBundle\Entity\DraftableInterface;
use Oro\Bundle\EntityConfigBundle\Provider\ConfigProvider;
use Oro\Bundle\UIBundle\Route\Router;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Acl\Util\ClassUtils;

/**
 * Responsible for functionality that can indicate draft state and ways to interact with the draft.
 * It should not implement any logic that could change the state of the draft.
 */
class DraftHelper
{
    public const SAVE_AS_DRAFT_ACTION = 'save_as_draft';

    /**
     * @var RequestStack
     */
    private $requestStack;

    /**
     * @var ConfigProvider
     */
    private $draftProvider;

    /**
     * @param RequestStack $requestStack
     * @param ConfigProvider $draftProvider
     */
    public function __construct(RequestStack $requestStack, ConfigProvider $draftProvider)
    {
        $this->requestStack = $requestStack;
        $this->draftProvider = $draftProvider;
    }

    /**
     * @return bool
     */
    public function isSaveAsDraftAction(): bool
    {
        if (!$this->requestStack->getMasterRequest()) {
            return false;
        }

        $action = $this->requestStack->getMasterRequest()->request->get(Router::ACTION_PARAMETER);

        return self::SAVE_AS_DRAFT_ACTION === $action;
    }

    /**
     * @param DraftableInterface $object
     *
     * @return bool
     */
    public static function isDraft(DraftableInterface $object): bool
    {
        return null != $object->getDraftUuid();
    }

    /**
     * @param DraftableInterface $source
     *
     * @return array
     */
    public function getDraftableProperties(DraftableInterface $source): array
    {
        $className = ClassUtils::getRealClass($source);
        $draftConfigs = $this->draftProvider->getConfigs($className, true);
        $fields = [];
        foreach ($draftConfigs as $config) {
            if ($config->is('draftable')) {
                $fields[] = $config->getId()->getFieldName();
            }
        }

        return $fields;
    }
}
