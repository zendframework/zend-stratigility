<?php
/**
 * @see       https://github.com/zendframework/zend-stratigility for the canonical source repository
 * @copyright Copyright (c) 2015-2018 Zend Technologies USA Inc. (https://www.zend.com)
 * @license   https://github.com/zendframework/zend-stratigility/blob/master/LICENSE.md New BSD License
 */

declare(strict_types=1);

namespace ZendTest\Stratigility;

use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use ReflectionClass;
use ReflectionMethod;
use ReflectionObject;
use Zend\Diactoros\Response;
use Zend\Diactoros\ServerRequest as Request;
use Zend\Stratigility\Exception;
use Zend\Stratigility\MiddlewarePipe;
use Zend\Stratigility\MiddlewarePipeInterface;

use function get_class;
use function sort;
use function spl_object_hash;
use function strpos;
use function var_export;

class MiddlewarePipeTest extends TestCase
{
    use MiddlewareTrait;

    /**
     * @var Request
     */
    private $request;

    /**
     * @var MiddlewarePipe
     */
    private $pipeline;

    protected function setUp()
    {
        $this->request  = new Request([], [], 'http://example.com/', 'GET', 'php://memory');
        $this->pipeline = new MiddlewarePipe();
    }

    private function createFinalHandler() : RequestHandlerInterface
    {
        $handler = $this->prophesize(RequestHandlerInterface::class);
        $handler->handle(Argument::any())->willReturn(new Response());

        return $handler->reveal();
    }

    /**
     * @group http-interop
     */
    public function testCanPipeInteropMiddleware()
    {
        $handler = $this->prophesize(RequestHandlerInterface::class)->reveal();

        $response = $this->prophesize(ResponseInterface::class)->reveal();
        $middleware = $this->prophesize(MiddlewareInterface::class);
        $middleware
            ->process(
                Argument::type(ServerRequestInterface::class),
                Argument::type(RequestHandlerInterface::class)
            )
            ->willReturn($response);

        $pipeline = new MiddlewarePipe();
        $pipeline->pipe($middleware->reveal());

        $this->assertSame($response, $pipeline->process($this->request, $handler));
    }

    public function testProcessInvokesUntilFirstHandlerThatDoesNotCallNext()
    {
        $this->pipeline->pipe(new class () implements MiddlewareInterface
        {
            public function process(ServerRequestInterface $req, RequestHandlerInterface $handler) : ResponseInterface
            {
                $res = $handler->handle($req);
                $res->getBody()->write("First\n");

                return $res;
            }
        });
        $this->pipeline->pipe(new class () implements MiddlewareInterface
        {
            public function process(ServerRequestInterface $req, RequestHandlerInterface $handler) : ResponseInterface
            {
                $res = $handler->handle($req);
                $res->getBody()->write("Second\n");

                return $res;
            }
        });

        $response = new Response();
        $response->getBody()->write("Third\n");
        $this->pipeline->pipe($this->getMiddlewareWhichReturnsResponse($response));

        $this->pipeline->pipe($this->getNotCalledMiddleware());

        $request = new Request([], [], 'http://local.example.com/foo', 'GET', 'php://memory');
        $response = $this->pipeline->process($request, $this->createFinalHandler());
        $body = (string) $response->getBody();
        $this->assertContains('First', $body);
        $this->assertContains('Second', $body);
        $this->assertContains('Third', $body);
    }

    public function testInvokesHandlerWhenQueueIsExhausted()
    {
        $expected = $this->prophesize(ResponseInterface::class)->reveal();

        $this->pipeline->pipe($this->getPassToHandlerMiddleware());
        $this->pipeline->pipe($this->getPassToHandlerMiddleware());
        $this->pipeline->pipe($this->getPassToHandlerMiddleware());

        $request = new Request([], [], 'http://local.example.com/foo', 'GET', 'php://memory');

        $handler = $this->prophesize(RequestHandlerInterface::class);
        $handler->handle($request)->willReturn($expected);

        $result = $this->pipeline->process($request, $handler->reveal());

        $this->assertSame($expected, $result);
    }

    public function testReturnsResponseReturnedByQueue()
    {
        $return = new Response();

        $this->pipeline->pipe($this->getPassToHandlerMiddleware());
        $this->pipeline->pipe($this->getPassToHandlerMiddleware());
        $this->pipeline->pipe($this->getMiddlewareWhichReturnsResponse($return));

        $this->pipeline->pipe($this->getNotCalledMiddleware());

        $request = new Request([], [], 'http://local.example.com/foo', 'GET', 'php://memory');
        $result  = $this->pipeline->process($request, $this->createFinalHandler());
        $this->assertSame($return, $result, var_export([
            spl_object_hash($return) => get_class($return),
            spl_object_hash($result) => get_class($result),
        ], true));
    }

    public function testHandleRaisesExceptionIfQueueIsEmpty()
    {
        $request = $this->prophesize(ServerRequestInterface::class)->reveal();

        $this->expectException(Exception\EmptyPipelineException::class);

        $this->pipeline->handle($request);
    }

    public function testHandleProcessesEnqueuedMiddleware()
    {
        $response = $this->prophesize(ResponseInterface::class)->reveal();
        $middleware1 = $this->prophesize(MiddlewareInterface::class);
        $middleware1
            ->process(
                $this->request,
                Argument::type(RequestHandlerInterface::class)
            )
            ->will(function ($args) {
                $request = $args[0];
                $handler = $args[1];
                return $handler->handle($request);
            });
        $middleware2 = $this->prophesize(MiddlewareInterface::class);
        $middleware2
            ->process(
                $this->request,
                Argument::type(RequestHandlerInterface::class)
            )
            ->willReturn($response);

        $pipeline = new MiddlewarePipe();
        $pipeline->pipe($middleware1->reveal());
        $pipeline->pipe($middleware2->reveal());

        $this->assertSame($response, $pipeline->handle($this->request));
    }

    public function testMiddlewarePipeOnlyImplementsMiddlewarePipeInterfaceApi()
    {
        $pipeline = new MiddlewarePipe();

        $r = new ReflectionObject($pipeline);
        $methods = $r->getMethods(ReflectionMethod::IS_PUBLIC);
        $actual = [];
        foreach ($methods as $method) {
            if (strpos($method->getName(), '__') !== 0) {
                $actual[] = $method->getName();
            }
        }
        sort($actual);

        $interfaceReflection = new ReflectionClass(MiddlewarePipeInterface::class);
        $interfaceMethods = $interfaceReflection->getMethods(ReflectionMethod::IS_PUBLIC);
        $expected = [];
        foreach ($interfaceMethods as $method) {
            $expected[] = $method->getName();
        }
        sort($expected);

        self::assertTrue($r->isFinal());
        self::assertEquals($expected, $actual);
        self::assertInstanceOf(MiddlewarePipeInterface::class, $pipeline);
    }
}
