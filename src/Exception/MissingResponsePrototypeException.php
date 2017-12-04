<?php
/**
 * @see       https://github.com/zendframework/zend-stratigility for the canonical source repository
 * @copyright Copyright (c) 2017 Zend Technologies USA Inc. (https://www.zend.com)
 * @license   https://github.com/zendframework/zend-stratigility/blob/master/LICENSE.md New BSD License
 */

namespace Zend\Stratigility\Exception;

use UnexpectedValueException;
use Zend\Stratigility\Middleware\DoublePassMiddlewareWrapper;

/**
 * Exception thrown by the DoublePassMiddlewareWrapper when no response
 * prototype is provided, and Diactoros is not available to create a default.
 */
class MissingResponsePrototypeException extends UnexpectedValueException
{
    public static function create() : self
    {
        return new self(sprintf(
            'Unable to create a %s instance; no response prototype provided,'
            . ' and zendframework/zend-diactoros is not installed',
            DoublePassMiddlewareWrapper::class
        ));
    }
}
