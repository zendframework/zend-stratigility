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
use Webimpress\HttpMiddlewareCompatibility\MiddlewareInterface as ServerMiddlewareInterface;

/**
 * @internal
 */
class MiddlewarePipeWithoutReturnType implements ServerMiddlewareInterface
{
    use MiddlewarePipeTrait {
        process as processTrait;
    }

    /**
     * http-interop invocation: single-pass with delegate.
     *
     * Executes the internal pipeline, passing $delegate as the "final
     * handler" in cases when the pipeline exhausts itself.
     *
     * @param ServerRequestInterface $request
     * @param DelegateInterface $delegate
     * @return ResponseInterface
     */
    public function process(ServerRequestInterface $request, DelegateInterface $delegate)
    {
        return $this->processTrait($request, $delegate);
    }
}
