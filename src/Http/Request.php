<?php
namespace Phly\Conduit\Http;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\StreamInterface;

/**
 * Decorator for PSR RequestInterface
 *
 * Decorates the PSR request interface to add the ability to manipulate
 * arbitrary instance members.
 */
class Request implements RequestInterface
{
    /**
     * User request parameters
     *
     * @var array
     */
    private $params = array();

    /**
     * @var RequestInterface
     */
    private $psrRequest;

    /**
     * @param RequestInterface $request
     */
    public function __construct(RequestInterface $request)
    {
        $this->psrRequest = $request;
    }

    /**
     * Return the original PSR request object
     *
     * @return RequestInterface
     */
    public function getOriginalRequest()
    {
        return $this->psrRequest;
    }

    /**
     * Property overloading: get property value
     *
     * Returns null if property is not set.
     *
     * @param string $name
     * @return mixed
     */
    public function __get($name)
    {
        if (! array_key_exists($name, $this->params)) {
            return null;
        }
        return $this->params[$name];
    }

    /**
     * Property overloading: set property
     *
     * @param string $name
     * @param mixed $value
     */
    public function __set($name, $value)
    {
        $this->params[$name] = $value;
    }

    /**
     * Property overloading: is property set?
     *
     * @param string $name
     * @return bool
     */
    public function __isset($name)
    {
        return array_key_exists($name, $this->params);
    }

    /**
     * Property overloading: unset property
     *
     * @param string $name
     */
    public function __unset($name)
    {
        if (! array_key_exists($name, $this->params)) {
            return;
        }
        unset($this->params[$name]);
    }

    /**
     * Proxy to RequestInterface::getProtocolVersion()
     *
     * @return string HTTP protocol version.
     */
    public function getProtocolVersion()
    {
        return $this->psrRequest->getProtocolVersion();
    }

    /**
     * Proxy to RequestInterface::getBody()
     *
     * @return StreamInterface|null Returns the body, or null if not set.
     */
    public function getBody()
    {
        return $this->psrRequest->getProtocolVersion();
    }

    /**
     * Proxy to RequestInterface::setBody()
     *
     * @param StreamInterface|null $body Body.
     * @throws \InvalidArgumentException When the body is not valid.
     */
    public function setBody(StreamInterface $body = null)
    {
        return $this->psrRequest->setBody($body);
    }

    /**
     * Proxy to RequestInterface::getHeaders()
     *
     * @return array Returns an associative array of the message's headers.
     */
    public function getHeaders()
    {
        return $this->psrRequest->getHeaders();
    }

    /**
     * Proxy to RequestInterface::hasHeader()
     *
     * @param string $header Case-insensitive header name.
     * @return bool Returns true if any header names match the given header
     *     name using a case-insensitive string comparison. Returns false if
     *     no matching header name is found in the message.
     */
    public function hasHeader($header)
    {
        return $this->psrRequest->hasHeader($header);
    }

    /**
     * Proxy to RequestInterface::getHeader()
     *
     * @param string $header Case-insensitive header name.
     * @return string
     */
    public function getHeader($header)
    {
        return $this->psrRequest->getHeader($header);
    }

    /**
     * Proxy to RequestInterface::getHeaderAsArray()
     *
     * @param string $header Case-insensitive header name.
     * @return string[]
     */
    public function getHeaderAsArray($header)
    {
        return $this->psrRequest->getHeaderAsArray($header);
    }

    /**
     * Proxy to RequestInterface::setHeader()
     *
     * @param string $header Header name
     * @param string|string[] $value  Header value(s)
     */
    public function setHeader($header, $value)
    {
        return $this->psrRequest->setHeader($header, $value);
    }

    /**
     * Proxy to RequestInterface::setHeaders()
     *
     * @param array $headers Headers to set.
     */
    public function setHeaders(array $headers)
    {
        return $this->psrRequest->setHeaders($headers);
    }

    /**
     * Proxy to RequestInterface::addHeader()
     *
     * @param string $header Header name to add
     * @param string $value  Value of the header
     */
    public function addHeader($header, $value)
    {
        return $this->psrRequest->addHeader($header, $value);
    }

    /**
     * Proxy to RequestInterface::addHeaders()
     *
     * @param array $headers Associative array of headers to add to the message
     */
    public function addHeaders(array $headers)
    {
        return $this->psrRequest->addHeaders($headers);
    }

    /**
     * Proxy to RequestInterface::removeHeader()
     *
     * @param string $header HTTP header to remove
     */
    public function removeHeader($header)
    {
        return $this->psrRequest->removeHeader($header);
    }

    /**
     * Proxy to RequestInterface::getMethod()
     *
     * @return string Returns the request method.
     */
    public function getMethod()
    {
        return $this->psrRequest->getMethod();
    }

    /**
     * Proxy to RequestInterface::setMethod()
     *
     * @param string $method Case-insensitive method.
     */
    public function setMethod($method)
    {
        return $this->psrRequest->setMethod($method);
    }

    /**
     * Proxy to RequestInterface::getUrl()
     *
     * @return string|object Returns the URL as a string, or an object that
     *    implements the `__toString()` method. The URL must be an absolute URI
     *    as specified in RFC 3986.
     * @link http://tools.ietf.org/html/rfc3986#section-4.3
     */
    public function getUrl()
    {
        return $this->psrRequest->getUrl();
    }

    /**
     * Proxy to RequestInterface::setUrl()
     *
     * @param string|object $url Request URL.
     * @throws \InvalidArgumentException If the URL is invalid.
     * @link http://tools.ietf.org/html/rfc3986#section-4.3
     */
    public function setUrl($url)
    {
        return $this->psrRequest->setUrl($url);
    }
}
