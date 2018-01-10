<?php
/**
 * @see       https://github.com/zendframework/zend-stratigility for the canonical source repository
 * @copyright Copyright (c) 2016-2017 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-stratigility/blob/master/LICENSE.md New BSD License
 */

namespace Zend\Stratigility\Middleware;

use Psr\Http\Message\ServerRequestInterface;
use Webimpress\HttpMiddlewareCompatibility\HandlerInterface as DelegateInterface;
use Webimpress\HttpMiddlewareCompatibility\MiddlewareInterface as ServerMiddlewareInterface;

/**
 * @deprecated since 2.2.0; to be removed in 3.0.0. Use CallableMiddlewareDecorator instead.
 */
class CallableInteropMiddlewareWrapper implements ServerMiddlewareInterface
{
    /**
     * @param callable
     */
    private $middleware;

    /**
     * @param callable $middleware
     */
    public function __construct(callable $middleware)
    {
        $this->middleware = $middleware;
    }

    /**
     * {@inheritDocs}
     */
    public function process(ServerRequestInterface $request, DelegateInterface $delegate)
    {
        $middleware = $this->middleware;
        return $middleware($request, $delegate);
    }
}
