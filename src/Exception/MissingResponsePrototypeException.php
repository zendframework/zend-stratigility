<?php
/**
 * @see       https://github.com/zendframework/zend-stratigility for the canonical source repository
 * @copyright Copyright (c) 2016 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-stratigility/blob/master/LICENSE.md New BSD License
 */

namespace Zend\Stratigility\Exception;

use RuntimeException;

/**
 * Exception thrown when the Dispatch::process is called and needs to execute
 * a non-interop middleware, but no response prototype was provided to the
 * instance.
 */
class MissingResponsePrototypeException extends RuntimeException
{
}
