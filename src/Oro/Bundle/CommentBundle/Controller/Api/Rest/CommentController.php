<?php

namespace Oro\Bundle\CommentBundle\Controller\Api\Rest;

use FOS\RestBundle\Controller\Annotations as Rest;
use FOS\RestBundle\Controller\Annotations\NamePrefix;
use FOS\RestBundle\Controller\Annotations\QueryParam;
use FOS\RestBundle\Controller\Annotations\RouteResource;
use Nelmio\ApiDocBundle\Annotation\ApiDoc;
use Oro\Bundle\CommentBundle\Entity\Manager\CommentApiManager;
use Oro\Bundle\SecurityBundle\Annotation\Acl;
use Oro\Bundle\SecurityBundle\Annotation\AclAncestor;
use Oro\Bundle\SoapBundle\Controller\Api\Rest\RestController;
use Oro\Bundle\SoapBundle\Form\Handler\ApiFormHandler;
use Oro\Bundle\SoapBundle\Request\Parameters\Filter\HttpDateTimeParameterFilter;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * API CRUD controller for Comment entity.
 *
 * @RouteResource("commentlist")
 * @NamePrefix("oro_api_")
 */
class CommentController extends RestController
{
    /**
     * Get filtered comment for given entity class name and id
     *
     * @param Request $request
     * @param string  $relationClass Entity class name
     * @param integer $relationId    Entity id
     *
     * @QueryParam(
     *      name="page",
     *      requirements="\d+",
     *      nullable=true,
     *      description="Page number, starting from 1. Default is 1."
     * )
     * @QueryParam(
     *      name="limit",
     *      requirements="\d+",
     *      nullable=true,
     *      description="Number of items per page. defaults to 10."
     * )
     * @QueryParam(
     *     name="createdAt",
     *     requirements="\d{4}(-\d{2}(-\d{2}([T ]\d{2}:\d{2}(:\d{2}(\.\d+)?)?(Z|([-+]\d{2}(:?\d{2})?))?)?)?)?",
     *     nullable=true,
     *     description="Date in RFC 3339 format. For example: 2009-11-05T13:15:30Z, 2008-07-01T22:35:17+08:00"
     * )
     * @QueryParam(
     *     name="updatedAt",
     *     requirements="\d{4}(-\d{2}(-\d{2}([T ]\d{2}:\d{2}(:\d{2}(\.\d+)?)?(Z|([-+]\d{2}(:?\d{2})?))?)?)?)?",
     *     nullable=true,
     *     description="Date in RFC 3339 format. For example: 2009-11-05T13:15:30Z, 2008-07-01T22:35:17+08:00"
     * )
     *
     * @Rest\Get(requirements={"relationId"="\d+"})
     *
     * @ApiDoc(
     *      description="Get filtered comment for given entity class name and id",
     *      resource=true,
     *      statusCodes={
     *          200="Returned when successful",
     *      }
     * )
     * @AclAncestor("oro_comment_view")
     *
     * @return JsonResponse
     */
    public function cgetAction(Request $request, $relationClass, int $relationId)
    {
        $page             = $request->get('page', 1);
        $limit            = $request->get('limit', self::ITEMS_PER_PAGE);
        $dateParamFilter  = new HttpDateTimeParameterFilter();
        $filterParameters = ['createdAt' => $dateParamFilter, 'updatedAt' => $dateParamFilter];
        $filterCriteria   = $this->getFilterCriteria(['createdAt', 'updatedAt'], $filterParameters);

        $result = $this->getManager()->getCommentList($relationClass, $relationId, $page, $limit, $filterCriteria);

        return new JsonResponse($result);
    }

    /**
     * Get comment
     *
     * @param int $id Comment id
     *
     * @Rest\Get(requirements={"id"="\d+"})
     *
     * @ApiDoc(
     *      description="Get comment item",
     *      resource=true
     * )
     * @AclAncestor("oro_comment_view")
     *
     * @return Response
     */
    public function getAction(int $id)
    {
        return $this->handleGetRequest($id);
    }

