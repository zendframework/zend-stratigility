<?php
/**
 * @see       https://github.com/zendframework/zend-stratigility for the canonical source repository
 * @copyright Copyright (c) 2016 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-stratigility/blob/master/LICENSE.md New BSD License
 */
declare(strict_types=1);

namespace ZendTest\Stratigility\Middleware;

use Interop\Http\Server\RequestHandlerInterface;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UriInterface;
use Zend\Stratigility\Middleware\OriginalMessages;

class OriginalMessagesTest extends TestCase
{
    public function setUp()
    {
        $this->uri = $this->prophesize(UriInterface::class);
        $this->request = $this->prophesize(ServerRequestInterface::class);
    }

    public function testNextReceivesRequestWithNewAttributes()
    {
        $middleware = new OriginalMessages();
        $expected   = $this->prophesize(ResponseInterface::class)->reveal();

        $handler = $this->prophesize(RequestHandlerInterface::class);
        $handler->handle($this->request->reveal())->willReturn($expected);

        $this->request->getUri()->will([$this->uri, 'reveal']);
        $this->request->withAttribute(
            'originalUri',
            Argument::that(function ($arg) {
                $this->assertSame($this->uri->reveal(), $arg);
                return $arg;
            })
        )->will([$this->request, 'reveal']);

        $this->request->withAttribute(
            'originalRequest',
            Argument::that(function ($arg) {
                $this->assertSame($this->request->reveal(), $arg);
                return $arg;
            })
        )->will([$this->request, 'reveal']);

        $response = $middleware->process($this->request->reveal(), $handler->reveal());

        $this->assertSame($expected, $response);
    }
}
