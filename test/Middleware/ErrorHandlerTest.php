<?php
/**
 * @see       https://github.com/zendframework/zend-stratigility for the canonical source repository
 * @copyright Copyright (c) 2016-2018 Zend Technologies USA Inc. (https://www.zend.com)
 * @license   https://github.com/zendframework/zend-stratigility/blob/master/LICENSE.md New BSD License
 */

declare(strict_types=1);

namespace ZendTest\Stratigility\Middleware;

use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\Prophecy\ObjectProphecy;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Server\RequestHandlerInterface;
use RuntimeException;
use Zend\Escaper\Escaper;
use Zend\Stratigility\Middleware\ErrorHandler;
use Zend\Stratigility\Middleware\ErrorResponseGenerator;

use function error_reporting;
use function trigger_error;

use const E_USER_DEPRECATED;

class ErrorHandlerTest extends TestCase
{
    /** @var ResponseInterface|ObjectProphecy */
    private $response;

    /** @var callable */
    private $responseFactory;

    public function setUp()
    {
        $this->response = $this->prophesize(ResponseInterface::class);
        $this->responseFactory = function () {
            return $this->response->reveal();
        };
        $this->request = $this->prophesize(ServerRequestInterface::class);
        $this->body = $this->prophesize(StreamInterface::class);
        $this->handler = $this->prophesize(RequestHandlerInterface::class);
        $this->errorReporting = error_reporting();
    }

    public function tearDown()
    {
        error_reporting($this->errorReporting);
    }

    public function createMiddleware($isDevelopmentMode = false)
    {
        $generator = new ErrorResponseGenerator($isDevelopmentMode);
        return new ErrorHandler($this->responseFactory, $generator);
    }

    public function testReturnsResponseFromHandlerWhenNoProblemsOccur()
    {
        $expectedResponse = $this->prophesize(ResponseInterface::class)->reveal();

        $this->handler
            ->handle(Argument::type(ServerRequestInterface::class))
            ->willReturn($expectedResponse);

        $this->response->withStatus(Argument::any())->shouldNotBeCalled();

        $middleware = $this->createMiddleware();
        $result = $middleware->process($this->request->reveal(), $this->handler->reveal());

        $this->assertSame($expectedResponse, $result);
    }

    public function testReturnsErrorResponseIfHandlerDoesNotReturnAResponse()
    {
        $this->handler
            ->handle(Argument::type(ServerRequestInterface::class))
            ->willReturn(null);

        $this->body->write('Unknown Error')->shouldBeCalled();
        $this->response->getStatusCode()->willReturn(200);
        $this->response->withStatus(500)->will([$this->response, 'reveal']);
        $this->response->getReasonPhrase()->willReturn('');
        $this->response->getBody()->will([$this->body, 'reveal']);

        $middleware = $this->createMiddleware();
        $result = $middleware->process($this->request->reveal(), $this->handler->reveal());

        $this->assertSame($this->response->reveal(), $result);
    }

    public function testReturnsErrorResponseIfHandlerRaisesAnErrorInTheErrorMask()
    {
        error_reporting(E_USER_DEPRECATED);
        $this->handler
            ->handle(Argument::type(ServerRequestInterface::class))
            ->will(function () {
                trigger_error('Deprecated', E_USER_DEPRECATED);
            });

        $this->body->write('Unknown Error')->shouldBeCalled();
        $this->response->getStatusCode()->willReturn(200);
        $this->response->withStatus(500)->will([$this->response, 'reveal']);
        $this->response->getReasonPhrase()->willReturn('');
        $this->response->getBody()->will([$this->body, 'reveal']);

        $middleware = $this->createMiddleware();
        $result = $middleware->process($this->request->reveal(), $this->handler->reveal());

        $this->assertSame($this->response->reveal(), $result);
    }

    public function testReturnsResponseFromHandlerWhenErrorRaisedIsNotInTheErrorMask()
    {
        $originalMask = error_reporting();
        error_reporting($originalMask & ~E_USER_DEPRECATED);

        $expectedResponse = $this->prophesize(ResponseInterface::class)->reveal();
        $this->handler
            ->handle(Argument::type(ServerRequestInterface::class))
            ->will(function () use ($expectedResponse) {
                trigger_error('Deprecated', E_USER_DEPRECATED);
                return $expectedResponse;
            });

        $this->body->write('Unknown Error')->shouldNotBeCalled();
        $this->response->getStatusCode()->shouldNotBeCalled();
        $this->response->withStatus(Argument::any())->shouldNotBeCalled();

        $middleware = $this->createMiddleware();
        $result = $middleware->process($this->request->reveal(), $this->handler->reveal());

        $this->assertSame($expectedResponse, $result);
    }

