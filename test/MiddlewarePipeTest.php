<?php
/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @see       http://github.com/zendframework/zend-stratigility for the canonical source repository
 * @copyright Copyright (c) 2015-2016 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-stratigility/blob/master/LICENSE.md New BSD License
 */

namespace ZendTest\Stratigility;

use Interop\Http\Middleware\DelegateInterface;
use Interop\Http\Middleware\MiddlewareInterface;
use Interop\Http\Middleware\ServerMiddlewareInterface;
use PHPUnit_Framework_Assert as Assert;
use PHPUnit_Framework_TestCase as TestCase;
use Prophecy\Argument;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use ReflectionProperty;
use RuntimeException;
use Zend\Diactoros\ServerRequest as Request;
use Zend\Diactoros\Response;
use Zend\Diactoros\Uri;
use Zend\Stratigility\MiddlewarePipe;
use Zend\Stratigility\Middleware\CallableInteropMiddlewareWrapper;
use Zend\Stratigility\Middleware\CallableMiddlewareWrapper;
use Zend\Stratigility\NoopFinalHandler;
use Zend\Stratigility\Utils;

class MiddlewarePipeTest extends TestCase
{
    public function setUp()
    {
        $this->request    = new Request([], [], 'http://example.com/', 'GET', 'php://memory');
        $this->response   = new Response();
        $this->middleware = new MiddlewarePipe();
        $this->middleware->setResponsePrototype($this->response);
    }

