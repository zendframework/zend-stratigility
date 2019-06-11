<?php
/**
 * @see       https://github.com/zendframework/zend-stratigility for the canonical source repository
 * @copyright Copyright (c) 2017 Zend Technologies USA Inc. (https://www.zend.com)
 * @license   https://github.com/zendframework/zend-stratigility/blob/master/LICENSE.md New BSD License
 */

declare(strict_types=1);

namespace Zend\Stratigility\Exception;

use DomainException;

class MiddlewarePipeNextHandlerAlreadyCalledException extends DomainException implements ExceptionInterface
{

    public static function create(): self
    {
        return new self("Cannot invoke Next handler more than once");
    }
}
