<?php
/**
 * @link      https://github.com/zendframework/zend-stratigility for the canonical source repository
 * @copyright Copyright (c) 2015-2016 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://framework.zend.com/license New BSD License
 */

namespace ZendTest\Stratigility;

use Interop\Http\Middleware\DelegateInterface;
use Interop\Http\Middleware\ServerMiddlewareInterface;
use PHPUnit_Framework_Assert as Assert;
use PHPUnit_Framework_TestCase as TestCase;
use Prophecy\Argument;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use ReflectionProperty;
use SplQueue;
use Zend\Diactoros\Response;
use Zend\Diactoros\ServerRequest as Request;
use Zend\Diactoros\Uri;
use Zend\Stratigility\Exception;
use Zend\Stratigility\Middleware\CallableMiddlewareWrapperFactory;
use Zend\Stratigility\Next;
use Zend\Stratigility\Route;

class NextTest extends TestCase
{
    public function setUp()
    {
        $this->queue     = new SplQueue();
        $this->request   = new Request([], [], 'http://example.com/', 'GET', 'php://memory');
        $this->response  = new Response();
        $this->decorator = new CallableMiddlewareWrapperFactory($this->response);
    }

    public function testInvokesItselfWhenRouteDoesNotMatchCurrentUrl()
    {
        // e.g., handler matches "/foo", but path is "/bar"
        $route = new Route('/foo', $this->decorator->decorateCallableMiddleware(
            function ($req, $res, $next) {
                $this->fail('Route should not be invoked if path does not match');
            }
        ));
        $this->queue->enqueue($route);

        $triggered = null;
        $done = new Route('/', $this->decorator->decorateCallableMiddleware(
            function ($req, $res) use (&$triggered) {
                $triggered = true;
                return $res;
            }
        ));
        $this->queue->enqueue($done);

        $this->request->withUri(new Uri('http://local.example.com/bar'));

        $next = new Next($this->queue);
        $next($this->request, $this->response);
        $this->assertTrue($triggered);
    }

    public function testInvokesItselfIfRouteDoesNotMatchAtABoundary()
    {
        // e.g., if route is "/foo", but path is "/foobar", no match
        $route = new Route('/foo', $this->decorator->decorateCallableMiddleware(
            function ($req, $res, $next) {
                $this->fail('Route should not be invoked if path does not match');
            }
        ));
        $this->queue->enqueue($route);

        $triggered = null;
        $done = new Route('/', $this->decorator->decorateCallableMiddleware(
            function ($req, $res, $err = null) use (&$triggered) {
                $triggered = true;
                return $res;
            }
        ));
        $this->queue->enqueue($done);

        $this->request->withUri(new Uri('http://local.example.com/foobar'));

        $next = new Next($this->queue);
        $next($this->request, $this->response);
        $this->assertTrue($triggered);
    }

    public function testInvokesHandlerWhenMatched()
    {
        // e.g., if route is "/foo", but path is "/foobar", no match
        $triggered = null;
        $route = new Route('/foo', $this->decorator->decorateCallableMiddleware(
            function ($req, $res, $next) use (&$triggered) {
                $triggered = true;
                return $res;
            }
        ));
        $this->queue->enqueue($route);

        $request = $this->request->withUri(new Uri('http://local.example.com/foo'));

        $next = new Next($this->queue);
        $next($request, $this->response);
        $this->assertTrue($triggered);
    }

    public function testRequestUriInInvokedHandlerDoesNotContainMatchedPortionOfRoute()
    {
        // e.g., if route is "/foo", and "/foo/bar" is the original path,
        // then the URI path in the handler is "/bar"
        $triggered = null;
        $route = new Route('/foo', $this->decorator->decorateCallableMiddleware(
            function ($req, $res, $next) use (&$triggered) {
                $triggered = $req->getUri()->getPath();
                return $res;
            }
        ));
        $this->queue->enqueue($route);

        $request = $this->request->withUri(new Uri('http://local.example.com/foo/bar'));

        $next = new Next($this->queue);
        $next($request, $this->response);
        $this->assertEquals('/bar', $triggered);
    }

    public function testSlashAndPathGetResetBeforeExecutingNextMiddleware()
    {
        $route1 = new Route('/foo', $this->decorator->decorateCallableMiddleware(
            function ($req, $res, $next) {
                return $next($req, $res);
            }
        ));
        $route2 = new Route('/foo/bar', $this->decorator->decorateCallableMiddleware(
            function ($req, $res, $next) {
                return $next($req, $res);
            }
        ));
        $route3 = new Route('/foo/baz', $this->decorator->decorateCallableMiddleware(
            function ($req, $res, $next) {
                $res->getBody()->write('done');
                return $res;
            }
        ));

        $this->queue->enqueue($route1);
        $this->queue->enqueue($route2);
        $this->queue->enqueue($route3);

        $request = $this->request->withUri(new Uri('http://example.com/foo/baz/bat'));
        $next = new Next($this->queue);
        $next($request, $this->response);
        $this->assertEquals('done', (string) $this->response->getBody());
    }

