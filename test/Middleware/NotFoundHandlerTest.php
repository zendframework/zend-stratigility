<?php
/**
 * @see       https://github.com/zendframework/zend-stratigility for the canonical source repository
 * @copyright Copyright (c) 2016-2017 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-stratigility/blob/master/LICENSE.md New BSD License
 */
declare(strict_types=1);

namespace ZendTest\Stratigility\Middleware;

use Interop\Http\Server\RequestHandlerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamInterface;
use Zend\Stratigility\Middleware\NotFoundHandler;

class NotFoundHandlerTest extends TestCase
{
    public function testReturnsResponseWith404StatusAndErrorMessageInBody()
    {
        $stream = $this->prophesize(StreamInterface::class);
        $stream->write('Cannot POST https://example.com/foo');

        $response = $this->prophesize(ResponseInterface::class);
        $response->withStatus(404)->will([$response, 'reveal']);
        $response->getBody()->will([$stream, 'reveal']);

        $request = $this->prophesize(ServerRequestInterface::class);
        $request->getMethod()->willReturn('POST');
        $request->getUri()->willReturn('https://example.com/foo');

        $middleware = new NotFoundHandler($response->reveal());

        $this->assertSame(
            $response->reveal(),
            $middleware->process($request->reveal(), $this->prophesize(RequestHandlerInterface::class)->reveal())
        );
    }
}
