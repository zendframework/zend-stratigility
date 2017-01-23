<?php
/**
 * @link      https://github.com/zendframework/zend-stratigility for the canonical source repository
 * @copyright Copyright (c) 2015-2017 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://framework.zend.com/license New BSD License
 */

namespace ZendTest\Stratigility;

use Interop\Http\ServerMiddleware\MiddlewareInterface as ServerMiddlewareInterface;
use InvalidArgumentException;
use OutOfRangeException;
use PHPUnit\Framework\TestCase;
use Zend\Stratigility\Route;

class RouteTest extends TestCase
{
    public function createEmptyMiddleware()
    {
        return $this->prophesize(ServerMiddlewareInterface::class)->reveal();
    }

    public function testPathAndHandlerAreAccessibleAfterInstantiation()
    {
        $path = '/foo';
        $handler = $this->createEmptyMiddleware();

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
        $route = new Route($path, $this->createEmptyMiddleware());
    }

    public function testExceptionIsRaisedIfUndefinedPropertyIsAccessed()
    {
        $route = new Route('/foo', $this->createEmptyMiddleware());

        $this->expectException(OutOfRangeException::class);
        $foo = $route->foo;
    }
}
