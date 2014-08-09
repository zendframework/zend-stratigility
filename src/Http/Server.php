<?php
namespace Phly\Conduit\Http;

use OutOfBoundsException;
use Phly\Conduit\Middleware;
use Phly\Conduit\Http\ResponseInterface as ResponseInterface;
use Phly\Conduit\Utils;
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
     * @param Middleware $middleware 
     * @param null|RequestInterface $request 
     * @param null|ResponseInterface $response 
     */
    private function __construct(
        Middleware $middleware,
        RequestInterface $request = null,
        ResponseInterface $response = null
    ) {
        if (null === $request) {
            $request = $this->marshalRequest();
        }
        if (null === $response) {
            $response = new Response();
        }

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
     * @param Middleware $middleware 
     * @param null|RequestInterface $request 
     * @param null|ResponseInterface $response 
     */
    public static function createServer(
        Middleware $middleware,
        RequestInterface $request = null,
        ResponseInterface $response = null
    ) {
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

        while (ob_get_level() > 0) {
            ob_end_flush();
        }

        echo $response->getBody();
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

    /**
     * Marshal a request object from the PHP environment
     *
     * Largely lifted from ZF2's Zend\Http\PhpEnvironment\Request class
     *
     * @copyright Copyright (c) 2005-2014 Zend Technologies USA Inc. (http://www.zend.com)
     * @license   http://framework.zend.com/license/new-bsd New BSD License
     * @return Request;
     */
    private function marshalRequest()
    {
        $server   = $this->marshalServer();
        $protocol = (isset($server['SERVER_PROTOCOL']) && $server['SERVER_PROTOCOL']) ? $server['SERVER_PROTOCOL'] : '1.1';
        $request  = new Request($protocol);
        $request->setMethod($server['REQUEST_METHOD']);
        $request->setHeaders($this->marshalHeaders($server));
        $request->setUrl($this->marshalUri($server, $request));
        return $request;
    }

    /**
     * Marshal the $_SERVER array
     *
     * Pre-processes and returns the $_SERVER superglobal.
     * 
     * @return array
     */
    private function marshalServer()
    {
        $server = $_SERVER;
        // This seems to be the only way to get the Authorization header on Apache
        if (function_exists('apache_request_headers')) {
            $apacheRequestHeaders = apache_request_headers();
            if (!isset($server['HTTP_AUTHORIZATION'])) {
                if (isset($apacheRequestHeaders['Authorization'])) {
                    $server['HTTP_AUTHORIZATION'] = $apacheRequestHeaders['Authorization'];
                } elseif (isset($apacheRequestHeaders['authorization'])) {
                    $server['HTTP_AUTHORIZATION'] = $apacheRequestHeaders['authorization'];
                }
            }
        }
        return $server;
    }

    /**
     * Detect the base URI for the request
     *
     * Looks at a variety of criteria in order to attempt to autodetect a base
     * URI, including rewrite URIs, proxy URIs, etc.
     *
     * From ZF2's Zend\Http\PhpEnvironment\Request class
     * @copyright Copyright (c) 2005-2014 Zend Technologies USA Inc. (http://www.zend.com)
     * @license   http://framework.zend.com/license/new-bsd New BSD License
     *
     * @param array $server
     * @return string
     */
    private function detectRequestUri(array $server)
    {
        $requestUri = null;

        // Check this first so IIS will catch.
        $httpXRewriteUrl = isset($server['HTTP_X_REWRITE_URL']) ? $server['HTTP_X_REWRITE_URL'] : null;
        if ($httpXRewriteUrl !== null) {
            $requestUri = $httpXRewriteUrl;
        }

        // Check for IIS 7.0 or later with ISAPI_Rewrite
        $httpXOriginalUrl = isset($server['HTTP_X_ORIGINAL_URL']) ? $server['HTTP_X_ORIGINAL_URL'] : null;
        if ($httpXOriginalUrl !== null) {
            $requestUri = $httpXOriginalUrl;
        }

        // IIS7 with URL Rewrite: make sure we get the unencoded url
        // (double slash problem).
        $iisUrlRewritten = isset($server['IIS_WasUrlRewritten']) ? $server['IIS_WasUrlRewritten'] : null;
        $unencodedUrl    = isset($server['UNENCODED_URL']) ? $server['UNENCODED_URL'] : '';
        if ('1' == $iisUrlRewritten && ! empty($unencodedUrl)) {
            return $unencodedUrl;
        }

        // HTTP proxy requests setup request URI with scheme and host [and port]
        // + the URL path, only use URL path.
        if (!$httpXRewriteUrl) {
            $requestUri = isset($server['REQUEST_URI']) ? $server['REQUEST_URI'] : null;
        }

        if ($requestUri !== null) {
            return preg_replace('#^[^/:]+://[^/]+#', '', $requestUri);
        }

        // IIS 5.0, PHP as CGI.
        $origPathInfo = isset($server['ORIG_PATH_INFO']) ? $server['ORIG_PATH_INFO'] : null;
        if ($origPathInfo !== null) {
            $queryString = isset($server['QUERY_STRING']) ? $server['QUERY_STRING'] : '';
            if ($queryString !== '') {
                $origPathInfo .= '?' . $queryString;
            }
            return $origPathInfo;
        }

        return '/';
    }

    /**
     * Marshal headers from $_SERVER
     * 
     * @param array $server 
     * @return array
     */
    private function marshalHeaders(array $server)
    {
        $headers = array();
        foreach ($server as $key => $value) {
            if ($value && strpos($key, 'HTTP_') === 0) {
                if (strpos($key, 'HTTP_COOKIE') === 0) {
                    // Cookies are handled using the $_COOKIE superglobal
                    continue;
                }
                $name = strtr(substr($key, 5), '_', ' ');
                $name = strtr(ucwords(strtolower($name)), ' ', '-');
            } elseif ($value && strpos($key, 'CONTENT_') === 0) {
                $name = substr($key, 8); // Content-
                $name = 'Content-' . (($name == 'MD5') ? $name : ucfirst(strtolower($name)));
            } else {
                continue;
            }

            $headers[$name] = $value;
        }
        return $headers;
    }

    /**
     * Marshal the URI from the $_SERVER array and headers
     * 
     * @param array $server 
     * @param RequestInterface $request 
     * @return string
     */
    private function marshalUri(array $server, RequestInterface $request)
    {
        $scheme   = 'http';
        $host     = null;
        $port     = 80;
        $path     = null;
        $query    = null;

        // URI scheme
        if ((! empty($server['HTTPS']) && $server['HTTPS'] !== 'off')
            || (! empty($server['HTTP_X_FORWARDED_PROTO']) && $server['HTTP_X_FORWARDED_PROTO'] == 'https')
        ) {
            $scheme = 'https';
        }

        // Set the host
        if ($request->hasHeader('host')) {
            $host = $request->getHeader('host');

            // works for regname, IPv4 & IPv6
            if (preg_match('|\:(\d+)$|', $host, $matches)) {
                $host = substr($host, 0, -1 * (strlen($matches[1]) + 1));
                $port = (int) $matches[1];
            }
        }

        if (! $host && isset($server['SERVER_NAME'])) {
            $host = $server['SERVER_NAME'];
            if (isset($server['SERVER_PORT'])) {
                $port = (int) $server['SERVER_PORT'];
            }

            // Check for missinterpreted IPv6-Address
            // Reported at least for Safari on Windows
            if (isset($server['SERVER_ADDR']) && preg_match('/^\[[0-9a-fA-F\:]+\]$/', $host)) {
                $host = '[' . $server['SERVER_ADDR'] . ']';
                if ($port . ']' == substr($host, strrpos($host, ':')+1)) {
                    // The last digit of the IPv6-Address has been taken as port
                    // Unset the port so the default port can be used
                    $port = 80;
                }
            }
        }

        // URI path
        $path = $this->detectRequestUri($server);
        if (($qpos = strpos($path, '?')) !== false) {
            $path = substr($path, 0, $qpos);
        }

        // URI query
        if (isset($server['QUERY_STRING'])) {
            $query = ltrim($server['QUERY_STRING'], '?');
        }

        return Utils::createUriString(compact(
            'scheme',
            'host',
            'port',
            'path',
            'query'
        ));
    }
}
