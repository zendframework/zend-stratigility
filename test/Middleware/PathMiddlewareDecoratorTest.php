<?php
/**
 * @see       https://github.com/zendframework/zend-stratigility for the canonical source repository
 * @copyright Copyright (c) 2018 Zend Technologies USA Inc. (https://www.zend.com)
 * @license   https://github.com/zendframework/zend-stratigility/blob/master/LICENSE.md New BSD License
 */
declare(strict_types=1);

namespace ZendTest\Stratigility\Middleware;

use Interop\Http\Server\MiddlewareInterface;
use Interop\Http\Server\RequestHandlerInterface;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UriInterface;
use Zend\Diactoros\Response;
use Zend\Diactoros\ServerRequest;
use Zend\Diactoros\Uri;
use Zend\Stratigility\Middleware\PathMiddlewareDecorator;

use function Zend\Stratigility\path;

class PathMiddlewareDecoratorTest extends TestCase
{
    public function setUp()
    {
        $this->uri = $this->prophesize(UriInterface::class);
        $this->request = $this->prophesize(ServerRequestInterface::class);
        $this->response = $this->prophesize(ResponseInterface::class);
        $this->handler = $this->prophesize(RequestHandlerInterface::class);
        $this->toDecorate = $this->prophesize(MiddlewareInterface::class);
    }

    public function createFinalHandler()
    {
    }

    public function testImplementsMiddlewareInterface()
    {
        $middleware = new PathMiddlewareDecorator('/foo', $this->toDecorate->reveal());
        $this->assertInstanceOf(MiddlewareInterface::class, $middleware);
    }

    public function testComposesMiddlewarePassedToConstructor()
    {
        $toDecorate = $this->prophesize(MiddlewareInterface::class)->reveal();
        $middleware = new PathMiddlewareDecorator('/foo', $toDecorate);
        $this->assertAttributeSame($toDecorate, 'middleware', $middleware);
    }

    public function testComposesPathPrefixPassedToConstructor()
    {
        $middleware = new PathMiddlewareDecorator('/foo', $this->toDecorate->reveal());
        $this->assertAttributeSame('/foo', 'prefix', $middleware);
    }

    public function testEmptyPathPrefixBecomesSingleSlash()
    {
        $middleware = new PathMiddlewareDecorator('', $this->toDecorate->reveal());
        $this->assertAttributeSame('/', 'prefix', $middleware);
    }

    public function testTrailingSlashIsStrippedFromPathPrefix()
    {
        $middleware = new PathMiddlewareDecorator('/foo/', $this->toDecorate->reveal());
        $this->assertAttributeSame('/foo', 'prefix', $middleware);
    }

    public function testAddsLeadingSlashWhenMissingFromPathPrefix()
    {
        $middleware = new PathMiddlewareDecorator('foo', $this->toDecorate->reveal());
        $this->assertAttributeSame('/foo', 'prefix', $middleware);
    }

    public function testDelegatesOriginalRequestToHandlerIfRequestPathIsShorterThanDecoratorPrefix()
    {
        $this->uri
            ->getPath()
            ->willReturn('/f');
        $this->request
            ->getUri()
            ->will([$this->uri, 'reveal']);
        $this->handler
            ->handle(Argument::that([$this->request, 'reveal']))
            ->will([$this->response, 'reveal']);

        $this->toDecorate->process(Argument::any())->shouldNotBeCalled();

        $middleware = new PathMiddlewareDecorator('/foo', $this->toDecorate->reveal());

        $this->assertSame(
            $this->response->reveal(),
            $middleware->process($this->request->reveal(), $this->handler->reveal())
        );
    }

    public function testDelegatesOriginalRequestToHandlerIfRequestPathIsDoesNotMatchDecoratorPath()
    {
        $this->uri
            ->getPath()
            ->willReturn('/bar');
        $this->request
            ->getUri()
            ->will([$this->uri, 'reveal']);
        $this->handler
            ->handle(Argument::that([$this->request, 'reveal']))
            ->will([$this->response, 'reveal']);

        $this->toDecorate->process(Argument::any())->shouldNotBeCalled();

        $middleware = new PathMiddlewareDecorator('/foo', $this->toDecorate->reveal());
        $middleware->process($this->request->reveal(), $this->handler->reveal());
    }

    public function testDelegatesOrignalRequestToHandlerIfRequestDoesNotMatchPrefixAtABoundary()
    {
        // e.g., if route is "/foo", but path is "/foobar", no match
        $uri = (new Uri())->withPath('/foobar');
        $request = (new ServerRequest())->withUri($uri);
        $response = new Response();

        $middleware = $this->prophesize(MiddlewareInterface::class);
        $middleware->process(Argument::any(), Argument::any())->shouldNotBeCalled();

        $decorator = new PathMiddlewareDecorator('/foo', $middleware->reveal());

        $handler = $this->prophesize(RequestHandlerInterface::class);
        $handler->handle($request)->willReturn($response);

        $this->assertSame(
            $response,
            $decorator->process($request, $handler->reveal())
        );
    }

