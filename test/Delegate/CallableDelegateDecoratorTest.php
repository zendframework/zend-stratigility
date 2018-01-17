<?php
/**
 * @see       https://github.com/zendframework/zend-stratigility for the canonical source repository
 * @copyright Copyright (c) 2018 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-stratigility/blob/master/LICENSE.md New BSD License
 */

namespace ZendTest\Stratigility\Delegate;

use PHPUnit\Framework\Assert;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Zend\Stratigility\Delegate\CallableDelegateDecorator;

class CallableDelegateDecoratorTest extends TestCase
{
    public function setUp()
    {
        $this->response = $this->prophesize(ResponseInterface::class)->reveal();
    }

    public function testProcessWillProxyToComposedDelegate()
    {
        $originalRequest = $this->prophesize(ServerRequestInterface::class)->reveal();

        $delegate = function ($request, $response) use ($originalRequest) {
            Assert::assertSame($originalRequest, $request);
            Assert::assertSame($this->response, $response);
            return $response;
        };

        $decorator = new CallableDelegateDecorator($delegate, $this->response);

        $this->assertSame($this->response, $decorator->process($originalRequest));
    }

    public function testHandleWillProxyToComposedDelegate()
    {
        $originalRequest = $this->prophesize(ServerRequestInterface::class)->reveal();

        $delegate = function ($request, $response) use ($originalRequest) {
            Assert::assertSame($originalRequest, $request);
            Assert::assertSame($this->response, $response);
            return $response;
        };

        $decorator = new CallableDelegateDecorator($delegate, $this->response);

        $this->assertSame($this->response, $decorator->handle($originalRequest));
    }

    public function testNextWillProxyToComposedDelegateUsingNonServerRequest()
    {
        $originalRequest = $this->prophesize(RequestInterface::class)->reveal();

        $delegate = function ($request, $response) use ($originalRequest) {
            Assert::assertSame($originalRequest, $request);
            Assert::assertSame($this->response, $response);
            return $response;
        };

        $decorator = new CallableDelegateDecorator($delegate, $this->response);

        $this->assertSame($this->response, $decorator->next($originalRequest));
    }

    public function testNextWillProxyToComposedDelegateUsingServerRequest()
    {
        $originalRequest = $this->prophesize(ServerRequestInterface::class)->reveal();

        $delegate = function ($request, $response) use ($originalRequest) {
            Assert::assertSame($originalRequest, $request);
            Assert::assertSame($this->response, $response);
            return $response;
        };

        $decorator = new CallableDelegateDecorator($delegate, $this->response);

        $this->assertSame($this->response, $decorator->next($originalRequest));
    }
}
