<?php
namespace Phly\Conduit\Http;

use Psr\Http\Message\ResponseInterface as PsrResponseInterface;
use Psr\Http\Message\StreamableInterface;

/**
 * Response decorator
 *
 * Adds in write, end, and isComplete from RequestInterface in order
 * to provide a common interface for all PSR HTTP implementations.
 */
class Response implements
    PsrResponseInterface,
    ResponseInterface
{
    /**
     * @var bool
     */
    private $complete = false;

    /**
     * @var PsrResponseInterface
     */
    private $psrResponse;

    /**
     * @param PsrResponseInterface $response
     */
    public function __construct(PsrResponseInterface $response)
    {
        $this->psrResponse = $response;
    }

    /**
     * Return the original PSR response object
     *
     * @return PsrResponseInterface
     */
    public function getOriginalResponse()
    {
        return $this->psrResponse;
    }

    /**
     * Write data to the response body
     *
     * Proxies to the underlying stream and writes the provided data to it.
     *
     * @param string $data
     */
    public function write($data)
    {
        if ($this->complete) {
            return $this;
        }

        $this->getBody()->write($data);
        return $this;
    }

    /**
     * Mark the response as complete
     *
     * A completed response should no longer allow manipulation of either
     * headers or the content body.
     *
     * If $data is passed, that data should be written to the response body
     * prior to marking the response as complete.
     *
     * @param string $data
     */
    public function end($data = null)
    {
        if ($this->complete) {
            return $this;
        }

        if ($data) {
            $this->write($data);
        }

        $new = clone $this;
        $new->complete = true;
        return $new;
    }

    /**
     * Indicate whether or not the response is complete.
     *
     * I.e., if end() has previously been called.
     *
     * @return bool
     */
    public function isComplete()
    {
        return $this->complete;
    }

    /**
     * Proxy to PsrResponseInterface::getProtocolVersion()
     *
     * @return string HTTP protocol version.
     */
    public function getProtocolVersion()
    {
        return $this->psrResponse->getProtocolVersion();
    }

    /**
     * Proxy to PsrResponseInterface::setProtocolVersion()
     *
     * @param string $version
     * @return Response
     */
    public function setProtocolVersion($version)
    {
        $new = $this->psrResponse->setProtocolVersion($version);
        return new self($new);
    }

    /**
     * Proxy to PsrResponseInterface::getBody()
     *
     * @return StreamableInterface|null Returns the body, or null if not set.
     */
    public function getBody()
    {
        return $this->psrResponse->getBody();
    }

    /**
     * Proxy to PsrResponseInterface::setBody()
     *
     * @param StreamableInterface $body Body.
     * @return Response
     * @throws \InvalidArgumentException When the body is not valid.
     */
    public function setBody(StreamableInterface $body)
    {
        if ($this->complete) {
            return $this;
        }

        $new = $this->psrResponse->setBody($body);
        return new self($new);
    }

    /**
     * Proxy to PsrResponseInterface::getHeaders()
     *
     * @return array Returns an associative array of the message's headers.
     */
    public function getHeaders()
    {
        return $this->psrResponse->getHeaders();
    }

    /**
     * Proxy to PsrResponseInterface::hasHeader()
     *
     * @param string $header Case-insensitive header name.
     * @return bool Returns true if any header names match the given header
     *     name using a case-insensitive string comparison. Returns false if
     *     no matching header name is found in the message.
     */
    public function hasHeader($header)
    {
        return $this->psrResponse->hasHeader($header);
    }

    /**
     * Proxy to PsrResponseInterface::getHeader()
     *
     * @param string $header Case-insensitive header name.
     * @return string
     */
    public function getHeader($header)
    {
        return $this->psrResponse->getHeader($header);
    }

    /**
     * Proxy to PsrResponseInterface::getHeaderAsArray()
     *
     * @param string $header Case-insensitive header name.
     * @return string[]
     */
    public function getHeaderLines($header)
    {
        return $this->psrResponse->getHeaderLines($header);
    }

    /**
     * Proxy to PsrResponseInterface::setHeader()
     *
     * @param string $header Header name
     * @param string|string[] $value  Header value(s)
     * @return Response
     */
    public function setHeader($header, $value)
    {
        if ($this->complete) {
            return $this;
        }

        $new = $this->psrResponse->setHeader($header, $value);
        return new self($new);
    }

    /**
     * Proxy to PsrResponseInterface::addHeader()
     *
     * @param string $header Header name to add or append
     * @param string|string[] $value Value(s) to add or merge into the header
     * @return Response
     */
    public function addHeader($header, $value)
    {
        if ($this->complete) {
            return $this;
        }

        $new = $this->psrResponse->addHeader($header, $value);
        return new self($new);
    }

    /**
     * Proxy to PsrResponseInterface::removeHeader()
     *
     * @param string $header HTTP header to remove
     * @return Response
     */
    public function removeHeader($header)
    {
        if ($this->complete) {
            return $this;
        }

        $new = $this->psrResponse->removeHeader($header);
        return new self($new);
    }

    /**
     * Proxy to PsrResponseInterface::getStatusCode()
     *
     * @return integer Status code.
     */
    public function getStatusCode()
    {
        return $this->psrResponse->getStatusCode();
    }

    /**
     * Proxy to PsrResponseInterface::setStatus()
     *
     * @param integer $code The 3-digit integer result code to set.
     * @param null|string $reasonPhrase The reason phrase to use with the status, if any.
     * @return Response
     */
    public function setStatus($code, $reasonPhrase = null)
    {
        if ($this->complete) {
            return $this;
        }

        $new = $this->psrResponse->setStatus($code, $reasonPhrase);
        return new self($new);
    }

    /**
     * Proxy to PsrResponseInterface::getReasonPhrase()
     *
     * @return string|null Reason phrase, or null if unknown.
     */
    public function getReasonPhrase()
    {
        return $this->psrResponse->getReasonPhrase();
    }
}
