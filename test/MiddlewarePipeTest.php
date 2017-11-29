<?php
/**
 * @see       https://github.com/zendframework/zend-stratigility for the canonical source repository
 * @copyright Copyright (c) 2015-2017 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-stratigility/blob/master/LICENSE.md New BSD License
 */

namespace ZendTest\Stratigility;

use Interop\Http\Server\MiddlewareInterface;
use Interop\Http\Server\RequestHandlerInterface;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Zend\Diactoros\Response;
use Zend\Diactoros\ServerRequest as Request;
use Zend\Diactoros\Uri;
use Zend\Stratigility\MiddlewarePipe;

class MiddlewarePipeTest extends TestCase
{
    use MiddlewareTrait;

    /**
     * @var Request
     */
    private $request;

    /**
     * @var MiddlewarePipe
     */
    private $pipeline;

    protected function setUp()
    {
        $this->request  = new Request([], [], 'http://example.com/', 'GET', 'php://memory');
        $this->pipeline = new MiddlewarePipe();
    }

    private function createFinalHandler() : RequestHandlerInterface
    {
        $handler = $this->prophesize(RequestHandlerInterface::class);
        $handler->handle(Argument::any())->willReturn(new Response\EmptyResponse());

        return $handler->reveal();
    }

    public function testHandleInvokesUntilFirstHandlerThatDoesNotCallNext()
    {
        $this->pipeline->pipe(new class () implements MiddlewareInterface
        {
            public function process(ServerRequestInterface $req, RequestHandlerInterface $handler) : ResponseInterface
            {
                $res = $handler->handle($req);
                $res->getBody()->write("First\n");

                return $res;
            }
        });
        $this->pipeline->pipe(new class () implements MiddlewareInterface
        {
            public function process(ServerRequestInterface $req, RequestHandlerInterface $handler) : ResponseInterface
            {
                $res = $handler->handle($req);
                $res->getBody()->write("Second\n");

                return $res;
            }
        });

        $response = new Response();
        $response->getBody()->write("Third\n");
        $this->pipeline->pipe($this->getMiddlewareWhichReturnsResponse($response));

        $this->pipeline->pipe($this->getNotCalledMiddleware());

        $request = new Request([], [], 'http://local.example.com/foo', 'GET', 'php://memory');
        $response = $this->pipeline->process($request, $this->createFinalHandler());
        $body = (string) $response->getBody();
        $this->assertContains('First', $body);
        $this->assertContains('Second', $body);
        $this->assertContains('Third', $body);
    }

    public function testInvokesHandlerWhenQueueIsExhausted()
    {
        $expected = $this->prophesize(ResponseInterface::class)->reveal();

        $this->pipeline->pipe($this->getPassToHandlerMiddleware());
        $this->pipeline->pipe($this->getPassToHandlerMiddleware());
        $this->pipeline->pipe($this->getPassToHandlerMiddleware());

        $request = new Request([], [], 'http://local.example.com/foo', 'GET', 'php://memory');

        $handler = $this->prophesize(RequestHandlerInterface::class);
        $handler->handle($request)->willReturn($expected);

        $result = $this->pipeline->process($request, $handler->reveal());

        $this->assertSame($expected, $result);
    }

    public function testReturnsResponseReturnedByQueue()
    {
        $return = new Response();

        $this->pipeline->pipe($this->getPassToHandlerMiddleware());
        $this->pipeline->pipe($this->getPassToHandlerMiddleware());
        $this->pipeline->pipe($this->getMiddlewareWhichReturnsResponse($return));

        $this->pipeline->pipe($this->getNotCalledMiddleware());

        $request = new Request([], [], 'http://local.example.com/foo', 'GET', 'php://memory');
        $result  = $this->pipeline->process($request, $this->createFinalHandler());
        $this->assertSame($return, $result, var_export([
            spl_object_hash($return) => get_class($return),
            spl_object_hash($result) => get_class($result),
        ], 1));
    }