    public function testReturnsErrorResponseIfHandlerRaisesAnException()
    {
        $this->handler
            ->handle(Argument::type(ServerRequestInterface::class))
            ->willThrow(new RuntimeException('Exception raised', 503));

        $this->body->write('Unknown Error')->shouldBeCalled();
        $this->response->getStatusCode()->willReturn(200);
        $this->response->withStatus(503)->will([$this->response, 'reveal']);
        $this->response->getReasonPhrase()->willReturn('');
        $this->response->getBody()->will([$this->body, 'reveal']);

        $middleware = $this->createMiddleware();
        $result = $middleware->process($this->request->reveal(), $this->handler->reveal());

        $this->assertSame($this->response->reveal(), $result);
    }

    public function testResponseErrorMessageIncludesStackTraceIfDevelopmentModeIsEnabled()
    {
        $exception = new RuntimeException('Exception raised', 503);
        $this->handler
            ->handle(Argument::type(ServerRequestInterface::class))
            ->willThrow($exception);

        $this->body
            ->write((new Escaper())
            ->escapeHtml((string) $exception))->shouldBeCalled();
        $this->response->getStatusCode()->willReturn(200);
        $this->response->withStatus(503)->will([$this->response, 'reveal']);
        $this->response->getReasonPhrase()->willReturn('');
        $this->response->getBody()->will([$this->body, 'reveal']);

        $middleware = $this->createMiddleware(true);
        $result = $middleware->process($this->request->reveal(), $this->handler->reveal());

        $this->assertSame($this->response->reveal(), $result);
    }

    public function testErrorHandlingTriggersListeners()
    {
        $exception = new RuntimeException('Exception raised', 503);
        $this->handler
            ->handle(Argument::type(ServerRequestInterface::class))
            ->willThrow($exception);

        $this->body->write('Unknown Error')->shouldBeCalled();
        $this->response->getStatusCode()->willReturn(200);
        $this->response->withStatus(503)->will([$this->response, 'reveal']);
        $this->response->getReasonPhrase()->willReturn('');
        $this->response->getBody()->will([$this->body, 'reveal']);

        $listener = function ($error, $request, $response) use ($exception) {
            $this->assertSame($exception, $error, 'Listener did not receive same exception as was raised');
            $this->assertSame($this->request->reveal(), $request, 'Listener did not receive same request');
            $this->assertSame($this->response->reveal(), $response, 'Listener did not receive same response');
        };
        $listener2 = clone $listener;

        $middleware = $this->createMiddleware();
        $middleware->attachListener($listener);
        $middleware->attachListener($listener2);

        $result = $middleware->process($this->request->reveal(), $this->handler->reveal());

        $this->assertSame($this->response->reveal(), $result);
    }

    public function testCanProvideAlternateErrorResponseGenerator()
    {
        $generator = function ($e, $request, $response) {
            $response = $response->withStatus(400);
            $response->getBody()->write('The client messed up');
            return $response;
        };

        $this->handler
            ->handle(Argument::type(ServerRequestInterface::class))
            ->willThrow(new RuntimeException('Exception raised', 503));

        $this->response->withStatus(400)->will([$this->response, 'reveal']);
        $this->response->getBody()->will([$this->body, 'reveal']);
        $this->body->write('The client messed up')->shouldBeCalled();

        $middleware = new ErrorHandler($this->responseFactory, $generator);
        $result = $middleware->process($this->request->reveal(), $this->handler->reveal());

        $this->assertSame($this->response->reveal(), $result);
    }

    public function testTheSameListenerIsAttachedOnlyOnce()
    {
        $middleware = $this->createMiddleware();
        $listener = function () {
        };

        $middleware->attachListener($listener);
        $middleware->attachListener($listener);

        self::assertAttributeCount(1, 'listeners', $middleware);
    }
}
