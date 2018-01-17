<?php
/**
 * @see       https://github.com/zendframework/zend-stratigility for the canonical source repository
 * @copyright Copyright (c) 2015-2018 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-stratigility/blob/master/LICENSE.md New BSD License
 */

namespace ZendTest\Stratigility;

use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use ReflectionProperty;
use Webimpress\HttpMiddlewareCompatibility\HandlerInterface as DelegateInterface;
use Webimpress\HttpMiddlewareCompatibility\MiddlewareInterface as ServerMiddlewareInterface;
use Zend\Diactoros\Response;
use Zend\Diactoros\ServerRequest as Request;
use Zend\Diactoros\Uri;
use Zend\Stratigility\Exception\InvalidMiddlewareException;
use Zend\Stratigility\Exception\MissingResponsePrototypeException;
use Zend\Stratigility\Middleware\CallableInteropMiddlewareWrapper;
use Zend\Stratigility\Middleware\CallableMiddlewareWrapper;
use Zend\Stratigility\Middleware\CallableMiddlewareDecorator;
use Zend\Stratigility\Middleware\DoublePassMiddlewareDecorator;
use Zend\Stratigility\Middleware\PathMiddlewareDecorator;
use Zend\Stratigility\MiddlewarePipe;
use Zend\Stratigility\NoopFinalHandler;

use const Webimpress\HttpMiddlewareCompatibility\HANDLER_METHOD;

class MiddlewarePipeTest extends TestCase
{
    public function setUp()
    {
        $this->request    = new Request([], [], 'http://example.com/', 'GET', 'php://memory');
        $this->response   = new Response();
        $this->middleware = new MiddlewarePipe();
        $this->middleware->setResponsePrototype($this->response);
    }

    /**
     * @return NoopFinalHandler
     */
    public function createFinalHandler()
    {
        return new NoopFinalHandler();
    }

    /**
     * @param \stdClass $errorContainer
     * @return callable
     */
    public function createDeprecationErrorHandler($errorContainer)
    {
        return function ($errno, $errstr) use ($errorContainer) {
            $errorContainer->type = $errno;
            $errorContainer->message = $errstr;
        };
    }

    public function invalidHandlers()
    {
        return [
            'null' => [null],
            'bool' => [true],
            'int' => [1],
            'float' => [1.1],
            'string' => ['non-function-string'],
            'array' => [['foo', 'bar']],
            'object' => [(object) ['foo' => 'bar']],
        ];
    }

    /**
     * @dataProvider invalidHandlers
     *
     * @param mixed $handler
     */
    public function testPipeThrowsExceptionForInvalidHandler($handler)
    {
        $this->expectException(InvalidMiddlewareException::class);
        $this->middleware->pipe('/foo', $handler);
    }

    public function testPipeTriggersDeprecationErrorWhenNonEmptyPathProvidedWithoutPathMiddlewareDecorator()
    {
        $error = false;
        set_error_handler(function ($errno, $errmessage) use (&$error) {
            $error = (object) [
                'type'    => $errno,
                'message' => $errmessage,
            ];
        }, E_USER_DEPRECATED);
        $this->middleware->pipe('/foo', $this->prophesize(ServerMiddlewareInterface::class)->reveal());
        restore_error_handler();

        $this->assertNotSame(false, $error);
        $this->assertSame(E_USER_DEPRECATED, $error->type);
        $this->assertContains(PathMiddlewareDecorator::class, $error->message);
    }

    public function testHandleInvokesUntilFirstHandlerThatDoesNotCallNext()
    {
        $this->middleware->pipe(new DoublePassMiddlewareDecorator(function ($req, $res, $next) {
            $res->getBody()->write("First\n");
            $response = $next($req, $res);
            $response->getBody()->write("First\n");
            return $response;
        }));
        $this->middleware->pipe(new DoublePassMiddlewareDecorator(function ($req, $res, $next) {
            $response = $next($req, $res);
            $response->getBody()->write("Second\n");
            return $response;
        }));
        $this->middleware->pipe(new DoublePassMiddlewareDecorator(function ($req, $res, $next) {
            $res->getBody()->write("Third\n");
            return $res;
        }));

        $this->middleware->pipe(new DoublePassMiddlewareDecorator(function ($req, $res, $next) {
            $this->fail('Should not hit fourth handler!');
        }));

        $request = new Request([], [], 'http://local.example.com/foo', 'GET', 'php://memory');
        $response = $this->middleware->__invoke($request, $this->response, $this->createFinalHandler());
        $body = (string) $response->getBody();
        $this->assertContains('First', $body);
        $this->assertContains('Second', $body);
        $this->assertContains('Third', $body);
    }

