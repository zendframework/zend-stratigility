<?php
/**
 * @see       https://github.com/zendframework/zend-stratigility for the canonical source repository
 * @copyright Copyright (c) 2017-2018 Zend Technologies USA Inc. (https://www.zend.com)
 * @license   https://github.com/zendframework/zend-stratigility/blob/master/LICENSE.md New BSD License
 */
declare(strict_types=1);

namespace ZendTest\Stratigility\Middleware;

use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Zend\Diactoros\Response;
use Zend\Stratigility\Exception;
use Zend\Stratigility\Middleware\DoublePassMiddlewareDecorator;

use function Zend\Stratigility\doublePassMiddleware;

class DoublePassMiddlewareDecoratorTest extends TestCase
{
    public function testCallableMiddlewareThatDoesNotProduceAResponseRaisesAnException()
    {
        $response = $this->prophesize(ResponseInterface::class)->reveal();
        $request = $this->prophesize(ServerRequestInterface::class)->reveal();
        $handler = $this->prophesize(RequestHandlerInterface::class)->reveal();

        $middleware = function ($request, $response, $next) {
            return 'foo';
        };

        $decorator = new DoublePassMiddlewareDecorator($middleware, $response);

        $this->expectException(Exception\MissingResponseException::class);
        $this->expectExceptionMessage('failed to produce a response');
        $decorator->process($request, $handler);
    }

    public function testCallableMiddlewareReturningAResponseSucceedsProcessCall()
    {
        $response = $this->prophesize(ResponseInterface::class)->reveal();
        $request  = $this->prophesize(ServerRequestInterface::class)->reveal();
        $handler  = $this->prophesize(RequestHandlerInterface::class)->reveal();

        $middleware = function ($request, $response, $next) {
            return $response;
        };

        $decorator = new DoublePassMiddlewareDecorator($middleware, $response);

        $this->assertSame($response, $decorator->process($request, $handler));
    }

    public function testCallableMiddlewareCanDelegateViaHandler()
    {
        $response = $this->prophesize(ResponseInterface::class);
        $request  = $this->prophesize(ServerRequestInterface::class);

        $handler = $this->prophesize(RequestHandlerInterface::class);
        $handler
            ->handle(Argument::that([$request, 'reveal']))
            ->will([$response, 'reveal']);

        $middleware = function ($request, $response, $next) {
            return $next($request, $response);
        };

        $decorator = new DoublePassMiddlewareDecorator($middleware, $response->reveal());

        $this->assertSame(
            $response->reveal(),
            $decorator->process($request->reveal(), $handler->reveal())
        );
    }

    public function testDecoratorCreatesAResponsePrototypeIfNoneIsProvided()
    {
        $request  = $this->prophesize(ServerRequestInterface::class)->reveal();
        $handler  = $this->prophesize(RequestHandlerInterface::class)->reveal();

        $middleware = function ($request, $response, $next) {
            return $response;
        };

        $decorator = new DoublePassMiddlewareDecorator($middleware);

        $response = $decorator->process($request, $handler);
        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertInstanceOf(Response::class, $response);
    }

    public function testDoublePassMiddlewareFunction()
    {
        $toDecorate = function ($request, $response, $next) {
            return 'foo';
        };

        $response = $this->prophesize(ResponseInterface::class)->reveal();

        $middleware = doublePassMiddleware($toDecorate, $response);
        self::assertInstanceOf(DoublePassMiddlewareDecorator::class, $middleware);
        self::assertAttributeSame($toDecorate, 'middleware', $middleware);
        self::assertAttributeSame($response, 'responsePrototype', $middleware);
    }
}
