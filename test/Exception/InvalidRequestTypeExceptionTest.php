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
use Zend\Stratigility\Exception\InvalidRequestTypeException;

class InvalidRequestTypeExceptionTest extends TestCase
{
    public function testInvalidRequestTypeException()
    {
        $e = new InvalidRequestTypeException('Exception Message');

        $this->assertInstanceOf(InvalidRequestTypeException::class, $e);

        $this->expectException(InvalidRequestTypeException::class);
        $this->expectExceptionMessage('Exception Message');

        throw $e;
    }
}
