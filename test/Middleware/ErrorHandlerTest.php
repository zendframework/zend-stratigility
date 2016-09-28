<?php
/**
 * @link      http://github.com/zendframework/zend-stratigility for the canonical source repository
 * @copyright Copyright (c) 2016 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 */

namespace ZendTest\Stratigility\Middleware;

use PHPUnit_Framework_TestCase as TestCase;
use Prophecy\Argument;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamInterface;
use RuntimeException;
use Zend\Escaper\Escaper;
use Zend\Stratigility\Exception\MissingDelegateException;
use Zend\Stratigility\Middleware\ErrorHandler;
use Zend\Stratigility\Middleware\ErrorResponseGenerator;

class ErrorHandlerTest extends TestCase
{
    public function setUp()
    {
        $this->response = $this->prophesize(ResponseInterface::class);
        $this->request = $this->prophesize(ServerRequestInterface::class);
        $this->body = $this->prophesize(StreamInterface::class);
        $this->errorReporting = error_reporting();
    }

    public function tearDown()
    {
        error_reporting($this->errorReporting);
    }

    public function createMiddleware($isDevelopmentMode = false)
    {
        $generator = new ErrorResponseGenerator($isDevelopmentMode);
        return new ErrorHandler($this->response->reveal(), $generator);
    }

    public function testReturnsResponseFromInnerMiddlewareWhenNoProblemsOccur()
    {
        $expectedResponse = $this->prophesize(ResponseInterface::class)->reveal();
        $innerMiddleware = function ($req) use ($expectedResponse) {
            return $expectedResponse;
        };

        $next = new TestAsset\Next;
        $next->push($innerMiddleware);

        $this->response->withStatus(Argument::any())->shouldNotBeCalled();

        $middleware = $this->createMiddleware();
        $result = $middleware($this->request->reveal(), $this->response->reveal(), $next);

        $this->assertSame($expectedResponse, $result);
    }

    public function testReturnsErrorResponseIfInnerMiddlewareDoesNotReturnAResponse()
    {
        $innerMiddleware = function () {
        };

        $next = new TestAsset\Next;
        $next->push($innerMiddleware);

        $this->body->write('Unknown Error')->shouldBeCalled();
        $this->response->getStatusCode()->willReturn(200);
        $this->response->withStatus(500)->will([$this->response, 'reveal']);
        $this->response->getReasonPhrase()->willReturn('');
        $this->response->getBody()->will([$this->body, 'reveal']);

        $middleware = $this->createMiddleware();
        $result = $middleware($this->request->reveal(), $this->response->reveal(), $next);

        $this->assertSame($this->response->reveal(), $result);
    }

    public function testReturnsErrorResponseIfInnerMiddlewareRaisesAnErrorInTheErrorMask()
    {
        error_reporting(E_USER_DEPRECATED);
        $innerMiddleware = function () {
            trigger_error('Deprecated', E_USER_DEPRECATED);
        };

        $next = new TestAsset\Next;
        $next->push($innerMiddleware);

        $this->body->write('Unknown Error')->shouldBeCalled();
        $this->response->getStatusCode()->willReturn(200);
        $this->response->withStatus(500)->will([$this->response, 'reveal']);
        $this->response->getReasonPhrase()->willReturn('');
        $this->response->getBody()->will([$this->body, 'reveal']);

        $middleware = $this->createMiddleware();
        $result = $middleware($this->request->reveal(), $this->response->reveal(), $next);

        $this->assertSame($this->response->reveal(), $result);
    }

