<?php
namespace Phly\Conduit\Http;

/**
 * URI implementation
 */
class Uri
{
    /**
     * @var string
     */
    private $scheme;

    /**
     * @var string
     */
    private $host;

    /**
     * @var int
     */
    private $port;

    /**
     * @var string
     */
    private $path;

    /**
     * @var string
     */
    private $query;

    /**
     * @var string
     */
    private $fragment;

    /**
     * Combination of host + port (if non-standard schema + port pairing)
     *
     * @var string
     */
    private $domain;

    /**
     * @var string
     */
    private $uri;

    /**
     * @param string $uri 
     */
    public function __construct($uri)
    {
        $this->uri = $uri;
        if ($this->isValid()) {
            $this->parseUri();
        }
    }

    /**
     * Retrieve properties
     * 
     * @param string $name 
     * @return null|string|int
     */
    public function __get($name)
    {
        if (! property_exists($this, $name)) {
            return null;
        }

        return $this->{$name};
    }

    /**
     * Return string representation of URI
     * 
     * @return string
     */
    public function __toString()
    {
        return $this->uri;
    }

    /**
     * Is the URI valid?
     * 
     * @return bool
     */
    public function isValid()
    {
        return filter_var(
            $this->uri,
            FILTER_VALIDATE_URL,
            FILTER_FLAG_PATH_REQUIRED
        );
    }

    /**
     * Parse a URI into its parts, and set the properties 
     */
    private function parseUri()
    {
        $parts = parse_url($this->uri);

        $this->scheme   = $parts['scheme'];
        $this->host     = $parts['host'];
        $this->port     = $parts['port'];
        $this->path     = $parts['path'];
        $this->query    = isset($parts['query'])    ? $parts['query']    : '';
        $this->fragment = isset($parts['fragment']) ? $parts['fragment'] : '';

        if ($parts['scheme'] === 'http'
            && $parts['port'] !== null
            && $parts['port'] !== 80
        ) {
            $this->domain = sprintf('%s:%d', $this->host, $this->port);
        } elseif ($parts['scheme'] === 'https'
            && $parts['port'] !== null
            && $parts['port'] !== 443
        ) {
            $this->domain = sprintf('%s:%d', $this->host, $this->port);
        } else {
            $this->domain = $this->host;
        }
    }
}
