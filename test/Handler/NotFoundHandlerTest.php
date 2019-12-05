<?php
/**
 * @see       https://github.com/zendframework/zend-stratigility for the canonical source repository
 * @copyright Copyright (c) 2016-2018 Zend Technologies USA Inc. (https://www.zend.com)
 * @license   https://github.com/zendframework/zend-stratigility/blob/master/LICENSE.md New BSD License
 */

declare(strict_types=1);

namespace ZendTest\Stratigility\Handler;

use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamInterface;
use Zend\Stratigility\Handler\NotFoundHandler;

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

        $responseFactory = function () use ($response) {
            return $response->reveal();
        };

        $middleware = new NotFoundHandler($responseFactory);

        $this->assertSame(
            $response->reveal(),
            $middleware->handle($request->reveal())
        );
    }
}
