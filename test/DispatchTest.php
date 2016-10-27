<?php
/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @see       http://github.com/zendframework/zend-stratigility for the canonical source repository
 * @copyright Copyright (c) 2015-2016 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-stratigility/blob/master/LICENSE.md New BSD License
 */

namespace ZendTest\Stratigility;

use Interop\Http\Middleware\DelegateInterface;
use Interop\Http\Middleware\MiddlewareInterface;
use Interop\Http\Middleware\ServerMiddlewareInterface;
use PHPUnit_Framework_Assert as Assert;
use PHPUnit_Framework_TestCase as TestCase;
use Prophecy\Argument;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;
use stdClass;
use TypeError;
use Zend\Stratigility\Dispatch;
use Zend\Stratigility\Exception;
use Zend\Stratigility\Http;
use Zend\Stratigility\Next;
use Zend\Stratigility\Route;

class DispatchTest extends TestCase
{
    private $request;

    private $response;

    public function setUp()
    {
        $this->request = $this->prophesize(ServerRequestInterface::class);
        $this->response = $this->prophesize(ResponseInterface::class);
    }

    public function interopMiddlewareProvider()
    {
        return [
            MiddlewareInterface::class => [MiddlewareInterface::class],
            ServerMiddlewareInterface::class => [ServerMiddlewareInterface::class],
        ];
    }

    public function testHasErrorAndHandleArityIsFourTriggersHandler()
    {
        $triggered = false;

        $handler = function ($err, $req, $res, $next) use (&$triggered) {
            $triggered = $err;
        };
        $next = function ($req, $res, $err) {
            Assert::fail('Next was called; it should not have been');
        };

        $route = new Route('/foo', $handler);
        $dispatch = new Dispatch();
        $err = (object) ['error' => true];
        $dispatch($route, $err, $this->request->reveal(), $this->response->reveal(), $next);
        $this->assertSame($err, $triggered);
    }

    public function testHasErrorAndHandleArityLessThanFourTriggersNextWithError()
    {
        $triggered = false;

        $handler = function ($req, $res, $next) {
            Assert::fail('Handler was called; it should not have been');
        };
        $next = function ($req, $res, $err) use (&$triggered) {
            $triggered = $err;
        };

        $route = new Route('/foo', $handler);
        $dispatch = new Dispatch();
        $err = (object) ['error' => true];
        $dispatch($route, $err, $this->request->reveal(), $this->response->reveal(), $next);
        $this->assertSame($err, $triggered);
    }

    public function testNoErrorAndHandleArityGreaterThanThreeTriggersNext()
    {
        $triggered = false;

        $handler = function ($err, $req, $res, $next) {
            Assert::fail('Handler was called; it should not have been');
        };
        $next = function ($req, $res, $err) use (&$triggered) {
            $triggered = $err;
        };

        $route = new Route('/foo', $handler);
        $dispatch = new Dispatch();
        $err = null;
        $dispatch($route, $err, $this->request->reveal(), $this->response->reveal(), $next);
        $this->assertSame($err, $triggered);
    }

    public function testNoErrorAndHandleArityLessThanFourTriggersHandler()
    {
        $triggered = false;

        $handler = function ($req, $res, $next) use (&$triggered) {
            $triggered = $req;
        };
        $next = function ($req, $res, $err) {
            Assert::fail('Next was called; it should not have been');
        };

        $route = new Route('/foo', $handler);
        $dispatch = new Dispatch();
        $err = null;
        $dispatch($route, $err, $this->request->reveal(), $this->response->reveal(), $next);
        $this->assertSame($this->request->reveal(), $triggered);
    }

    public function testThrowingExceptionInErrorHandlerTriggersNextWithException()
    {
        $exception = new RuntimeException;
        $triggered = null;

        $handler = function ($err, $req, $res, $next) use ($exception) {
            throw $exception;
        };
        $next = function ($req, $res, $err) use (&$triggered) {
            $triggered = $err;
        };

        $route = new Route('/foo', $handler);
        $dispatch = new Dispatch();
        $err = (object) ['error' => true];
        $dispatch($route, $err, $this->request->reveal(), $this->response->reveal(), $next);
        $this->assertSame($exception, $triggered);
    }