    public function testMiddlewareReturningResponseShortcircuits()
    {
        $route1 = new Route('/foo', $this->decorator->decorateCallableMiddleware(
            function ($req, $res, $next) {
                return $res;
            }
        ));
        $route2 = new Route('/foo/bar', $this->decorator->decorateCallableMiddleware(
            function ($req, $res, $next) {
                $next($req, $res);
                $this->fail('Should not hit route2 handler');
            }
        ));
        $route3 = new Route('/foo/baz', $this->decorator->decorateCallableMiddleware(
            function ($req, $res, $next) {
                $next($req, $res);
                $this->fail('Should not hit route3 handler');
            }
        ));

        $this->queue->enqueue($route1);
        $this->queue->enqueue($route2);
        $this->queue->enqueue($route3);

        $request = $this->request->withUri(new Uri('http://example.com/foo/bar/baz'));
        $next = new Next($this->queue);
        $result = $next($request, $this->response);
        $this->assertSame($this->response, $result);
    }

    public function testMiddlewareCallingNextWithRequestPassesRequestToNextMiddleware()
    {
        $request       = $this->request->withUri(new Uri('http://example.com/foo/bar/baz'));
        $cannedRequest = clone $request;
        $cannedRequest = $cannedRequest->withMethod('POST');

        $route1 = new Route('/foo/bar', $this->decorator->decorateCallableMiddleware(
            function ($req, $res, $next) use ($cannedRequest) {
                return $next($cannedRequest, $res);
            }
        ));
        $route2 = new Route('/foo/bar/baz', $this->decorator->decorateCallableMiddleware(
            function ($req, $res, $next) use ($cannedRequest) {
                $this->assertEquals($cannedRequest->getMethod(), $req->getMethod());
                return $res;
            }
        ));

        $this->queue->enqueue($route1);
        $this->queue->enqueue($route2);

        $next = new Next($this->queue);
        $next($request, $this->response);
    }

