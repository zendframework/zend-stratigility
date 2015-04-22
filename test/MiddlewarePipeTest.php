<?php
namespace PhlyTest\Conduit;

use Phly\Conduit\Http\Request as RequestDecorator;
use Phly\Conduit\Http\Response as ResponseDecorator;
use Phly\Conduit\MiddlewarePipe;
use Phly\Conduit\Utils;
use Phly\Http\ServerRequest as Request;
use Phly\Http\Response;
use Phly\Http\Uri;
use PHPUnit_Framework_TestCase as TestCase;
use ReflectionProperty;

class MiddlewarePipeTest extends TestCase
{
    public function setUp()
    {
        $this->request    = new Request([], [], 'http://example.com/', 'GET', 'php://memory');
        $this->response   = new Response();
        $this->middleware = new MiddlewarePipe();
    }

    public function invalidHandlers()
    {
        return [
            'null' => [null],
            'bool' => [true],
            'int' => [1],
            'float' => [1.1],
            'string' => ['non-function-string'],
            'array' => [['foo', 'bar']],
            'object' => [(object) ['foo' => 'bar']],
        ];
    }

    /**
     * @dataProvider invalidHandlers
     */
    public function testPipeThrowsExceptionForInvalidHandler($handler)
    {
        $this->setExpectedException('InvalidArgumentException');
        $this->middleware->pipe('/foo', $handler);
    }

    public function testHandleInvokesUntilFirstHandlerThatDoesNotCallNext()
    {
        $this->middleware->pipe(function ($req, $res, $next) {
            $res->write("First\n");
            $next($req, $res);
        });
        $this->middleware->pipe(function ($req, $res, $next) {
            $res->write("Second\n");
            $next($req, $res);
        });
        $this->middleware->pipe(function ($req, $res, $next) {
            $res->write("Third\n");
        });
        $phpunit = $this;
        $this->middleware->pipe(function ($req, $res, $next) use ($phpunit) {
            $phpunit->fail('Should not hit fourth handler!');
        });

        $request = new Request([], [], 'http://local.example.com/foo', 'GET', 'php://memory');
        $this->middleware->__invoke($request, $this->response);
        $body = (string) $this->response->getBody();
        $this->assertContains('First', $body);
        $this->assertContains('Second', $body);
        $this->assertContains('Third', $body);
    }

    public function testHandleInvokesFirstErrorHandlerOnErrorInChain()
    {
        $this->middleware->pipe(function ($req, $res, $next) {
            $next($req, $res->write("First\n"));
        });
        $this->middleware->pipe(function ($req, $res, $next) {
            return $next($req, $res, 'error');
        });
        $this->middleware->pipe(function ($req, $res, $next) {
            return $res->write("Third\n");
        });
        $this->middleware->pipe(function ($err, $req, $res, $next) {
            return $res->write("ERROR HANDLER\n");
        });
        $phpunit = $this;
        $this->middleware->pipe(function ($req, $res, $next) use ($phpunit) {
            $phpunit->fail('Should not hit fourth handler!');
        });

        $request  = new Request([], [], 'http://local.example.com/foo', 'GET', 'php://memory');
        $response = $this->middleware->__invoke($request, $this->response);
        $body     = (string) $response->getBody();
        $this->assertContains('First', $body);
        $this->assertContains('ERROR HANDLER', $body);
        $this->assertNotContains('Third', $body);
    }

    public function testHandleInvokesOutHandlerIfQueueIsExhausted()
    {
        $triggered = null;
        $out = function ($err = null) use (&$triggered) {
            $triggered = true;
        };

        $this->middleware->pipe(function ($req, $res, $next) {
            $next($req, $res);
        });
        $this->middleware->pipe(function ($req, $res, $next) {
            $next($req, $res);
        });
        $this->middleware->pipe(function ($req, $res, $next) {
            $next($req, $res);
        });

        $request = new Request([], [], 'http://local.example.com/foo', 'GET', 'php://memory');
        $this->middleware->__invoke($request, $this->response, $out);
        $this->assertTrue($triggered);
    }

