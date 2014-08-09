<?php
namespace Phly\Conduit\Http;

class Uri
{
    private $scheme;
    private $host;
    private $port;
    private $path;
    private $query;
    private $fragment;

    private $domain;
    private $uri;

    public function __construct($uri)
    {
        $this->uri = $uri;
        if ($this->isValid()) {
            $this->parseUri();
        }
    }

    public function __get($name)
    {
        if (! property_exists($this, $name)) {
            return null;
        }

        return $this->{$name};
    }

    public function __toString()
    {
        return $this->uri;
    }

    public function isValid()
    {
        return filter_var(
            $this->uri,
            FILTER_VALIDATE_URL,
            FILTER_FLAG_PATH_REQUIRED
        );
    }

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
