<?php
/**
 * @see       https://github.com/zendframework/zend-stratigility for the canonical source repository
 * @copyright Copyright (c) 2015-2019 Zend Technologies USA Inc. (https://www.zend.com)
 * @license   https://github.com/zendframework/zend-stratigility/blob/master/LICENSE.md New BSD License
 */

declare(strict_types=1);

namespace ZendTest\Stratigility;

use PHPUnit\Framework\Assert;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use SplQueue;
use ZendTest\Stratigility\TestAsset\DelegatingMiddleware;
use ZendTest\Stratigility\TestAsset\ShortCircuitingMiddleware;
use Zend\Diactoros\Response;
use Zend\Diactoros\ServerRequest as Request;
use Zend\Diactoros\Uri;
use Zend\Stratigility\Exception\MiddlewarePipeNextHandlerAlreadyCalledException;
use Zend\Stratigility\Next;

class NextTest extends TestCase
{
    use MiddlewareTrait;

    /**
     * @var SplQueue
     */
    private $queue;

    /**
     * @var Request
     */
    private $request;

    /**
     * @todo: do we need it?
     */
    protected $errorHandler;

    protected function setUp()
    {
        $this->queue   = new SplQueue();
        $this->request = new Request([], [], 'http://example.com/', 'GET', 'php://memory');
        $this->fallbackHandler = $this->createFallbackHandler();
    }

    public function createFallbackHandler(ResponseInterface $response = null) : RequestHandlerInterface
    {
        $response = $response ?: $this->createDefaultResponse();
        return new class ($response) implements RequestHandlerInterface {
            /** @var ResponseInterface */
            private $response;

            public function __construct(ResponseInterface $response)
            {
                $this->response = $response;
            }

            public function handle(ServerRequestInterface $request) : ResponseInterface
            {
                return $this->response;
            }
        };
    }

    public function createDefaultResponse() : ResponseInterface
    {
        $this->response = $this->prophesize(ResponseInterface::class);
        return $this->response->reveal();
    }

    /**
     * @group http-interop
     */
    public function testNextImplementsRequestHandlerInterface()
    {
        $next = new Next($this->queue, $this->fallbackHandler);
        $this->assertInstanceOf(RequestHandlerInterface::class, $next);
    }

    /**
     * @group 25
     */
    public function testNextShouldCloneQueueOnInstantiation()
    {
        $next = new Next($this->queue, $this->fallbackHandler);
        $this->assertAttributeNotSame($this->queue, 'queue', $next);
        $this->assertAttributeEquals($this->queue, 'queue', $next);
    }

    public function testNextComposesAFallbackHandler()
    {
        $next = new Next($this->queue, $this->fallbackHandler);
        $this->assertAttributeSame($this->fallbackHandler, 'fallbackHandler', $next);
    }

    public function testMiddlewareCallingNextWithRequestPassesRequestToNextMiddleware()
    {
        $request       = $this->request->withUri(new Uri('http://example.com/foo/bar/baz'));
        $cannedRequest = clone $request;
        $cannedRequest = $cannedRequest->withMethod('POST');

        $middleware1 = new class($cannedRequest) implements MiddlewareInterface
        {
            private $cannedRequest;

            public function __construct($cannedRequest)
            {
                $this->cannedRequest = $cannedRequest;
            }

            public function process(ServerRequestInterface $req, RequestHandlerInterface $handler) : ResponseInterface
            {
                return $handler->handle($this->cannedRequest);
            }
        };

        $middleware2 = new class($cannedRequest) implements MiddlewareInterface
        {
            private $cannedRequest;

            public function __construct($cannedRequest)
            {
                $this->cannedRequest = $cannedRequest;
            }

            public function process(ServerRequestInterface $req, RequestHandlerInterface $handler) : ResponseInterface
            {
                Assert::assertEquals($this->cannedRequest->getMethod(), $req->getMethod());
                return new Response();
            }
        };

        $this->queue->enqueue($middleware1);
        $this->queue->enqueue($middleware2);

        $next = new Next($this->queue, $this->fallbackHandler);
        $response = $next->handle($request);
        $this->assertNotSame($this->response, $response);
    }

    /**
     * @group http-interop
     */
    public function testNextDelegatesToFallbackHandlerWhenQueueIsEmpty()
    {
        $expectedResponse = $this->prophesize(ResponseInterface::class)->reveal();
        $fallbackHandler = $this->prophesize(RequestHandlerInterface::class);
        $fallbackHandler
            ->handle($this->request)
            ->willReturn($expectedResponse)->shouldBeCalled();
        $next = new Next($this->queue, $fallbackHandler->reveal());
        $this->assertSame($expectedResponse, $next->handle($this->request));
    }

