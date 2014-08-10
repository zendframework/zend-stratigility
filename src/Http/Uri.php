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
     * Create a URI based on the parts provided.
     *
     * Parts SHOULD contain the following:
     *
     * - scheme (defaults to http)
     * - host
     * - port (defaults to null)
     * - path
     * - query
     * - fragment
     *
     * All but scheme and host are optional.
     *
     * @param array $parts
     * @return self
     */
    public static function fromArray(array $parts)
    {
        $scheme   = isset($parts['scheme'])   ? $parts['scheme']   : 'http';
        $host     = isset($parts['host'])     ? $parts['host']     : '';
        $port     = isset($parts['port'])     ? $parts['port']     : null;
        $path     = isset($parts['path'])     ? $parts['path']     : '';
        $query    = isset($parts['query'])    ? $parts['query']    : '';
        $fragment = isset($parts['fragment']) ? $parts['fragment'] : '';

        $uri = sprintf('%s://%s', $scheme, $host);
        if (($host && $port)
            && (($scheme === 'https' && $port && $port !== 443)
                || ($scheme === 'http' && $port && $port !== 80))
        ) {
            $uri .= sprintf(':%d', $port);
        }

        if ($path) {
            if ('/' !== $path[0]) {
                $path = '/' . $path;
            }
            $uri .= $path;
        }

        if ($query) {
            $uri .= sprintf('?%s', $query);
        }

        if ($fragment) {
            $uri .= sprintf('#%s', $fragment);
        }

        return new static($uri);
    }

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
     * Not using filter_var + FILTER_VALIDATE_URL because perfectly valid
     * URIs were being flagged as invalid (e.g., https://local.example.com:3001/foo).
     *
     * @return bool
     */
    public function isValid()
    {
        $parts = parse_url($this->uri);
        if (! isset($parts['scheme']) || empty($parts['scheme'])) {
            return false;
        }

        $scheme = strtolower($parts['scheme']);
        if ($scheme !== 'file'
            && (! isset($parts['host']) || empty($parts['host']))
        ) {
            return false;
        }

        if (in_array($scheme, ['http', 'https'])
            && (! isset($parts['path']) || empty($parts['path']))
        ) {
            return false;
        }

        return true;
    }

    /**
     * Set a new path in the URI
     *
     * Returns a cloned version of the URI instance, with the new path.
     *
     * If the path is not valid, raises an exception.
     * 
     * @param  string $path 
     * @return Uri
     * @throws InvalidArgumentException.php
     */
    public function setPath($path)
    {
        if (! $this->isValid()) {
            throw new InvalidArgumentException('Cannot set path on invalid URI');
        }

        $path = $path ?: '/';
        if ($path[0] !== '/') {
            $path = '/' . $path;
        }

        $new = clone $this;
        $new->path = $path;
        $new->uri  = static::fromArray([
            'scheme'   => $new->scheme,
            'host'     => $new->host,
            'port'     => $new->port,
            'path'     => $path,
            'query'    => $new->query,
            'fragment' => $new->fragment,
        ]);

        if (! $new->isValid()) {
            throw new InvalidArgumentException('Invalid path provided');
        }
        return $new;
    }

    /**
     * Parse a URI into its parts, and set the properties
     */
    private function parseUri()
    {
        $parts = parse_url($this->uri);

        $this->scheme   = $parts['scheme'];
        $this->host     = $parts['host'];
        $this->port     = isset($parts['port'])     ? $parts['port']     : null;
        $this->path     = $parts['path'];
        $this->query    = isset($parts['query'])    ? $parts['query']    : '';
        $this->fragment = isset($parts['fragment']) ? $parts['fragment'] : '';

        if ($this->scheme === 'http'
            && $this->port
            && $this->port !== 80
        ) {
            $this->domain = sprintf('%s:%d', $this->host, $this->port);
        } elseif ($this->scheme === 'https'
            && $this->port
            && $this->port !== 443
        ) {
            $this->domain = sprintf('%s:%d', $this->host, $this->port);
        } else {
            $this->domain = $this->host;
        }
    }
}
