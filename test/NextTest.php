<?php
/**
 * @see       https://github.com/zendframework/zend-stratigility for the canonical source repository
 * @copyright Copyright (c) 2015-2018 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-stratigility/blob/master/LICENSE.md New BSD License
 */

namespace ZendTest\Stratigility;

use PHPUnit\Framework\Assert;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use ReflectionProperty;
use SplQueue;
use Webimpress\HttpMiddlewareCompatibility\HandlerInterface as DelegateInterface;
use Webimpress\HttpMiddlewareCompatibility\MiddlewareInterface as ServerMiddlewareInterface;
use Zend\Diactoros\Response;
use Zend\Diactoros\ServerRequest as Request;
use Zend\Diactoros\Uri;
use Zend\Stratigility\Exception;
use Zend\Stratigility\Middleware\DoublePassMiddlewareDecorator;
use Zend\Stratigility\Middleware\PathMiddlewareDecorator;
use Zend\Stratigility\Next;
use Zend\Stratigility\Route;

use const Webimpress\HttpMiddlewareCompatibility\HANDLER_METHOD;

use function Zend\Stratigility\middleware;

class NextTest extends TestCase
{
    protected $errorHandler;

    public function setUp()
    {
        $this->queue     = new SplQueue();
        $this->request   = new Request([], [], 'http://example.com/', 'GET', 'php://memory');
        $this->response  = new Response();
    }

    /**
     * Decorate double-pass middleware to test against.
     *
     * @param callable $middleware
     * @param null|string $path to segregate against
     * @return ServerMiddlewareInterface
     */
    public function decorateCallableMiddleware(callable $middleware, $path = null)
    {
        $middleware = new DoublePassMiddlewareDecorator($middleware, $this->response);
        $middleware = $path ? new PathMiddlewareDecorator($path, $middleware) : $middleware;
        return $middleware;
    }

    public function testMiddlewareReturningResponseShortcircuits()
    {
        $route1 = new Route('/foo', $this->decorateCallableMiddleware(
            function ($req, $res, $next) {
                return $res;
            },
            '/foo'
        ));
        $route2 = new Route('/foo/bar', $this->decorateCallableMiddleware(
            function ($req, $res, $next) {
                $next($req, $res);
                $this->fail('Should not hit route2 handler');
            },
            '/foo/bar'
        ));
        $route3 = new Route('/foo/baz', $this->decorateCallableMiddleware(
            function ($req, $res, $next) {
                $next($req, $res);
                $this->fail('Should not hit route3 handler');
            },
            '/foo/baz'
        ));

        $this->queue->enqueue($route1);
        $this->queue->enqueue($route2);
        $this->queue->enqueue($route3);

        $request = $this->request->withUri(new Uri('http://example.com/foo/bar/baz'));
        $next = new Next($this->queue);
        $result = $next($request);
        $this->assertSame($this->response, $result);
    }

    public function testMiddlewareCallingNextWithRequestPassesRequestToNextMiddleware()
    {
        $request       = $this->request->withUri(new Uri('http://example.com/foo/bar/baz'));
        $cannedRequest = clone $request;
        $cannedRequest = $cannedRequest->withMethod('POST');

        $route1 = new Route('/', $this->decorateCallableMiddleware(
            function ($req, $res, $next) use ($cannedRequest) {
                return $next($cannedRequest, $res);
            }
        ));
        $route2 = new Route('/', $this->decorateCallableMiddleware(
            function ($req, $res, $next) use ($cannedRequest) {
                $this->assertEquals($cannedRequest->getMethod(), $req->getMethod());
                return $res;
            }
        ));

        $this->queue->enqueue($route1);
        $this->queue->enqueue($route2);

        $next = new Next($this->queue);
        $next($request);
    }

    public function testNextShouldRaiseExceptionIfMiddlewareDoesNotReturnResponse()
    {
        $route1 = new Route('/foo', $this->decorateCallableMiddleware(
            function ($req, $res, $next) {
                // Explicitly not returning a value
                $next($req, $res);
            },
            '/foo'
        ));
        $route2 = new Route('/foo/bar', $this->decorateCallableMiddleware(
            function ($req, $res, $next) {
                return $res;
            },
            '/foo/bar'
        ));

        $this->queue->enqueue($route1);
        $this->queue->enqueue($route2);

        $request = $this->request->withUri(new Uri('http://example.com/foo/bar/baz'));
        $next    = new Next($this->queue);

        $this->expectException(Exception\MissingResponseException::class);
        $next($request);
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

        $this->expectException(Exception\MissingResponseException::class);
        $this->expectExceptionMessage('exhausted');
        $next->process($this->request);
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
            }), Argument::type(DelegateInterface::class))
            ->willReturn($response);
        $this->queue->enqueue(new Route('/foo', new PathMiddlewareDecorator('/foo', $route1->reveal())));

        $route2 = $this->prophesize(ServerMiddlewareInterface::class);
        $route2
            ->process(Argument::type(RequestInterface::class), Argument::type(Next::class))
            ->shouldNotBeCalled();
        $this->queue->enqueue(new Route('/foo/bar', new PathMiddlewareDecorator('/foo/bar', $route2->reveal())));

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
            }), Argument::type(DelegateInterface::class))
            ->willReturn('foobar');
        $this->queue->enqueue(new Route('/foo', new PathMiddlewareDecorator('/foo', $route1->reveal())));

        $next = new Next($this->queue);

        $this->expectException(Exception\MissingResponseException::class);
        $next->process($request);
    }
}