    /**
     * @group http-interop
     */
    public function testNextProcessesEnqueuedMiddleware()
    {
        $fallbackHandler = $this->prophesize(RequestHandlerInterface::class);
        $fallbackHandler
            ->handle(Argument::any())
            ->shouldNotBeCalled();

        $response = $this->prophesize(ResponseInterface::class)->reveal();

        $middleware = $this->prophesize(MiddlewareInterface::class);
        $middleware
            ->process($this->request, Argument::type(Next::class))
            ->willReturn($response);

        $this->queue->enqueue($middleware->reveal());

        // Creating after middleware enqueued, as Next clones the queue during
        // instantiation.
        $next = new Next($this->queue, $fallbackHandler->reveal());

        $this->assertSame($response, $next->handle($this->request));
    }

    /**
     * @group http-interop
     */
    public function testMiddlewareReturningResponseShortCircuitsProcess()
    {
        $fallbackHandler = $this->prophesize(RequestHandlerInterface::class);
        $fallbackHandler
            ->handle(Argument::any())
            ->shouldNotBeCalled();

        $response = $this->prophesize(ResponseInterface::class)->reveal();

        $route1 = $this->prophesize(MiddlewareInterface::class);
        $route1
            ->process($this->request, Argument::type(Next::class))
            ->willReturn($response);
        $this->queue->enqueue($route1->reveal());

        $route2 = $this->prophesize(MiddlewareInterface::class);
        $route2
            ->process(Argument::type(RequestInterface::class), Argument::type(Next::class))
            ->shouldNotBeCalled();
        $this->queue->enqueue($route2->reveal());

        // Creating after middleware enqueued, as Next clones the queue during
        // instantiation.
        $next = new Next($this->queue, $fallbackHandler->reveal());

        $this->assertSame($response, $next->handle($this->request));
    }

    public function testNextHandlerCannotBeInvokedTwice()
    {
        $fallbackHandler = $this->prophesize(RequestHandlerInterface::class);
        $fallbackHandler
            ->handle(Argument::any())
            ->willReturn(new Response());

        $this->queue->push(new DelegatingMiddleware());

        $next = new Next($this->queue, $fallbackHandler->reveal());
        $next->handle($this->request);

        $this->expectException(MiddlewarePipeNextHandlerAlreadyCalledException::class);
        $next->handle($this->request);
    }

    public function testSecondInvocationAttemptDoesNotInvokeFinalHandler()
    {
        $fallBackHandler = $this->prophesize(RequestHandlerInterface::class);
        $fallBackHandler
            ->handle(Argument::any())
            ->willReturn(new Response())
            ->shouldBeCalledTimes(1);

        $this->queue->push(new DelegatingMiddleware());

        $next = new Next($this->queue, $fallBackHandler->reveal());
        $next->handle($this->request);

        $this->expectException(MiddlewarePipeNextHandlerAlreadyCalledException::class);
        $next->handle($this->request);
    }

    public function testSecondInvocationAttemptDoesNotInvokeMiddleware()
    {
        $fallBackHandler = $this->prophesize(RequestHandlerInterface::class);
        $fallBackHandler
            ->handle(Argument::any())
            ->willReturn(new Response());

        $middleware = $this->prophesize(MiddlewareInterface::class);
        $middleware
            ->process(
                Argument::type(ServerRequestInterface::class),
                Argument::type(RequestHandlerInterface::class)
            )
            ->will(function (array $args): ResponseInterface {
                return $args[1]->handle($args[0]);
            })
            ->shouldBeCalledTimes(1);

        $this->queue->push($middleware->reveal());

        $next = new Next($this->queue, $fallBackHandler->reveal());
        $next->handle($this->request);

        $this->expectException(MiddlewarePipeNextHandlerAlreadyCalledException::class);
        $next->handle($this->request);
    }

    public function testShortCircuitingMiddlewareDoesNotEnableSecondInvocation()
    {
        $fallBackHandler = $this->prophesize(RequestHandlerInterface::class);
        $fallBackHandler
            ->handle(Argument::any())
            ->willReturn(new Response())
            ->shouldNotBeCalled();

        $this->queue->push(new ShortCircuitingMiddleware());

        // The middleware above shorcircuits (when handler is invoked first)
        // The middleware below still exists in the queue (when handler is invoked again)
        $this->queue->push(new DelegatingMiddleware());

        $next = new Next($this->queue, $fallBackHandler->reveal());
        $next->handle($this->request);

        $this->expectException(MiddlewarePipeNextHandlerAlreadyCalledException::class);
        $next->handle($this->request);
    }

    public function testSecondInvocationAttemptWithEmptyQueueDoesNotInvokeFinalHandler()
    {
        $fallBackHandler = $this->prophesize(RequestHandlerInterface::class);
        $fallBackHandler
            ->handle(Argument::any())
            ->willReturn(new Response())
            ->shouldBeCalledTimes(1);

        $next = new Next($this->queue, $fallBackHandler->reveal());

        $next->handle($this->request);

        $this->expectException(MiddlewarePipeNextHandlerAlreadyCalledException::class);
        $next->handle($this->request);
    }
}
