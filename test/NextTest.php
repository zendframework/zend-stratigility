<?php
/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @see       http://github.com/zendframework/zend-stratigility for the canonical source repository
 * @copyright Copyright (c) 2015-2016 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-stratigility/blob/master/LICENSE.md New BSD License
 */

namespace ZendTest\Stratigility;

use PHPUnit_Framework_TestCase as TestCase;
use ReflectionProperty;
use SplQueue;
use Zend\Diactoros\ServerRequest as Request;
use Zend\Diactoros\Response;
use Zend\Diactoros\Uri;
use Zend\Stratigility\Next;
use Zend\Stratigility\Route;

class NextTest extends TestCase
{
    public function setUp()
    {
        $this->queue    = new SplQueue();
        $this->request  = new Request([], [], 'http://example.com/', 'GET', 'php://memory');
        $this->response = new Response();
    }

    public function testReturnsResponseAtInvocationWhenQueueIsExhausted()
    {
        $next = new Next($this->queue);
        $this->assertSame($this->response, $next($this->request, $this->response));
    }

    public function testInvokesItselfWhenRouteDoesNotMatchCurrentUrl()
    {
        // e.g., handler matches "/foo", but path is "/bar"
        $route = new Route('/foo', function ($req, $res, $next) {
            $this->fail('Route should not be invoked if path does not match');
        });
        $this->queue->enqueue($route);

        $triggered = null;
        $done = new Route('/', function ($req, $res) use (&$triggered) {
            $triggered = true;
        });
        $this->queue->enqueue($done);

        $this->request->withUri(new Uri('http://local.example.com/bar'));

        $next = new Next($this->queue);
        $next($this->request, $this->response);
        $this->assertTrue($triggered);
    }

    public function testInvokesItselfIfRouteDoesNotMatchAtABoundary()
    {
        // e.g., if route is "/foo", but path is "/foobar", no match
        $route = new Route('/foo', function ($req, $res, $next) {
            $this->fail('Route should not be invoked if path does not match');
        });
        $this->queue->enqueue($route);

        $triggered = null;
        $done = new Route('/', function ($req, $res, $err = null) use (&$triggered) {
            $triggered = true;
        });
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
        $route = new Route('/foo', function ($req, $res, $next) use (&$triggered) {
            $triggered = true;
        });
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
        $route = new Route('/foo', function ($req, $res, $next) use (&$triggered) {
            $triggered = $req->getUri()->getPath();
        });
        $this->queue->enqueue($route);

        $request = $this->request->withUri(new Uri('http://local.example.com/foo/bar'));

        $next = new Next($this->queue);
        $next($request, $this->response);
        $this->assertEquals('/bar', $triggered);
    }

    public function testSlashAndPathGetResetBeforeExecutingNextMiddleware()
    {
        $route1 = new Route('/foo', function ($req, $res, $next) {
            $next($req, $res);
        });
        $route2 = new Route('/foo/bar', function ($req, $res, $next) {
            $next($req, $res);
        });
        $route3 = new Route('/foo/baz', function ($req, $res, $next) {
            $res->getBody()->write('done');
            return $res;
        });

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
        $route1 = new Route('/foo', function ($req, $res, $next) {
            return $res;
        });
        $route2 = new Route('/foo/bar', function ($req, $res, $next) {
            $next($req, $res);
            $this->fail('Should not hit route2 handler');
        });
        $route3 = new Route('/foo/baz', function ($req, $res, $next) {
            $next($req, $res);
            $this->fail('Should not hit route3 handler');
        });

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

        $route1 = new Route('/foo/bar', function ($req, $res, $next) use ($cannedRequest) {
            return $next($cannedRequest, $res);
        });
        $route2 = new Route('/foo/bar/baz', function ($req, $res, $next) use ($cannedRequest) {
            $this->assertEquals($cannedRequest->getMethod(), $req->getMethod());
            return $res;
        });

        $this->queue->enqueue($route1);
        $this->queue->enqueue($route2);

        $next = new Next($this->queue);
        $next($request, $this->response);
    }

    public function testMiddlewareCallingNextWithResponseResetsResponse()
    {
        $cannedResponse = new Response();

        $route1 = new Route('/foo', function ($req, $res, $next) use ($cannedResponse) {
            return $next($req, $cannedResponse);
        });
        $route2 = new Route('/foo/bar', function ($req, $res, $next) use ($cannedResponse) {
            $this->assertSame($cannedResponse, $res);
            return $res;
        });

        $this->queue->enqueue($route1);
        $this->queue->enqueue($route2);

        $request = $this->request->withUri(new Uri('http://example.com/foo/bar/baz'));
        $next = new Next($this->queue);
        $next($request, $this->response);
    }

    public function testNextShouldReturnReturnValueOfMiddlewareInvoked()
    {
        $cannedResponse = new Response();

        $route1 = new Route('/foo', function ($req, $res, $next) use ($cannedResponse) {
            $next($req, $cannedResponse);
        });
        $route2 = new Route('/foo/bar', function ($req, $res, $next) use ($cannedResponse) {
            $this->assertSame($cannedResponse, $res);
            return $res;
        });

        $this->queue->enqueue($route1);
        $this->queue->enqueue($route2);

        $request = $this->request->withUri(new Uri('http://example.com/foo/bar/baz'));
        $next    = new Next($this->queue);
        $result  = $next($request, $this->response);

        $this->assertNull($result);
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
}