    public function testInvokesDelegateWhenQueueIsExhausted()
    {
        $expected = $this->prophesize(ResponseInterface::class)->reveal();
        $this->middleware->setResponsePrototype($expected);

        $this->middleware->pipe(new DoublePassMiddlewareDecorator(function ($req, $res, $next) {
            return $next($req, $res);
        }));
        $this->middleware->pipe(new DoublePassMiddlewareDecorator(function ($req, $res, $next) {
            return $next($req, $res);
        }));
        $this->middleware->pipe(new DoublePassMiddlewareDecorator(function ($req, $res, $next) {
            return $next($req, $res);
        }));

        $request = new Request([], [], 'http://local.example.com/foo', 'GET', 'php://memory');

        $delegate = $this->prophesize(DelegateInterface::class);
        $delegate->{HANDLER_METHOD}($request)->willReturn($expected);

        $result = $this->middleware->__invoke($request, $this->response, $delegate->reveal());

        $this->assertSame($expected, $result);
    }

    public function testReturnsResponseReturnedByQueue()
    {
        $return = new Response();

        $this->middleware->pipe(new DoublePassMiddlewareDecorator(function ($req, $res, $next) {
            return $next($req, $res);
        }));
        $this->middleware->pipe(new DoublePassMiddlewareDecorator(function ($req, $res, $next) {
            return $next($req, $res);
        }));
        $this->middleware->pipe(new DoublePassMiddlewareDecorator(function ($req, $res, $next) use ($return) {
            return $return;
        }));

        $this->middleware->pipe(new DoublePassMiddlewareDecorator(function ($req, $res, $next) {
            $this->fail('Should not hit fourth handler!');
        }));

        $request = new Request([], [], 'http://local.example.com/foo', 'GET', 'php://memory');
        $result  = $this->middleware->__invoke($request, $this->response, $this->createFinalHandler());
        $this->assertSame($return, $result, var_export([
            spl_object_hash($return) => get_class($return),
            spl_object_hash($result) => get_class($result),
        ], 1));
    }

    public function testNestedMiddlewareMayInvokeDoneToInvokeNextOfParent()
    {
        $childMiddleware = $this->prophesize(ServerMiddlewareInterface::class);
        $childMiddleware
            ->process(Argument::type(ServerRequestInterface::class), Argument::type(DelegateInterface::class))
            ->will(function ($args) {
                $request = $args[0];
                $next = $args[1];
                return $next->process($request);
            });

        $childPipeline = new MiddlewarePipe();
        $childPipeline->pipe($childMiddleware->reveal());

        $outerMiddleware = $this->prophesize(ServerMiddlewareInterface::class);
        $outerMiddleware
            ->process(Argument::type(ServerRequestInterface::class), Argument::type(DelegateInterface::class))
            ->will(function ($args) {
                $request = $args[0];
                $next = $args[1];
                return $next->process($request);
            });

        $expected = $this->prophesize(ResponseInterface::class)->reveal();
        $innerMiddleware = $this->prophesize(ServerMiddlewareInterface::class);
        $innerMiddleware
            ->process(Argument::type(ServerRequestInterface::class), Argument::type(DelegateInterface::class))
            ->willReturn($expected);

        $pipeline = $this->middleware;
        $pipeline->setResponsePrototype($this->response);
        $pipeline->pipe($outerMiddleware->reveal());
        $pipeline->pipe(new PathMiddlewareDecorator('/test', $childPipeline));
        $pipeline->pipe($innerMiddleware->reveal());

        $request = new Request([], [], 'http://local.example.com/test', 'GET', 'php://memory');
        $final = $this->prophesize(DelegateInterface::class);
        $final->{HANDLER_METHOD}(Argument::any())->shouldNotBeCalled();

        $result = $pipeline->process($request, $final->reveal());
        $this->assertSame($expected, $result);
    }

