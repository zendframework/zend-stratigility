<?php
/**
 * @see       https://github.com/zendframework/zend-stratigility for the canonical source repository
 * @copyright Copyright (c) 2018 Zend Technologies USA Inc. (https://www.zend.com)
 * @license   https://github.com/zendframework/zend-stratigility/blob/master/LICENSE.md New BSD License
 */

declare(strict_types=1);

namespace Zend\Stratigility\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

use function rtrim;
use function strlen;
use function strtolower;
use function substr;

final class PathMiddlewareDecorator implements MiddlewareInterface
{
    /** @var MiddlewareInterface */
    private $middleware;

    /** @var string Path prefix under which the middleware is segregated.  */
    private $prefix;

    public function __construct(string $prefix, MiddlewareInterface $middleware)
    {
        $this->prefix = $this->normalizePrefix($prefix);
        $this->middleware = $middleware;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler) : ResponseInterface
    {
        $path = $request->getUri()->getPath() ?: '/';

        // Current path is shorter than decorator path
        if (strlen($path) < strlen($this->prefix)) {
            return $handler->handle($request);
        }

        // Current path does not match decorator path
        if (substr(strtolower($path), 0, strlen($this->prefix)) !== strtolower($this->prefix)) {
            return $handler->handle($request);
        }

        // Skip if match is not at a border ('/' or end)
        $border = $this->getBorder($path);
        if ($border && '/' !== $border) {
            return $handler->handle($request);
        }

        // Trim off the part of the url that matches the prefix if it is not / only
        $requestToProcess = $this->prefix !== '/'
            ? $this->prepareRequestWithTruncatedPrefix($request)
            : $request;

        // Process our middleware.
        // If the middleware calls on the handler, the handler should be provided
        // the original request, as this indicates we've left the path-segregated
        // layer.
        return $this->middleware->process(
            $requestToProcess,
            $this->prepareHandlerForOriginalRequest($handler)
        );
    }

    private function getBorder(string $path) : string
    {
        if ($this->prefix === '/') {
            return '/';
        }

        $length = strlen($this->prefix);
        return strlen($path) > $length ? $path[$length] : '';
    }

    private function prepareRequestWithTruncatedPrefix(ServerRequestInterface $request) : ServerRequestInterface
    {
        $uri  = $request->getUri();
        $path = $this->getTruncatedPath($this->prefix, $uri->getPath());
        $new  = $uri->withPath($path);
        return $request->withUri($new);
    }

    private function getTruncatedPath(string $segment, string $path) : string
    {
        if ($segment === $path) {
            // Decorated path and current path are the same; return empty string
            return '';
        }

        // Strip decorated path from start of current path
        return substr($path, strlen($segment));
    }

    private function prepareHandlerForOriginalRequest(RequestHandlerInterface $handler) : RequestHandlerInterface
    {
        return new class ($handler, $this->prefix) implements RequestHandlerInterface {
            /** @var RequestHandlerInterface */
            private $handler;

            /** @var string */
            private $prefix;

            public function __construct(RequestHandlerInterface $handler, string $prefix)
            {
                $this->handler = $handler;
                $this->prefix = $prefix;
            }

            /**
             * Invokes the composed handler with a request using the original URI.
             *
             * The decorated middleware may provide an altered response. However,
             * we want to reset the path to the original path on invocation, as
             * that is the part we originally modified, and is a part the decorated
             * middleware should not modify.
             *
             * {@inheritDoc}
             */
            public function handle(ServerRequestInterface $request) : ResponseInterface
            {
                $uri = $request->getUri();
                $uri = $uri->withPath($this->prefix . $uri->getPath());
                return $this->handler->handle($request->withUri($uri));
            }
        };
    }

    /**
     * Ensures that the right-most slash is trimmed for prefixes of more than
     * one character, and that the prefix begins with a slash.
     */
    private function normalizePrefix(string $prefix) : string
    {
        $prefix = strlen($prefix) > 1 ? rtrim($prefix, '/') : $prefix;
        if ('/' !== substr($prefix, 0, 1)) {
            $prefix = '/' . $prefix;
        }
        return $prefix;
    }
}