    public function testSlashShouldNotBeAppendedInChildMiddlewareWhenLayerDoesNotIncludeIt()
    {
        $this->pipeline->pipe('/admin', $this->getPassToHandlerMiddleware());

        $this->pipeline->pipe(new class () implements MiddlewareInterface
        {
            public function process(ServerRequestInterface $req, RequestHandlerInterface $handler) : ResponseInterface
            {
                $res = new Response();
                $res->getBody()->write($req->getUri()->getPath());

                return $res;
            }
        });

        $request = new Request([], [], 'http://local.example.com/admin', 'GET', 'php://memory');
        $result  = $this->pipeline->process($request, $this->createFinalHandler());
        $body    = (string) $result->getBody();
        $this->assertSame('/admin', $body);
    }

    public function testSlashShouldBeAppendedInChildMiddlewareWhenRequestUriIncludesIt()
    {
        $this->pipeline->pipe('/admin', $this->getPassToHandlerMiddleware());

        $this->pipeline->pipe(new class () implements MiddlewareInterface
        {
            public function process(ServerRequestInterface $req, RequestHandlerInterface $handler) : ResponseInterface
            {
                $res = new Response();
                $res->getBody()->write($req->getUri()->getPath());

                return $res;
            }
        });

        $request = new Request([], [], 'http://local.example.com/admin/', 'GET', 'php://memory');
        $result  = $this->pipeline->process($request, $this->createFinalHandler());
        $body    = (string) $result->getBody();
        $this->assertSame('/admin/', $body);
    }

    public function testNestedMiddlewareMayInvokeDoneToInvokeNextOfParent()
    {
        $childMiddleware = $this->prophesize(MiddlewareInterface::class);
        $childMiddleware
            ->process(
                Argument::type(ServerRequestInterface::class),
                Argument::type(RequestHandlerInterface::class)
            )
            ->will(function ($args) {
                $request = $args[0];
                $next = $args[1];
                return $next->handle($request);
            });

        $childPipeline = new MiddlewarePipe();
        $childPipeline->pipe('/', $childMiddleware->reveal());

        $outerMiddleware = $this->prophesize(MiddlewareInterface::class);
        $outerMiddleware
            ->process(
                Argument::type(ServerRequestInterface::class),
                Argument::type(RequestHandlerInterface::class)
            )
            ->will(function ($args) {
                $request = $args[0];
                $next = $args[1];
                return $next->handle($request);
            });

        $expected = $this->prophesize(ResponseInterface::class)->reveal();
        $innerMiddleware = $this->prophesize(MiddlewareInterface::class);
        $innerMiddleware
            ->process(
                Argument::type(ServerRequestInterface::class),
                Argument::type(RequestHandlerInterface::class)
            )
            ->willReturn($expected);

        $pipeline = $this->pipeline;
        //$pipeline->setResponsePrototype($this->response);
        $pipeline->pipe($outerMiddleware->reveal());
        $pipeline->pipe('/test', $childPipeline);
        $pipeline->pipe($innerMiddleware->reveal());

        $request = new Request([], [], 'http://local.example.com/test', 'GET', 'php://memory');
        $final = $this->prophesize(RequestHandlerInterface::class);
        $final->handle(Argument::any())->shouldNotBeCalled();

        $result = $pipeline->process($request, $final->reveal());
        $this->assertSame($expected, $result);
    }

