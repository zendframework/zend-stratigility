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
use Zend\Stratigility\Exception\MissingDelegateException;

class MissingDelegateExceptionTest extends TestCase
{
    public function testInvalidRequestTypeException()
    {
        $e = new MissingDelegateException('Exception Message');

        $this->assertInstanceOf(MissingDelegateException::class, $e);

        $this->expectException(MissingDelegateException::class);
        $this->expectExceptionMessage('Exception Message');

        throw $e;
    }
}
