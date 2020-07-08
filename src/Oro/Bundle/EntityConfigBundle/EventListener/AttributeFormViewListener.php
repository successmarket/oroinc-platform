<?php

namespace Oro\Bundle\EntityConfigBundle\EventListener;

use Oro\Bundle\EntityConfigBundle\Attribute\Entity\AttributeFamilyAwareInterface;
use Oro\Bundle\EntityConfigBundle\Attribute\Entity\AttributeGroup;
use Oro\Bundle\EntityConfigBundle\Entity\FieldConfigModel;
use Oro\Bundle\EntityConfigBundle\Manager\AttributeManager;
use Oro\Bundle\UIBundle\Event\BeforeListRenderEvent;
use Oro\Bundle\UIBundle\View\ScrollData;
use Symfony\Component\Form\FormView;
use Twig\Environment;

/**
 * Add attributes to view for entity view and edit pages.
 */
class AttributeFormViewListener
{
    /**
     * @var AttributeManager
     */
    private $attributeManager;

    /**
     * @param AttributeManager $attributeManager
     */
    public function __construct(AttributeManager $attributeManager)
    {
        $this->attributeManager = $attributeManager;
    }

    /**
     * @param BeforeListRenderEvent $event
     */
    public function onEdit(BeforeListRenderEvent $event)
    {
        $entity = $event->getEntity();

        if (!$entity instanceof AttributeFamilyAwareInterface) {
            return;
        }

        $scrollData = $event->getScrollData();
        $groupsData = $this->attributeManager->getGroupsWithAttributes($entity->getAttributeFamily());
        $this->filterGroupAttributes($groupsData, 'form', 'is_enabled');
        $this->addNotEmptyGroupBlocks($scrollData, $groupsData);

        foreach ($groupsData as $groupsDatum) {
            $this->addAttributeEditBlocks($event, $groupsDatum['group'], $groupsDatum['attributes']);
        }

        $this->removeEmptyGroupBlocks($scrollData, $groupsData);
    }

    /**
     * @param ScrollData $scrollData
     * @param array $groups
     */
    private function removeEmptyGroupBlocks(ScrollData $scrollData, array $groups)
    {
        $data = $scrollData->getData();
        if (empty($data[ScrollData::DATA_BLOCKS])) {
            return;
        }

        foreach ($data[ScrollData::DATA_BLOCKS] as $blockId => $data) {
            if (!is_string($blockId)) {
                continue;
            }
            $isEmpty = true;
            if (!empty($data[ScrollData::SUB_BLOCKS])) {
                foreach ($data[ScrollData::SUB_BLOCKS] as $subblockId => $subblockData) {
                    if (!empty($subblockData[ScrollData::DATA])) {
                        $isEmpty = false;
                    }
                }
            }

            if ($isEmpty) {
                $scrollData->removeNamedBlock($blockId);
            }
        }
    }

    /**
     * @param ScrollData $scrollData
     * @param array $groups
     */
    protected function addNotEmptyGroupBlocks(ScrollData $scrollData, array $groups)
    {
        foreach ($groups as $group) {
            if (!empty($group['attributes'])) {
                /** @var AttributeGroup $currentGroup */
                $currentGroup = $group['group'];
                $scrollData->addNamedBlock($currentGroup->getCode(), $currentGroup->getLabel()->getString());
            }
        }
    }

    /**
     * @param BeforeListRenderEvent $event
     * @param AttributeGroup $group
     * @param FieldConfigModel[] $attributes
     */
    protected function addAttributeEditBlocks(BeforeListRenderEvent $event, AttributeGroup $group, array $attributes)
    {
        $scrollData = $event->getScrollData();
        $formView = $event->getFormView();

        foreach ($attributes as $attribute) {
            $fieldId = $attribute->getFieldName();
            $attributeView = $formView->offsetGet($fieldId);

            if (!$attributeView->isRendered()) {
                $html = $this->renderAttributeEditData($event->getEnvironment(), $attributeView, $attribute);

                $subblockId = $scrollData->addSubBlock($group->getCode());
                $scrollData->addSubBlockData($group->getCode(), $subblockId, $html, $fieldId);
            } else {
                $this->moveFieldToBlock($scrollData, $attribute->getFieldName(), $group->getCode());
            }
        }
    }

    /**
     * @param Environment $twig
     * @param FormView $attributeView
     * @param FieldConfigModel $attribute
     * @return string
     */
    protected function renderAttributeEditData(Environment $twig, FormView $attributeView, FieldConfigModel $attribute)
    {
        return $twig->render('OroEntityConfigBundle:Attribute:row.html.twig', ['child' => $attributeView]);
    }

    /**
     * @param BeforeListRenderEvent $event
     */
    public function onViewList(BeforeListRenderEvent $event)
    {
        $entity = $event->getEntity();

        if (!$entity instanceof AttributeFamilyAwareInterface) {
            return;
        }

        $groups = $this->attributeManager->getGroupsWithAttributes($entity->getAttributeFamily());
        $scrollData = $event->getScrollData();
        $this->filterGroupAttributes($groups, 'view', 'is_displayable');
        $this->addNotEmptyGroupBlocks($scrollData, $groups);

        /** @var AttributeGroup $group */
        foreach ($groups as $groupData) {
            $this->addAttributeViewBlocks($event, $groupData['group'], $groupData['attributes']);
        }

        $this->removeEmptyGroupBlocks($scrollData, $groups);
    }

    /**
     * @param BeforeListRenderEvent $event
     * @param AttributeGroup $group
     * @param FieldConfigModel[] $attributes
     */
    protected function addAttributeViewBlocks(BeforeListRenderEvent $event, AttributeGroup $group, array $attributes)
    {
        $scrollData = $event->getScrollData();

        foreach ($attributes as $attribute) {
            $fieldName = $attribute->getFieldName();
            if ($scrollData->hasNamedField($fieldName)) {
                $this->moveFieldToBlock($scrollData, $fieldName, $group->getCode());
                continue;
            }

            $html = $this->renderAttributeViewData($event->getEnvironment(), $event->getEntity(), $attribute);

            $subblockId = $scrollData->addSubBlock($group->getCode());
            $scrollData->addSubBlockData($group->getCode(), $subblockId, $html, $fieldName);
        }
    }

    /**
     * @param Environment $twig
     * @param object $entity
     * @param FieldConfigModel $attribute
     * @return string
     */
    protected function renderAttributeViewData(Environment $twig, $entity, FieldConfigModel $attribute)
    {
        return $twig->render(
            'OroEntityConfigBundle:Attribute:attributeView.html.twig',
            ['entity' => $entity, 'field' => $attribute]
        );
    }

    /**
     * @param ScrollData $scrollData
     * @param string $fieldName
     * @param string $blockId
     */
    protected function moveFieldToBlock(ScrollData $scrollData, $fieldName, $blockId)
    {
        $scrollData->moveFieldToBlock($fieldName, $blockId);
    }

    /**
     * @param array $groups
     * @param string $scope
     * @param string $option
     */
    private function filterGroupAttributes(array &$groups, $scope, $option)
    {
        foreach ($groups as &$group) {
            $group['attributes'] = array_filter(
                $group['attributes'],
                function (FieldConfigModel $attribute) use ($scope, $option) {
                    $attributeScopedConfig = $attribute->toArray($scope);
                    return !empty($attributeScopedConfig[$option]);
                }
            );
        }
    }
}
