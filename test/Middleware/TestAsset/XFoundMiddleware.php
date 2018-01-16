<?php
/**
 * @see       https://github.com/zendframework/zend-stratigility for the canonical source repository
 * @copyright Copyright (c) 2018 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-stratigility/blob/master/LICENSE.md New BSD License
 */

namespace ZendTest\Stratigility\Middleware\TestAsset;

use Psr\Http\Message\ServerRequestInterface;
use Webimpress\HttpMiddlewareCompatibility\HandlerInterface as RequestHandlerInterface;
use Webimpress\HttpMiddlewareCompatibility\MiddlewareInterface;
use Zend\Diactoros\Response;

class XFoundMiddleware implements MiddlewareInterface
{
    /**
     * @return Response
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler)
    {
        return (new Response())->withHeader('X-Found', 'true');
    }
}
