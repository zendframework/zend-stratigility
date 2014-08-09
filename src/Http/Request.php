<?php
namespace Phly\Conduit\Http;

use InvalidArgumentException;
use RuntimeException;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\StreamInterface;

class Request extends AbstractMessage implements RequestInterface
{
    private $method;
    private $url;
    private $userParams = array();

    public function __construct($protocol = '1.1', $stream = 'php://input')
    {
        $this->protocol = $protocol;
        $this->setBody(new Stream($stream));
    }

    public function __get($name)
    {
        if (! array_key_exists($name, $this->userParams)) {
            return null;
        }

        return $this->userParams[$name];
    }

    public function __set($name, $value)
    {
        $this->userParams[$name] = $value;
    }

    public function __isset($name)
    {
        return array_key_exists($name, $this->userParams);
    }

    public function __unset($name)
    {
        if (! array_key_exists($name, $this->userParams)) {
            return;
        }

        unset($this->userParams[$name]);
    }

    /**
     * Gets the HTTP method of the request.
     *
     * @return string Returns the request method.
     */
    public function getMethod()
    {
        return $this->method;
    }

    /**
     * Sets the method to be performed on the resource identified by the Request-URI.
     *
     * While HTTP method names are typically all uppercase characters, HTTP
     * method names are case-sensitive and thus implementations SHOULD NOT
     * modify the given string.
     *
     * @param string $method Case-insensitive method.
     *
     * @return void
     */
    public function setMethod($method)
    {
        if ($this->method !== null) {
            throw new RuntimeException('Method cannot be overwritten');
        }
        $this->method = $method;
    }

    /**
     * Gets the absolute request URL.
     *
     * @return Uri
     */
    public function getUrl()
    {
        return $this->url;
    }

    /**
     * Sets the request URL.
     *
     * @param string|Url $url Request URL.
     *
     * @throws InvalidArgumentException If the URL is invalid.
     */
    public function setUrl($url)
    {
        if (! $url instanceof Uri) {
            $url = new Uri($url);
        }

        if (! $url->isValid()) {
            throw new InvalidArgumentException('Invalid URL provided');
        }

        if ($this->originalUrl === null) {
            $this->originalUrl = $url;
        }
        $this->url = $url;
    }
}
