<?php

namespace Oro\Bundle\EmailBundle\Controller\Api\Rest;

use FOS\RestBundle\Controller\Annotations as Rest;
use FOS\RestBundle\Controller\Annotations\Get;
use FOS\RestBundle\Controller\Annotations\NamePrefix;
use FOS\RestBundle\Controller\Annotations\QueryParam;
use FOS\RestBundle\Controller\Annotations\RouteResource;
use Nelmio\ApiDocBundle\Annotation\ApiDoc;
use Oro\Bundle\EmailBundle\Cache\EmailCacheManager;
use Oro\Bundle\EmailBundle\Entity\Email;
use Oro\Bundle\EmailBundle\Entity\Manager\EmailApiEntityManager;
use Oro\Bundle\SecurityBundle\Annotation\AclAncestor;
use Oro\Bundle\SoapBundle\Controller\Api\Rest\RestController;
use Oro\Bundle\SoapBundle\Request\Parameters\Filter\StringToArrayParameterFilter;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The api controller for the email related functionality.
 *
 * @RouteResource("email")
 * @NamePrefix("oro_api_")
 */
class EmailController extends RestController
{
    /**
     * Get emails.
     *
     * @QueryParam(
     *      name="page",
     *      requirements="\d+",
     *      nullable=true,
     *      description="Page number, starting from 1. Defaults to 1."
     * )
     * @QueryParam(
     *      name="limit",
     *      requirements="\d+",
     *      nullable=true,
     *      description="Number of items per page. Defaults to 10."
     * )
     * @QueryParam(
     *     name="messageId",
     *     requirements=".+",
     *     nullable=true,
     *     description="The email 'Message-ID' attribute. One or several message ids separated by comma."
     * )
     * @ApiDoc(
     *      description="Get emails",
     *      resource=true
     * )
     * @AclAncestor("oro_email_email_view")
     * @param Request $request
     * @return Response
     */
    public function cgetAction(Request $request)
    {
        $page  = (int)$request->get('page', 1);
        $limit = (int)$request->get('limit', self::ITEMS_PER_PAGE);

        $filterParameters = [
            'messageId' => new StringToArrayParameterFilter()
        ];
        $criteria         = $this->getFilterCriteria(
            $this->getSupportedQueryParameters(__FUNCTION__),
            $filterParameters
        );

        return $this->handleGetListRequest($page, $limit, $criteria);
    }

    /**
     * Get email.
     *
     * @param string $id
     *
     * @Get("/emails/{id}", requirements={"id"="\d+"})
     *
     * @ApiDoc(
     *      description="Get email",
     *      resource=true
     * )
     * @AclAncestor("oro_email_email_view")
     * @return Response
     */
    public function getAction($id)
    {
        return $this->handleGetRequest($id);
    }

    /**
     * Update email.
     *
     * @param int $id The id of the email
     *
     * @Rest\Put(requirements={"id"="\d+"})
     *
     * @ApiDoc(
     *      description="Update email",
     *      resource=true
     * )
     * @AclAncestor("oro_email_email_user_edit")
     * @return Response
     */
    public function putAction(int $id)
    {
        return $this->handleUpdateRequest($id);
    }

    /**
     * Create new email.
     *
     * @ApiDoc(
     *      description="Create new email",
     *      resource=true
     * )
     * @AclAncestor("oro_email_email_user_edit")
     */
    public function postAction()
    {
        return $this->handleCreateRequest();
    }

    /**
     * Get email cache manager
     *
     * @return EmailCacheManager
     */
    protected function getEmailCacheManager()
    {
        return $this->container->get(EmailCacheManager::class);
    }

    /**
     * Get entity manager
     *
     * @return EmailApiEntityManager
     */
    public function getManager()
    {
        return $this->container->get('oro_email.manager.email.api');
    }

    /**
     * {@inheritdoc}
     */
    public function getForm()
    {
        return $this->get('oro_email.form.email.api');
    }

    /**
     * {@inheritdoc}
     */
    public function getFormHandler()
    {
        return $this->get('oro_email.form.handler.email.api');
    }

    /**
     * @param string $attribute
     * @param Email $email
     *
     * @return bool
     */
    protected function assertEmailAccessGranted($attribute, Email $email)
    {
        return $this->isGranted($attribute, $email);
    }
}
