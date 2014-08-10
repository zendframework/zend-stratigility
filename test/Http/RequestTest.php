<?php
namespace PhlyTest\Conduit\Http;

use Phly\Conduit\Http\Request;
use PHPUnit_Framework_TestCase as TestCase;

class RequestTest extends TestCase
{
    public function setUp()
    {
        $this->request = new Request();
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

    public function testMethodIsNullByDefault()
    {
        $this->assertNull($this->request->getMethod());
    }

    public function testMethodIsMutable()
    {
        $this->request->setMethod('GET');
        $this->assertEquals('GET', $this->request->getMethod());
    }

    public function testUrlIsNullByDefault()
    {
        $this->assertNull($this->request->getUrl());
    }

    public function testSetUrlCastsStringsToUriObjects()
    {
        $url = 'http://test.example.com/foo';
        $this->request->setUrl($url);
        $uri = $this->request->getUrl();
        $this->assertInstanceOf('Phly\Conduit\Http\Uri', $uri);
        $this->assertEquals($url, $uri->uri);
    }
}
