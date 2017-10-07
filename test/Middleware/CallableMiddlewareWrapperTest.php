<?php
/**
 * @see       https://github.com/zendframework/zend-stratigility for the canonical source repository
 * @copyright Copyright (c) 2016-2017 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-stratigility/blob/master/LICENSE.md New BSD License
 */

namespace ZendTest\Stratigility\Middleware;

use Closure;
use Interop\Http\Server\RequestHandlerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Zend\Stratigility\Middleware\CallableMiddlewareWrapper;
use Zend\Stratigility\Next;

class CallableMiddlewareWrapperTest extends TestCase
{
    public function testWrapperDecoratesAndProxiesToCallableMiddleware()
    {
        $request  = $this->prophesize(ServerRequestInterface::class)->reveal();
        $handler  = $this->prophesize(RequestHandlerInterface::class)->reveal();
        $response = $this->prophesize(ResponseInterface::class)->reveal();

        $decorator = new CallableMiddlewareWrapper(
            function ($request, $response, $handler) {
                return $response;
            },
            $response
        );

        $this->assertSame($response, $decorator->process($request, $handler));
    }

    public function testWrapperDoesNotDecorateNextInstancesWhenProxying()
    {
        $request  = $this->prophesize(ServerRequestInterface::class)->reveal();
        $response = $this->prophesize(ResponseInterface::class)->reveal();

        $handler   = $this->prophesize(Next::class)->reveal();
        $decorator = new CallableMiddlewareWrapper(
            function ($request, $response, $next) use ($handler) {
                $this->assertSame($handler, $next);

                return $response;
            },
            $response
        );

        $this->assertSame($response, $decorator->process($request, $handler));
    }

    public function testWrapperDecoratesDelegatesNotExtendingNext()
    {
        $request  = $this->prophesize(ServerRequestInterface::class)->reveal();
        $response = $this->prophesize(ResponseInterface::class)->reveal();

        $handler   = $this->prophesize(RequestHandlerInterface::class)->reveal();
        $decorator = new CallableMiddlewareWrapper(
            function ($request, $response, $next) use ($handler) {
                $this->assertNotSame($handler, $next);
                $this->assertInstanceOf(Closure::class, $next);

                return $response;
            },
            $response
        );

        $this->assertSame($response, $decorator->process($request, $handler));
    }

    public function testDecoratedDelegateWillBeInvokedWithOnlyRequest()
    {
        $request  = $this->prophesize(ServerRequestInterface::class)->reveal();
        $response = $this->prophesize(ResponseInterface::class)->reveal();
        $expected = $this->prophesize(ResponseInterface::class)->reveal();

        $handler = $this->prophesize(RequestHandlerInterface::class);
        $handler->handle($request)->willReturn($expected);

        $decorator = new CallableMiddlewareWrapper(
            function ($request, $response, $next) {
                return $next($request, $response);
            },
            $response
        );

        $this->assertSame($expected, $decorator->process($request, $handler->reveal()));
    }
}