    public function testReturnsResponseFromInnerMiddlewareWhenErrorRaisedIsNotInTheErrorMask()
    {
        $originalMask = error_reporting();
        error_reporting($originalMask & ~E_USER_DEPRECATED);

        $expectedResponse = $this->prophesize(ResponseInterface::class)->reveal();
        $innerMiddleware = function () use ($expectedResponse) {
            trigger_error('Deprecated', E_USER_DEPRECATED);
            return $expectedResponse;
        };

        $next = new TestAsset\Next;
        $next->push($innerMiddleware);

        $this->body->write('Unknown Error')->shouldNotBeCalled();
        $this->response->getStatusCode()->shouldNotBeCalled();
        $this->response->withStatus(Argument::any())->shouldNotBeCalled();

        $middleware = $this->createMiddleware();
        $result = $middleware($this->request->reveal(), $this->response->reveal(), $next);

        $this->assertSame($expectedResponse, $result);
    }

    public function testReturnsErrorResponseIfInnerMiddlewareRaisesAnException()
    {
        $innerMiddleware = function () {
            throw new RuntimeException('Exception raised', 503);
        };

        $next = new TestAsset\Next;
        $next->push($innerMiddleware);

        $this->body->write('Unknown Error')->shouldBeCalled();
        $this->response->getStatusCode()->willReturn(200);
        $this->response->withStatus(503)->will([$this->response, 'reveal']);
        $this->response->getReasonPhrase()->willReturn('');
        $this->response->getBody()->will([$this->body, 'reveal']);

        $middleware = $this->createMiddleware();
        $result = $middleware($this->request->reveal(), $this->response->reveal(), $next);

        $this->assertSame($this->response->reveal(), $result);
    }

    public function testResponseErrorMessageIncludesStackTraceIfDevelopmentModeIsEnabled()
    {
        $exception = new RuntimeException('Exception raised', 503);
        $innerMiddleware = function () use ($exception) {
            throw $exception;
        };

        $next = new TestAsset\Next;
        $next->push($innerMiddleware);

        $this->body
            ->write((new Escaper())
            ->escapeHtml((string) $exception))->shouldBeCalled();
        $this->response->getStatusCode()->willReturn(200);
        $this->response->withStatus(503)->will([$this->response, 'reveal']);
        $this->response->getReasonPhrase()->willReturn('');
        $this->response->getBody()->will([$this->body, 'reveal']);

        $middleware = $this->createMiddleware(true);
        $result = $middleware($this->request->reveal(), $this->response->reveal(), $next);

        $this->assertSame($this->response->reveal(), $result);
    }

    public function testErrorHandlingTriggersListeners()
    {
        $exception = new RuntimeException('Exception raised', 503);

        $innerMiddleware = function () use ($exception) {
            throw $exception;
        };

        $next = new TestAsset\Next;
        $next->push($innerMiddleware);

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

        $result = $middleware($this->request->reveal(), $this->response->reveal(), $next);

        $this->assertSame($this->response->reveal(), $result);
    }

    public function testCanProvideAlternateErrorResponseGenerator()
    {
        $generator = function ($e, $request, $response) {
            $response = $response->withStatus(400);
            $response->getBody()->write('The client messed up');
            return $response;
        };

        $innerMiddleware = function () {
            throw new RuntimeException('Exception raised', 503);
        };

        $next = new TestAsset\Next;
        $next->push($innerMiddleware);

        $this->response->withStatus(400)->will([$this->response, 'reveal']);
        $this->response->getBody()->will([$this->body, 'reveal']);
        $this->body->write('The client messed up')->shouldBeCalled();

        $middleware = new ErrorHandler($this->response->reveal(), $generator);
        $result = $middleware($this->request->reveal(), $this->response->reveal(), $next);

        $this->assertSame($this->response->reveal(), $result);
    }

    public function testInvokingErrorHandlerWithoutNextArgumentResultsInErrorResponse()
    {
        $generator = function ($e, $request, $response) {
            $this->assertInstanceOf(MissingDelegateException::class, $e);
            return $response;
        };

        $middleware = new ErrorHandler($this->response->reveal(), $generator);
        $response = $middleware($this->request->reveal(), $this->response->reveal());

        $this->assertSame($this->response->reveal(), $response);
    }
}
