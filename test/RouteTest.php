<?php
/**
 * @see       https://github.com/zendframework/zend-stratigility for the canonical source repository
 * @copyright Copyright (c) 2015-2017 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-stratigility/blob/master/LICENSE.md New BSD License
 */

namespace ZendTest\Stratigility;

use Webimpress\HttpMiddlewareCompatibility\MiddlewareInterface as ServerMiddlewareInterface;
use InvalidArgumentException;
use OutOfRangeException;
use PHPUnit\Framework\TestCase;
use Zend\Stratigility\Middleware\PathMiddlewareDecorator;
use Zend\Stratigility\Route;

class RouteTest extends TestCase
{
    public function createEmptyMiddleware($path = '/')
    {
        return new PathMiddlewareDecorator($path, $this->prophesize(ServerMiddlewareInterface::class)->reveal());
    }

    public function testPathAndHandlerAreAccessibleAfterInstantiation()
    {
        $path = '/foo';
        $handler = $this->createEmptyMiddleware($path);

        $route = new Route($path, $handler);
        $this->assertSame($path, $route->path);
        $this->assertSame($handler, $route->handler);
    }

    public function nonStringPaths()
    {
        return [
            'null' => [null],
            'int' => [1],
            'float' => [1.1],
            'bool' => [true],
            'array' => [[]],
            'object' => [(object) []],
        ];
    }

    /**
     * @dataProvider nonStringPaths
     *
     * @param mixed $path
     */
    public function testDoesNotAllowNonStringPaths($path)
    {
        $this->expectException(InvalidArgumentException::class);
        new Route($path, $this->createEmptyMiddleware($path));
    }

    public function testExceptionIsRaisedIfUndefinedPropertyIsAccessed()
    {
        $route = new Route('/foo', $this->createEmptyMiddleware('/foo'));

        $this->expectException(OutOfRangeException::class);
        $route->foo;
    }

    public function testConstructorTriggersDeprecationErrorWhenNonEmptyPathProvidedWithoutPathMiddleware()
    {
        $error = false;
        set_error_handler(function ($errno, $errmessage) use (&$error) {
            $error = (object) [
                'type'    => $errno,
                'message' => $errmessage,
            ];
        }, E_USER_DEPRECATED);
        new Route('/foo', $this->prophesize(ServerMiddlewareInterface::class)->reveal());
        restore_error_handler();

        $this->assertNotSame(false, $error);
        $this->assertSame(E_USER_DEPRECATED, $error->type);
        $this->assertContains(PathMiddlewareDecorator::class, $error->message);
    }
}
