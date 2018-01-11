<?php
/**
 * @see       https://github.com/zendframework/zend-stratigility for the canonical source repository
 * @copyright Copyright (c) 2018 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-stratigility/blob/master/LICENSE.md New BSD License
 */

namespace Zend\Stratigility\Middleware;

use Psr\Http\Message\ServerRequestInterface;
use Webimpress\HttpMiddlewareCompatibility\HandlerInterface as RequestHandlerInterface;

use const Webimpress\HttpMiddlewareCompatibility\HANDLER_METHOD;

/**
 * Request handler decorator for the PathMiddlewareDecorator
 *
 * Wraps the original request and original request handler passed when processing
 * a PathMiddlewareDecorator. If the decorated middleware calls on the handler
 * provided to it, this decorator ensures that the original handler is called,
 * using the original request.
 *
 * @internal This class is an internal detail of the PathMiddlewareDecorator.
 * @deprecated since 2.2.0; to be removed in 3.0, where it can be replaced with
 *     an anonymous class implementation.
 */
class PathRequestHandlerDecorator implements RequestHandlerInterface
{
    /**
     * @var RequestHandlerInterface
     */
    private $handler;

    /**
     * @var ServerRequestInterface
     */
    private $originalRequest;

    public function __construct(RequestHandlerInterface $handler, ServerRequestInterface $originalRequest)
    {
        $this->handler = $handler;
        $this->originalRequest = $originalRequest;
    }

    /**
     * Invokes the composed handler with the original server request.
     * {@inheritDocs}
     */
    public function handle(ServerRequestInterface $request)
    {
        $uri = $request->getUri()
            ->withPath($this->originalRequest->getUri()->getPath());
        return $this->handler->{HANDLER_METHOD}($request->withUri($uri));
    }
}
