<?php
/**
 * @see       https://github.com/zendframework/zend-stratigility for the canonical source repository
 * @copyright Copyright (c) 2018 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-stratigility/blob/master/LICENSE.md New BSD License
 */

namespace ZendTest\Stratigility\Middleware\TestAsset;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Webimpress\HttpMiddlewareCompatibility\HandlerInterface as RequestHandlerInterface;
use Webimpress\HttpMiddlewareCompatibility\MiddlewareInterface;

class DecoratorMiddleware implements MiddlewareInterface
{
    /**
     * @var MiddlewareInterface
     */
    private $middleware;

    public function __construct(MiddlewareInterface $middleware)
    {
        $this->middleware = $middleware;
    }

    /**
     * @return ResponseInterface
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler)
    {
        return $this->middleware->process($request, $handler);
    }
}