    public function testMiddlewareRequestPathMustBeTrimmedOffWithPipeRoutePath()
    {
        $request  = new Request([], [], 'http://local.example.com/foo/bar', 'GET', 'php://memory');

        $middleware = $this->prophesize(MiddlewareInterface::class);
        $middleware
            ->process(
                Argument::that(function (ServerRequestInterface $req) {
                    Assert::assertSame('/bar', $req->getUri()->getPath());

                    return true;
                }),
                Argument::any()
            )
            ->willReturn(new Response())
            ->shouldBeCalledTimes(1);

        $this->pipeline->pipe('/foo', $middleware->reveal());
        $this->pipeline->process($request, $this->createFinalHandler());
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
     *
     * @param string $path
     */
    public function testMiddlewareTreatsBothSlashAndEmptyPathAsTheRootPath($path)
    {
        $middleware = $this->pipeline;
        $middleware->pipe($path, new class () implements MiddlewareInterface
        {
            public function process(ServerRequestInterface $req, RequestHandlerInterface $handler) : ResponseInterface
            {
                $res = new Response();
                return $res->withHeader('X-Found', 'true');
            }
        });
        $uri     = (new Uri())->withPath($path);
        $request = (new Request)->withUri($uri);

        $response = $middleware->process($request, $this->createFinalHandler());
        $this->assertTrue($response->hasHeader('x-found'));
    }

    public function nestedPaths()
    {
        return [
            'empty-bare-bare'            => ['',       'foo',    '/foo',          true],
            'empty-bare-bareplus'        => ['',       'foo',    '/foobar',       false],
            'empty-bare-tail'            => ['',       'foo',    '/foo/',         true],
            'empty-bare-tailplus'        => ['',       'foo',    '/foo/bar',      true],
            'empty-tail-bare'            => ['',       'foo/',   '/foo',          true],
            'empty-tail-bareplus'        => ['',       'foo/',   '/foobar',       false],
            'empty-tail-tail'            => ['',       'foo/',   '/foo/',         true],
            'empty-tail-tailplus'        => ['',       'foo/',   '/foo/bar',      true],
            'empty-prefix-bare'          => ['',       '/foo',   '/foo',          true],
            'empty-prefix-bareplus'      => ['',       '/foo',   '/foobar',       false],
            'empty-prefix-tail'          => ['',       '/foo',   '/foo/',         true],
            'empty-prefix-tailplus'      => ['',       '/foo',   '/foo/bar',      true],
            'empty-surround-bare'        => ['',       '/foo/',  '/foo',          true],
            'empty-surround-bareplus'    => ['',       '/foo/',  '/foobar',       false],
            'empty-surround-tail'        => ['',       '/foo/',  '/foo/',         true],
            'empty-surround-tailplus'    => ['',       '/foo/',  '/foo/bar',      true],
            'root-bare-bare'             => ['/',      'foo',    '/foo',          true],
            'root-bare-bareplus'         => ['/',      'foo',    '/foobar',       false],
            'root-bare-tail'             => ['/',      'foo',    '/foo/',         true],
            'root-bare-tailplus'         => ['/',      'foo',    '/foo/bar',      true],
            'root-tail-bare'             => ['/',      'foo/',   '/foo',          true],
            'root-tail-bareplus'         => ['/',      'foo/',   '/foobar',       false],
            'root-tail-tail'             => ['/',      'foo/',   '/foo/',         true],
            'root-tail-tailplus'         => ['/',      'foo/',   '/foo/bar',      true],
            'root-prefix-bare'           => ['/',      '/foo',   '/foo',          true],
            'root-prefix-bareplus'       => ['/',      '/foo',   '/foobar',       false],
            'root-prefix-tail'           => ['/',      '/foo',   '/foo/',         true],
            'root-prefix-tailplus'       => ['/',      '/foo',   '/foo/bar',      true],
            'root-surround-bare'         => ['/',      '/foo/',  '/foo',          true],
            'root-surround-bareplus'     => ['/',      '/foo/',  '/foobar',       false],
            'root-surround-tail'         => ['/',      '/foo/',  '/foo/',         true],
            'root-surround-tailplus'     => ['/',      '/foo/',  '/foo/bar',      true],
            'bare-bare-bare'             => ['foo',    'bar',    '/foo/bar',      true],
            'bare-bare-bareplus'         => ['foo',    'bar',    '/foo/barbaz',   false],
            'bare-bare-tail'             => ['foo',    'bar',    '/foo/bar/',     true],
            'bare-bare-tailplus'         => ['foo',    'bar',    '/foo/bar/baz',  true],
            'bare-tail-bare'             => ['foo',    'bar/',   '/foo/bar',      true],
            'bare-tail-bareplus'         => ['foo',    'bar/',   '/foo/barbaz',   false],
            'bare-tail-tail'             => ['foo',    'bar/',   '/foo/bar/',     true],
            'bare-tail-tailplus'         => ['foo',    'bar/',   '/foo/bar/baz',  true],
            'bare-prefix-bare'           => ['foo',    '/bar',   '/foo/bar',      true],
            'bare-prefix-bareplus'       => ['foo',    '/bar',   '/foo/barbaz',   false],
            'bare-prefix-tail'           => ['foo',    '/bar',   '/foo/bar/',     true],
            'bare-prefix-tailplus'       => ['foo',    '/bar',   '/foo/bar/baz',  true],
            'bare-surround-bare'         => ['foo',    '/bar/',  '/foo/bar',      true],
            'bare-surround-bareplus'     => ['foo',    '/bar/',  '/foo/barbaz',   false],
            'bare-surround-tail'         => ['foo',    '/bar/',  '/foo/bar/',     true],
            'bare-surround-tailplus'     => ['foo',    '/bar/',  '/foo/bar/baz',  true],
            'tail-bare-bare'             => ['foo/',   'bar',    '/foo/bar',      true],
            'tail-bare-bareplus'         => ['foo/',   'bar',    '/foo/barbaz',   false],
            'tail-bare-tail'             => ['foo/',   'bar',    '/foo/bar/',     true],
            'tail-bare-tailplus'         => ['foo/',   'bar',    '/foo/bar/baz',  true],
            'tail-tail-bare'             => ['foo/',   'bar/',   '/foo/bar',      true],
            'tail-tail-bareplus'         => ['foo/',   'bar/',   '/foo/barbaz',   false],
            'tail-tail-tail'             => ['foo/',   'bar/',   '/foo/bar/',     true],
            'tail-tail-tailplus'         => ['foo/',   'bar/',   '/foo/bar/baz',  true],
            'tail-prefix-bare'           => ['foo/',   '/bar',   '/foo/bar',      true],
            'tail-prefix-bareplus'       => ['foo/',   '/bar',   '/foo/barbaz',   false],
            'tail-prefix-tail'           => ['foo/',   '/bar',   '/foo/bar/',     true],
            'tail-prefix-tailplus'       => ['foo/',   '/bar',   '/foo/bar/baz',  true],
            'tail-surround-bare'         => ['foo/',   '/bar/',  '/foo/bar',      true],
            'tail-surround-bareplus'     => ['foo/',   '/bar/',  '/foo/barbaz',   false],
            'tail-surround-tail'         => ['foo/',   '/bar/',  '/foo/bar/',     true],
            'tail-surround-tailplus'     => ['foo/',   '/bar/',  '/foo/bar/baz',  true],
            'prefix-bare-bare'           => ['/foo',   'bar',    '/foo/bar',      true],
            'prefix-bare-bareplus'       => ['/foo',   'bar',    '/foo/barbaz',   false],
            'prefix-bare-tail'           => ['/foo',   'bar',    '/foo/bar/',     true],
            'prefix-bare-tailplus'       => ['/foo',   'bar',    '/foo/bar/baz',  true],
            'prefix-tail-bare'           => ['/foo',   'bar/',   '/foo/bar',      true],
            'prefix-tail-bareplus'       => ['/foo',   'bar/',   '/foo/barbaz',   false],
            'prefix-tail-tail'           => ['/foo',   'bar/',   '/foo/bar/',     true],
            'prefix-tail-tailplus'       => ['/foo',   'bar/',   '/foo/bar/baz',  true],
            'prefix-prefix-bare'         => ['/foo',   '/bar',   '/foo/bar',      true],
            'prefix-prefix-bareplus'     => ['/foo',   '/bar',   '/foo/barbaz',   false],
            'prefix-prefix-tail'         => ['/foo',   '/bar',   '/foo/bar/',     true],
            'prefix-prefix-tailplus'     => ['/foo',   '/bar',   '/foo/bar/baz',  true],
            'prefix-surround-bare'       => ['/foo',   '/bar/',  '/foo/bar',      true],
            'prefix-surround-bareplus'   => ['/foo',   '/bar/',  '/foo/barbaz',   false],
            'prefix-surround-tail'       => ['/foo',   '/bar/',  '/foo/bar/',     true],
            'prefix-surround-tailplus'   => ['/foo',   '/bar/',  '/foo/bar/baz',  true],
            'surround-bare-bare'         => ['/foo/',  'bar',    '/foo/bar',      true],
            'surround-bare-bareplus'     => ['/foo/',  'bar',    '/foo/barbaz',   false],
            'surround-bare-tail'         => ['/foo/',  'bar',    '/foo/bar/',     true],
            'surround-bare-tailplus'     => ['/foo/',  'bar',    '/foo/bar/baz',  true],
            'surround-tail-bare'         => ['/foo/',  'bar/',   '/foo/bar',      true],
            'surround-tail-bareplus'     => ['/foo/',  'bar/',   '/foo/barbaz',   false],
            'surround-tail-tail'         => ['/foo/',  'bar/',   '/foo/bar/',     true],
            'surround-tail-tailplus'     => ['/foo/',  'bar/',   '/foo/bar/baz',  true],
            'surround-prefix-bare'       => ['/foo/',  '/bar',   '/foo/bar',      true],
            'surround-prefix-bareplus'   => ['/foo/',  '/bar',   '/foo/barbaz',   false],
            'surround-prefix-tail'       => ['/foo/',  '/bar',   '/foo/bar/',     true],
            'surround-prefix-tailplus'   => ['/foo/',  '/bar',   '/foo/bar/baz',  true],
            'surround-surround-bare'     => ['/foo/',  '/bar/',  '/foo/bar',      true],
            'surround-surround-bareplus' => ['/foo/',  '/bar/',  '/foo/barbaz',   false],
            'surround-surround-tail'     => ['/foo/',  '/bar/',  '/foo/bar/',     true],
            'surround-surround-tailplus' => ['/foo/',  '/bar/',  '/foo/bar/baz',  true],
        ];
    }

    /**
     * @group matching
     * @group nesting
     * @dataProvider nestedPaths
     *
     * @param string $topPath
     * @param string $nestedPath
     * @param string $fullPath
     * @param bool $expected
     */
    public function testNestedMiddlewareMatchesOnlyAtPathBoundaries(
        string $topPath,
        string $nestedPath,
        string $fullPath,
        bool $expected
    ) {
        $middleware = $this->pipeline;

        $nest = new MiddlewarePipe();
        $nest->pipe($nestedPath, new class () implements MiddlewareInterface
        {
            public function process(
                ServerRequestInterface $req,
                RequestHandlerInterface $handler
            ) : ResponseInterface {
                $res = new Response();

                return $res->withHeader('X-Found', 'true');
            }
        });
        $middleware->pipe($topPath, new class ($nest) implements MiddlewareInterface
        {
            private $nest;

            public function __construct($nest)
            {
                $this->nest = $nest;
            }

            public function process(
                ServerRequestInterface $req,
                RequestHandlerInterface $handler
            ) : ResponseInterface {
                return $this->nest->process($req, $handler);
            }
        });

        $uri      = (new Uri())->withPath($fullPath);
        $request  = (new Request)->withUri($uri);
        $response = $middleware->process($request, $this->createFinalHandler());
        $this->assertSame(
            $expected,
            $response->hasHeader('X-Found'),
            sprintf(
                "Failed with full path %s against top pipe '%s' and nested pipe '%s'\n",
                $fullPath,
                $topPath,
                $nestedPath
            )
        );
    }

    /**
     * @group http-interop
     */
    public function testCanPipeInteropMiddleware()
    {
        $handler = $this->prophesize(RequestHandlerInterface::class)->reveal();

        $response = $this->prophesize(ResponseInterface::class)->reveal();
        $middleware = $this->prophesize(MiddlewareInterface::class);
        $middleware
            ->process(
                Argument::type(ServerRequestInterface::class),
                Argument::type(RequestHandlerInterface::class)
            )
            ->willReturn($response);

        $pipeline = new MiddlewarePipe();
        $pipeline->pipe($middleware->reveal());

        $this->assertSame($response, $pipeline->process($this->request, $handler));
    }
}
