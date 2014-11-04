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
        $this->original = new PsrRequest(
            'http://example.com/',
            'GET',
            [],
            'php://memory'
        );
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
        $this->request->setUrl($url);
        $this->assertSame('http://example.com/', $this->request->originalUrl);
        $this->assertSame($url, $this->request->getUrl());
    }

    public function testConstructorSetsOriginalUrlIfDecoratedRequestHasUrl()
    {
        $url = 'http://example.com/foo';
        $baseRequest = new PsrRequest(
            $url,
            'GET',
            [],
            'php://memory'
        );
        $request = new Request($baseRequest);
        $this->assertSame($baseRequest->getUrl(), $request->originalUrl);
    }

    public function testCanAccessOriginalRequest()
    {
        $this->assertSame($this->original, $this->request->getOriginalRequest());
    }

    public function testDecoratorProxiesToAllMethods()
    {
        $stream = $this->getMock('Psr\Http\Message\StreamableInterface');
        $psrRequest = new PsrRequest(
            'http://example.com/',
            'POST',
            [
                'Accept' => 'application/xml',
                'X-URL'  => 'http://example.com/foo',
            ],
            $stream
        );
        $request = new Request($psrRequest);

        $this->assertEquals('1.1', $request->getProtocolVersion());
        $this->assertSame($stream, $request->getBody());
        $this->assertSame($psrRequest->getHeaders(), $request->getHeaders());
    }

    public function testPropertyAccessProxiesToRequestAttributes()
    {
        $this->original->setAttributes([
            'foo' => 'bar',
            'bar' => 'baz',
        ]);

        $this->assertTrue(isset($this->request->foo));
        $this->assertTrue(isset($this->request->bar));
        $this->assertFalse(isset($this->request->baz));

        $this->request->baz = 'quz';
        $this->assertTrue(isset($this->request->baz));
        $this->assertEquals('quz', $this->original->getAttribute('baz', false));
    }
}