    /**
     * @group http-interop
     */
    public function testNoResponsePrototypeComposeByDefault()
    {
        $pipeline = new MiddlewarePipe();
        $this->assertAttributeEmpty('responsePrototype', $pipeline);
    }

    /**
     * @group http-interop
     */
    public function testCanComposeResponsePrototype()
    {
        $response = $this->prophesize(Response::class)->reveal();
        $pipeline = new MiddlewarePipe();
        $pipeline->setResponsePrototype($response);
        $this->assertAttributeSame($response, 'responsePrototype', $pipeline);
    }

    /**
     * @group http-interop
     */
    public function testCanPipeInteropMiddleware()
    {
        $delegate = $this->prophesize(DelegateInterface::class)->reveal();

        $response = $this->prophesize(ResponseInterface::class);
        $middleware = $this->prophesize(ServerMiddlewareInterface::class);
        $middleware
            ->process(Argument::type(RequestInterface::class), Argument::type(DelegateInterface::class))
            ->will([$response, 'reveal']);

        $pipeline = new MiddlewarePipe();
        $pipeline->pipe($middleware->reveal());

        $this->assertSame($response->reveal(), $pipeline->process($this->request, $delegate));
    }

    /**
     * @group http-interop
     */
    public function testWillDecorateCallableMiddlewareAsDoublePassMiddlewareIfResponsePrototypePresent()
    {
        $pipeline = new MiddlewarePipe();
        $pipeline->setResponsePrototype($this->response);

        $middleware = function () {
        };

        $error = (object) [];
        set_error_handler($this->createDeprecationErrorHandler($error), E_USER_DEPRECATED);
        $pipeline->pipe($middleware);
        restore_error_handler();

        $this->assertObjectHasAttribute('type', $error);
        $this->assertSame(E_USER_DEPRECATED, $error->type);
        $this->assertObjectHasAttribute('message', $error);
        $this->assertContains(DoublePassMiddlewareDecorator::class, $error->message);

        $r = new ReflectionProperty($pipeline, 'pipeline');
        $r->setAccessible(true);
        $queue = $r->getValue($pipeline);

        $route = $queue->dequeue();
        $test = $route->handler;
        $this->assertInstanceOf(DoublePassMiddlewareDecorator::class, $test);
        $this->assertAttributeSame($middleware, 'middleware', $test);
        $this->assertAttributeSame($this->response, 'responsePrototype', $test);
    }

    public function testWillDecorateACallableDefiningADelegateArgumentUsingAlternateDecorator()
    {
        $pipeline = new MiddlewarePipe();
        $pipeline->setResponsePrototype($this->response);

        $middleware = function ($request, DelegateInterface $delegate) {
        };

        $error = (object) [];
        set_error_handler($this->createDeprecationErrorHandler($error), E_USER_DEPRECATED);
        $pipeline->pipe($middleware);
        restore_error_handler();

        $this->assertObjectHasAttribute('type', $error);
        $this->assertSame(E_USER_DEPRECATED, $error->type);
        $this->assertObjectHasAttribute('message', $error);
        $this->assertContains(CallableMiddlewareDecorator::class, $error->message);

        $r = new ReflectionProperty($pipeline, 'pipeline');
        $r->setAccessible(true);
        $queue = $r->getValue($pipeline);

        $route = $queue->dequeue();
        $test = $route->handler;
        $this->assertInstanceOf(CallableMiddlewareDecorator::class, $test);
        $this->assertAttributeSame($middleware, 'middleware', $test);
    }

    public function testWillDecorateCallableMiddlewareAsInteropMiddleware()
    {
        $interface = \Interop\Http\Server\RequestHandlerInterface::class;

        if (! interface_exists($interface)) {
            $this->markTestSkipped('This tests requires http-interop/http-middleware 0.5.0');
            return;
        }

        $pipeline = new MiddlewarePipe();
        $pipeline->setResponsePrototype($this->response);

        $middleware = function ($request, \Interop\Http\Server\RequestHandlerInterface $handler) {
        };

        $error = (object) [];
        set_error_handler($this->createDeprecationErrorHandler($error), E_USER_DEPRECATED);
        $pipeline->pipe($middleware);
        restore_error_handler();

        $this->assertObjectHasAttribute('type', $error);
        $this->assertSame(E_USER_DEPRECATED, $error->type);
        $this->assertObjectHasAttribute('message', $error);
        $this->assertContains(CallableMiddlewareDecorator::class, $error->message);

        $r = new ReflectionProperty($pipeline, 'pipeline');
        $r->setAccessible(true);
        $queue = $r->getValue($pipeline);

        $route = $queue->dequeue();
        $test = $route->handler;
        $this->assertInstanceOf(CallableMiddlewareDecorator::class, $test);
        $this->assertAttributeSame($middleware, 'middleware', $test);
    }

