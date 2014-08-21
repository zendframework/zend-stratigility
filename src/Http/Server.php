<?php
namespace Phly\Conduit\Http;

use OutOfBoundsException;
use Phly\Conduit\Middleware;
use Phly\Conduit\Http\ResponseInterface as ResponseInterface;
use Psr\Http\Message\RequestInterface as RequestInterface;

/**
 * "Serve" incoming HTTP requests
 *
 * Given middleware, takes an incoming request, dispatches it to the
 * middleware, and then sends a response.
 *
 * If not provided an incoming Request object, marshals one from the
 * PHP environment.
 */
class Server
{
    /**
     * Level of output buffering at start of listen cycle; never flush more
     * than this.
     *
     * @var int
     */
    private $bufferLevel;

    /**
     * @var Middleware
     */
    private $middleware;

    /**
     * @var RequestInterface
     */
    private $request;

    /**
     * @var ResponseInterface
     */
    private $response;

    /**
     * Constructor
     *
     * Given middleware, a request, and a response, we can create a server.
     *
     * @param Middleware $middleware
     * @param RequestInterface $request
     * @param ResponseInterface $response
     */
    public function __construct(
        Middleware $middleware,
        RequestInterface $request,
        ResponseInterface $response
    ) {
        $this->middleware = $middleware;
        $this->request    = $request;
        $this->response   = $response;
    }

    /**
     * Allow retrieving the request, response and middleware as properties
     *
     * @param string $name
     * @return mixed
     * @throws OutOfBoundsException for invalid properties
     */
    public function __get($name)
    {
        if (! property_exists($this, $name)) {
            throw new OutOfBoundsException('Cannot retrieve arbitrary properties from server');
        }
        return $this->{$name};
    }

    /**
     * Create a Server instance
     *
     * Creates a server instance from the middleware and server array
     * passed; typically this will be the $_SERVER superglobal.
     *
     * @param Middleware $middleware
     * @param array $server
     * @return self
     */
    public static function createServer(
        Middleware $middleware,
        array $server
    ) {
        $request  = RequestFactory::fromServer($server);
        $response = new Response();
        return new self($middleware, $request, $response);
    }

    /**
     * Create a Server instance from an existing request object
     *
     * Provided middleware, an existing request object, and optionally an
     * existing response object, create and return the Server instance.
     *
     * If no Response object is provided, one will be created.
     *
     * @param Middleware $middleware
     * @param RequestInterface $request
     * @param null|ResponseInterface $response
     * @return self
     */
    public static function createServerFromRequest(
        Middleware $middleware,
        RequestInterface $request,
        ResponseInterface $response = null
    ) {
        if (! $response) {
            $response = new Response();
        }
        return new self($middleware, $request, $response);
    }

    /**
     * "Listen" to an incoming request
     *
     * If provided a $finalHandler, that callable will be used for
     * incomplete requests.
     *
     * Output buffering is enabled prior to invoking the attached
     * middleware; any output buffered will be sent prior to any
     * response body content.
     *
     * @param null|callable $finalHandler
     */
    public function listen(callable $finalHandler = null)
    {
        ob_start();
        $this->bufferLevel = ob_get_level();
        $this->middleware->handle($this->request, $this->response, $finalHandler);
        $this->send($this->response);
    }

    /**
     * Send the response
     *
     * If headers have not yet been sent, they will be.
     *
     * If any output buffering remains active, it will be flushed.
     *
     * Finally, the response body will be emitted.
     *
     * @param ResponseInterface $response
     */
    private function send(ResponseInterface $response)
    {
        if (! headers_sent()) {
            $this->sendHeaders($response);
        }

        while (ob_get_level() >= $this->bufferLevel) {
            ob_end_flush();
        }

        $this->bufferLevel = null;

        // Using printf so that we can override the function when testing
        printf($response->getBody());
    }

    /**
     * Send response headers
     *
     * Sends the response status/reason, followed by all headers;
     * header names are filtered to be word-cased.
     *
     * @param ResponseInterface $response
     */
    private function sendHeaders(ResponseInterface $response)
    {
        if ($response->getReasonPhrase()) {
            header(sprintf(
                'HTTP/%s %d %s',
                $response->getProtocolVersion(),
                $response->getStatusCode(),
                $response->getReasonPhrase()
            ));
        } else {
            header(sprintf(
                'HTTP/%s %d',
                $response->getProtocolVersion(),
                $response->getStatusCode()
            ));
        }

        foreach ($response->getHeaders() as $header => $value) {
            header(sprintf(
                '%s: %s',
                $this->filterHeader($header),
                implode(',', $value)
            ));
        }
    }

    /**
     * Filter a header name to wordcase
     *
     * @param string $header
     * @return string
     */
    private function filterHeader($header)
    {
        $filtered = str_replace('-', ' ', $header);
        $filtered = ucwords($filtered);
        return str_replace(' ', '-', $filtered);
    }
}
