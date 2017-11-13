<?php
/**
 * @see       https://github.com/zendframework/zend-stratigility for the canonical source repository
 * @copyright Copyright (c) 2017 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-stratigility/blob/master/LICENSE.md New BSD License
 */

namespace Zend\Stratigility\Middleware;

use Psr\Http\Message\ServerRequestInterface;
use Webimpress\HttpMiddlewareCompatibility\HandlerInterface as DelegateInterface;
use Webimpress\HttpMiddlewareCompatibility\MiddlewareInterface as ServerMiddlewareInterface;

final class ErrorHandlerWithoutReturnType implements ServerMiddlewareInterface
{
    use ErrorHandlerTrait {
        process as processTrait;
    }

    public function process(ServerRequestInterface $request, DelegateInterface $delegate)
    {
        return $this->processTrait($request, $delegate);
    }
}
