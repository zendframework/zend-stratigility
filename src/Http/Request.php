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
     * Original ServerRequestInterface instance.
     *
     * @var mixed
     */
    private $originalRequest;

    /**
     * The currently decorated ServerRequestInterface instance; it may or may
     * not be the same as the originalRequest, depending on how many changes
     * have been pushed to the original.
     *
     * @var ServerRequestInterface
     */
    private $psrRequest;

    /**
     * @param ServerRequestInterface $request
     */
    public function __construct(
        ServerRequestInterface $decoratedRequest,
        ServerRequestInterface $originalRequest = null
    ) {
        if (null === $originalRequest) {
            $originalRequest = $decoratedRequest;
        }

        $this->originalRequest = $originalRequest;
        $this->psrRequest      = $decoratedRequest->setAttribute('originalUrl', $originalRequest->getUrl());
    }

    /**
     * Return the currently decorated PSR request instance
     *
     * @return ServerRequestInterface
     */
    public function getCurrentRequest()
    {
        return $this->psrRequest;
    }

    /**
     * Return the original PSR request instance
     *
     * @return ServerRequestInterface
     */
    public function getOriginalRequest()
    {
        return $this->originalRequest;
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
     * @return Request
     */
    public function setProtocolVersion($version)
    {
        $new = $this->psrRequest->setProtocolVersion($version);
        return new self($new, $this->originalRequest);
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
     * @return Request
     * @throws \InvalidArgumentException When the body is not valid.
     */
    public function setBody(StreamableInterface $body)
    {
        $new = $this->psrRequest->setBody($body);
        return new self($new, $this->originalRequest);
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
     * @return Request
     */
    public function setHeader($header, $value)
    {
        $new = $this->psrRequest->setHeader($header, $value);
        return new self($new, $this->originalRequest);
    }

    /**
     * Proxy to ServerRequestInterface::addHeader()
     *
     * @param string $header Header name to add or append
     * @param string|string[] $value Value(s) to add or merge into the header
     * @return Request
     */
    public function addHeader($header, $value)
    {
        $new = $this->psrRequest->addHeader($header, $value);
        return new self($new, $this->originalRequest);
    }

    /**
     * Proxy to ServerRequestInterface::removeHeader()
     *
     * @param string $header HTTP header to remove
     * @return Request
     */
    public function removeHeader($header)
    {
        $new = $this->psrRequest->removeHeader($header);
        return new self($new, $this->originalRequest);
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
     * @return Request
     */
    public function setMethod($method)
    {
        $new = $this->psrRequest->setMethod($method);
        return new self($new, $this->originalRequest);
    }

    /**
     * Proxy to ServerRequestInterface::getAbsoluteUri()
     *
     * @return string Returns the absolute URI as a string
     * @link http://tools.ietf.org/html/rfc3986#section-4.3
     */
    public function getAbsoluteUri()
    {
        return $this->psrRequest->getAbsoluteUri();
    }

    /**
     * Allow mutating the absolute URI
     *
     * @link http://tools.ietf.org/html/rfc3986#section-4.3
     * @param string|object $uri Absolute request URI.
     * @return Request
     * @throws \InvalidArgumentException If the URL is invalid.
     */
    public function setAbsoluteUri($uri)
    {
        $new = $this->psrRequest->setAbsoluteUri($uri);
        return new self($new, $this->originalRequest);
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
        return $this->psrRequest->getUrl();
    }

    /**
     * Allow mutating the URL
     *
     * @link http://tools.ietf.org/html/rfc3986#section-4.3
     * @param string|object $url Request URL.
     * @return Request
     * @throws \InvalidArgumentException If the URL is invalid.
     */
    public function setUrl($url)
    {
        $new = $this->psrRequest->setUrl($url);
        return new self($new, $this->originalRequest);
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
     * @return Request
     */
    public function setCookieParams(array $cookies)
    {
        $new = $this->psrRequest->setCookieParams($cookies);
        return new self($new, $this->originalRequest);
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
     * @return Request
     */
    public function setQueryParams(array $query)
    {
        $new = $this->psrRequest->setQueryParams($query);
        return new self($new, $this->originalRequest);
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
     * @param array $params The deserialized body parameters.
     * @return Request
     */
    public function setBodyParams(array $params)
    {
        $new = $this->psrRequest->setBodyParams($params);
        return new self($new, $this->originalRequest);
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
     * @return Request
     */
    public function setAttributes(array $values)
    {
        $new = $this->psrRequest->setAttributes($values);
        return new self($new, $this->originalRequest);
    }

    /**
     * Proxy to ServerRequestInterface::setAttribute()
     *
     * @param string $attribute
     * @param mixed $value
     * @return Request
     */
    public function setAttribute($attribute, $value)
    {
        $new = $this->psrRequest->setAttribute($attribute, $value);
        return new self($new, $this->originalRequest);
    }
}
