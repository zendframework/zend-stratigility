<?php
/**
 * @see       https://github.com/zendframework/zend-stratigility for the canonical source repository
 * @copyright Copyright (c) 2015 Zend Technologies USA Inc. (https://www.zend.com)
 * @license   https://github.com/zendframework/zend-stratigility/blob/master/LICENSE.md New BSD License
 */
declare(strict_types=1);

namespace ZendTest\Stratigility\Exception;

use Interop\Http\Server\MiddlewareInterface;
use PHPUnit\Framework\TestCase;
use stdClass;
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
            'object'       => [(object) ['not', 'callable'], stdClass::class],
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
            'Middleware must implement %s; received middleware of type %s',
            MiddlewareInterface::class,
            $expected
        ), $e->getMessage());
    }
}
