<?php
/**
 * @see       https://github.com/zendframework/zend-stratigility for the canonical source repository
 * @copyright Copyright (c) 2016-2017 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-stratigility/blob/master/LICENSE.md New BSD License
 */

namespace Zend\Stratigility\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Webimpress\HttpMiddlewareCompatibility\HandlerInterface as DelegateInterface;
use Webimpress\HttpMiddlewareCompatibility\MiddlewareInterface as ServerMiddlewareInterface;
use Zend\Stratigility\Next;

use const Webimpress\HttpMiddlewareCompatibility\HANDLER_METHOD;

/**
 * Decorate legacy callable middleware to make it dispatchable as server
 * middleware.
 *
 * @deprecated since 2.2.0; to be removed in 3.0.0. Use DoublePassMiddlewareDecorator instead.
 */
class CallableMiddlewareWrapper implements ServerMiddlewareInterface
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
        $this->middleware = $middleware;
        $this->responsePrototype = $prototype;
    }

    /**
     * Proxies to underlying middleware, using composed response prototype.
     *
     * Also decorates the $delegator using the CallableMiddlewareWrapper.
     *
     * {@inheritDocs}
     */
    public function process(ServerRequestInterface $request, DelegateInterface $delegate)
    {
        $middleware = $this->middleware;
        $delegate = $delegate instanceof Next
            ? $delegate
            : function ($request) use ($delegate) {
                return $delegate->{HANDLER_METHOD}($request);
            };

        return $middleware($request, $this->responsePrototype, $delegate);
    }
}
