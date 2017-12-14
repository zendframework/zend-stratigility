<?php
/**
 * @see       https://github.com/zendframework/zend-stratigility for the canonical source repository
 * @copyright Copyright (c) 2015-2017 Zend Technologies USA Inc. (https://www.zend.com)
 * @license   https://github.com/zendframework/zend-stratigility/blob/master/LICENSE.md New BSD License
 */
declare(strict_types=1);

namespace Zend\Stratigility;

use Interop\Http\Server\MiddlewareInterface;
use Interop\Http\Server\RequestHandlerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use SplQueue;
use Zend\Stratigility\Exception\InvalidMiddlewareException;

/**
 * Pipe middleware like unix pipes.
 *
 * This class implements a pipeline of middleware, which can be attached using
 * the `pipe()` method, and is itself middleware.
 *
 * It creates an instance of `Next` internally, invoking it with the provided
 * request and response instances, passing the original request and the returned
 * response to the `$next` argument when complete.
 *
 * Inspired by Sencha Connect.
 *
 * @see https://github.com/sencha/connect
 */
class MiddlewarePipe implements MiddlewareInterface
{
    /**
     * @var SplQueue
     */
    protected $pipeline;

    /**
     * Initializes the queue.
     */
    public function __construct()
    {
        $this->pipeline = new SplQueue();
    }

    /**
     * PSR-15 middleware invocation.
     *
     * Executes the internal pipeline, passing $handler as the "final
     * handler" in cases when the pipeline exhausts itself.
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler) : ResponseInterface
    {
        $next = new Next($this->pipeline, $handler);

        return $next->handle($request);
    }

    /**
     * Attach middleware to the pipeline.
     *
     * Each middleware will be associated with a particular path
     */
    public function pipe(string $path, MiddlewareInterface $middleware) : self
    {
        $normalizedPath = $this->normalizePipePath($path);
        $route = new Route($normalizedPath, $middleware);

        $this->pipeline->enqueue($route);

        return $this;
    }

    /**
     * Attach middleware to the pipeline.
     *
     * Each middleware should be executed every request cycle.
     */
    public function pipeMiddleware(MiddlewareInterface $middleware) : self
    {
        return $this->pipe('/', $middleware);
    }

    /**
     * Normalize a path used when defining a pipe
     *
     * Strips trailing slashes, and prepends a slash.
     */
    private function normalizePipePath(string $path) : string
    {
        return '/' . trim($path, '/');
    }
}
