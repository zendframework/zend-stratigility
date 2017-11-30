<?php
/**
 * @see       https://github.com/zendframework/zend-stratigility for the canonical source repository
 * @copyright Copyright (c) 2015-2017 Zend Technologies USA Inc. (https://www.zend.com)
 * @license   https://github.com/zendframework/zend-stratigility/blob/master/LICENSE.md New BSD License
 */

namespace ZendTest\Stratigility;

use Interop\Http\Server\MiddlewareInterface;
use Interop\Http\Server\RequestHandlerInterface;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use ReflectionProperty;
use SplQueue;
use Zend\Diactoros\Response;
use Zend\Diactoros\ServerRequest as Request;
use Zend\Diactoros\Uri;
use Zend\Stratigility\Exception;
use Zend\Stratigility\Next;
use Zend\Stratigility\Route;

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
    }

    public function testInvokesItselfWhenRouteDoesNotMatchCurrentUrl()
    {
        // e.g., handler matches "/foo", but path is "/bar"
        $route = new Route('/foo', $this->getNotCalledMiddleware());
        $this->queue->enqueue($route);

        $done = new Route('/', $this->getMiddlewareWhichReturnsResponse(new Response()));
        $this->queue->enqueue($done);

        $this->request->withUri(new Uri('http://local.example.com/bar'));

        $next = new Next($this->queue);
        $next->handle($this->request);
    }

    public function testInvokesItselfIfRouteDoesNotMatchAtABoundary()
    {
        // e.g., if route is "/foo", but path is "/foobar", no match
        $route = new Route('/foo', $this->getNotCalledMiddleware());
        $this->queue->enqueue($route);

        $done = new Route('/', $this->getMiddlewareWhichReturnsResponse(new Response()));
        $this->queue->enqueue($done);

        $this->request->withUri(new Uri('http://local.example.com/foobar'));

        $next = new Next($this->queue);
        $next->handle($this->request);
    }

    public function testInvokesHandlerWhenMatched()
    {
        // e.g., if route is "/foo", but path is "/foobar", no match
        $triggered = null;
        $route = new Route('/foo', $this->getMiddlewareWhichReturnsResponse(new Response()));
        $this->queue->enqueue($route);

        $request = $this->request->withUri(new Uri('http://local.example.com/foo'));

        $next = new Next($this->queue);
        $next->handle($request);
    }

    public function testRequestUriInInvokedHandlerDoesNotContainMatchedPortionOfRoute()
    {
        $middleware = $this->prophesize(MiddlewareInterface::class);
        $middleware
            ->process(Argument::that(function (ServerRequestInterface $req) {
                Assert::assertSame('/bar', $req->getUri()->getPath());

                return true;
            }), Argument::any())
            ->shouldBeCalledTimes(1);

        // e.g., if route is "/foo", and "/foo/bar" is the original path,
        // then the URI path in the handler is "/bar"
        $route = new Route('/foo', $middleware->reveal());
        $this->queue->enqueue($route);

        $request = $this->request->withUri(new Uri('http://local.example.com/foo/bar'));

        $next = new Next($this->queue);
        $next->handle($request);
    }

    public function testSlashAndPathGetResetBeforeExecutingNextMiddleware()
    {
        $response = new Response();
        $response->getBody()->write('done');

        $route1 = new Route('/foo', $this->getPassToHandlerMiddleware());
        $route2 = new Route('/foo/bar', $this->getNotCalledMiddleware());
        $route3 = new Route('/foo/baz', $this->getMiddlewareWhichReturnsResponse($response));

        $this->queue->enqueue($route1);
        $this->queue->enqueue($route2);
        $this->queue->enqueue($route3);

        $request = $this->request->withUri(new Uri('http://example.com/foo/baz/bat'));
        $next = new Next($this->queue);
        self::assertSame('done', (string) $next->handle($request)->getBody());
    }

    public function testMiddlewareReturningResponseShortcircuits()
    {
        $response = new Response();
        $route1 = new Route('/foo', $this->getMiddlewareWhichReturnsResponse($response));
        $route2 = new Route('/foo/bar', $this->getNotCalledMiddleware());
        $route3 = new Route('/foo/baz', $this->getNotCalledMiddleware());

        $this->queue->enqueue($route1);
        $this->queue->enqueue($route2);
        $this->queue->enqueue($route3);

        $request = $this->request->withUri(new Uri('http://example.com/foo/bar/baz'));
        $next = new Next($this->queue);
        $result = $next->handle($request);
        $this->assertSame($response, $result);
    }

    public function testMiddlewareCallingNextWithRequestPassesRequestToNextMiddleware()
    {
        $request       = $this->request->withUri(new Uri('http://example.com/foo/bar/baz'));
        $cannedRequest = clone $request;
        $cannedRequest = $cannedRequest->withMethod('POST');

        $route1 = new Route('/foo/bar', new class($cannedRequest) implements MiddlewareInterface
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
        });
        $route2 = new Route('/foo/bar/baz', new class($cannedRequest) implements MiddlewareInterface
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
        });

        $this->queue->enqueue($route1);
        $this->queue->enqueue($route2);

        $next = new Next($this->queue);
        $next->handle($request);
    }

    /**
     * @group 25
     */
    public function testNextShouldCloneQueueOnInstantiation()
    {
        $next = new Next($this->queue);

        $r = new ReflectionProperty($next, 'queue');
        $r->setAccessible(true);
        $queue = $r->getValue($next);

        $this->assertNotSame($this->queue, $queue);
        $this->assertEquals($this->queue, $queue);
    }

    /**
     * @group http-interop
     */
    public function testNextImplementsRequestHandlerInterface()
    {
        $next = new Next($this->queue);

        $this->assertInstanceOf(RequestHandlerInterface::class, $next);
    }

    /**
     * @group http-interop
     */
    public function testExceptionIsRaisedWhenQueueIsExhaustedAndNoNextRequestHandlerPresent()
    {
        $next = new Next($this->queue);

        $this->expectException(Exception\MissingResponseException::class);
        $this->expectExceptionMessage('exhausted');
        $next->handle($this->request);
    }

    /**
     * @group http-interop
     */
    public function testProcessReinvokesItselfWhenRouteDoesNotMatchCurrentUrl()
    {
        // e.g., handler matches "/foo", but path is "/bar"
        $request = $this->request->withUri(new Uri('http://local.example.com/bar'));
        $response = $this->prophesize(ResponseInterface::class)->reveal();

        $first = $this->prophesize(MiddlewareInterface::class);
        $first
            ->process($request, Argument::type(Next::class))
            ->will(function () {
                // This one should be skipped
                Assert::fail('Route should not be invoked if path does not match');
            });
        $this->queue->enqueue(new Route('/foo', $first->reveal()));

        $second = $this->prophesize(MiddlewareInterface::class);
        $second
            ->process(Argument::type(RequestInterface::class), Argument::type(Next::class))
            ->willReturn($response);
        $this->queue->enqueue(new Route('/bar', $second->reveal()));

        $next = new Next($this->queue);

        $this->assertSame($response, $next->handle($request));
    }

    /**
     * @group http-interop
     */
    public function testProcessReinvokesItselfIfRouteDoesNotMatchAtABoundary()
    {
        // e.g., if route is "/foo", but path is "/foobar", no match
        $request = $this->request->withUri(new Uri('http://local.example.com/foobar'));
        $response = $this->prophesize(ResponseInterface::class)->reveal();

        $first = $this->prophesize(MiddlewareInterface::class);
        $first
            ->process($request, Argument::type(Next::class))
            ->will(function () {
                // This one should be skipped
                Assert::fail('Route should not be invoked if path does not match');
            });
        $this->queue->enqueue(new Route('/foo', $first->reveal()));

        $second = $this->prophesize(MiddlewareInterface::class);
        $second
            ->process(Argument::type(RequestInterface::class), Argument::type(Next::class))
            ->willReturn($response);
        $this->queue->enqueue(new Route('/foobar', $second->reveal()));

        $next = new Next($this->queue);
        $this->assertSame($response, $next->handle($request));
    }

    /**
     * @group http-interop
     */
    public function testProcessDispatchesHandlerWhenMatched()
    {
        $request = $this->request->withUri(new Uri('http://local.example.com/foo'));
        $response = $this->prophesize(ResponseInterface::class)->reveal();

        $middleware = $this->prophesize(MiddlewareInterface::class);
        $middleware
            ->process(Argument::type(RequestInterface::class), Argument::type(Next::class))
            ->willReturn($response);
        $this->queue->enqueue(new Route('/foo', $middleware->reveal()));

        $next = new Next($this->queue);
        $this->assertSame($response, $next->handle($request));
    }

    /**
     * @group http-interop
     */
    public function testRequestUriInHandlerInvokedByProcessDoesNotContainMatchedPortionOfRoute()
    {
        // e.g., if route is "/foo", and "/foo/bar" is the original path,
        // then the URI path in the handler is "/bar"
        $request = $this->request->withUri(new Uri('http://local.example.com/foo/bar'));
        $response = $this->prophesize(ResponseInterface::class)->reveal();

        $middleware = $this->prophesize(MiddlewareInterface::class);
        $middleware
            ->process(Argument::that(function ($arg) {
                Assert::assertInstanceOf(RequestInterface::class, $arg);
                Assert::assertEquals('/bar', $arg->getUri()->getPath());
                return true;
            }), Argument::type(Next::class))
            ->willReturn($response);
        $this->queue->enqueue(new Route('/foo', $middleware->reveal()));

        $next = new Next($this->queue);
        $this->assertSame($response, $next->handle($request));
    }

    /**
     * @group http-interop
     */
    public function testSlashAndPathGetResetByProcessBeforeExecutingNextMiddleware()
    {
        $request = $this->request->withUri(new Uri('http://example.com/foo/baz/bat'));
        $response = $this->prophesize(ResponseInterface::class)->reveal();

        $route1 = $this->prophesize(MiddlewareInterface::class);
        $route1
            ->process(Argument::type(RequestInterface::class), Argument::type(Next::class))
            ->will(function ($args) {
                $request = $args[0];
                $next = $args[1];
                return $next->handle($request);
            });
        $this->queue->enqueue(new Route('/foo', $route1->reveal()));

        $route2 = $this->prophesize(MiddlewareInterface::class);
        $route2
            ->process(Argument::type(RequestInterface::class), Argument::type(Next::class))
            ->shouldNotBeCalled();
        $this->queue->enqueue(new Route('/foo/bar', $route2->reveal()));

        $route3 = $this->prophesize(MiddlewareInterface::class);
        $route3
            ->process(Argument::that(function ($arg) {
                Assert::assertEquals('/bat', $arg->getUri()->getPath());
                return true;
            }), Argument::type(Next::class))
            ->willReturn($response);
        $this->queue->enqueue(new Route('/foo/baz', $route3->reveal()));

        $next = new Next($this->queue);
        $this->assertSame($response, $next->handle($request));
    }

    /**
     * @group http-interop
     */
    public function testMiddlewareReturningResponseShortCircuitsProcess()
    {
        $request = $this->request->withUri(new Uri('http://example.com/foo/bar/baz'));
        $response = $this->prophesize(ResponseInterface::class)->reveal();

        $route1 = $this->prophesize(MiddlewareInterface::class);
        $route1
            ->process(Argument::that(function ($arg) {
                Assert::assertEquals('/bar/baz', $arg->getUri()->getPath());
                return true;
            }), Argument::type(Next::class))
            ->willReturn($response);
        $this->queue->enqueue(new Route('/foo', $route1->reveal()));

        $route2 = $this->prophesize(MiddlewareInterface::class);
        $route2
            ->process(Argument::type(RequestInterface::class), Argument::type(Next::class))
            ->shouldNotBeCalled();
        $this->queue->enqueue(new Route('/foo/bar', $route2->reveal()));

        $next = new Next($this->queue);
        $this->assertSame($response, $next->handle($request));
    }
}
