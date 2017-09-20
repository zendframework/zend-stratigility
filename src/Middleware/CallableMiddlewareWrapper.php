<?php
/**
 * @see       https://github.com/zendframework/zend-stratigility for the canonical source repository
 * @copyright Copyright (c) 2016-2017 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-stratigility/blob/master/LICENSE.md New BSD License
 */

namespace Zend\Stratigility\Middleware;

use Interop\Http\Server\MiddlewareInterface;
use Interop\Http\Server\RequestHandlerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Zend\Stratigility\Next;

/**
 * Decorate legacy callable middleware to make it dispatchable as server
 * middleware.
 */
class CallableMiddlewareWrapper implements MiddlewareInterface
{
    /**
     * @var callable
     */
    private $middleware;

    /**
     * @var ResponseInterface
     */
    private $responsePrototype;

    /**
     * @param callable $middleware
     * @param ResponseInterface $prototype
     */
    public function __construct(callable $middleware, ResponseInterface $prototype)
    {
        $this->middleware        = $middleware;
        $this->responsePrototype = $prototype;
    }

    /**
     * Proxies to underlying middleware, using composed response prototype.
     *
     * Also decorates the $handler using the CallableMiddlewareWrapper.
     *
     * {@inheritDocs}
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler)
    {
        $middleware = $this->middleware;
        $handler    = $handler instanceof Next
            ? $handler
            : function ($request) use ($handler) {
                return $handler->handle($request);
            };

        return $middleware($request, $this->responsePrototype, $handler);
    }
}
