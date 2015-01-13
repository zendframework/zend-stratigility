<?php
namespace PhlyTest\Conduit\Http;

use Phly\Conduit\Http\Request;
use Phly\Http\ServerRequest as PsrRequest;
use Phly\Http\Uri;
use PHPUnit_Framework_TestCase as TestCase;

class RequestTest extends TestCase
{
    public function setUp()
    {
        $psrRequest = new PsrRequest('php://memory');
        $psrRequest = $psrRequest->setMethod('GET');
        $psrRequest = $psrRequest->setAbsoluteUri('http://example.com/');
        $this->original = $psrRequest;
        $this->request  = new Request($this->original);
    }

    public function testCallingSetAbsoluteUriSetsUriInRequestAndOriginalRequestInClone()
    {
        $url = 'http://example.com/foo';
        $request = $this->request->setAbsoluteUri($url);
        $this->assertNotSame($this->request, $request);
        $this->assertSame($this->original, $request->getOriginalRequest());
        $this->assertSame($url, $request->getAbsoluteUri());
    }

    public function testCallingSetUrlSetsOriginalUrlPropertyInClone()
    {
        $url = '/foo';
        $request = $this->request->setUrl($url);
        $this->assertNotSame($this->request, $request);
        $this->assertSame('/', $request->getAttribute('originalUrl'));
        $this->assertSame($url, $request->getUrl());
    }

    public function testConstructorSetsOriginalRequestIfNoneProvided()
    {
        $url = 'http://example.com/foo';
        $baseRequest = new PsrRequest('php://memory');
        $baseRequest = $baseRequest->setMethod('GET');
        $baseRequest = $baseRequest->setAbsoluteUri($url);

        $request = new Request($baseRequest);
        $this->assertSame($baseRequest, $request->getOriginalRequest());
    }

    public function testCallingSettersRetainsOriginalRequest()
    {
        $url = 'http://example.com/foo';
        $baseRequest = new PsrRequest('php://memory');
        $baseRequest = $baseRequest->setMethod('GET');
        $baseRequest = $baseRequest->setAbsoluteUri($url);

        $request = new Request($baseRequest);
        $request = $request->setMethod('POST');
        $new     = $request->addHeader('X-Foo', 'Bar');

        $this->assertNotSame($request, $new);
        $this->assertNotSame($baseRequest, $new);
        $this->assertNotSame($baseRequest, $new->getCurrentRequest());
        $this->assertSame($baseRequest, $new->getOriginalRequest());
    }

    public function testCanAccessOriginalRequest()
    {
        $this->assertSame($this->original, $this->request->getOriginalRequest());
    }

    public function testDecoratorProxiesToAllMethods()
    {
        $stream = $this->getMock('Psr\Http\Message\StreamableInterface');
        $psrRequest = new PsrRequest($stream);
        $psrRequest = $psrRequest->setMethod('POST');
        $psrRequest = $psrRequest->setAbsoluteUri('http://example.com/');
        $psrRequest = $psrRequest->setHeader('Accept', 'application/xml');
        $psrRequest = $psrRequest->setHeader('X-URL', 'http://example.com/foo');
        $request = new Request($psrRequest);

        $this->assertEquals('1.1', $request->getProtocolVersion());
        $this->assertSame($stream, $request->getBody());
        $this->assertSame($psrRequest->getHeaders(), $request->getHeaders());
    }
}
