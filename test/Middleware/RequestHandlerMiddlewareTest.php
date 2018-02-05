<?php
/**
 * @see       https://github.com/zendframework/zend-stratigility for the canonical source repository
 * @copyright Copyright (c) 2018 Zend Technologies USA Inc. (https://www.zend.com)
 * @license   https://github.com/zendframework/zend-stratigility/blob/master/LICENSE.md New BSD License
 */
declare(strict_types=1);

namespace ZendTest\Stratigility\Middleware;

use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Zend\Stratigility\Middleware\RequestHandlerMiddleware;

class RequestHandlerMiddlewareTest extends TestCase
{
    public function setUp()
    {
        $this->request = $this->prophesize(ServerRequestInterface::class)->reveal();
        $this->response = $this->prophesize(ResponseInterface::class)->reveal();

        $this->handler = $this->prophesize(RequestHandlerInterface::class);
        $this->handler->handle($this->request)->willReturn($this->response);

        $this->middleware = new RequestHandlerMiddleware($this->handler->reveal());
    }

    public function testDecoratesHandlerAsMiddleware()
    {
        $handler = $this->prophesize(RequestHandlerInterface::class);
        $handler->handle(Argument::any())->shouldNotBeCalled();

        $this->assertSame(
            $this->response,
            $this->middleware->process($this->request, $handler->reveal())
        );
    }

    public function testDecoratesHandlerAsHandler()
    {
        $this->assertSame(
            $this->response,
            $this->middleware->handle($this->request)
        );
    }
}
