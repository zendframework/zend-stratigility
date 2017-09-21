<?php
/**
 * @see       https://github.com/zendframework/zend-stratigility for the canonical source repository
 * @copyright Copyright (c) 2016-2017 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-stratigility/blob/master/LICENSE.md New BSD License
 */

namespace ZendTest\Stratigility\Middleware;

use Interop\Http\Server\RequestHandlerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Zend\Stratigility\Middleware\CallableInteropMiddlewareWrapper;

class CallableInteropMiddlewareWrapperTest extends TestCase
{
    public function testWrapperDecoratesAndProxiesToCallableInteropMiddleware()
    {
        $request  = $this->prophesize(ServerRequestInterface::class)->reveal();
        $handler  = $this->prophesize(RequestHandlerInterface::class)->reveal();
        $response = $this->prophesize(ResponseInterface::class)->reveal();

        $decorator = new CallableInteropMiddlewareWrapper(
            function ($request, RequestHandlerInterface $handler) use ($response) {
                return $response;
            }
        );

        $this->assertSame($response, $decorator->process($request, $handler));
    }
}
