<?php
namespace PhlyTest\Conduit\Http;

use Phly\Conduit\Http\Request;
use Phly\Conduit\Http\Stream;
use PHPUnit_Framework_TestCase as TestCase;

class AbstractMessageTest extends TestCase
{
    public function setUp()
    {
        $this->stream  = new Stream('php://memory', 'wb+');
        $this->message = new Request('1.1', $this->stream);
    }

    public function testUsesProtocolVersionProvidedInConstructor()
    {
        $message = new Request('1.0', $this->stream);
        $this->assertEquals('1.0', $message->getProtocolVersion());
    }

    public function testUsesStreamProvidedInConstructorAsBody()
    {
        $this->assertSame($this->stream, $this->message->getBody());
    }

    public function testBodyIsMutable()
    {
        $stream  = new Stream('php://memory', 'wb+');
        $this->message->setBody($stream);
        $this->assertSame($stream, $this->message->getBody());
    }

    public function testCanSetHeaders()
    {
        $headers = array(
            'Origin'        => 'http://example.com',
            'Accept'        => 'application/json',
            'Content-Type'  => 'application/json',
            'Authorization' => 'Bearer foobartoken',
        );

        $this->message->setHeaders($headers);
        $expected = array_change_key_case($headers);
        array_walk($expected, function (&$value) {
            $value = [$value];
        });
        $this->assertEquals($expected, $this->message->getHeaders());
    }

    public function testGetHeaderAsArrayReturnsHeaderValueAsArray()
    {
        $this->message->setHeader('X-Foo', ['Foo', 'Bar']);
        $this->assertEquals(['Foo', 'Bar'], $this->message->getHeaderAsArray('X-Foo'));
    }

    public function testGetHeaderReturnsHeaderValueAsCommaConcatenatedString()
    {
        $this->message->setHeader('X-Foo', ['Foo', 'Bar']);
        $this->assertEquals('Foo,Bar', $this->message->getHeader('X-Foo'));
    }

    public function testHasHeaderReturnsFalseIfHeaderIsNotPresent()
    {
        $this->assertFalse($this->message->hasHeader('X-Foo'));
    }

    public function testHasHeaderReturnsTrueIfHeaderIsPresent()
    {
        $this->message->setHeader('X-Foo', 'Foo');
        $this->assertTrue($this->message->hasHeader('X-Foo'));
    }

    public function testAddHeaderAppendsToExistingHeader()
    {
        $this->message->setHeader('X-Foo', 'Foo');
        $this->message->addHeader('X-Foo', 'Bar');
        $this->assertEquals('Foo,Bar', $this->message->getHeader('X-Foo'));
    }

    public function testAddHeadersMergesWithExistingHeaders()
    {
        $headers = [
            'Origin'        => 'http://example.com',
            'Accept'        => 'application/json',
            'Content-Type'  => 'application/json',
            'Authorization' => 'Bearer foobartoken',
        ];
        $this->message->setHeaders($headers);

        $this->message->addHeaders([
            'Accept' => 'application/*+json',
            'X-Foo'  => 'Foo',
        ]);

        $this->assertEquals(['application/json', 'application/*+json'], $this->message->getHeaderAsArray('accept'));
        $this->assertEquals('Foo', $this->message->getHeader('x-foo'));
    }

    public function testCanRemoveHeaders()
    {
        $this->message->setHeader('X-Foo', 'Foo');
        $this->assertTrue($this->message->hasHeader('x-foo'));
        $this->message->removeHeader('x-foo');
        $this->assertFalse($this->message->hasHeader('X-Foo'));
    }
}
