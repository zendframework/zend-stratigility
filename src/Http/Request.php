<?php
namespace Phly\Conduit\Http;

use ArrayObject;
use Psr\Http\Message\IncomingRequestInterface;
use Psr\Http\Message\StreamableInterface;

/**
 * Decorator for PSR IncomingRequestInterface
 *
 * Decorates the PSR incoming request interface to add the ability to 
 * manipulate arbitrary instance members.
 *
 * @property \Phly\Http\Uri $originalUrl Original URL of this instance
 */
class Request implements IncomingRequestInterface
{
    /**
     * Current URL (URL set in the proxy)
     * 
     * @var string
     */
    private $currentUrl;

    /**
     * User request parameters
     *
     * @var array
     */
    private $params = array();

    /**
     * @var IncomingRequestInterface
     */
    private $psrRequest;

    /**
     * @param IncomingRequestInterface $request
     */
    public function __construct(IncomingRequestInterface $request)
    {
        $this->psrRequest = $request;
        $this->originalUrl = $request->getUrl();
    }

    /**
     * Return the original PSR request object
     *
     * @return IncomingRequestInterface
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
     * Proxy to IncomingRequestInterface::getProtocolVersion()
     *
     * @return string HTTP protocol version.
     */
    public function getProtocolVersion()
    {
        return $this->psrRequest->getProtocolVersion();
    }

    /**
     * Proxy to IncomingRequestInterface::getBody()
     *
     * @return StreamableInterface Returns the body stream.
     */
    public function getBody()
    {
        return $this->psrRequest->getBody();
    }

    /**
     * Proxy to IncomingRequestInterface::getHeaders()
     *
     * @return array Returns an associative array of the message's headers.
     */
    public function getHeaders()
    {
        return $this->psrRequest->getHeaders();
    }

    /**
     * Proxy to IncomingRequestInterface::hasHeader()
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
     * Proxy to IncomingRequestInterface::getHeader()
     *
     * @param string $header Case-insensitive header name.
     * @return string
     */
    public function getHeader($header)
    {
        return $this->psrRequest->getHeader($header);
    }

    /**
     * Proxy to IncomingRequestInterface::getHeaderAsArray()
     *
     * @param string $header Case-insensitive header name.
     * @return string[]
     */
    public function getHeaderAsArray($header)
    {
        return $this->psrRequest->getHeaderAsArray($header);
    }

    /**
     * Proxy to IncomingRequestInterface::getMethod()
     *
     * @return string Returns the request method.
     */
    public function getMethod()
    {
        return $this->psrRequest->getMethod();
    }

    /**
     * Proxy to IncomingRequestInterface::getUrl()
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
     * Proxy to IncomingRequestInterface::getServerParams()
     * 
     * @return array
     */
    public function getServerParams()
    {
        return $this->psrRequest->getServerParams();
    }

    /**
     * Proxy to IncomingRequestInterface::getCookieParams()
     *
     * @return array
     */
    public function getCookieParams()
    {
        return $this->psrRequest->getCookieParams();
    }

    /**
     * Proxy to IncomingRequestInterface::getQueryParams()
     * 
     * @return array
     */
    public function getQueryParams()
    {
        return $this->psrRequest->getQueryParams();
    }

    /**
     * Proxy to IncomingRequestInterface::getFileParams()
     * 
     * @return array Upload file(s) metadata, if any.
     */
    public function getFileParams()
    {
        return $this->psrRequest->getFileParams();
    }

    /**
     * Proxy to IncomingRequestInterface::getBodyParams()
     *
     * 
     * @return array The deserialized body parameters, if any.
     */
    public function getBodyParams()
    {
        return $this->psrRequest->getBodyParams();
    }

    /**
     * Proxy to IncomingRequestInterface::getAttributes()
     *
     * @return array Attributes derived from the request
     */
    public function getAttributes()
    {
        return $this->psrRequest->getAttributes();
    }

    /**
     * Proxy to IncomingRequestInterface::getAttribute()
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
     * Proxy to IncomingRequestInterface::setAttributes()
     *
     * @param array Attributes derived from the request
     */
    public function setAttributes(array $values)
    {
        return $this->psrRequest->setAttributes($values);
    }

    /**
     * Proxy to IncomingRequestInterface::setAttribute()
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
