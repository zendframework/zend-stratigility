<?php
namespace PhlyTest\Conduit;

use Phly\Conduit\Route;
use PHPUnit_Framework_TestCase as TestCase;

class RouteTest extends TestCase
{
    public function testPathAndHandlerAreAccessibleAfterInstantiation()
    {
        $path = '/foo';
        $handler = function () {
        };
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
     */
    public function testDoesNotAllowNonStringPaths($path)
    {
        $this->setExpectedException('InvalidArgumentException');
        $route = new Route($path, function () {
        });
    }

    public function testExceptionIsRaisedIfUndefinedPropertyIsAccessed()
    {
        $route = new Route('/foo', function () {
        });

        $this->setExpectedException('OutOfRangeException');
        $foo = $route->foo;
    }
}
