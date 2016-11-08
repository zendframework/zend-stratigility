<?php
/**
 * @link      https://github.com/zendframework/zend-stratigility for the canonical source repository
 * @copyright Copyright (c) 2015 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://framework.zend.com/license New BSD License
 */

namespace ZendTest\Stratigility\Exception;

use PHPUnit_Framework_TestCase as TestCase;
use Zend\Stratigility\Exception\InvalidMiddlewareException;

class InvalidMiddlewareExceptionTest extends TestCase
{
    public function invalidMiddlewareValues()
    {
        return [
            'null'         => [null, 'NULL'],
            'true'         => [true, 'boolean'],
            'false'        => [false, 'boolean'],
            'empty-string' => ['', 'string'],
            'string'       => ['not-callable', 'string'],
            'int'          => [1, 'integer'],
            'float'        => [1.1, 'double'],
            'array'        => [['not', 'callable'], 'array'],
            'object'       => [(object) ['not', 'callable'], 'stdClass'],
        ];
    }

    /**
     * @dataProvider invalidMiddlewareValues
     *
     * @param mixed $value
     * @param string $expected
     */
    public function testFromValueProvidesNewExceptionWithMessageRelatedToValue($value, $expected)
    {
        $e = InvalidMiddlewareException::fromValue($value);
        $this->assertEquals(sprintf(
            'Middleware must be callable, %s found',
            $expected
        ), $e->getMessage());
    }
}
