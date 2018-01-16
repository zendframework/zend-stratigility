<?php
/**
 * @see       https://github.com/zendframework/zend-stratigility for the canonical source repository
 * @copyright Copyright (c) 2016-2018 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-stratigility/blob/master/LICENSE.md New BSD License
 */

namespace Zend\Stratigility\Middleware;

use Psr\Http\Message\ResponseInterface;

/**
 * @deprecated since 2.2.0; to be removed in 3.0.0.
 */
class CallableMiddlewareWrapperFactory
{
    /**
     * @var ResponseInterface
     */
    private $responsePrototype;

    /**
     * @param ResponseInterface $prototype
     */
    public function __construct(ResponseInterface $prototype)
    {
        $this->responsePrototype = $prototype;
    }

    /**
     * @param callable $middleware
     * @return CallableMiddlewareWrapper
     */
    public function decorateCallableMiddleware(callable $middleware)
    {
        return new CallableMiddlewareWrapper($middleware, $this->responsePrototype);
    }
}