    /**
     * @return NoopFinalHandler
     */
    public function createFinalHandler()
    {
        return new NoopFinalHandler();
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
            $res->getBody()->write("First\n");
            return $next($req, $res);
        });
        $this->middleware->pipe(function ($req, $res, $next) {
            $res->getBody()->write("Second\n");
            return $next($req, $res);
        });
        $this->middleware->pipe(function ($req, $res, $next) {
            $res->getBody()->write("Third\n");
            return $res;
        });

        $this->middleware->pipe(function ($req, $res, $next) {
            $this->fail('Should not hit fourth handler!');
        });

        $request = new Request([], [], 'http://local.example.com/foo', 'GET', 'php://memory');
        $this->middleware->__invoke($request, $this->response, $this->createFinalHandler());
        $body = (string) $this->response->getBody();
        $this->assertContains('First', $body);
        $this->assertContains('Second', $body);
        $this->assertContains('Third', $body);
    }

    public function testInvokesDelegateWhenQueueIsExhausted()
    {
        $expected = $this->prophesize(ResponseInterface::class)->reveal();
        $this->middleware->setResponsePrototype($expected);

        $this->middleware->pipe(function ($req, $res, $next) {
            return $next($req, $res);
        });
        $this->middleware->pipe(function ($req, $res, $next) {
            return $next($req, $res);
        });
        $this->middleware->pipe(function ($req, $res, $next) {
            return $next($req, $res);
        });

        $request = new Request([], [], 'http://local.example.com/foo', 'GET', 'php://memory');

        $delegate = $this->prophesize(DelegateInterface::class);
        $delegate->process($request)->willReturn($expected);

        $result = $this->middleware->__invoke($request, $this->response, $delegate->reveal());

        $this->assertSame($expected, $result);
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

        $this->middleware->pipe(function ($req, $res, $next) {
            $this->fail('Should not hit fourth handler!');
        });

        $request = new Request([], [], 'http://local.example.com/foo', 'GET', 'php://memory');
        $result  = $this->middleware->__invoke($request, $this->response, $this->createFinalHandler());
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

        $this->middleware->pipe(function ($req, $res, $next) {
            $res->getBody()->write($req->getUri()->getPath());
            return $res;
        });

        $request = new Request([], [], 'http://local.example.com/admin', 'GET', 'php://memory');
        $result  = $this->middleware->__invoke($request, $this->response, $this->createFinalHandler());
        $body    = (string) $result->getBody();
        $this->assertSame('/admin', $body);
    }

    public function testSlashShouldBeAppendedInChildMiddlewareWhenRequestUriIncludesIt()
    {
        $this->middleware->pipe('/admin', function ($req, $res, $next) {
            return $next($req, $res);
        });

        $this->middleware->pipe(function ($req, $res, $next) {
            $res->getBody()->write($req->getUri()->getPath());
            return $res;
        });

        $request = new Request([], [], 'http://local.example.com/admin/', 'GET', 'php://memory');
        $result  = $this->middleware->__invoke($request, $this->response, $this->createFinalHandler());
        $body    = (string) $result->getBody();
        $this->assertSame('/admin/', $body);
    }

    public function testNestedMiddlewareMayInvokeDoneToInvokeNextOfParent()
    {
        $childMiddleware = $this->prophesize(ServerMiddlewareInterface::class);
        $childMiddleware
            ->process(Argument::type(ServerRequestInterface::class), Argument::type(DelegateInterface::class))
            ->will(function ($args) {
                $request = $args[0];
                $next = $args[1];
                return $next->process($request);
            });

        $childPipeline = new MiddlewarePipe();
        $childPipeline->pipe('/', $childMiddleware->reveal());

        $outerMiddleware = $this->prophesize(ServerMiddlewareInterface::class);
        $outerMiddleware
            ->process(Argument::type(ServerRequestInterface::class), Argument::type(DelegateInterface::class))
            ->will(function ($args) {
                $request = $args[0];
                $next = $args[1];
                return $next->process($request);
            });

        $expected = $this->prophesize(ResponseInterface::class)->reveal();
        $innerMiddleware = $this->prophesize(ServerMiddlewareInterface::class);
        $innerMiddleware
            ->process(Argument::type(ServerRequestInterface::class), Argument::type(DelegateInterface::class))
            ->willReturn($expected);

        $pipeline = $this->middleware;
        $pipeline->setResponsePrototype($this->response);
        $pipeline->pipe($outerMiddleware->reveal());
        $pipeline->pipe('/test', $childPipeline);
        $pipeline->pipe($innerMiddleware->reveal());

        $request = new Request([], [], 'http://local.example.com/test', 'GET', 'php://memory');
        $final = $this->prophesize(DelegateInterface::class);
        $final->process(Argument::any())->shouldNotBeCalled();

        $result = $pipeline->process($request, $final->reveal());
        $this->assertSame($expected, $result);
    }

    public function testMiddlewareRequestPathMustBeTrimmedOffWithPipeRoutePath()
    {
        $request  = new Request([], [], 'http://local.example.com/foo/bar', 'GET', 'php://memory');
        $executed = false;

        $this->middleware->pipe('/foo', function ($req, $res, $next) use (&$executed) {
            $this->assertEquals('/bar', $req->getUri()->getPath());
            $executed = true;
            return $res;
        });

        $this->middleware->__invoke($request, $this->response, $this->createFinalHandler());
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

        $response = $middleware($request, $this->response, $this->createFinalHandler());
        $this->assertTrue($response->hasHeader('x-found'));
    }

    public function nestedPaths()
    {
        return [
            'empty-bare-bare'            => ['',       'foo',    '/foo',          'assertTrue'],
            'empty-bare-bareplus'        => ['',       'foo',    '/foobar',       'assertFalse'],
            'empty-bare-tail'            => ['',       'foo',    '/foo/',         'assertTrue'],
            'empty-bare-tailplus'        => ['',       'foo',    '/foo/bar',      'assertTrue'],
            'empty-tail-bare'            => ['',       'foo/',   '/foo',          'assertTrue'],
            'empty-tail-bareplus'        => ['',       'foo/',   '/foobar',       'assertFalse'],
            'empty-tail-tail'            => ['',       'foo/',   '/foo/',         'assertTrue'],
            'empty-tail-tailplus'        => ['',       'foo/',   '/foo/bar',      'assertTrue'],
            'empty-prefix-bare'          => ['',       '/foo',   '/foo',          'assertTrue'],
            'empty-prefix-bareplus'      => ['',       '/foo',   '/foobar',       'assertFalse'],
            'empty-prefix-tail'          => ['',       '/foo',   '/foo/',         'assertTrue'],
            'empty-prefix-tailplus'      => ['',       '/foo',   '/foo/bar',      'assertTrue'],
            'empty-surround-bare'        => ['',       '/foo/',  '/foo',          'assertTrue'],
            'empty-surround-bareplus'    => ['',       '/foo/',  '/foobar',       'assertFalse'],
            'empty-surround-tail'        => ['',       '/foo/',  '/foo/',         'assertTrue'],
            'empty-surround-tailplus'    => ['',       '/foo/',  '/foo/bar',      'assertTrue'],
            'root-bare-bare'             => ['/',      'foo',    '/foo',          'assertTrue'],
            'root-bare-bareplus'         => ['/',      'foo',    '/foobar',       'assertFalse'],
            'root-bare-tail'             => ['/',      'foo',    '/foo/',         'assertTrue'],
            'root-bare-tailplus'         => ['/',      'foo',    '/foo/bar',      'assertTrue'],
            'root-tail-bare'             => ['/',      'foo/',   '/foo',          'assertTrue'],
            'root-tail-bareplus'         => ['/',      'foo/',   '/foobar',       'assertFalse'],
            'root-tail-tail'             => ['/',      'foo/',   '/foo/',         'assertTrue'],
            'root-tail-tailplus'         => ['/',      'foo/',   '/foo/bar',      'assertTrue'],
            'root-prefix-bare'           => ['/',      '/foo',   '/foo',          'assertTrue'],
            'root-prefix-bareplus'       => ['/',      '/foo',   '/foobar',       'assertFalse'],
            'root-prefix-tail'           => ['/',      '/foo',   '/foo/',         'assertTrue'],
            'root-prefix-tailplus'       => ['/',      '/foo',   '/foo/bar',      'assertTrue'],
            'root-surround-bare'         => ['/',      '/foo/',  '/foo',          'assertTrue'],
            'root-surround-bareplus'     => ['/',      '/foo/',  '/foobar',       'assertFalse'],
            'root-surround-tail'         => ['/',      '/foo/',  '/foo/',         'assertTrue'],
            'root-surround-tailplus'     => ['/',      '/foo/',  '/foo/bar',      'assertTrue'],
            'bare-bare-bare'             => ['foo',    'bar',    '/foo/bar',      'assertTrue'],
            'bare-bare-bareplus'         => ['foo',    'bar',    '/foo/barbaz',   'assertFalse'],
            'bare-bare-tail'             => ['foo',    'bar',    '/foo/bar/',     'assertTrue'],
            'bare-bare-tailplus'         => ['foo',    'bar',    '/foo/bar/baz',  'assertTrue'],
            'bare-tail-bare'             => ['foo',    'bar/',   '/foo/bar',      'assertTrue'],
            'bare-tail-bareplus'         => ['foo',    'bar/',   '/foo/barbaz',   'assertFalse'],
            'bare-tail-tail'             => ['foo',    'bar/',   '/foo/bar/',     'assertTrue'],
            'bare-tail-tailplus'         => ['foo',    'bar/',   '/foo/bar/baz',  'assertTrue'],
            'bare-prefix-bare'           => ['foo',    '/bar',   '/foo/bar',      'assertTrue'],
            'bare-prefix-bareplus'       => ['foo',    '/bar',   '/foo/barbaz',   'assertFalse'],
            'bare-prefix-tail'           => ['foo',    '/bar',   '/foo/bar/',     'assertTrue'],
            'bare-prefix-tailplus'       => ['foo',    '/bar',   '/foo/bar/baz',  'assertTrue'],
            'bare-surround-bare'         => ['foo',    '/bar/',  '/foo/bar',      'assertTrue'],
            'bare-surround-bareplus'     => ['foo',    '/bar/',  '/foo/barbaz',   'assertFalse'],
            'bare-surround-tail'         => ['foo',    '/bar/',  '/foo/bar/',     'assertTrue'],
            'bare-surround-tailplus'     => ['foo',    '/bar/',  '/foo/bar/baz',  'assertTrue'],
            'tail-bare-bare'             => ['foo/',   'bar',    '/foo/bar',      'assertTrue'],
            'tail-bare-bareplus'         => ['foo/',   'bar',    '/foo/barbaz',   'assertFalse'],
            'tail-bare-tail'             => ['foo/',   'bar',    '/foo/bar/',     'assertTrue'],
            'tail-bare-tailplus'         => ['foo/',   'bar',    '/foo/bar/baz',  'assertTrue'],
            'tail-tail-bare'             => ['foo/',   'bar/',   '/foo/bar',      'assertTrue'],
            'tail-tail-bareplus'         => ['foo/',   'bar/',   '/foo/barbaz',   'assertFalse'],
            'tail-tail-tail'             => ['foo/',   'bar/',   '/foo/bar/',     'assertTrue'],
            'tail-tail-tailplus'         => ['foo/',   'bar/',   '/foo/bar/baz',  'assertTrue'],
            'tail-prefix-bare'           => ['foo/',   '/bar',   '/foo/bar',      'assertTrue'],
            'tail-prefix-bareplus'       => ['foo/',   '/bar',   '/foo/barbaz',   'assertFalse'],
            'tail-prefix-tail'           => ['foo/',   '/bar',   '/foo/bar/',     'assertTrue'],
            'tail-prefix-tailplus'       => ['foo/',   '/bar',   '/foo/bar/baz',  'assertTrue'],
            'tail-surround-bare'         => ['foo/',   '/bar/',  '/foo/bar',      'assertTrue'],
            'tail-surround-bareplus'     => ['foo/',   '/bar/',  '/foo/barbaz',   'assertFalse'],
            'tail-surround-tail'         => ['foo/',   '/bar/',  '/foo/bar/',     'assertTrue'],
            'tail-surround-tailplus'     => ['foo/',   '/bar/',  '/foo/bar/baz',  'assertTrue'],
            'prefix-bare-bare'           => ['/foo',   'bar',    '/foo/bar',      'assertTrue'],
            'prefix-bare-bareplus'       => ['/foo',   'bar',    '/foo/barbaz',   'assertFalse'],
            'prefix-bare-tail'           => ['/foo',   'bar',    '/foo/bar/',     'assertTrue'],
            'prefix-bare-tailplus'       => ['/foo',   'bar',    '/foo/bar/baz',  'assertTrue'],
            'prefix-tail-bare'           => ['/foo',   'bar/',   '/foo/bar',      'assertTrue'],
            'prefix-tail-bareplus'       => ['/foo',   'bar/',   '/foo/barbaz',   'assertFalse'],
            'prefix-tail-tail'           => ['/foo',   'bar/',   '/foo/bar/',     'assertTrue'],
            'prefix-tail-tailplus'       => ['/foo',   'bar/',   '/foo/bar/baz',  'assertTrue'],
            'prefix-prefix-bare'         => ['/foo',   '/bar',   '/foo/bar',      'assertTrue'],
            'prefix-prefix-bareplus'     => ['/foo',   '/bar',   '/foo/barbaz',   'assertFalse'],
            'prefix-prefix-tail'         => ['/foo',   '/bar',   '/foo/bar/',     'assertTrue'],
            'prefix-prefix-tailplus'     => ['/foo',   '/bar',   '/foo/bar/baz',  'assertTrue'],
            'prefix-surround-bare'       => ['/foo',   '/bar/',  '/foo/bar',      'assertTrue'],
            'prefix-surround-bareplus'   => ['/foo',   '/bar/',  '/foo/barbaz',   'assertFalse'],
            'prefix-surround-tail'       => ['/foo',   '/bar/',  '/foo/bar/',     'assertTrue'],
            'prefix-surround-tailplus'   => ['/foo',   '/bar/',  '/foo/bar/baz',  'assertTrue'],
            'surround-bare-bare'         => ['/foo/',  'bar',    '/foo/bar',      'assertTrue'],
            'surround-bare-bareplus'     => ['/foo/',  'bar',    '/foo/barbaz',   'assertFalse'],
            'surround-bare-tail'         => ['/foo/',  'bar',    '/foo/bar/',     'assertTrue'],
            'surround-bare-tailplus'     => ['/foo/',  'bar',    '/foo/bar/baz',  'assertTrue'],
            'surround-tail-bare'         => ['/foo/',  'bar/',   '/foo/bar',      'assertTrue'],
            'surround-tail-bareplus'     => ['/foo/',  'bar/',   '/foo/barbaz',   'assertFalse'],
            'surround-tail-tail'         => ['/foo/',  'bar/',   '/foo/bar/',     'assertTrue'],
            'surround-tail-tailplus'     => ['/foo/',  'bar/',   '/foo/bar/baz',  'assertTrue'],
            'surround-prefix-bare'       => ['/foo/',  '/bar',   '/foo/bar',      'assertTrue'],
            'surround-prefix-bareplus'   => ['/foo/',  '/bar',   '/foo/barbaz',   'assertFalse'],
            'surround-prefix-tail'       => ['/foo/',  '/bar',   '/foo/bar/',     'assertTrue'],
            'surround-prefix-tailplus'   => ['/foo/',  '/bar',   '/foo/bar/baz',  'assertTrue'],
            'surround-surround-bare'     => ['/foo/',  '/bar/',  '/foo/bar',      'assertTrue'],
            'surround-surround-bareplus' => ['/foo/',  '/bar/',  '/foo/barbaz',   'assertFalse'],
            'surround-surround-tail'     => ['/foo/',  '/bar/',  '/foo/bar/',     'assertTrue'],
            'surround-surround-tailplus' => ['/foo/',  '/bar/',  '/foo/bar/baz',  'assertTrue'],
        ];
    }

    /**
     * @group matching
     * @group nesting
     * @dataProvider nestedPaths
     */
    public function testNestedMiddlewareMatchesOnlyAtPathBoundaries($topPath, $nestedPath, $fullPath, $assertion)
    {
        $middleware = $this->middleware;

        $nest = new MiddlewarePipe();
        $nest->setResponsePrototype($this->response);
        $nest->pipe($nestedPath, function ($req, $res) use ($nestedPath) {
            return $res->withHeader('X-Found', 'true');
        });
        $middleware->pipe($topPath, function ($req, $res, $next = null) use ($topPath, $nest) {
            $result = $nest($req, $res, $next);
            return $result;
        });

        $uri      = (new Uri())->withPath($fullPath);
        $request  = (new Request)->withUri($uri);
        $response = $middleware($request, $this->response, $this->createFinalHandler());
        $this->$assertion(
            $response->hasHeader('X-Found'),
            sprintf(
                "%s failed with full path %s against top pipe '%s' and nested pipe '%s'\n",
                $assertion,
                $fullPath,
                $topPath,
                $nestedPath
            )
        );
    }

    /**
     * @group http-interop
     */
    public function testNoResponsePrototypeComposeByDefault()
    {
        $pipeline = new MiddlewarePipe();
        $this->assertAttributeEmpty('responsePrototype', $pipeline);
    }

    /**
     * @group http-interop
     */
    public function testCanComposeResponsePrototype()
    {
        $response = $this->prophesize(Response::class)->reveal();
        $pipeline = new MiddlewarePipe();
        $pipeline->setResponsePrototype($response);
        $this->assertAttributeSame($response, 'responsePrototype', $pipeline);
    }

    public function interopMiddleware()
    {
        return [
            MiddlewareInterface::class => [MiddlewareInterface::class],
            ServerMiddlewareInterface::class => [ServerMiddlewareInterface::class],
        ];
    }

    /**
     * @group http-interop
     * @dataProvider interopMiddleware
     */
    public function testCanPipeInteropMiddleware($middlewareType)
    {
        $delegate = $this->prophesize(DelegateInterface::class)->reveal();

        $response = $this->prophesize(ResponseInterface::class);
        $middleware = $this->prophesize($middlewareType);
        $middleware
            ->process(Argument::type(RequestInterface::class), Argument::type(DelegateInterface::class))
            ->will([$response, 'reveal']);

        $pipeline = new MiddlewarePipe();
        $pipeline->pipe($middleware->reveal());

        $done = function () {
        };

        $this->assertSame($response->reveal(), $pipeline->process($this->request, $delegate));
    }

    /**
     * @group http-interop
     */
    public function testWillDecorateCallableMiddlewareAsInteropMiddlewareIfResponsePrototypePresent()
    {
        $pipeline = new MiddlewarePipe();
        $pipeline->setResponsePrototype($this->response);

        $middleware = function () {
        };
        $pipeline->pipe($middleware);

        $r = new ReflectionProperty($pipeline, 'pipeline');
        $r->setAccessible(true);
        $queue = $r->getValue($pipeline);

        $route = $queue->dequeue();
        $test = $route->handler;
        $this->assertInstanceOf(CallableMiddlewareWrapper::class, $test);
        $this->assertAttributeSame($middleware, 'middleware', $test);
        $this->assertAttributeSame($this->response, 'responsePrototype', $test);
    }

    public function testWillDecorateACallableDefiningADelegateArgumentUsingAlternateDecorator()
    {
        $pipeline = new MiddlewarePipe();
        $pipeline->setResponsePrototype($this->response);

        $middleware = function ($request, DelegateInterface $delegate) {
        };
        $pipeline->pipe($middleware);

        $r = new ReflectionProperty($pipeline, 'pipeline');
        $r->setAccessible(true);
        $queue = $r->getValue($pipeline);

        $route = $queue->dequeue();
        $test = $route->handler;
        $this->assertInstanceOf(CallableInteropMiddlewareWrapper::class, $test);
        $this->assertAttributeSame($middleware, 'middleware', $test);
    }

    /**
     * Used to test that array callables are decorated correctly.
     *
     * @param RequestInterface $request
     * @param ResponseInterface $response
     * @param callable $next
     * @return ResponseInterface
     */
    public function sampleMiddleware($request, $response, $next)
    {
        return $response;
    }

    public function testWillDecorateCallableArrayMiddlewareWithoutErrors()
    {
        $pipeline = new MiddlewarePipe();
        $pipeline->setResponsePrototype($this->response);

        $middleware = [$this, 'sampleMiddleware'];
        $pipeline->pipe($middleware);

        $r = new ReflectionProperty($pipeline, 'pipeline');
        $r->setAccessible(true);
        $queue = $r->getValue($pipeline);

        $route = $queue->dequeue();
        $test = $route->handler;
        $this->assertInstanceOf(CallableMiddlewareWrapper::class, $test);
        $this->assertAttributeSame($middleware, 'middleware', $test);
    }
}
