<?php
namespace Phly\Conduit;

use ArrayObject;
use InvalidArgumentException;
use Psr\Http\Message\RequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;

class Conduit
{
    /**
     * @var ArrayObject
     */
    private $stack;

    public function __construct()
    {
        $this->stack      = new ArrayObject(array());
    }

    /**
     * Attach middleware to the conduit
     *
     * Was "use", but "use" is a reserved keyword in PHP
     *
     * @param string|callable|object $path Either a URI path prefix, or a handler
     * @param null|callable|object $handler A handler
     */
    public function attach($path, $handler = null)
    {
        if (! is_string($path)) {
            $handler = $path;
            $path    = '/';
        }

        if ('/' === substr($path, -1)) {
            $path = substr($path, 0, -1);
        }

        if (! is_callable($handler)
            && (! is_object($handler) || ! method_exists($handler, 'handle'))
        ) {
            throw new InvalidArgumentException('Handler must be callable or an object implementing the method "handle"');
        }

        if (! is_callable($handler)
            && (is_object($handler) && method_exists($handler, 'handle'))
        ) {
            if (Utils::getArity($handler) < 4) {
                $handler = function ($request, $response, $next) use ($handler) {
                    $handler->handle($request, $response, $next);
                };
            } else {
                $handler = function ($err, $request, $response, $next) use ($handler) {
                    $handler->handle($err, $request, $response, $next);
                };
            }
        }

        // @todo Trigger event here with route details?
        $this->stack[] = new Route($path, $handler);
        return $this;
    }

    public function handle(Request $request, Response $response, callable $out = null)
    {
        $stack    = $this->stack;
        $url      = $request->setUrl($this->getUrlFromRequest($request));
        $done     = is_callable($out) ? $out : new FinalHandler($request, $response);
        $next     = new Next($this->stack, $request, $response, $done);
        $next();
    }

    /**
     * Ensure the request URI is an Http\Uri instance
     * 
     * @param Request $request 
     * @return Http\Uri
     */
    private function getUrlFromRequest(Request $request)
    {
        $url = $request->getUrl();
        if (! $url instanceof Http\Uri) {
            $url = new Http\Uri($url);
        }
        return $url;
    }
}
