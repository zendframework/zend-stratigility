<?php
/**
 * @see       https://github.com/zendframework/zend-stratigility for the canonical source repository
 * @copyright Copyright (c) 2017 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-stratigility/blob/master/LICENSE.md New BSD License
 */

namespace ZendTest\Stratigility\Middleware;

use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Webimpress\HttpMiddlewareCompatibility\HandlerInterface as RequestHandlerInterface;
use Zend\Stratigility\Exception;
use Zend\Stratigility\Middleware\CallableMiddlewareDecorator;

class CallableMiddlewareDecoratorTest extends TestCase
{
    public function testCallableMiddlewareThatDoesNotProduceAResponseRaisesAnException()
    {
        $request = $this->prophesize(ServerRequestInterface::class)->reveal();
        $handler = $this->prophesize(RequestHandlerInterface::class)->reveal();

        $middleware = function ($request, $handler) {
            return 'foo';
        };

        $decorator = new CallableMiddlewareDecorator($middleware);

        $this->expectException(Exception\MissingResponseException::class);
        $this->expectExceptionMessage('failed to produce a response');
        $decorator->process($request, $handler);
    }

    public function testCallableMiddlewareReturningAResponseSucceedsProcessCall()
    {
        $request  = $this->prophesize(ServerRequestInterface::class)->reveal();
        $handler  = $this->prophesize(RequestHandlerInterface::class)->reveal();
        $response = $this->prophesize(ResponseInterface::class)->reveal();

        $middleware = function ($request, $handler) use ($response) {
            return $response;
        };

        $decorator = new CallableMiddlewareDecorator($middleware);

        $this->assertSame($response, $decorator->process($request, $handler));
    }
}
