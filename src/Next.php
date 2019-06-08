<?php
/**
 * @see       https://github.com/zendframework/zend-stratigility for the canonical source repository
 * @copyright Copyright (c) 2015-2018 Zend Technologies USA Inc. (https://www.zend.com)
 * @license   https://github.com/zendframework/zend-stratigility/blob/master/LICENSE.md New BSD License
 */

declare(strict_types=1);

namespace Zend\Stratigility;

use Zend\Stratigility\Middleware\RequestHandlerMiddleware;
use Zend\Stratigility\Exception\EmptyPipelineException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use SplQueue;

/**
 * Iterate a queue of middlewares and execute them.
 */
final class Next implements RequestHandlerInterface
{
    /**
     * @var RequestHandlerInterface
     */
    private $fallbackHandler;

    /**
     * @var SplQueue
     */
    private $queue;

    /**
     * Clones the queue provided to allow re-use.
     *
     * @param RequestHandlerInterface $fallbackHandler Fallback handler to
     *     invoke when the queue is exhausted.
     */
    public function __construct(SplQueue $queue, RequestHandlerInterface $fallbackHandler)
    {
        $this->queue           = clone $queue;
        $this->fallbackHandler = $fallbackHandler;
        $this->queue->push(
            new RequestHandlerMiddleware($fallbackHandler)
        );
    }

    public function handle(ServerRequestInterface $request) : ResponseInterface
    {
        if ($this->queue->isEmpty()) {
            throw EmptyPipelineException::forClass(__CLASS__);
        }

        $middleware = $this->queue->dequeue();

        return $middleware->process($request, $this);
    }
}