    /**
     * Used to test that array callables are decorated correctly.
     *
     * @param RequestInterface $request
     * @param ResponseInterface $response
     * @param callable $next
     * @return ResponseInterface
     */
    public function sampleMiddleware($request, $response, $next)
    {
        return $response;
    }

    public function testTriggersDeprecationNoticeWhenDecoratingCallableArrayMiddleware()
    {
        $pipeline = new MiddlewarePipe();
        $pipeline->setResponsePrototype($this->response);

        $middleware = [$this, 'sampleMiddleware'];

        $error = (object) [];
        set_error_handler($this->createDeprecationErrorHandler($error), E_USER_DEPRECATED);
        $pipeline->pipe($middleware);
        restore_error_handler();

        $this->assertObjectHasAttribute('type', $error);
        $this->assertSame(E_USER_DEPRECATED, $error->type);
        $this->assertObjectHasAttribute('message', $error);
        $this->assertContains(DoublePassMiddlewareDecorator::class, $error->message);

        $r = new ReflectionProperty($pipeline, 'pipeline');
        $r->setAccessible(true);
        $queue = $r->getValue($pipeline);

        $route = $queue->dequeue();
        $test = $route->handler;
        $this->assertInstanceOf(DoublePassMiddlewareDecorator::class, $test);
        $this->assertAttributeSame($middleware, 'middleware', $test);
    }

    public function testPipeShouldNotWrapMiddlewarePipeInstancesAsCallableMiddleware()
    {
        $nested = new MiddlewarePipe();
        $pipeline = new MiddlewarePipe();

        $pipeline->pipe($nested);

        $r = new ReflectionProperty($pipeline, 'pipeline');
        $r->setAccessible(true);
        $queue = $r->getValue($pipeline);

        $route = $queue->dequeue();
        $this->assertSame($nested, $route->handler);
    }

    public function testPipeTriggersDeprecationErrorWhenPipingDoublePassMiddlewareWithoutNextArgument()
    {
        $pipeline = new MiddlewarePipe();
        $pipeline->setResponsePrototype($this->response);

        $middleware = function ($request, $response) {
        };

        $error = (object) [];
        set_error_handler($this->createDeprecationErrorHandler($error), E_USER_DEPRECATED);
        $pipeline->pipe($middleware);
        restore_error_handler();

        $this->assertObjectHasAttribute('type', $error);
        $this->assertSame(E_USER_DEPRECATED, $error->type);
        $this->assertObjectHasAttribute('message', $error);
        $this->assertContains(DoublePassMiddlewareDecorator::class, $error->message);

        $r = new ReflectionProperty($pipeline, 'pipeline');
        $r->setAccessible(true);
        $queue = $r->getValue($pipeline);

        $route = $queue->dequeue();
        $test = $route->handler;
        $this->assertInstanceOf(DoublePassMiddlewareDecorator::class, $test);
        $this->assertAttributeSame($middleware, 'middleware', $test);
    }

    public function testPipeTriggersExceptionWhenDecoratingDoublePassMiddlewareAndNoResponsePrototypePresent()
    {
        $pipeline = new MiddlewarePipe();

        $middleware = function ($request, $response, $next) {
        };

        $error = (object) [];
        set_error_handler($this->createDeprecationErrorHandler($error), E_USER_DEPRECATED);
        try {
            $pipeline->pipe($middleware);
        } catch (MissingResponsePrototypeException $e) {
        }
        restore_error_handler();

        $this->assertObjectHasAttribute('type', $error);
        $this->assertSame(E_USER_DEPRECATED, $error->type);
        $this->assertObjectHasAttribute('message', $error);
        $this->assertContains(DoublePassMiddlewareDecorator::class, $error->message);

        $this->assertContains('Cannot wrap callable middleware', $e->getMessage());
    }
}