    public function testPipeWillCreateErrorClosureForObjectImplementingHandle()
    {
        $this->markTestIncomplete();
        $handler = new TestAsset\ErrorHandler();
        $this->middleware->pipe($handler);
        $r = new ReflectionProperty($this->middleware, 'queue');
        $r->setAccessible(true);
        $queue = $r->getValue($this->middleware);
        $route = $queue[$queue->count() - 1];
        $this->assertInstanceOf('Phly\Conduit\Route', $route);
        $handler = $route->handler;
        $this->assertInstanceOf('Closure', $handler);
        $this->assertEquals(4, Utils::getArity($handler));
    }

    public function testCanUseDecoratedRequestAndResponseDirectly()
    {
        $baseRequest = new Request([], [], 'http://local.example.com/foo', 'GET', 'php://memory');

        $request  = new RequestDecorator($baseRequest);
        $response = new ResponseDecorator($this->response);
        $phpunit  = $this;
        $executed = false;

        $middleware = $this->middleware;
        $middleware->pipe(function ($req, $res, $next) use ($phpunit, $request, $response, &$executed) {
            $phpunit->assertSame($request, $req);
            $phpunit->assertSame($response, $res);
            $executed = true;
        });

        $middleware($request, $response, function ($err = null) use ($phpunit) {
            $phpunit->fail('Next should not be called');
        });

        $this->assertTrue($executed);
    }

    public function testReturnsOrigionalResponseIfQueueDoesNotReturnAResponse()
    {
        $this->middleware->pipe(function ($req, $res, $next) {
            $next($req, $res);
        });
        $this->middleware->pipe(function ($req, $res, $next) {
            $next($req, $res);
        });
        $this->middleware->pipe(function ($req, $res, $next) {
            return;
        });
        $phpunit = $this;
        $this->middleware->pipe(function ($req, $res, $next) use ($phpunit) {
            $phpunit->fail('Should not hit fourth handler!');
        });

        $request = new Request([], [], 'http://local.example.com/foo', 'GET', 'php://memory');
        $result  = $this->middleware->__invoke($request, $this->response);
        $this->assertSame($this->response, $result->getOriginalResponse());
    }

    public function testReturnsResponseReturnedByQueue()
    {
        $return = new Response();

        $this->middleware->pipe(function ($req, $res, $next) {
            return $next($req, $res);
        });
        $this->middleware->pipe(function ($req, $res, $next) {
            return $next($req, $res);
        });
        $this->middleware->pipe(function ($req, $res, $next) use ($return) {
            return $return;
        });
        $phpunit = $this;
        $this->middleware->pipe(function ($req, $res, $next) use ($phpunit) {
            $phpunit->fail('Should not hit fourth handler!');
        });

        $request = new Request([], [], 'http://local.example.com/foo', 'GET', 'php://memory');
        $result  = $this->middleware->__invoke($request, $this->response);
        $this->assertSame($return, $result, var_export([
            spl_object_hash($return) => get_class($return),
            spl_object_hash($result) => get_class($result),
        ], 1));
    }

    public function testSlashShouldNotBeAppendedInChildMiddlewareWhenLayerDoesNotIncludeIt()
    {
        $this->middleware->pipe('/admin', function ($req, $res, $next) {
            return $next($req, $res);
        });
        $phpunit = $this;
        $this->middleware->pipe(function ($req, $res, $next) use ($phpunit) {
            return $res->write($req->getUri()->getPath());
        });
        $request = new Request([], [], 'http://local.example.com/admin', 'GET', 'php://memory');
        $result  = $this->middleware->__invoke($request, $this->response);
        $body    = (string) $result->getBody();
        $this->assertSame('/admin', $body);
    }

