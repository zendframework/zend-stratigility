<?php
/**
 * @see       https://github.com/zendframework/zend-stratigility for the canonical source repository
 * @copyright Copyright (c) 2015-2017 Zend Technologies USA Inc. (https://www.zend.com)
 * @license   https://github.com/zendframework/zend-stratigility/blob/master/LICENSE.md New BSD License
 */

namespace Zend\Stratigility\Exception;

use Interop\Http\Server\MiddlewareInterface;
use InvalidArgumentException;

class InvalidMiddlewareException extends InvalidArgumentException
{
    /**
     * Create and return an InvalidArgumentException detailing the invalid middleware type.
     *
     * @param mixed $value
     * @return InvalidArgumentException
     */
    public static function fromValue($value)
    {
        $received = is_object($value)
            ? get_class($value)
            : gettype($value);

        return new self(
            sprintf(
                'Middleware must implement %s; received middleware of type %s',
                MiddlewareInterface::class,
                $received
            )
        );
    }
}
