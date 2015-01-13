<?php
namespace PhlyTest\Conduit;

use ArrayObject;
use Phly\Conduit\Http\Request;
use Phly\Conduit\Http\Response;
use Phly\Conduit\Next;
use Phly\Conduit\Route;
use Phly\Http\ServerRequest as PsrRequest;
use Phly\Http\Response as PsrResponse;
use PHPUnit_Framework_TestCase as TestCase;

class NextTest extends TestCase
{
    public function setUp()
    {
        $psrRequest = new PsrRequest('php://memory');
        $psrRequest = $psrRequest->setMethod('GET');
        $psrRequest = $psrRequest->setAbsoluteUri('http://example.com/');

        $this->stack    = new ArrayObject();
        $this->request  = new Request($psrRequest);
        $this->response = new Response(new PsrResponse());
    }

    public function testDoneHandlerIsInvokedWhenStackIsExhausted()
    {
        // e.g., 0 length array, or all handlers call next
        $triggered = null;
        $done = function ($err = null) use (&$triggered) {
            $triggered = true;
        };

        $next = new Next($this->stack, $this->request, $this->response, $done);
        $next();
        $this->assertTrue($triggered);
    }

    public function testInvokesItselfWhenRouteDoesNotMatchCurrentUrl()
    {
        // e.g., handler matches "/foo", but path is "/bar"
        $phpunit = $this;
        $route = new Route('/foo', function ($req, $res, $next) use ($phpunit) {
            $phpunit->fail('Route should not be invoked if path does not match');
        });
        $this->stack[] = $route;

        $triggered = null;
        $done = function ($err = null) use (&$triggered) {
            $triggered = true;
        };

        $this->request->setUrl('http://local.example.com/bar');

        $next = new Next($this->stack, $this->request, $this->response, $done);
        $next();
        $this->assertTrue($triggered);
    }

    public function testInvokesItselfIfRouteDoesNotMatchAtABoundary()
    {
        // e.g., if route is "/foo", but path is "/foobar", no match
        $phpunit = $this;
        $route = new Route('/foo', function ($req, $res, $next) use ($phpunit) {
            $phpunit->fail('Route should not be invoked if path does not match');
        });
        $this->stack[] = $route;

        $triggered = null;
        $done = function ($err = null) use (&$triggered) {
            $triggered = true;
        };

        $this->request->setUrl('http://local.example.com/foobar');

        $next = new Next($this->stack, $this->request, $this->response, $done);
        $next();
        $this->assertTrue($triggered);
    }

    public function testInvokesHandlerWhenMatched()
    {
        // e.g., if route is "/foo", but path is "/foobar", no match
        $triggered = null;
        $route = new Route('/foo', function ($req, $res, $next) use (&$triggered) {
            $triggered = true;
        });
        $this->stack[] = $route;

        $phpunit = $this;
        $done = function ($err = null) use ($phpunit) {
            $phpunit->fail('Should not hit done handler');
        };

        $request = $this->request->setUrl('http://local.example.com/foo');

        $next = new Next($this->stack, $request, $this->response, $done);
        $next();
        $this->assertTrue($triggered);
    }

    public function testRequestUriInInvokedHandlerDoesNotContainMatchedPortionOfRoute()
    {
        // e.g., if route is "/foo", and "/foo/bar" is the original path,
        // then the URI path in the handler is "/bar"
        $triggered = null;
        $route = new Route('/foo', function ($req, $res, $next) use (&$triggered) {
            $triggered = parse_url($req->getUrl(), PHP_URL_PATH);
        });
        $this->stack[] = $route;

        $phpunit = $this;
        $done = function ($err = null) use ($phpunit) {
            $phpunit->fail('Should not hit done handler');
        };

        $request = $this->request->setUrl('http://local.example.com/foo/bar');

        $next = new Next($this->stack, $request, $this->response, $done);
        $next();
        $this->assertEquals('/bar', $triggered);
    }

    public function testSlashAndPathGetResetBeforeExecutingNextMiddleware()
    {
        $route1 = new Route('/foo', function ($req, $res, $next) {
            $next();
        });
        $route2 = new Route('/foo/bar', function ($req, $res, $next) {
            $next();
        });
        $route3 = new Route('/foo/baz', function ($req, $res, $next) {
            $res->end('done');
            return $res;
        });

        $this->stack->append($route1);
        $this->stack->append($route2);
        $this->stack->append($route3);

        $phpunit = $this;
        $done = function ($err) use ($phpunit) {
            $phpunit->fail('Should not hit final handler');
        };

        $request = $this->request->setUrl('http://example.com/foo/baz/bat');
        $next = new Next($this->stack, $request, $this->response, $done);
        $next();
        $this->assertEquals('done', (string) $this->response->getBody());
    }

