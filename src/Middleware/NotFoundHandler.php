<?php
/**
 * @link      http://github.com/zendframework/zend-stratigility for the canonical source repository
 * @copyright Copyright (c) 2016 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 */

namespace Zend\Stratigility\Middleware;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;

class NotFoundHandler
{
    /**
     * @var ResponseInterface
     */
    private $responsePrototype;

    /**
     * @param ResponseInterface $responsePrototype Empty/prototype response to
     *     update and return when returning an 404 response.
     */
    public function __construct(ResponseInterface $responsePrototype)
    {
        $this->responsePrototype = $responsePrototype;
    }

    /**
     * Creates and returns a 404 response.
     *
     * @param ServerRequestInterface $request
     * @return ResponseInterface
     */
    public function __invoke(ServerRequestInterface $request)
    {
        $response = $this->responsePrototype
            ->withStatus(404);
        $response->getBody()->write(sprintf(
            "Cannot %s %s",
            $request->getMethod(),
            (string) $request->getUri()
        ));
        return $response;
    }
}
