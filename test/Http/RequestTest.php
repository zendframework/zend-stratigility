<?php
namespace PhlyTest\Conduit\Http;

use Phly\Conduit\Http\Request;
use Phly\Http\Request as PsrRequest;
use PHPUnit_Framework_TestCase as TestCase;

class RequestTest extends TestCase
{
    public function setUp()
    {
        $this->request = new Request(new PsrRequest());
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
}