    public function testMiddlewareReturningResponseShortcircuits()
    {
        $phpunit = $this;
        $route1 = new Route('/foo', function ($req, $res, $next) {
            return $res;
        });
        $route2 = new Route('/foo/bar', function ($req, $res, $next) use ($phpunit) {
            $next();
            $phpunit->fail('Should not hit route2 handler');
        });
        $route3 = new Route('/foo/baz', function ($req, $res, $next) use ($phpunit) {
            $next();
            $phpunit->fail('Should not hit route3 handler');
        });

        $this->stack->append($route1);
        $this->stack->append($route2);
        $this->stack->append($route3);

        $done = function ($err) use ($phpunit) {
            $phpunit->fail('Should not hit final handler');
        };

        $request = $this->request->setUrl('http://example.com/foo/bar/baz');
        $next = new Next($this->stack, $request, $this->response, $done);
        $result = $next();
        $this->assertSame($this->response, $result);
    }

    public function testMiddlewareCallingNextWithResponseShortCircuits()
    {
        $phpunit = $this;
        $cannedResponse = new Response(new PsrResponse());

        $route1 = new Route('/foo', function ($req, $res, $next) use ($cannedResponse) {
            return $next($cannedResponse);
        });
        $route2 = new Route('/foo/bar', function ($req, $res, $next) use ($phpunit) {
            $next();
            $phpunit->fail('Should not hit route2 handler');
        });
        $route3 = new Route('/foo/baz', function ($req, $res, $next) use ($phpunit) {
            $next();
            $phpunit->fail('Should not hit route3 handler');
        });

        $this->stack->append($route1);
        $this->stack->append($route2);
        $this->stack->append($route3);

        $done = function ($err) use ($phpunit) {
            $phpunit->fail('Should not hit final handler');
        };

        $request = $this->request->setUrl('http://example.com/foo/bar/baz');
        $next = new Next($this->stack, $request, $this->response, $done);
        $result = $next();
        $this->assertSame($cannedResponse, $result);
    }

    public function testMiddlewareCallingNextWithRequestResetsRequest()
    {
        $phpunit       = $this;
        $request       = $this->request->setUrl('http://example.com/foo/bar/baz');
        $cannedRequest = clone $request;
        $cannedRequest = $cannedRequest->setMethod('POST');

        $route1 = new Route('/foo/bar', function ($req, $res, $next) use ($cannedRequest) {
            return $next($cannedRequest);
        });
        $route2 = new Route('/foo/bar/baz', function ($req, $res, $next) use ($phpunit, $cannedRequest) {
            $phpunit->assertEquals($cannedRequest->getMethod(), $req->getMethod());
            return $res;
        });

        $this->stack->append($route1);
        $this->stack->append($route2);

        $done = function ($err) use ($phpunit) {
            $phpunit->fail('Should not hit final handler');
        };

        $next = new Next($this->stack, $request, $this->response, $done);
        $next();
    }

    public function testMiddlewareCallingNextWithResponseResetsResponse()
    {
        $phpunit        = $this;
        $cannedResponse = new Response(new PsrResponse());

        $route1 = new Route('/foo', function ($req, $res, $next) use ($cannedResponse) {
            return $next(null, $cannedResponse);
        });
        $route2 = new Route('/foo/bar', function ($req, $res, $next) use ($phpunit, $cannedResponse) {
            $phpunit->assertSame($cannedResponse, $res);
            return $res;
        });

        $this->stack->append($route1);
        $this->stack->append($route2);

        $done = function ($err) use ($phpunit) {
            $phpunit->fail('Should not hit final handler');
        };

        $request = $this->request->setUrl('http://example.com/foo/bar/baz');
        $next = new Next($this->stack, $request, $this->response, $done);
        $next();
    }

    public function testNextShouldReturnCurrentResponseAlways()
    {
        $phpunit        = $this;
        $cannedResponse = new Response(new PsrResponse());

        $route1 = new Route('/foo', function ($req, $res, $next) use ($cannedResponse) {
            $next(null, $cannedResponse);
        });
        $route2 = new Route('/foo/bar', function ($req, $res, $next) use ($phpunit, $cannedResponse) {
            $phpunit->assertSame($cannedResponse, $res);
            return $res;
        });

        $this->stack->append($route1);
        $this->stack->append($route2);

        $done = function ($err) use ($phpunit) {
            $phpunit->fail('Should not hit final handler');
        };

        $request = $this->request->setUrl('http://example.com/foo/bar/baz');
        $next    = new Next($this->stack, $request, $this->response, $done);
        $result  = $next();
        $this->assertSame($cannedResponse, $result);
    }
}
