<?php
/**
 * @see       https://github.com/zendframework/zend-stratigility for the canonical source repository
 * @copyright Copyright (c) 2015-2017 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-stratigility/blob/master/LICENSE.md New BSD License
 */

namespace Zend\Stratigility;

use InvalidArgumentException;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;
use SplQueue;
use Webimpress\HttpMiddlewareCompatibility\HandlerInterface as DelegateInterface;
use Webimpress\HttpMiddlewareCompatibility\MiddlewareInterface;

use const Webimpress\HttpMiddlewareCompatibility\HANDLER_METHOD;

/**
 * Iterate a queue of middlewares and execute them.
 */
class Next implements DelegateInterface
{
    /**
     * @var null|DelegateInterface
     */
    private $nextDelegate;

    /**
     * @var SplQueue
     */
    private $queue;

    /**
     * @var string
     */
    private $removed = '';

    /**
     * Constructor.
     *
     * Clones the queue provided to allow re-use.
     *
     * @param SplQueue $queue
     * @param null|DelegateInterface $nextDelegate Next delegate to invoke when the
     *     queue is exhausted.
     * @throws InvalidArgumentException for a non-callable, non-delegate $done
     *     argument.
     */
    public function __construct(SplQueue $queue, DelegateInterface $nextDelegate = null)
    {
        $this->queue        = clone $queue;
        $this->nextDelegate = $nextDelegate;
    }

    /**
     * Invokable form; proxy to process().
     *
     * Ignores any arguments other than the request.
     *
     * @param ServerRequestInterface $request
     * @return ResponseInterface
     * @throws Exception\MissingResponseException If the queue is exhausted, and
     *     no "next delegate" is present.
     * @throws Exception\MissingResponseException If the middleware executed does
     *     not return a response.
     */
    public function __invoke(ServerRequestInterface $request)
    {
        return $this->process($request);
    }

    /**
     * Proxy to handle method.
     * It is needed to support http-interop/http-middleware 0.1.1.
     *
     * @param RequestInterface $request
     * @return ResponseInterface
     */
    public function next(RequestInterface $request)
    {
        return $this->handle($request);
    }

    /**
     * Proxy to handle method.
     * It is needed to support http-interop/http-middleware 0.2-0.4.1.
     *
     * @param ServerRequestInterface $request
     * @return ResponseInterface
     */
    public function process(ServerRequestInterface $request)
    {
        return $this->handle($request);
    }

    /**
     * @param ServerRequestInterface $request
     * @return ResponseInterface
     * @throws Exception\MissingResponseException If the queue is exhausted, and
     *     no "next delegate" is present.
     * @throws Exception\MissingResponseException If the middleware executed does
     *     not return a response.
     */
    public function handle(ServerRequestInterface $request)
    {
        // No middleware remains; done
        if ($this->queue->isEmpty()) {
            if ($this->nextDelegate) {
                return $this->nextDelegate->{HANDLER_METHOD}($request);
            }

            throw new Exception\MissingResponseException(sprintf(
                'Queue provided to %s was exhausted, with no response returned',
                get_class($this)
            ));
        }

        $route      = $this->queue->dequeue();
        $middleware = $this->getMiddlewareFromRoute($route);
        $response   = $middleware->process($request, $this);

        if (! $response instanceof ResponseInterface) {
            throw new Exception\MissingResponseException(sprintf(
                "Last middleware executed did not return a response.\nMethod: %s\nPath: %s\n.Handler: %s",
                $request->getMethod(),
                $request->getUri()->getPath(),
                get_class($middleware)
            ));
        }

        return $response;
    }

    /**
     * Toggle the "raise throwables" flag on.
     *
     * @deprecated Since 2.0.0; this functionality is now a no-op.
     * @return void
     */
    public function raiseThrowables()
    {
    }

    /**
     * Retrieve the middleware composed by a Route instance.
     *
     * If the path is a non-root path and the composed middleware is not a
     * PathMiddlewareDecorator instance, this will decorate the middleware
     * as a PathMiddlewareDecorator using the path before returning it.
     *
     * Otherwise, it returns the middleware as-is.
     *
     * @return MiddlewareInterface
     */
    private function getMiddlewareFromRoute(Route $route)
    {
        $path = $route->path;
        $middleware = $route->handler;

        if (! in_array($path, ['', '/'])
            && ! $middleware instanceof Middleware\PathMiddlewareDecorator
        ) {
            return new Middleware\PathMiddlewareDecorator($path, $middleware);
        }

        return $middleware;
    }
}
