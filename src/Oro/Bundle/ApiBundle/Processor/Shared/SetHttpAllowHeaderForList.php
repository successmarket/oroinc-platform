<?php

namespace Oro\Bundle\ApiBundle\Processor\Shared;

use Oro\Bundle\ApiBundle\Request\ApiAction;
use Symfony\Component\HttpFoundation\Request;

/**
 * Sets "Allow" HTTP header if the response status code is 405 (Method Not Allowed).
 * If there are no any allowed HTTP methods, the response status code is changed to 404.
 */
class SetHttpAllowHeaderForList extends SetHttpAllowHeader
{
    /**
     * {@inheritdoc}
     */
    protected function getHttpMethodToActionsMap()
    {
        return [
            Request::METHOD_OPTIONS => ApiAction::OPTIONS,
            Request::METHOD_GET     => ApiAction::GET_LIST,
            Request::METHOD_PATCH   => ApiAction::UPDATE_LIST,
            Request::METHOD_POST    => ApiAction::CREATE,
            Request::METHOD_DELETE  => ApiAction::DELETE_LIST
        ];
    }
}
