<?php
/**
 * @see       https://github.com/zendframework/zend-stratigility for the canonical source repository
 * @copyright Copyright (c) 2017 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-stratigility/blob/master/LICENSE.md New BSD License
 */

namespace Zend\Stratigility;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Webimpress\HttpMiddlewareCompatibility\HandlerInterface as DelegateInterface;

class NextWithReturnType implements DelegateInterface
{
    use NextTrait {
        handle as handleTrait;
    }

    public function handle(ServerRequestInterface $request) : ResponseInterface
    {
        return $this->handleTrait($request);
    }
}
