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

        $this->scheme   = $data['scheme'];
        $this->host     = $data['host'];
        $this->port     = $data['port'];
        $this->path     = $data['path'];
        $this->query    = $data['query'];
        $this->fragment = $data['fragment'];

        if ($data['scheme'] === 'http'
            && $data['port'] !== null
            && $data['port'] !== 80
        ) {
            $this->domain = printf('%s:%d', $this->host, $this->port);
        } elseif ($data['scheme'] === 'https'
            && $data['port'] !== null
            && $data['port'] !== 443
        ) {
            $this->domain = printf('%s:%d', $this->host, $this->port);
        } else {
            $this->domain = $this->host;
        }
    }
}
