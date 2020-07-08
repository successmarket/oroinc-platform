<?php

namespace Oro\Bundle\ActionBundle\Controller;

use Doctrine\Common\Collections\Collection;
use Oro\Bundle\ActionBundle\Handler\ExecuteOperationHandler;
use Oro\Bundle\ActionBundle\Handler\ExecuteOperationResult;
use Oro\Bundle\ActionBundle\Model\Operation;
use Oro\Bundle\ActionBundle\Model\OperationRegistry;
use Oro\Bundle\SecurityBundle\Annotation\AclAncestor;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Ajax Action execution controller
 */
class AjaxController extends AbstractController
{
    /**
     * @Route("/operation/execute/{operationName}", name="oro_action_operation_execute", methods={"POST"})
     * @AclAncestor("oro_action")
     *
     * @param Request $request
     * @param string  $operationName
     *
     * @return Response
     */
    public function executeAction(Request $request, $operationName): Response
    {
        $operation = $this->get(OperationRegistry::class)->findByName($operationName);
        if (!$operation instanceof Operation) {
            $message = sprintf('Operation with name "%s" not found', $operationName);

            $routeName = $request->get('route');
            if (null !== $routeName && !$request->isXmlHttpRequest()) {
                return $this->handleFailedNonAjaxResponse($message, $routeName);
            }

            return new JsonResponse(
                [
                    'message' => $message,
                    'success' => false
                ],
                Response::HTTP_NOT_FOUND
            );
        }
        $executionResult = $this->get(ExecuteOperationHandler::class)->process($operation);

        return $this->handleExecutionResult($executionResult, $request);
    }

    /**
     * @param ExecuteOperationResult $result
     * @param Request                $request
     *
     * @return Response
     * @throws \InvalidArgumentException
     */
    protected function handleExecutionResult(ExecuteOperationResult $result, Request $request): Response
    {
        $validationErrors = $result->getValidationErrors();
        $actionData = $result->getActionData();
        $success = $result->isSuccess();
        $response   = [
            'success'    => $success,
            'message'    => $result->getExceptionMessage(),
            'messages'   => $this->prepareMessages($validationErrors),
            'pageReload' => $result->isPageReload()
        ];
        if (!$success) {
            $response['refreshGrid'] = $actionData->getRefreshGrid();
            $routeName = $request->get('route');
            if (null !== $routeName && !$request->isXmlHttpRequest()) {
                return $this->handleFailedNonAjaxResponse($response['message'], $routeName);
            }
        } else {
            if (!$response['pageReload'] || $actionData->getRefreshGrid()) {
                $response['refreshGrid'] = $actionData->getRefreshGrid();
                $response['flashMessages'] = $this->get(SessionInterface::class)->getFlashBag()->all();
            } elseif ($actionData->getRedirectUrl()) {
                if ($request->isXmlHttpRequest()) {
                    $response['redirectUrl'] = $actionData->getRedirectUrl();
                    if ($actionData->isNewTab() === true) {
                        $response['newTab'] = $actionData->isNewTab();
                        $response['pageReload'] = false;
                    }
                } else {
                    return $this->redirect($actionData->getRedirectUrl());
                }
            }
        }

        return new JsonResponse($response, $result->getCode());
    }

    /**
     * @param Collection $messages
     *
     * @return array
     * @throws \InvalidArgumentException
     */
    protected function prepareMessages(Collection $messages): array
    {
        $translator = $this->get(TranslatorInterface::class);
        $result = [];
        foreach ($messages as $message) {
            $result[] = $translator->trans($message['message'], $message['parameters']);
        }

        return $result;
    }

    /**
     * Handle failed response non ajax requests
     *
     * @param string $message
     * @param string $routeName
     *
     * @return RedirectResponse
     */
    protected function handleFailedNonAjaxResponse(string $message, string $routeName): RedirectResponse
    {
        $this->get(SessionInterface::class)->getFlashBag()->add('error', $message);

        return $this->redirect($this->generateUrl($routeName));
    }

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedServices()
    {
        return array_merge(
            parent::getSubscribedServices(),
            [
                TranslatorInterface::class,
                SessionInterface::class,
                OperationRegistry::class,
                ExecuteOperationHandler::class,
            ]
        );
    }
}
