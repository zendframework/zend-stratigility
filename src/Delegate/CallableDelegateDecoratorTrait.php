<?php
/**
 * @see       https://github.com/zendframework/zend-stratigility for the canonical source repository
 * @copyright Copyright (c) 2016-2017 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-stratigility/blob/master/LICENSE.md New BSD License
 */

namespace Zend\Stratigility\Delegate;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Decorate callable delegates as http-interop delegates in order to process
 * incoming requests.
 *
 * @internal
 */
trait CallableDelegateDecoratorTrait
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
     * Method provided for compatibility with http-interop/http-middleware 0.4.1
     *
     * {@inheritDoc}
     */
    public function process(ServerRequestInterface $request)
    {
        return $this->handle($request);
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

    /**
     * Method provided for compatibility with http-interop/http-middleware 0.1.1.
     *
     * @param RequestInterface $request
     * @return mixed
     */
    public function next(RequestInterface $request)
    {
        return $this->handle($request);
    }
}
