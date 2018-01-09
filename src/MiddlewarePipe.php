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
final class MiddlewarePipe implements MiddlewareInterface, RequestHandlerInterface
{
    /**
     * @var SplQueue
     */
    private $pipeline;

    /**
     * Initializes the queue.
     */
    public function __construct()
    {
        $this->pipeline = new SplQueue();
    }

    /**
     * Perform a deep clone.
     */
    public function __clone()
    {
        $this->pipeline = clone $this->pipeline;
    }

    /**
     * Handle an incoming request.
     *
     * Attempts to handle an incoming request by doing the following:
     *
     * - Cloning itself, to produce a request handler.
     * - Dequeuing the first middleware in the cloned handler.
     * - Processing the first middleware using the request and the cloned handler.
     *
     * If the pipeline is empty at the time this method is invoked, it will
     * raise an exception.
     *
     * @throws Exception\EmptyPipelineException if no middleware is present in
     *     the instance in order to process the request.
     */
    public function handle(ServerRequestInterface $request) : ResponseInterface
    {
        if (0 === count($this->pipeline)) {
            throw Exception\EmptyPipelineException::forClass(__CLASS__);
        }

        $nextHandler = clone $this;
        $middleware = $nextHandler->pipeline->dequeue();
        return $middleware->process($request, $nextHandler);
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
     */
    public function pipe(MiddlewareInterface $middleware) : void
    {
        $this->pipeline->enqueue($middleware);
    }
}
