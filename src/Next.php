<?php
namespace Phly\Conduit;

use ArrayObject;
use Psr\Http\Message\RequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;

class Next
{
    /**
     * @var Dispatch
     */
    private $dispatch;

    /**
     * @var Callable
     */
    private $done;

    /**
     * @var int
     */
    private $index = 0;

    /**
     * @var string
     */
    private $removed = '';

    /**
     * @var Request
     */
    private $request;

    /**
     * @var Response
     */
    private $response;

    /**
     * @var bool
     */
    private $slashAdded = false;

    /**
     * @var ArrayObject
     */
    private $stack;

    public function __construct(ArrayObject $stack, Request $request, Response $response, callable $done)
    {
        $this->dispatch = new Dispatch();

        $this->stack    = $stack;
        $this->request  = $request;
        $this->response = $response;
        $this->done     = $done;
    }

    /**
     * Call the next Route in the stack
     * 
     * @param  null|mixed $err 
     */
    public function __invoke($err = null)
    {
        $dispatch = $this->dispatch;
        $done     = $this->done;

        if ($this->slashAdded) {
            $uri  = $this->request->getUrl();
            $path = substr($uri->path, 1);
            $this->setUriPath($this->request, $uri, $path);
        }

        if ($this->removed) {
            $uri  = $this->request->getUrl();
            $path = $this->removed . $uri->path;
            $this->setUriPath($this->request, $uri, $path);
            $this->removed = '';
        }

        // No middleware remains; done
        if (! isset($this->stack[$this->index])) {
            return $done($err);
        }

        $layer = $this->stack[$this->index++];
        $path  = $this->request->getUrl()->path || '/';
        $route = $layer->path;

        // Skip if layer path does not match current url
        if (substr(strtolower($path), 0, strlen($route)) !== strtolower($route)) {
            return $this($err);
        }

        // Skip if match is not at a border ('/', '.', or end)
        $border = $path[strlen($route)];
        if ($border && '/' !== $border && '.' !== $border) {
            return $this($err);
        }

        // Trim off the part of the url that matches the layer route
        if (strlen($route) !== 0 && $route !== '/') {
            $this->removed = $route;

            $uri  = $this->request->getUrl();
            $path = substr($uri->path, strlen($route));
            $this->setUriPath($this->request, $uri, $path);

            if ($path[0] !== '/') {
                $path = '/' . $path;
                $this->setUriPath($this->request, $this->request->getUri(), $path);
                $this->slashAdded = true;
            }
        }

        $dispatch(
            $layer,
            $err,
            $this->request,
            $this->response,
            $this
        );
    }

    private function setUriPath(Request $request, Http\Uri $uri, $path)
    {
        $request->setUrl(new Http\Uri(Utils::createUriString(array(
            'scheme'   => $uri->scheme,
            'host'     => $uri->host,
            'port'     => $uri->port,
            'path'     => $path,
            'query'    => $uri->query,
            'fragment' => $uri->fragment,
        ))));
    }
}
