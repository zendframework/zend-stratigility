<?php
/**
 * @link      http://github.com/zendframework/zend-stratigility for the canonical source repository
 * @copyright Copyright (c) 2005-2016 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 */

namespace ZendTest\Stratigility\Middleware\TestAsset;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class Next
{
    /**
     * @var callable[]
     */
    private $middleware = [];

    /**
     * @param ServerRequestInterface $request
     * @param ResponseInterface $response
     * @return ResponseInterface
     */
    public function __invoke(ServerRequestInterface $request, ResponseInterface $response)
    {
        if (0 === count($this->middleware)) {
            return $response;
        }

        $middleware = array_shift($this->middleware);
        return $middleware($request, $response);
    }

    /**
     * @param callable $middleware
     * @return void
     */
    public function push(callable $middleware)
    {
        $this->middleware[] = $middleware;
    }
}
