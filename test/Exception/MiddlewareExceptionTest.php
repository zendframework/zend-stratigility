<?php
/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @see       http://github.com/zendframework/zend-stratigility for the canonical source repository
 * @copyright Copyright (c) 2016 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-stratigility/blob/master/LICENSE.md New BSD License
 */

namespace ZendTest\Stratigility\Exception;

use PHPUnit_Framework_TestCase as TestCase;
use Zend\Stratigility\Exception\MiddlewareException;

class MiddlewareExceptionTest extends TestCase
{
    /**
     * @dataProvider provideObjectAsErrorValue
     */
    public function testCanGenerateExceptionFromValidErrorValue($value, $expected)
    {
        $e = MiddlewareException::fromErrorValue($value);
        $this->assertEquals(sprintf(
            'Middleware raised an error condition: %s',
            $expected
        ), $e->getMessage());

        $this->expectException(MiddlewareException::class);
        throw $e;
    }

    public function provideObjectAsErrorValue()
    {
        return [
            [new \stdClass(), 'stdClass'],
            [null, 'NULL'],
            [true, 'true'],
            [false, 'false'],
            [1, '1'],
            [1.2, 1.2],
            [['not', 'callable'], 'array'],
            [(object) ['not', 'callable'], 'stdClass'],
        ];
    }
}