    public function testNextShouldRaiseExceptionIfMiddlewareDoesNotReturnResponse()
    {
        $route1 = new Route('/foo', $this->decorator->decorateCallableMiddleware(
            function ($req, $res, $next) {
                // Explicitly not returning a value
                $next($req, $res);
            }
        ));
        $route2 = new Route('/foo/bar', $this->decorator->decorateCallableMiddleware(
            function ($req, $res, $next) {
                return $res;
            }
        ));

        $this->queue->enqueue($route1);
        $this->queue->enqueue($route2);

        $request = $this->request->withUri(new Uri('http://example.com/foo/bar/baz'));
        $next    = new Next($this->queue);

        $this->setExpectedException(Exception\MissingResponseException::class);
        $next($request, $this->response);
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
    public function testNextImplementsDelegateInterface()
    {
        $next = new Next($this->queue);

        $this->assertInstanceOf(DelegateInterface::class, $next);
    }

    /**
     * @group http-interop
     */
    public function testExceptionIsRaisedWhenQueueIsExhaustedAndNoNextDelegatePresent()
    {
        $next = new Next($this->queue);

        $this->setExpectedException(Exception\MissingResponseException::class, 'exhausted');
        $next->process($this->request);
    }

    /**
     * @group http-interop
     */
    public function testProcessReinvokesItselfWhenRouteDoesNotMatchCurrentUrl()
    {
        // e.g., handler matches "/foo", but path is "/bar"
        $request = $this->request->withUri(new Uri('http://local.example.com/bar'));
        $response = $this->prophesize(ResponseInterface::class)->reveal();

        $first = $this->prophesize(ServerMiddlewareInterface::class);
        $first
            ->process($request, Argument::type(Next::class))
            ->will(function () {
                // This one should be skipped
                Assert::fail('Route should not be invoked if path does not match');
            });
        $this->queue->enqueue(new Route('/foo', $first->reveal()));

        $second = $this->prophesize(ServerMiddlewareInterface::class);
        $second
            ->process(Argument::type(RequestInterface::class), Argument::type(Next::class))
            ->willReturn($response);
        $this->queue->enqueue(new Route('/bar', $second->reveal()));

        $next = new Next($this->queue);

        $this->assertSame($response, $next->process($request));
    }

    /**
     * @group http-interop
     */
    public function testProcessReinvokesItselfIfRouteDoesNotMatchAtABoundary()
    {
        // e.g., if route is "/foo", but path is "/foobar", no match
        $request = $this->request->withUri(new Uri('http://local.example.com/foobar'));
        $response = $this->prophesize(ResponseInterface::class)->reveal();

        $first = $this->prophesize(ServerMiddlewareInterface::class);
        $first
            ->process($request, Argument::type(Next::class))
            ->will(function () {
                // This one should be skipped
                Assert::fail('Route should not be invoked if path does not match');
            });
        $this->queue->enqueue(new Route('/foo', $first->reveal()));

        $second = $this->prophesize(ServerMiddlewareInterface::class);
        $second
            ->process(Argument::type(RequestInterface::class), Argument::type(Next::class))
            ->willReturn($response);
        $this->queue->enqueue(new Route('/foobar', $second->reveal()));

        $next = new Next($this->queue);
        $this->assertSame($response, $next->process($request));
    }

    /**
     * @group http-interop
     */
    public function testProcessDispatchesHandlerWhenMatched()
    {
        $request = $this->request->withUri(new Uri('http://local.example.com/foo'));
        $response = $this->prophesize(ResponseInterface::class)->reveal();

        $middleware = $this->prophesize(ServerMiddlewareInterface::class);
        $middleware
            ->process(Argument::type(RequestInterface::class), Argument::type(Next::class))
            ->willReturn($response);
        $this->queue->enqueue(new Route('/foo', $middleware->reveal()));

        $next = new Next($this->queue);
        $this->assertSame($response, $next->process($request));
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

        $middleware = $this->prophesize(ServerMiddlewareInterface::class);
        $middleware
            ->process(Argument::that(function ($arg) {
                Assert::assertInstanceOf(RequestInterface::class, $arg);
                Assert::assertEquals('/bar', $arg->getUri()->getPath());
                return true;
            }), Argument::type(Next::class))
            ->willReturn($response);
        $this->queue->enqueue(new Route('/foo', $middleware->reveal()));

        $next = new Next($this->queue);
        $this->assertSame($response, $next->process($request));
    }

    /**
     * @group http-interop
     */
    public function testSlashAndPathGetResetByProcessBeforeExecutingNextMiddleware()
    {
        $request = $this->request->withUri(new Uri('http://example.com/foo/baz/bat'));
        $response = $this->prophesize(ResponseInterface::class)->reveal();

        $route1 = $this->prophesize(ServerMiddlewareInterface::class);
        $route1
            ->process(Argument::type(RequestInterface::class), Argument::type(Next::class))
            ->will(function ($args) {
                $request = $args[0];
                $next = $args[1];
                return $next->process($request);
            });
        $this->queue->enqueue(new Route('/foo', $route1->reveal()));

        $route2 = $this->prophesize(ServerMiddlewareInterface::class);
        $route2
            ->process(Argument::type(RequestInterface::class), Argument::type(Next::class))
            ->shouldNotBeCalled();
        $this->queue->enqueue(new Route('/foo/bar', $route2->reveal()));

        $route3 = $this->prophesize(ServerMiddlewareInterface::class);
        $route3
            ->process(Argument::that(function ($arg) {
                Assert::assertEquals('/bat', $arg->getUri()->getPath());
                return true;
            }), Argument::type(Next::class))
            ->willReturn($response);
        $this->queue->enqueue(new Route('/foo/baz', $route3->reveal()));

        $next = new Next($this->queue);
        $this->assertSame($response, $next->process($request));
    }

    /**
     * @group http-interop
     */
    public function testMiddlewareReturningResponseShortCircuitsProcess()
    {
        $request = $this->request->withUri(new Uri('http://example.com/foo/bar/baz'));
        $response = $this->prophesize(ResponseInterface::class)->reveal();

        $route1 = $this->prophesize(ServerMiddlewareInterface::class);
        $route1
            ->process(Argument::that(function ($arg) {
                Assert::assertEquals('/bar/baz', $arg->getUri()->getPath());
                return true;
            }), Argument::type(Next::class))
            ->willReturn($response);
        $this->queue->enqueue(new Route('/foo', $route1->reveal()));

        $route2 = $this->prophesize(ServerMiddlewareInterface::class);
        $route2
            ->process(Argument::type(RequestInterface::class), Argument::type(Next::class))
            ->shouldNotBeCalled();
        $this->queue->enqueue(new Route('/foo/bar', $route2->reveal()));

        $next = new Next($this->queue);
        $this->assertSame($response, $next->process($request));
    }

    /**
     * @group http-interop
     */
    public function testProcessRaisesExceptionIfNoResponseReturnedByMiddleware()
    {
        $request = $this->request->withUri(new Uri('http://example.com/foo/bar/baz'));

        $route1 = $this->prophesize(ServerMiddlewareInterface::class);
        $route1
            ->process(Argument::that(function ($arg) {
                Assert::assertEquals('/bar/baz', $arg->getUri()->getPath());
                return true;
            }), Argument::type(Next::class))
            ->willReturn('foobar');
        $this->queue->enqueue(new Route('/foo', $route1->reveal()));

        $next = new Next($this->queue);

        $this->setExpectedException(Exception\MissingResponseException::class);
        $next->process($request);
    }
}