    public function nestedPathCombinations()
    {
        return [
            // name                      => [$prefix, $nestPrefix, $uriPath,      $expectsHeader ]
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
     * @dataProvider nestedPathCombinations
     */
    public function testNestedMiddlewareOnlyMatchesAtPathBoundaries(
        string $prefix,
        string $nestPrefix,
        string $uriPath,
        bool $expectsHeader
    ) {
        $finalHandler = $this->prophesize(RequestHandlerInterface::class);
        $finalHandler->handle(Argument::any())->willReturn(new Response());

        $nested = new PathMiddlewareDecorator($nestPrefix, new class () implements MiddlewareInterface {
            public function process(
                ServerRequestInterface $request,
                RequestHandlerInterface $handler
            ) : ResponseInterface {
                return (new Response())->withHeader('X-Found', 'true');
            }
        });

        $topLevel = new PathMiddlewareDecorator($prefix, new class ($nested) implements MiddlewareInterface {
            private $middleware;

            public function __construct(MiddlewareInterface $middleware)
            {
                $this->middleware = $middleware;
            }

            public function process(
                ServerRequestInterface $request,
                RequestHandlerInterface $handler
            ) : ResponseInterface {
                return $this->middleware->process($request, $handler);
            }
        });

        $uri = (new Uri())->withPath($uriPath);
        $request = (new ServerRequest())->withUri($uri);

        $response = $topLevel->process($request, $finalHandler->reveal());

        $this->assertSame(
            $expectsHeader,
            $response->hasHeader('X-Found'),
            sprintf(
                'Failed with full path %s against top-level prefix "%s" and nested prefix "%s"; expected %s',
                $uriPath,
                $prefix,
                $nestPrefix,
                var_export($expectsHeader, true)
            )
        );
    }

    public function rootPathsProvider()
    {
        return [
            'empty' => [''],
            'root'  => ['/'],
        ];
    }

    /**
     * @group matching
     * @dataProvider rootPathsProvider
     *
     * @param string $path
     */
    public function testTreatsBothSlashAndEmptyPathAsTheRootPath($path)
    {
        $finalHandler = $this->prophesize(RequestHandlerInterface::class);
        $finalHandler->handle(Argument::any())->willReturn(new Response());

        $middleware = new PathMiddlewareDecorator($path, new class () implements MiddlewareInterface {
            public function process(ServerRequestInterface $req, RequestHandlerInterface $handler) : ResponseInterface
            {
                $res = new Response();
                return $res->withHeader('X-Found', 'true');
            }
        });
        $uri     = (new Uri())->withPath($path);
        $request = (new ServerRequest)->withUri($uri);

        $response = $middleware->process($request, $finalHandler->reveal());
        $this->assertTrue($response->hasHeader('x-found'));
    }

    public function testRequestPathPassedToDecoratedMiddlewareTrimsPathPrefix()
    {
        $finalHandler = $this->prophesize(RequestHandlerInterface::class);
        $finalHandler->handle(Argument::any())->willReturn(new Response());

        $request  = new ServerRequest([], [], 'http://local.example.com/foo/bar', 'GET', 'php://memory');

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

        $decorator = new PathMiddlewareDecorator('/foo', $middleware->reveal());
        $decorator->process($request, $finalHandler->reveal());
    }

    public function testInvocationOfHandlerByDecoratedMiddlewareWillInvokeWithOriginalRequestPath()
    {
        $request = new ServerRequest([], [], 'http://local.example.com/test', 'GET', 'php://memory');
        $expectedResponse = new Response();

        $finalHandler = $this->prophesize(RequestHandlerInterface::class);
        $finalHandler
            ->handle(Argument::that(function ($received) use ($request) {
                Assert::assertNotSame(
                    $request,
                    $received,
                    'Final handler received same request, and should not have'
                );

                Assert::assertSame(
                    $request->getUri()->getPath(),
                    $received->getUri()->getPath(),
                    'Final handler received request with different path'
                );

                return $received;
            }))
            ->willReturn($expectedResponse);

        $segregatedMiddleware = $this->prophesize(MiddlewareInterface::class);
        $segregatedMiddleware
            ->process(
                Argument::that(function ($received) use ($request) {
                    Assert::assertNotSame(
                        $request,
                        $received,
                        'Segregated middleware received same request as decorator, but should not have'
                    );
                    return $received;
                }),
                Argument::type(RequestHandlerInterface::class)
            )
            ->will(function ($args) {
                $request = $args[0];
                $next = $args[1];
                return $next->handle($request);
            });

        $decoratedMiddleware = new PathMiddlewareDecorator('/test', $segregatedMiddleware->reveal());

        $this->assertSame(
            $expectedResponse,
            $decoratedMiddleware->process($request, $finalHandler->reveal())
        );
    }

    public function testPathFunction()
    {
        $toDecorate = $this->toDecorate->reveal();
        $middleware = path('/foo', $toDecorate);
        self::assertInstanceOf(PathMiddlewareDecorator::class, $middleware);
        self::assertAttributeSame('/foo', 'prefix', $middleware);
        self::assertAttributeSame($toDecorate, 'middleware', $middleware);
    }
}
