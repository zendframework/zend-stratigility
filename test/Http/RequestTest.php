<?php
namespace PhlyTest\Conduit\Http;

use Phly\Conduit\Http\Request;
use Phly\Http\IncomingRequest as PsrRequest;
use Phly\Http\Uri;
use PHPUnit_Framework_TestCase as TestCase;

class RequestTest extends TestCase
{
    public function setUp()
    {
        $this->original = new PsrRequest();
        $this->request  = new Request($this->original);
    }

    public function testAllowsManipulatingArbitraryNonPrivateProperties()
    {
        $this->request->originalUrl = 'http://foo.example.com/foo';
        $this->assertTrue(isset($this->request->originalUrl));
        $this->assertEquals('http://foo.example.com/foo', $this->request->originalUrl);
        unset($this->request->originalUrl);
        $this->assertNull($this->request->originalUrl);
    }

    public function testFetchingUnknownPropertyYieldsNull()
    {
        $this->assertNull($this->request->somePropertyWeMadeUp);
    }

    public function testArrayPropertyValueIsCastToArrayObject()
    {
        $original = ['test' => 'value'];
        $this->request->anArray = $original;
        $this->assertInstanceOf('ArrayObject', $this->request->anArray);
        $this->assertEquals($original, $this->request->anArray->getArrayCopy());
    }

    public function testCallingSetUrlSetsOriginalUrlProperty()
    {
        $url = 'http://example.com/foo';
        $uri = new Uri($url);
        $this->request->setUrl($uri);
        $this->assertSame($uri, $this->request->originalUrl);
    }

    public function testConstructorSetsOriginalUrlIfDecoratedRequestHasUrl()
    {
        $url = 'http://example.com/foo';
        $baseRequest = new PsrRequest();
        $baseRequest->setUrl($url);
        $request = new Request($baseRequest);
        $this->assertSame($baseRequest->getUrl(), $request->originalUrl);
    }

    public function testCanAccessOriginalRequest()
    {
        $this->assertSame($this->original, $this->request->getOriginalRequest());
    }

    public function testDecoratorProxiesToAllMethods()
    {
        $this->assertEquals('1.1', $this->request->getProtocolVersion());

        $stream = $this->getMock('Psr\Http\Message\StreamableInterface');
        $this->request->setBody($stream);
        $this->assertSame($stream, $this->request->getBody());

        $this->assertSame($this->original->getHeaders(), $this->request->getHeaders());

        $this->request->setHeaders([
            'Accept'       => 'application/json',
            'Content-Type' => 'application/json',
        ]);
        $this->assertSame($this->original->getHeaders(), $this->request->getHeaders());

        $this->request->setHeader('Accept', 'application/xml');
        $this->assertTrue($this->request->hasHeader('Accept'));
        $this->assertEquals('application/xml', $this->request->getHeader('Accept'));

        $this->request->addHeader('X-URL', 'http://example.com/foo');
        $this->assertTrue($this->request->hasHeader('X-URL'));

        $this->request->addHeaders([
            'X-Url'  => 'http://example.com/bar',
            'X-Flag' => 'true',
        ]);
        $this->assertEquals('http://example.com/foo,http://example.com/bar', $this->request->getHeader('X-URL'));
        $this->assertTrue($this->request->hasHeader('X-Flag'));
        $this->assertTrue($this->request->hasHeader('Accept'));
        $this->assertTrue($this->request->hasHeader('Content-Type'));

        $this->request->removeHeader('X-Flag');
        $this->assertFalse($this->request->hasHeader('X-Flag'));
    }
}
