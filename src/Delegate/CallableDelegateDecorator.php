<?php
/**
 * @see       https://github.com/zendframework/zend-stratigility for the canonical source repository
 * @copyright Copyright (c) 2016-2017 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-stratigility/blob/master/LICENSE.md New BSD License
 */

namespace Zend\Stratigility\Delegate;

use Interop\Http\Server\RequestHandlerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Decorate callable delegates as http-interop delegates in order to process
 * incoming requests.
 */
class CallableDelegateDecorator implements RequestHandlerInterface
{
    /**
     * @var callable
     */
    private $handler;

    /**
     * @var ResponseInterface
     */
    private $response;

    /**
     * @param callable $handler
     * @param ResponseInterface $response
     */
    public function __construct(callable $handler, ResponseInterface $response)
    {
        $this->handler = $handler;
        $this->response = $response;
    }

    /**
     * Proxies to the underlying callable delegate to process a request.
     *
     * {@inheritDoc}
     */
    public function handle(ServerRequestInterface $request) : ResponseInterface
    {
        $handler = $this->handler;
        return $handler($request, $this->response);
    }
}
