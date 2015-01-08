<?php
namespace Phly\Conduit\Http;

use ArrayObject;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamableInterface;

/**
 * Decorator for PSR ServerRequestInterface
 *
 * Decorates the PSR incoming request interface to add the ability to 
 * manipulate arbitrary instance members.
 *
 * @property \Phly\Http\Uri $originalUrl Original URL of this instance
 */
class Request implements ServerRequestInterface
{
    /**
     * Current absolute URI (URI set in the proxy)
     * 
     * @var string
     */
    private $currentAbsoluteUri;

    /**
     * Current URL (URL set in the proxy)
     * 
     * @var string
     */
    private $currentUrl;

    /**
     * @var ServerRequestInterface
     */
    private $psrRequest;

    /**
     * @param ServerRequestInterface $request
     */
    public function __construct(ServerRequestInterface $request)
    {
        $this->psrRequest = $request;
        $this->originalUrl = $request->getUrl();
    }

    /**
     * Return the original PSR request object
     *
     * @return ServerRequestInterface
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
        return $this->psrRequest->getAttribute($name);
    }

    /**
     * Property overloading: set property
     *
     * @param string $name
     * @param mixed $value
     */
    public function __set($name, $value)
    {
        if (is_array($value)) {
            $value = new ArrayObject($value, ArrayObject::ARRAY_AS_PROPS);
        }

        return $this->psrRequest->setAttribute($name, $value);
    }

    /**
     * Property overloading: is property set?
     *
     * @param string $name
     * @return bool
     */
    public function __isset($name)
    {
        return (bool) $this->psrRequest->getAttribute($name, false);
    }

    /**
     * Property overloading: unset property
     *
     * @param string $name
     */
    public function __unset($name)
    {
        $this->psrRequest->setAttribute($name, null);
    }

    /**
     * Proxy to ServerRequestInterface::getProtocolVersion()
     *
     * @return string HTTP protocol version.
     */
    public function getProtocolVersion()
    {
        return $this->psrRequest->getProtocolVersion();
    }

    /**
     * Proxy to ServerRequestInterface::setProtocolVersion()
     *
     * @param string $version HTTP protocol version.
     */
    public function setProtocolVersion($version)
    {
        return $this->psrRequest->setProtocolVersion($version);
    }

    /**
     * Proxy to ServerRequestInterface::getBody()
     *
     * @return StreamableInterface Returns the body stream.
     */
    public function getBody()
    {
        return $this->psrRequest->getBody();
    }

    /**
     * Proxy to ServerRequestInterface::setBody()
     *
     * @param StreamableInterface $body Body.
     * @throws \InvalidArgumentException When the body is not valid.
     */
    public function setBody(StreamableInterface $body)
    {
        return $this->psrRequest->setBody($body);
    }

    /**
     * Proxy to ServerRequestInterface::getHeaders()
     *
     * @return array Returns an associative array of the message's headers.
     */
    public function getHeaders()
    {
        return $this->psrRequest->getHeaders();
    }

    /**
     * Proxy to ServerRequestInterface::hasHeader()
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
     * Proxy to ServerRequestInterface::getHeader()
     *
     * @param string $header Case-insensitive header name.
     * @return string
     */
    public function getHeader($header)
    {
        return $this->psrRequest->getHeader($header);
    }

    /**
     * Proxy to ServerRequestInterface::getHeaderLines()
     *
     * @param string $header Case-insensitive header name.
     * @return string[]
     */
    public function getHeaderLines($header)
    {
        return $this->psrRequest->getHeaderLines($header);
    }

    /**
     * Proxy to ServerRequestInterface::setHeader()
     *
     * @param string $header Header name
     * @param string|string[] $value  Header value(s)
     */
    public function setHeader($header, $value)
    {
        return $this->psrRequest->setHeader($header, $value);
    }

    /**
     * Proxy to ServerRequestInterface::addHeader()
     *
     * @param string $header Header name to add or append
     * @param string|string[] $value Value(s) to add or merge into the header
     */
    public function addHeader($header, $value)
    {
        return $this->psrRequest->addHeader($header, $value);
    }

    /**
     * Proxy to ServerRequestInterface::removeHeader()
     *
     * @param string $header HTTP header to remove
     */
    public function removeHeader($header)
    {
        return $this->psrRequest->removeHeader($header);
    }

    /**
     * Proxy to ServerRequestInterface::getMethod()
     *
     * @return string Returns the request method.
     */
    public function getMethod()
    {
        return $this->psrRequest->getMethod();
    }

    /**
     * Proxy to ServerRequestInterface::setMethod()
     *
     * @param string $method The request method.
     */
    public function setMethod($method)
    {
        $this->psrRequest->setMethod($method);
    }

