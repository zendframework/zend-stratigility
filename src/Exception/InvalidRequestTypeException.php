<?php
/**
 * @see       https://github.com/zendframework/zend-stratigility for the canonical source repository
 * @copyright Copyright (c) 2016 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-stratigility/blob/master/LICENSE.md New BSD License
 */

namespace Zend\Stratigility\Exception;

use RuntimeException;

/**
 * Exception thrown when Dispatch::process() is called with a non-interop
 * handler provided, and the request is not a server request type.
 */
class InvalidRequestTypeException extends RuntimeException
{
}
