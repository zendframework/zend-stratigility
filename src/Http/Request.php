<?php
namespace Phly\Conduit\Http;

use ArrayObject;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamableInterface;
use Psr\Http\Message\UriTargetInterface;

/**
 * Decorator for PSR ServerRequestInterface
 *
 * Decorates the PSR incoming request interface to add the ability to
 * manipulate arbitrary instance members.
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
        $this->psrRequest      = $decoratedRequest->withAttribute('originalUri', $originalRequest->getUri());
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
     * Proxy to ServerRequestInterface::withProtocolVersion()
     *
     * @param string $version HTTP protocol version.
     * @return self
     */
    public function withProtocolVersion($version)
    {
        $new = $this->psrRequest->withProtocolVersion($version);
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
     * Proxy to ServerRequestInterface::withBody()
     *
     * @param StreamableInterface $body Body.
     * @return self
     * @throws \InvalidArgumentException When the body is not valid.
     */
    public function withBody(StreamableInterface $body)
    {
        $new = $this->psrRequest->withBody($body);
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
     * Proxy to ServerRequestInterface::withHeader()
     *
     * @param string $header Header name
     * @param string|string[] $value  Header value(s)
     * @return self
     */
    public function withHeader($header, $value)
    {
        $new = $this->psrRequest->withHeader($header, $value);
        return new self($new, $this->originalRequest);
    }

    /**
     * Proxy to ServerRequestInterface::addHeader()
     *
     * @param string $header Header name to add or append
     * @param string|string[] $value Value(s) to add or merge into the header
     * @return self
     */
    public function withAddedHeader($header, $value)
    {
        $new = $this->psrRequest->withAddedHeader($header, $value);
        return new self($new, $this->originalRequest);
    }

    /**
     * Proxy to ServerRequestInterface::removeHeader()
     *
     * @param string $header HTTP header to remove
     * @return self
     */
    public function withoutHeader($header)
    {
        $new = $this->psrRequest->withoutHeader($header);
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
     * Proxy to ServerRequestInterface::withMethod()
     *
     * @param string $method The request method.
     * @return self
     */
    public function withMethod($method)
    {
        $new = $this->psrRequest->withMethod($method);
        return new self($new, $this->originalRequest);
    }

    /**
     * Proxy to ServerRequestInterface::getUri()
     *
     * @return UriTargetInterface Returns a UriTargetInterface instance
     * @link http://tools.ietf.org/html/rfc3986#section-4.3
     */
    public function getUri()
    {
        return $this->psrRequest->getUri();
    }

    /**
     * Allow mutating the URI
     *
     * @link http://tools.ietf.org/html/rfc3986#section-4.3
     * @param UriTargetInterface $uri Request URI.
     * @return self
     * @throws \InvalidArgumentException If the URI is invalid.
     */
    public function withUri(UriTargetInterface $uri)
    {
        $new = $this->psrRequest->withUri($uri);
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
     * Proxy to ServerRequestInterface::withCookieParams()
     *
     * @param array $cookies
     * @return self
     */
    public function withCookieParams(array $cookies)
    {
        $new = $this->psrRequest->withCookieParams($cookies);
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
     * Proxy to ServerRequestInterface::withQueryParams()
     *
     * @param array $query
     * @return self
     */
    public function withQueryParams(array $query)
    {
        $new = $this->psrRequest->withQueryParams($query);
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
     * Proxy to ServerRequestInterface::withBodyParams()
     *
     * @param array $params The deserialized body parameters.
     * @return self
     */
    public function withBodyParams(array $params)
    {
        $new = $this->psrRequest->withBodyParams($params);
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
     * Proxy to ServerRequestInterface::withAttribute()
     *
     * @param string $attribute
     * @param mixed $value
     * @return self
     */
    public function withAttribute($attribute, $value)
    {
        $new = $this->psrRequest->withAttribute($attribute, $value);
        return new self($new, $this->originalRequest);
    }

    /**
     * Proxy to ServerRequestInterface::withoutAttribute()
     *
     * @param string $attribute
     * @return self
     */
    public function withoutAttribute($attribute)
    {
        $new = $this->psrRequest->withoutAttribute($attribute);
        return new self($new, $this->originalRequest);
    }
}
