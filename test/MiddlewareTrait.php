<?php
/**
 * @see       https://github.com/zendframework/zend-stratigility for the canonical source repository
 * @copyright Copyright (c) 2017 Zend Technologies USA Inc. (https://www.zend.com)
 * @license   https://github.com/zendframework/zend-stratigility/blob/master/LICENSE.md New BSD License
 */

namespace ZendTest\Stratigility;

use Interop\Http\Server\MiddlewareInterface;
use Prophecy\Argument;
use Psr\Http\Message\ResponseInterface;

trait MiddlewareTrait
{
    private function getNotCalledMiddleware() : MiddlewareInterface
    {
        $middleware = $this->prophesize(MiddlewareInterface::class);
        $middleware->process(Argument::any(), Argument::any())
            ->shouldNotBeCalled();

        return $middleware->reveal();
    }

    private function getPassToHandlerMiddleware() : MiddlewareInterface
    {
        $middleware = $this->prophesize(MiddlewareInterface::class);
        $middleware->process(Argument::any(), Argument::any())
            ->will(function (array $args) {
                return $args[1]->handle($args[0]);
            })
            ->shouldBeCalledTimes(1);

        return $middleware->reveal();
    }

    private function getMiddlewareWhichReturnsResponse(ResponseInterface $response) : MiddlewareInterface
    {
        $middleware = $this->prophesize(MiddlewareInterface::class);
        $middleware->process(Argument::any(), Argument::any())
            ->willReturn($response)
            ->shouldBeCalledTimes(1);

        return $middleware->reveal();
    }
}
