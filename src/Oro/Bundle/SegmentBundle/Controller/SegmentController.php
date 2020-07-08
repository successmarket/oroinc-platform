<?php

namespace Oro\Bundle\SegmentBundle\Controller;

use Oro\Bundle\EntityBundle\Provider\EntityProvider;
use Oro\Bundle\EntityConfigBundle\Config\ConfigManager;
use Oro\Bundle\FeatureToggleBundle\Checker\FeatureChecker;
use Oro\Bundle\QueryDesignerBundle\QueryDesigner\Manager;
use Oro\Bundle\SecurityBundle\Annotation\Acl;
use Oro\Bundle\SecurityBundle\Annotation\AclAncestor;
use Oro\Bundle\SegmentBundle\Entity\Manager\StaticSegmentManager;
use Oro\Bundle\SegmentBundle\Entity\Segment;
use Oro\Bundle\SegmentBundle\Form\Handler\SegmentHandler;
use Oro\Bundle\SegmentBundle\Form\Type\SegmentType;
use Oro\Bundle\SegmentBundle\Grid\ConfigurationProvider;
use Oro\Bundle\SegmentBundle\Provider\EntityNameProvider;
use Oro\Bundle\UIBundle\Route\Router;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Covers the CRUD functionality and the additional operation clone for the Segment entity.
 */
class SegmentController extends AbstractController
{
    /**
     * @Route(
     *      "/{_format}",
     *      name="oro_segment_index",
     *      requirements={"_format"="html|json"},
     *      defaults={"_format" = "html"}
     * )
     *
     * @Template
     * @AclAncestor("oro_segment_view")
     * @return array
     */
    public function indexAction()
    {
        return [];
    }

    /**
     * @Route("/view/{id}", name="oro_segment_view", requirements={"id"="\d+"})
     * @Acl(
     *      id="oro_segment_view",
     *      type="entity",
     *      permission="VIEW",
     *      class="OroSegmentBundle:Segment"
     * )
     * @Template
     *
     * @param Segment $entity
     * @return array
     */
    public function viewAction(Segment $entity)
    {
        $this->checkSegment($entity);

        $this->get(EntityNameProvider::class)->setCurrentItem($entity);

        $segmentGroup = $this->get(ConfigManager::class)
            ->getEntityConfig('entity', $entity->getEntity())
            ->get('plural_label');

        $gridName = $entity::GRID_PREFIX . $entity->getId();
        if (!$this->get(ConfigurationProvider::class)->isConfigurationValid($gridName)) {
            // unset grid name if invalid
            $gridName = false;
        }

        return [
            'entity'       => $entity,
            'segmentGroup' => $segmentGroup,
            'gridName'     => $gridName
        ];
    }

    /**
     * @Route("/create", name="oro_segment_create")
     * @Template("OroSegmentBundle:Segment:update.html.twig")
     * @Acl(
     *      id="oro_segment_create",
     *      type="entity",
     *      permission="CREATE",
     *      class="OroSegmentBundle:Segment"
     * )
     */
    public function createAction()
    {
        return $this->update(new Segment());
    }

    /**
     * @Route("/update/{id}", name="oro_segment_update", requirements={"id"="\d+"})
     *
     * @Template
     * @Acl(
     *      id="oro_segment_update",
     *      type="entity",
     *      permission="EDIT",
     *      class="OroSegmentBundle:Segment"
     * )
     *
     * @param Segment $entity
     * @return array
     */
    public function updateAction(Segment $entity)
    {
        $this->checkSegment($entity);

        return $this->update($entity);
    }

    /**
     * @Route("/clone/{id}", name="oro_segment_clone", requirements={"id"="\d+"})
     * @Template("OroSegmentBundle:Segment:update.html.twig")
     * @AclAncestor("oro_segment_create")
     *
     * @param Segment $entity
     * @return array
     */
    public function cloneAction(Segment $entity)
    {
        $this->checkSegment($entity);

        $clonedEntity = clone $entity;
        $clonedEntity->setName(
            $this->get(TranslatorInterface::class)->trans(
                'oro.segment.action.clone.name_format',
                [
                    '{name}' => $clonedEntity->getName()
                ]
            )
        );

        return $this->update($clonedEntity);
    }

    /**
     * @Route("/refresh/{id}", name="oro_segment_refresh", requirements={"id"="\d+"})
     * @AclAncestor("oro_segment_update")
     *
     * @param Segment $entity
     * @return RedirectResponse
     */
    public function refreshAction(Segment $entity)
    {
        $this->checkSegment($entity);

        if ($entity->isStaticType()) {
            $this->get(StaticSegmentManager::class)->run($entity);

            $this->get('session')->getFlashBag()->add(
                'success',
                $this->get(TranslatorInterface::class)->trans('oro.segment.refresh_dialog.success')
            );
        }

        return $this->redirectToRoute('oro_segment_view', ['id' => $entity->getId()]);
    }

    /**
     * @param Segment $entity
     *
     * @return array
     */
    protected function update(Segment $entity)
    {
        $form = $this->get('form.factory')
            ->createNamed('oro_segment_form', SegmentType::class);

        if ($this->get(SegmentHandler::class)->process($form, $entity)) {
            $this->get('session')->getFlashBag()->add(
                'success',
                $this->get(TranslatorInterface::class)->trans('oro.segment.entity.saved')
            );

            return $this->get(Router::class)->redirect($entity);
        }

        return [
            'entity'   => $entity,
            'form'     => $form->createView(),
            'entities' => $this->get('oro_segment.entity_provider')->getEntities(),
            'metadata' => $this->get(Manager::class)->getMetadata('segment')
        ];
    }

    /**
     * @param Segment $segment
     */
    protected function checkSegment(Segment $segment)
    {
        if ($segment->getEntity() &&
            !$this->getFeatureChecker()->isResourceEnabled($segment->getEntity(), 'entities')
        ) {
            throw $this->createNotFoundException();
        }
    }

    /**
     * @return FeatureChecker
     */
    protected function getFeatureChecker()
    {
        return $this->get(FeatureChecker::class);
    }

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedServices()
    {
        return array_merge(
            parent::getSubscribedServices(),
            [
                'oro_segment.entity_provider' => EntityProvider::class,
                ConfigManager::class,
                FeatureChecker::class,
                ConfigurationProvider::class,
                TranslatorInterface::class,
                Router::class,
                StaticSegmentManager::class,
                SegmentHandler::class,
                Manager::class,
                EntityNameProvider::class,
            ]
        );
    }
}
