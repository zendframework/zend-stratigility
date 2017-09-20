<?php
/**
 * @see       https://github.com/zendframework/zend-stratigility for the canonical source repository
 * @copyright Copyright (c) 2016-2017 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-stratigility/blob/master/LICENSE.md New BSD License
 */

namespace Zend\Stratigility\Handler;

use Interop\Http\Server\RequestHandlerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Decorate callable delegates as http-interop delegates in order to process
 * incoming requests.
 */
class CallableHandlerDecorator implements RequestHandlerInterface
{
    /**
     * @var callable
     */
    private $delegate;

    /**
     * @var ResponseInterface
     */
    private $response;

    /**
     * @param callable $delegate
     * @param ResponseInterface $response
     */
    public function __construct(callable $delegate, ResponseInterface $response)
    {
        $this->delegate = $delegate;
        $this->response = $response;
    }

    /**
     * Proxies to the underlying callable delegate to process a request.
     *
     * {@inheritDoc}
     */
    public function handle(ServerRequestInterface $request)
    {
        $delegate = $this->delegate;

        return $delegate($request, $this->response);
    }
}
