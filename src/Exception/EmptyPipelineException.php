<?php
/**
 * @see       https://github.com/zendframework/zend-stratigility for the canonical source repository
 * @copyright Copyright (c) 2018 Zend Technologies USA Inc. (https://www.zend.com)
 * @license   https://github.com/zendframework/zend-stratigility/blob/master/LICENSE.md New BSD License
 */

declare(strict_types=1);

namespace Zend\Stratigility\Exception;

use OutOfBoundsException;

use function sprintf;

/**
 * Exception thrown when a MiddlewarePipe attempts to handle() a request,
 * but no middleware are composed in the instance.
 */
class EmptyPipelineException extends OutOfBoundsException implements ExceptionInterface
{
    public static function forClass(string $className) : self
    {
        return new self(sprintf(
            '%s cannot handle request; no middleware available to process the request',
            $className
        ));
    }
}