    public function testThrowingExceptionInNonErrorHandlerTriggersNextWithException()
    {
        $exception = new RuntimeException;
        $triggered = null;

        $handler = function ($req, $res, $next) use ($exception) {
            throw $exception;
        };
        $next = function ($req, $res, $err) use (&$triggered) {
            $triggered = $err;
        };

        $route = new Route('/foo', $handler);
        $dispatch = new Dispatch();
        $err = null;
        $dispatch($route, $err, $this->request->reveal(), $this->response->reveal(), $next);
        $this->assertSame($exception, $triggered);
    }

    public function testReturnsValueFromNonErrorHandler()
    {
        $handler = function ($req, $res, $next) {
            return $res;
        };
        $next = function ($req, $res, $err) {
            Assert::fail('Next was called; it should not have been');
        };

        $route = new Route('/foo', $handler);
        $dispatch = new Dispatch();
        $err = null;
        $result = $dispatch($route, $err, $this->request->reveal(), $this->response->reveal(), $next);
        $this->assertSame($this->response->reveal(), $result);
    }

    public function testIfErrorHandlerReturnsResponseDispatchReturnsTheResponse()
    {
        $handler = function ($err, $req, $res, $next) {
            return $res;
        };
        $next = function ($req, $res, $err) {
            Assert::fail('Next was called; it should not have been');
        };

        $route = new Route('/foo', $handler);
        $dispatch = new Dispatch();
        $err = (object) ['error' => true];
        $result = $dispatch($route, $err, $this->request->reveal(), $this->response->reveal(), $next);
        $this->assertSame($this->response->reveal(), $result);
    }

    /**
     * @group 28
     */
    public function testShouldAllowDispatchingPsr7Instances()
    {
        $handler = function ($req, $res, $next) {
            return $res;
        };
        $next = function ($req, $res, $err) {
            Assert::fail('Next was called; it should not have been');
        };

        $request  = $this->prophesize(ServerRequestInterface::class);
        $response = $this->prophesize(ResponseInterface::class);
        $dispatch = new Dispatch();
        $route    = new Route('/foo', $handler);
        $err      = null;
        $result = $dispatch($route, $err, $request->reveal(), $response->reveal(), $next);
        $this->assertSame($response->reveal(), $result);
    }

    /**
     * @requires PHP 7.0
     * @group 37
     */
    public function testWillCatchPhp7Throwable()
    {
        $callableWithHint = function (stdClass $parameter) {
            // will not be executed
        };

        $middleware = function ($req, $res, $next) use ($callableWithHint) {
            $callableWithHint('not an stdClass');
        };

        // Using PHPUnit mock here to allow asserting that the method is called.
        // Prophecy doesn't allow defining arbitrary methods on mocks it generates.
        $errorHandler = $this->getMockBuilder('stdClass')
            ->setMethods(['__invoke'])
            ->getMock();
        $errorHandler
            ->expects(self::once())
            ->method('__invoke')
            ->with(
                $this->request->reveal(),
                $this->response->reveal(),
                self::callback(function (TypeError $throwable) {
                    self::assertStringStartsWith(
                        'Argument 1 passed to ZendTest\Stratigility\DispatchTest::ZendTest\Stratigility\{closure}()'
                        . ' must be an instance of stdClass, string given',
                        $throwable->getMessage()
                    );

                    return true;
                })
            );

        $dispatch = new Dispatch();

        $dispatch(
            new Route('/foo', $middleware),
            null,
            $this->request->reveal(),
            $this->response->reveal(),
            $errorHandler
        );
    }

    /**
     * @group http-interop
     */
    public function testResponsePrototypeIsAbsentByDefault()
    {
        $dispatch = new Dispatch();
        $this->assertAttributeEmpty('responsePrototype', $dispatch);
    }

