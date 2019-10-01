<?php
/**
 * @see       https://github.com/zendframework/zend-stratigility for the canonical source repository
 * @copyright Copyright (c) 2016-2019 Zend Technologies USA Inc. (https://www.zend.com)
 * @license   https://github.com/zendframework/zend-stratigility/blob/master/LICENSE.md New BSD License
 */

declare(strict_types=1);

namespace Zend\Stratigility\Middleware;

use ErrorException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Throwable;
use Zend\Stratigility\Exception\MissingResponseException;

use function error_reporting;
use function in_array;
use function restore_error_handler;
use function set_error_handler;

/**
 * @deprecated This class is being dropped in v4.0 in favor of the ErrorMiddleware.
 */
class ErrorHandler extends ErrorHandlerMiddleware
{
}
