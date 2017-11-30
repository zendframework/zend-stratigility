<?php
/**
 * @see       https://github.com/zendframework/zend-stratigility for the canonical source repository
 * @copyright Copyright (c) 2016 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-stratigility/blob/master/LICENSE.md New BSD License
 */
declare(strict_types=1);

namespace Zend\Stratigility\Middleware;

use Interop\Http\Server\MiddlewareInterface;
use Interop\Http\Server\RequestHandlerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Inject attributes containing the original request and URI instances.
 *
 * This middleware will add request attributes as follows:
 *
 * - "originalRequest", representing the request provided to this middleware.
 * - "originalUri", representing the URI composed by the request provided to
 *   this middleware.
 *
 * These can then be reference later, for tasks such as:
 *
 * - Determining the base path when generating a URI (as layers may receive
 *   URIs stripping path segments).
 * - Determining if changes to the response have occurred.
 * - Providing prototypes for factories.
 */
class OriginalMessages implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler) : ResponseInterface
    {
        $request = $request
            ->withAttribute('originalUri', $request->getUri())
            ->withAttribute('originalRequest', $request);

        return $handler->handle($request);
    }
}