    /**
     * Create new comment
     *
     * @param string $relationClass
     * @param string $relationId
     *
     * @Rest\Post(requirements={"relationId"="\d+"})
     *
     * @ApiDoc(
     *      description="Create new comment",
     *      resource=true
     * )
     *
     * @AclAncestor("oro_comment_create")
     *
     * @return Response
     */
    public function postAction($relationClass, int $relationId)
    {
        $isProcessed = false;

        $entity    = call_user_func_array(array($this, 'createEntity'), func_get_args());
        $exception = $this->getForm();

        $this->getManager()->setRelationField($entity, $relationClass, $relationId);

        $entity = $this->processForm($entity);

        if ($entity) {
            $view = $this->view(
                $this->getManager()->getEntityViewModel($entity, $relationClass, $relationId),
                Response::HTTP_CREATED
            );
            $isProcessed = true;
        } else {
            $view = $this->view($exception, Response::HTTP_BAD_REQUEST);
        }

        return $this->buildResponse($view, self::ACTION_CREATE, ['success' => $isProcessed, 'entity' => $entity]);
    }

    /**
     * Update comment
     *
     * @param int $id Comment item id
     *
     * @Rest\Put(requirements={"id"="\d+"})
     *
     * @ApiDoc(
     *      description="Update comment",
     *      resource=true
     * )
     * @AclAncestor("oro_comment_update")
     *
     * @return Response
     */
    public function putAction(int $id)
    {
        $entity = $this->getManager()->find($id);

        if ($entity) {
            $entity = $this->processForm($entity);
            if ($entity) {
                $view = $this->view($this->getManager()->getEntityViewModel($entity), Response::HTTP_OK);
            } else {
                $view = $this->view($this->getForm(), Response::HTTP_BAD_REQUEST);
            }
        } else {
            $view = $this->view(null, Response::HTTP_NOT_FOUND);
        }

        return $this->buildResponse($view, self::ACTION_UPDATE, ['id' => $id, 'entity' => $entity]);
    }

    /**
     * Remove Attachment
     *
     * @param int $id Comment item id
     *
     * @Rest\Delete(requirements={"id"="\d+"})
     *
     * @ApiDoc(
     *      description="Remove Attachment",
     *      resource=true
     * )
     * @AclAncestor("oro_comment_update")
     *
     * @return Response
     */
    public function removeAttachmentAction(int $id)
    {
        $entity = $this->getManager()->find($id);

        if ($entity) {
            $entity->setAttachment(null);
            $entity = $this->processForm($entity);
            if ($entity) {
                $view = $this->view($this->getManager()->getEntityViewModel($entity), Response::HTTP_OK);
            } else {
                $view = $this->view($this->getForm(), Response::HTTP_BAD_REQUEST);
            }
        } else {
            $view = $this->view(null, Response::HTTP_NOT_FOUND);
        }

        return $this->buildResponse($view, self::ACTION_UPDATE, ['id' => $id, 'entity' => $entity]);
    }

    /**
     * Delete Comment
     *
     * @param int $id comment id
     *
     * @Rest\Delete(requirements={"id"="\d+"})
     *
     * @ApiDoc(
     *      description="Delete Comment",
     *      resource=true
     * )
     * @Acl(
     *      id="oro_comment_delete",
     *      type="entity",
     *      permission="DELETE",
     *      class="OroCommentBundle:Comment"
     * )
     * @return Response
     */
    public function deleteAction(int $id)
    {
        return $this->handleDeleteRequest($id);
    }

    /**
     * @return FormInterface
     */
    public function getForm()
    {
        return $this->get('oro_comment.form.comment.api');
    }

    /**
     * Get entity Manager
     *
     * @return CommentApiManager
     */
    public function getManager()
    {
        return $this->get('oro_comment.comment.api_manager');
    }

    /**
     * @return ApiFormHandler
     */
    public function getFormHandler()
    {
        return $this->get('oro_comment.api.form.handler');
    }

    /**
     * {@inheritdoc}
     */
    protected function fixFormData(array &$data, $entity)
    {
        parent::fixFormData($data, $entity);

        unset($data['id']);
        unset($data['owner']);
        unset($data['owner_id']);
        unset($data['editor']);
        unset($data['editor_id']);
        unset($data['relationClass']);
        unset($data['relationId']);
        unset($data['createdAt']);
        unset($data['updatedAt']);
        unset($data['editable']);
        unset($data['removable']);
        unset($data['avatarUrl']);

        return true;
    }
}
