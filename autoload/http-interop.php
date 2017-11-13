<?php
/**
 * @see       https://github.com/zendframework/zend-stratigility for the canonical source repository
 * @copyright Copyright (c) 2017 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-stratigility/blob/master/LICENSE.md New BSD License
 */

use const Webimpress\HttpMiddlewareCompatibility\HAS_RETURN_TYPE;

$classes = [
    \Zend\Stratigility\Delegate\CallableDelegateDecorator::class,
    \Zend\Stratigility\Middleware\CallableInteropMiddlewareWrapper::class,
    \Zend\Stratigility\Middleware\CallableMiddlewareWrapper::class,
    \Zend\Stratigility\Middleware\ErrorHandler::class,
    \Zend\Stratigility\Middleware\NotFoundHandler::class,
    \Zend\Stratigility\MiddlewarePipe::class,
    \Zend\Stratigility\Next::class,
];

$suffix = HAS_RETURN_TYPE ? 'WithReturnType' : 'WithoutReturnType';

foreach ($classes as $class) {
    class_alias($class . $suffix, $class);
}
