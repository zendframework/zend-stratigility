<?php
/**
 * @see       https://github.com/zendframework/zend-stratigility for the canonical source repository
 * @copyright Copyright (c) 2018 Zend Technologies USA Inc. (https://www.zend.com)
 * @license   https://github.com/zendframework/zend-stratigility/blob/master/LICENSE.md New BSD License
 */

declare(strict_types=1);

namespace ZendTest\Stratigility\Middleware;

use PHPUnit\Framework\Assert;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Zend\Diactoros\Response;
use Zend\Diactoros\ServerRequest;
use Zend\Diactoros\Uri;
use Zend\Stratigility\Middleware\PathMiddlewareDecorator;
use Zend\Stratigility\MiddlewarePipe;

class PathMiddlewareDecoratorIntegrationTest extends TestCase
{
    public function testPipelineComposingPathDecoratedMiddlewareExecutesAsExpected()
    {
        $uri = (new Uri)->withPath('/foo/bar/baz');
        $request = (new ServerRequest())->withUri($uri);
        $response = new Response();

        $pipeline = new MiddlewarePipe();

        $first = $this->createPassThroughMiddleware(function ($received) use ($request) {
            Assert::assertSame(
                $request,
                $received,
                'First middleware did not receive original request, but should have'
            );
            return $request;
        });
        $second = new PathMiddlewareDecorator('/foo', $this->createNestedPipeline($request));
        $last = $this->createPassThroughMiddleware(function ($received) use ($request) {
            Assert::assertNotSame(
                $request,
                $received,
                'Last middleware received original request, but should not have'
            );

            $originalUri = $request->getUri();
            $receivedUri = $received->getUri();

            Assert::assertNotSame(
                $originalUri,
                $receivedUri,
                'Last middleware received original URI instance, but should not have'
            );

            Assert::assertSame(
                $originalUri->getPath(),
                $receivedUri->getPath(),
                'Last middleware did not receive original URI path, but should have'
            );

            return $request;
        });

        $pipeline->pipe($first);
        $pipeline->pipe($second);
        $pipeline->pipe($last);

        $handler = $this->prophesize(RequestHandlerInterface::class);
        $handler
            ->handle($request)
            ->willReturn($response);

        $this->assertSame(
            $response,
            $pipeline->process($request, $handler->reveal())
        );
    }

    public function createPassThroughMiddleware(callable $requestAssertion) : MiddlewareInterface
    {
        $middleware = $this->prophesize(MiddlewareInterface::class);
        $middleware
            ->process(
                Argument::that($requestAssertion),
                Argument::type(RequestHandlerInterface::class)
            )
            ->will(function ($args) {
                $request = $args[0];
                $next = $args[1];
                return $next->handle($request);
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
                return $next->handle($request);
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
                return $next->handle($request);
            });

        $pipeline->pipe($decorated);
        $pipeline->pipe($normal->reveal());

        return $pipeline;
    }
}
