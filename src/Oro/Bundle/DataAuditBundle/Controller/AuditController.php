<?php

namespace Oro\Bundle\DataAuditBundle\Controller;

use Oro\Bundle\SecurityBundle\Annotation\Acl;
use Oro\Bundle\SecurityBundle\Annotation\AclAncestor;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;

/**
 * Audits list and audit history actions
 */
class AuditController extends Controller
{
    /**
     * @Route(
     *      "/{_format}",
     *      name="oro_dataaudit_index",
     *      requirements={"_format"="html|json"},
     *      defaults={"_format" = "html"}
     * )
     * @Template
     * @AclAncestor("oro_dataaudit_view")
     * @param Request $request
     * @return array
     */
    public function indexAction(Request $request)
    {
        return [];
    }

    /**
     * @Route(
     *      "/history/{entity}/{id}/{_format}",
     *      name="oro_dataaudit_history",
     *      requirements={"entity"="[a-zA-Z0-9_]+", "id"="[a-zA-Z0-9_-]+"},
     *      defaults={"entity"="entity", "id"=0, "_format" = "html"}
     * )
     * @Template
     * @Acl(
     *      id="oro_dataaudit_view",
     *      type="entity",
     *      class="OroDataAuditBundle:AbstractAudit",
     *      permission="VIEW"
     * )
     * @param string $entity
     * @param string|int $id
     * @return array
     */
    public function historyAction($entity, $id)
    {
        return array(
            'gridName' => 'audit-history-grid',
            'entityClass' => $entity,
            'entityId' => $id,
        );
    }
}