    /**
     * @group http-interop
     */
    public function testCanInjectAResponsePrototype()
    {
        $dispatch = new Dispatch();
        $dispatch->setResponsePrototype($this->response->reveal());
        $this->assertAttributeSame($this->response->reveal(), 'responsePrototype', $dispatch);
    }

    /**
     * @group http-interop
     */
    public function testProcessRaisesExceptionForNonInteropHandlersWhenNoResponsePrototypeIsPresent()
    {
        $handler = function ($req, $res, $next) use (&$triggered) {
            Assert::fail('Handler was called; it should not have been');
        };
        $next = function ($req, $res, $err) {
            Assert::fail('Next was called; it should not have been');
        };
        $route = new Route('/foo', $handler);
        $dispatch = new Dispatch();

        $this->setExpectedException(Exception\MissingResponsePrototypeException::class);
        $dispatch->process($route, $this->request->reveal(), $next);
    }

    /**
     * @group http-interop
     */
    public function testProcessRaisesExceptionForNonInteropHandlersWhenNotProvidedAServerRequest()
    {
        $request = $this->prophesize(RequestInterface::class)->reveal();
        $handler = function ($req, $res, $next) use (&$triggered) {
            Assert::fail('Handler was called; it should not have been');
        };
        $next = function ($req, $res, $err) {
            Assert::fail('Next was called; it should not have been');
        };
        $route = new Route('/foo', $handler);
        $dispatch = new Dispatch();
        $dispatch->setResponsePrototype($this->response->reveal());

        $this->setExpectedException(Exception\InvalidRequestTypeException::class);
        $dispatch->process($route, $request, $next);
    }

    /**
     * @group http-interop
     */
    public function testProcessUsesResponsePrototypeForNonInteropHandlerWhenPresent()
    {
        $handler = function ($req, $res, $next) {
            return $res;
        };
        $next = function ($req, $res, $err) {
            Assert::fail('Next was called; it should not have been');
        };
        $route = new Route('/foo', $handler);
        $dispatch = new Dispatch();
        $dispatch->setResponsePrototype($this->response->reveal());

        $this->assertSame($this->response->reveal(), $dispatch->process($route, $this->request->reveal(), $next));
    }

    /**
     * @group http-interop
     * @dataProvider interopMiddlewareProvider
     */
    public function testProcessRaisesExceptionWhenCatchingAnExceptionAndNoResponsePrototypePresent($middlewareType)
    {
        $next = $this->prophesize(Next::class);
        $next->willImplement(DelegateInterface::class);

        $request = $this->prophesize(ServerRequestInterface::class)->reveal();

        $exception = new RuntimeException();

        $middleware = $this->prophesize($middlewareType);
        $middleware
            ->process($request, Argument::that([$next, 'reveal']))
            ->willThrow($exception);

        $route = new Route('/foo', $middleware->reveal());

        $dispatch = new Dispatch();

        try {
            $dispatch->process($route, $request, $next->reveal());
            $this->fail('Dispatch::process succeeded when it should have raised an exception');
        } catch (Exception\MissingResponsePrototypeException $e) {
            $this->assertSame($exception, $e->getPrevious(), $e);
        } catch (\Throwable $e) {
            $this->fail(sprintf(
                'Expected MissingResponsePrototypeException; received %s',
                get_class($e)
            ));
        } catch (\Exception $e) {
            $this->fail(sprintf(
                'Expected MissingResponsePrototypeException; received %s',
                get_class($e)
            ));
        }
    }