    public function testSlashShouldBeAppendedInChildMiddlewareWhenRequestUriIncludesIt()
    {
        $this->middleware->pipe('/admin', function ($req, $res, $next) {
            return $next($req, $res);
        });
        $phpunit = $this;
        $this->middleware->pipe(function ($req, $res, $next) use ($phpunit) {
            return $res->write($req->getUri()->getPath());
        });
        $request = new Request([], [], 'http://local.example.com/admin/', 'GET', 'php://memory');
        $result  = $this->middleware->__invoke($request, $this->response);
        $body    = (string) $result->getBody();
        $this->assertSame('/admin/', $body);
    }

    public function testNestedMiddlewareMayInvokeDoneToInvokeNextOfParent()
    {
        $child = new MiddlewarePipe();
        $child->pipe('/', function ($req, $res, $next) {
            return $next($req, $res);
        });

        $this->middleware->pipe(function ($req, $res, $next) {
            return $next($req, $res);
        });

        $this->middleware->pipe('/test', $child);

        $triggered = false;
        $this->middleware->pipe(function ($req, $res, $next) use (&$triggered) {
            $triggered = true;
            return $res;
        });

        $request = new Request([], [], 'http://local.example.com/test', 'GET', 'php://memory');
        $result  = $this->middleware->__invoke($request, $this->response);
        $this->assertTrue($triggered);
        $this->assertInstanceOf('Phly\Conduit\Http\Response', $result);
        $this->assertSame($this->response, $result->getOriginalResponse());
    }

    public function testMiddlewareRequestPathMustBeTrimmedOffWithPipeRoutePath()
    {
        $request  = new Request([], [], 'http://local.example.com/foo/bar', 'GET', 'php://memory');

        $phpunit  = $this;
        $executed = false;

        $this->middleware->pipe('/foo', function ($req, $res, $next) use ($phpunit, &$executed) {
            $phpunit->assertEquals('/bar', $req->getUri()->getPath());
            $executed = true;
        });

        $this->middleware->__invoke($request, $this->response);
        $this->assertTrue($executed);
    }

    public function rootPaths()
    {
        return [
            'empty' => [''],
            'root'  => ['/'],
        ];
    }

    /**
     * @group matching
     * @dataProvider rootPaths
     */
    public function testMiddlewareTreatsBothSlashAndEmptyPathAsTheRootPath($path)
    {
        $middleware = $this->middleware;
        $middleware->pipe($path, function ($req, $res) {
            return $res->withHeader('X-Found', 'true');
        });
        $uri     = (new Uri())->withPath($path);
        $request = (new Request)->withUri($uri);

        $response = $middleware($request, $this->response);
        $this->assertTrue($response->hasHeader('x-found'));
    }

