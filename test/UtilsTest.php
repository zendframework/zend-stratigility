<?php
namespace PhlyTest\Conduit;

use Phly\Conduit\Dispatch;
use Phly\Conduit\Middleware;
use Phly\Conduit\Utils;
use PHPUnit_Framework_TestCase as TestCase;

class UtilsTest extends TestCase
{
    public function callablesWithVaryingArity()
    {
        return [
            'function' => ['strlen', 1],
            'closure' => [function ($x, $y) {
            }, 2],
            'invokable' => [new Dispatch(), 5],
            'handler' => [new Middleware(), 2], // 2 REQUIRED arguments!
        ];
    }

    /**
     * @dataProvider callablesWithVaryingArity
     */
    public function testArity($callable, $expected)
    {
        $this->assertEquals($expected, Utils::getArity($callable));
    }
}
