<?php
namespace PhlyTest\Conduit\Http;

use Phly\Conduit\Http\Uri;
use PHPUnit_Framework_TestCase as TestCase;

class UriTest extends TestCase
{
    public function uriFragmentsAndRelatedStrings()
    {
        return [
            'nothing' => [[], 'http://'],
            'scheme-only' => [[
                'scheme' => 'file',
            ], 'file://'],
            'host-only' => [[
                'host' => 'localhost',
            ], 'http://localhost'],
            'host-and-port' => [[
                'host' => 'localhost',
                'port' => 3001,
            ], 'http://localhost:3001'],
            'host-and-port-80' => [[
                'host' => 'localhost',
                'port' => 80,
            ], 'http://localhost'],
            'https-host-and-port' => [[
                'scheme' => 'https',
                'host' => 'localhost',
                'port' => 3001,
            ], 'https://localhost:3001'],
            'https-host-and-port-443' => [[
                'scheme' => 'https',
                'host' => 'localhost',
                'port' => 443,
            ], 'https://localhost'],
            'host-and-path' => [[
                'host' => 'localhost',
                'path' => '/foo/bar',
            ], 'http://localhost/foo/bar'],
            'host-and-path-no-leading-slash' => [[
                'host' => 'localhost',
                'path' => 'foo/bar',
            ], 'http://localhost/foo/bar'],
            'host-no-path-query' => [[
                'host' => 'localhost',
                'query' => 'foo=bar',
            ], 'http://localhost?foo=bar'],
            'host-no-path-fragment' => [[
                'host' => 'localhost',
                'fragment' => 'foo',
            ], 'http://localhost#foo'],
            'host-path-query' => [[
                'host' => 'localhost',
                'path' => '/path',
                'query' => 'foo=bar',
            ], 'http://localhost/path?foo=bar'],
            'host-path-fragment' => [[
                'host' => 'localhost',
                'path' => '/path',
                'fragment' => 'foo',
            ], 'http://localhost/path#foo'],
            'host-path-query-fragment' => [[
                'host' => 'localhost',
                'path' => '/path',
                'query' => 'foo=bar',
                'fragment' => 'foo',
            ], 'http://localhost/path?foo=bar#foo'],
        ];
    }

    /**
     * @dataProvider uriFragmentsAndRelatedStrings
     */
    public function testUriCreation($parts, $expected)
    {
        $this->assertEquals($expected, Uri::fromArray($parts)->uri);
    }
}