    public function nestedPaths()
    {
        return [
            'empty-bare-bare'        => ['',      'foo',   '/foo'],
            'empty-bare-tail'        => ['',      'foo',   '/foo/'],
            'empty-tail-bare'        => ['',      'foo/',  '/foo'],
            'empty-tail-tail'        => ['',      'foo/',  '/foo/'],
            'empty-prefix-bare'      => ['',      '/foo',  '/foo'],
            'empty-prefix-tail'      => ['',      '/foo',  '/foo/'],
            'empty-surround-bare'    => ['',      '/foo/', '/foo'],
            'empty-surround-tail'    => ['',      '/foo/', '/foo/'],
            'root-bare-bare'         => ['/',     'foo',   '/foo'],
            'root-bare-tail'         => ['/',     'foo',   '/foo/'],
            'root-tail-bare'         => ['/',     'foo/',  '/foo'],
            'root-tail-tail'         => ['/',     'foo/',  '/foo/'],
            'root-prefix-bare'       => ['/',     '/foo',  '/foo'],
            'root-prefix-tail'       => ['/',     '/foo',  '/foo/'],
            'root-surround-bare'     => ['/',     '/foo/', '/foo'],
            'root-surround-tail'     => ['/',     '/foo/', '/foo/'],
            'bare-bare-bare'         => ['foo',   'bar',   '/foo/bar'],
            'bare-bare-tail'         => ['foo',   'bar',   '/foo/bar/'],
            'bare-tail-bare'         => ['foo',   'bar/',  '/foo/bar'],
            'bare-tail-tail'         => ['foo',   'bar/',  '/foo/bar/'],
            'bare-prefix-bare'       => ['foo',   '/bar',  '/foo/bar'],
            'bare-prefix-tail'       => ['foo',   '/bar',  '/foo/bar/'],
            'bare-surround-bare'     => ['foo',   '/bar/', '/foo/bar'],
            'bare-surround-tail'     => ['foo',   '/bar/', '/foo/bar/'],
            'tail-bare-bare'         => ['foo/',  'bar',   '/foo/bar'],
            'tail-bare-tail'         => ['foo/',  'bar',   '/foo/bar/'],
            'tail-tail-bare'         => ['foo/',  'bar/',  '/foo/bar'],
            'tail-tail-tail'         => ['foo/',  'bar/',  '/foo/bar/'],
            'tail-prefix-bare'       => ['foo/',  '/bar',  '/foo/bar'],
            'tail-prefix-tail'       => ['foo/',  '/bar',  '/foo/bar/'],
            'tail-surround-bare'     => ['foo/',  '/bar/', '/foo/bar'],
            'tail-surround-tail'     => ['foo/',  '/bar/', '/foo/bar/'],
            'prefix-bare-bare'       => ['/foo',  'bar',   '/foo/bar'],
            'prefix-bare-tail'       => ['/foo',  'bar',   '/foo/bar/'],
            'prefix-tail-bare'       => ['/foo',  'bar/',  '/foo/bar'],
            'prefix-tail-tail'       => ['/foo',  'bar/',  '/foo/bar/'],
            'prefix-prefix-bare'     => ['/foo',  '/bar',  '/foo/bar'],
            'prefix-prefix-tail'     => ['/foo',  '/bar',  '/foo/bar/'],
            'prefix-surround-bare'   => ['/foo',  '/bar/', '/foo/bar'],
            'prefix-surround-tail'   => ['/foo',  '/bar/', '/foo/bar/'],
            'surround-bare-bare'     => ['/foo/', 'bar',   '/foo/bar'],
            'surround-bare-tail'     => ['/foo/', 'bar',   '/foo/bar/'],
            'surround-tail-bare'     => ['/foo/', 'bar/',  '/foo/bar'],
            'surround-tail-tail'     => ['/foo/', 'bar/',  '/foo/bar/'],
            'surround-prefix-bare'   => ['/foo/', '/bar',  '/foo/bar'],
            'surround-prefix-tail'   => ['/foo/', '/bar',  '/foo/bar/'],
            'surround-surround-bare' => ['/foo/', '/bar/', '/foo/bar'],
            'surround-surround-tail' => ['/foo/', '/bar/', '/foo/bar/'],
        ];
    }

    /**
     * @group matching
     * @group nesting
     * @dataProvider nestedPaths
     */
    public function testNestedMiddlewareMatchesOnlyAtPathBoundaries($topPath, $nestedPath, $match)
    {
        $middleware = $this->middleware;

        $nest = new MiddlewarePipe();
        $nest->pipe($nestedPath, function ($req, $res) use ($nestedPath) {
            return $res->withHeader('X-Found', 'true');
        });
        $middleware->pipe($topPath, function ($req, $res, $next = null) use ($topPath, $nest) {
            $result = $nest($req, $res, $next);
            return $result;
        });

        $uri      = (new Uri())->withPath($match);
        $request  = (new Request)->withUri($uri);
        $response = $middleware($request, $this->response);
        $this->assertTrue(
            $response->hasHeader('X-Found'),
            sprintf(
                "Failed matching %s against top path '%s' and nested path '%s'\n",
                $match,
                $topPath,
                $nestedPath
            )
        );
    }
}
