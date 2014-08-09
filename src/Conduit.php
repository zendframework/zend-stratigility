<?php
namespace Phly\Conduit;

use ArrayObject;
use Psr\Http\Message\RequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;

class Conduit
{
    private $stack;

    public function __construct()
    {
        $this->stack = new ArrayObject(array());
    }

    /**
     * Was "use", but "use" is a reserved keyword in PHP
     */
    public function attach($route, $handler)
    {
    }

    public function handle(Request $request, Response $response, $out)
    {
        $stack = $this->stack;
        $url   = $request->getUrl();
        list($scheme, $domain, $path, $query) = $this->parseUrl($url);

    }

    private function parseUrl($url)
    {
        $data = parse_url($url);
        $domain = $data['host'];
        if (($data['scheme'] === 'http' && $data['port'] != 80)
            || ($data['scheme'] === 'https' && $data['port'] != 443)
        ) {
            $domain .= ':' . $data['port'];
        }

        return array(
            $data['scheme'],
            $domain,
            $data['path'] || '/',
            $data['query'],
        );
    }
}