    /**
     * @group http-interop
     */
    public function testProcessRaisesExceptionWhenCatchingAnExceptionAndRequestIsNotAServerSideRequest()
    {
        $next = $this->prophesize(Next::class);
        $next->willImplement(DelegateInterface::class);

        $request = $this->prophesize(RequestInterface::class)->reveal();

        $exception = new RuntimeException();

        $middleware = $this->prophesize(MiddlewareInterface::class);
        $middleware
            ->process($request, Argument::that([$next, 'reveal']))
            ->willThrow($exception);

        $route = new Route('/foo', $middleware->reveal());

        $dispatch = new Dispatch();
        $dispatch->setResponsePrototype($this->response->reveal());

        try {
            $dispatch->process($route, $request, $next->reveal());
            $this->fail('Dispatch::process succeeded when it should have raised an exception');
        } catch (Exception\InvalidRequestTypeException $e) {
            $this->assertSame($exception, $e->getPrevious(), $e);
        } catch (\Throwable $e) {
            $this->fail(sprintf(
                'Expected MissingResponsePrototypeException; received %s',
                get_class($e)
            ));
        } catch (\Exception $e) {
            $this->fail(sprintf(
                'Expected MissingResponsePrototypeException; received %s',
                get_class($e)
            ));
        }
    }

    /**
     * @group http-interop
     * @dataProvider interopMiddlewareProvider
     */
    public function testCallingProcessWithInteropMiddlewareDispatchesIt($middlewareType)
    {
        $next = $this->prophesize(Next::class);
        $next->willImplement(DelegateInterface::class);

        $request = $this->prophesize(ServerRequestInterface::class)->reveal();
        $response = $this->prophesize(ResponseInterface::class);

        $middleware = $this->prophesize($middlewareType);
        $middleware
            ->process($request, Argument::that([$next, 'reveal']))
            ->will([$response, 'reveal']);

        $route = new Route('/foo', $middleware->reveal());

        $dispatch = new Dispatch();

        $this->assertSame(
            $response->reveal(),
            $dispatch->process($route, $request, $next->reveal())
        );
    }

    /**
     * @group http-interop
     */
    public function testCallingProcessWithCallableMiddlewareDispatchesIt()
    {
        $next = $this->prophesize(Next::class);

        $request = $this->prophesize(ServerRequestInterface::class)->reveal();
        $response = $this->prophesize(ResponseInterface::class)->reveal();

        $middleware = function ($req, $res, $next) use ($response) {
            return $response;
        };

        $route = new Route('/foo', $middleware);

        $dispatch = new Dispatch();
        $dispatch->setResponsePrototype($this->response->reveal());

        $this->assertSame(
            $response,
            $dispatch->process($route, $request, $next->reveal())
        );
    }

    /**
     * @group http-interop
     * @dataProvider interopMiddlewareProvider
     */
    public function testInvokingWithInteropMiddlewareDispatchesIt($middlewareType)
    {
        $next = $this->prophesize(Next::class);
        $next->willImplement(DelegateInterface::class);

        $request = $this->prophesize(ServerRequestInterface::class)->reveal();
        $response = $this->prophesize(ResponseInterface::class)->reveal();

        $middleware = $this->prophesize($middlewareType);
        $middleware
            ->process($request, Argument::that([$next, 'reveal']))
            ->willReturn($response);

        $route = new Route('/foo', $middleware->reveal());

        $dispatch = new Dispatch();

        $this->assertSame(
            $response,
            $dispatch($route, null, $request, $this->response->reveal(), $next->reveal())
        );
    }

    /**
     * @group http-interop
     */
    public function testInvokingMemoizesResponseIfNonePreviouslyPresent()
    {
        $next = $this->prophesize(Next::class);

        $request = $this->prophesize(ServerRequestInterface::class)->reveal();
        $response = $this->prophesize(ResponseInterface::class)->reveal();

        $middleware = function ($req, $res, $next) use ($response) {
            return $response;
        };

        $route = new Route('/foo', $middleware);

        $dispatch = new Dispatch();

        $this->assertSame(
            $response,
            $dispatch($route, null, $request, $this->response->reveal(), $next->reveal())
        );

        $this->assertAttributeSame($this->response->reveal(), 'responsePrototype', $dispatch);
    }
}
