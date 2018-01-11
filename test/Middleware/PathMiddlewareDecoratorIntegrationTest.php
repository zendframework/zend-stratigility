<?php
/**
 * @see       https://github.com/zendframework/zend-stratigility for the canonical source repository
 * @copyright Copyright (c) 2018 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-stratigility/blob/master/LICENSE.md New BSD License
 */

namespace ZendTest\Stratigility\Middleware;

use PHPUnit\Framework\Assert;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UriInterface;
use Webimpress\HttpMiddlewareCompatibility\HandlerInterface as RequestHandlerInterface;
use Webimpress\HttpMiddlewareCompatibility\MiddlewareInterface as MiddlewareInterface;
use Zend\Diactoros\Response;
use Zend\Diactoros\ServerRequest;
use Zend\Diactoros\Uri;
use Zend\Stratigility\MiddlewarePipe;
use Zend\Stratigility\Middleware\PathMiddlewareDecorator;

use const Webimpress\HttpMiddlewareCompatibility\HANDLER_METHOD;

class PathMiddlewareDecoratorIntegrationTest extends TestCase
{
    public function testPipelineComposingPathDecoratedMiddlewareExecutesAsExpected()
    {
        $uri = (new Uri)->withPath('/foo/bar/baz');
        $request = (new ServerRequest())->withUri($uri);
        $response = new Response();

        $pipeline = new MiddlewarePipe();

        $first = $this->createPassThroughMiddleware(function ($receivedRequest) use ($request) {
            Assert::assertSame(
                $receivedRequest,
                $request,
                'First middleware did not receive original request, but should have'
            );
            return $request;
        });
        $second = new PathMiddlewareDecorator('/foo', $this->createNestedPipeline($request));
        $last = $this->createPassThroughMiddleware(function ($receivedRequest) use ($request) {
            Assert::assertNotSame(
                $receivedRequest,
                $request,
                'Last middleware received original request, but should not have'
            );

            $originalUri = $request->getUri();
            $receivedUri = $receivedRequest->getUri();

            Assert::assertNotSame(
                $receivedUri,
                $originalUri,
                'Last middleware received original URI instance, but should not have'
            );

            Assert::assertSame(
                $receivedUri->getPath(),
                $originalUri->getPath(),
                'Last middleware received different URI path thatn original, but should not have'
            );

            return $receivedRequest;
        });

        $pipeline->pipe($first);
        $pipeline->pipe($second);
        $pipeline->pipe($last);

        $handler = $this->prophesize(RequestHandlerInterface::class);
        $handler
            ->{HANDLER_METHOD}($request)
            ->willReturn($response);

        $this->assertSame(
            $response,
            $pipeline->process($request, $handler->reveal())
        );
    }

    public function createPassThroughMiddleware(
        callable $requestAssertion
    ) : MiddlewareInterface {
        $middleware = $this->prophesize(MiddlewareInterface::class);
        $middleware
            ->process(
                Argument::that($requestAssertion),
                Argument::type(RequestHandlerInterface::class)
            )
            ->will(function ($args) {
                $request = $args[0];
                $next = $args[1];
                return $next->{HANDLER_METHOD}($request);
            });
        return $middleware->reveal();
    }

    public function createNestedPipeline(ServerRequestInterface $originalRequest) : MiddlewareInterface
    {
        $pipeline = new MiddlewarePipe();

        $barMiddleware = $this->prophesize(MiddlewareInterface::class);
        $barMiddleware
            ->process(
                Argument::that(function ($request) use ($originalRequest) {
                    Assert::assertNotSame(
                        $originalRequest,
                        $request,
                        'Decorated middleware received original request, but should not have'
                    );
                    $path = $request->getUri()->getPath();
                    Assert::assertSame(
                        '/baz',
                        $path,
                        'Decorated middleware expected path "/baz"; received ' . $path
                    );
                    return $request;
                }),
                Argument::type(RequestHandlerInterface::class)
            )
            ->will(function ($args) {
                $request = $args[0];
                $next = $args[1];
                return $next->{HANDLER_METHOD}($request);
            });
        $decorated = new PathMiddlewareDecorator('/bar', $barMiddleware->reveal());

        $normal = $this->prophesize(MiddlewareInterface::class);
        $normal
            ->process(
                Argument::that(function ($request) use ($originalRequest) {
                    Assert::assertNotSame(
                        $originalRequest,
                        $request,
                        'Decorated middleware received original request, but should not have'
                    );
                    $path = $request->getUri()->getPath();
                    Assert::assertSame(
                        '/bar/baz',
                        $path,
                        'Decorated middleware expected path "/bar/baz"; received ' . $path
                    );
                    return $request;
                }),
                Argument::type(RequestHandlerInterface::class)
            )
            ->will(function ($args) {
                $request = $args[0];
                $next = $args[1];
                return $next->{HANDLER_METHOD}($request);
            });

        $pipeline->pipe($decorated);
        $pipeline->pipe($normal->reveal());

        return $pipeline;
    }
}