    /**
     * Proxy to ServerRequestInterface::getAbsoluteUri()
     *
     * @return string Returns the absolute URI as a string
     * @link http://tools.ietf.org/html/rfc3986#section-4.3
     */
    public function getAbsoluteUri()
    {
        if ($this->currentAbsoluteUri) {
            return $this->currentAbsoluteUri;
        }

        return $this->psrRequest->getAbsoluteUri();
    }

    /**
     * Allow mutating the absolute URI
     *
     * Also sets originalAbsoluteUri property if not previously set.
     *
     * @link http://tools.ietf.org/html/rfc3986#section-4.3
     * @param string|object $uri Absolute request URI.
     * @throws \InvalidArgumentException If the URL is invalid.
     */
    public function setAbsoluteUri($uri)
    {
        $this->currentAbsoluteUri = $uri;

        if (! $this->originalAbsoluteUri) {
            $this->originalAbsoluteUri = $this->psrRequest->getAbsoluteUri();
        }
    }

    /**
     * Proxy to ServerRequestInterface::getUrl()
     *
     * @return string|object Returns the URL as a string, or an object that
     *    implements the `__toString()` method. The URL must be an absolute URI
     *    as specified in RFC 3986.
     * @link http://tools.ietf.org/html/rfc3986#section-4.3
     */
    public function getUrl()
    {
        if ($this->currentUrl) {
            return $this->currentUrl;
        }

        return $this->psrRequest->getUrl();
    }

    /**
     * Allow mutating the URL
     *
     * Also sets originalUrl property if not previously set.
     *
     * @link http://tools.ietf.org/html/rfc3986#section-4.3
     * @param string|object $url Request URL.
     * @throws \InvalidArgumentException If the URL is invalid.
     */
    public function setUrl($url)
    {
        $this->currentUrl = $url;

        if (! $this->originalUrl) {
            $this->originalUrl = $this->psrRequest->getUrl();
        }
    }

    /**
     * Proxy to ServerRequestInterface::getServerParams()
     * 
     * @return array
     */
    public function getServerParams()
    {
        return $this->psrRequest->getServerParams();
    }

    /**
     * Proxy to ServerRequestInterface::getCookieParams()
     *
     * @return array
     */
    public function getCookieParams()
    {
        return $this->psrRequest->getCookieParams();
    }

    /**
     * Proxy to ServerRequestInterface::setCookieParams()
     *
     * @param array $cookies
     */
    public function setCookieParams(array $cookies)
    {
        $this->psrRequest->setCookieParams($cookies);
    }

    /**
     * Proxy to ServerRequestInterface::getQueryParams()
     * 
     * @return array
     */
    public function getQueryParams()
    {
        return $this->psrRequest->getQueryParams();
    }

    /**
     * Proxy to ServerRequestInterface::setQueryParams()
     * 
     * @param array $query
     */
    public function setQueryParams(array $query)
    {
        $this->psrRequest->setQueryParams($query);
    }

    /**
     * Proxy to ServerRequestInterface::getFileParams()
     * 
     * @return array Upload file(s) metadata, if any.
     */
    public function getFileParams()
    {
        return $this->psrRequest->getFileParams();
    }

    /**
     * Proxy to ServerRequestInterface::getBodyParams()
     *
     * 
     * @return array The deserialized body parameters, if any.
     */
    public function getBodyParams()
    {
        return $this->psrRequest->getBodyParams();
    }

    /**
     * Proxy to ServerRequestInterface::setBodyParams()
     *
     * 
     * @param array $params The deserialized body parameters.
     */
    public function setBodyParams(array $params)
    {
        return $this->psrRequest->setBodyParams($params);
    }

    /**
     * Proxy to ServerRequestInterface::getAttributes()
     *
     * @return array Attributes derived from the request
     */
    public function getAttributes()
    {
        return $this->psrRequest->getAttributes();
    }

    /**
     * Proxy to ServerRequestInterface::getAttribute()
     * 
     * @param string $attribute 
     * @param mixed $default 
     * @return mixed
     */
    public function getAttribute($attribute, $default = null)
    {
        return $this->psrRequest->getAttribute($attribute, $default);
    }

    /**
     * Proxy to ServerRequestInterface::setAttributes()
     *
     * @param array Attributes derived from the request
     */
    public function setAttributes(array $values)
    {
        return $this->psrRequest->setAttributes($values);
    }

    /**
     * Proxy to ServerRequestInterface::setAttribute()
     * 
     * @param string $attribute 
     * @param mixed $value 
     * @return void
     */
    public function setAttribute($attribute, $value)
    {
        return $this->psrRequest->setAttribute($attribute, $value);
    }
}
