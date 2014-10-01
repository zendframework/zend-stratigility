<?php
namespace PhlyTest\Conduit\Http;

use Phly\Conduit\Http\Response;
use Phly\Http\Response as PsrResponse;
use Phly\Http\Stream;
use PHPUnit_Framework_TestCase as TestCase;

class ResponseTest extends TestCase
{
    public function setUp()
    {
        $this->original = new PsrResponse();
        $this->response = new Response($this->original);
    }

    public function testIsNotCompleteByDefault()
    {
        $this->assertFalse($this->response->isComplete());
    }

    public function testCallingEndMarksAsComplete()
    {
        $this->response->end();
        $this->assertTrue($this->response->isComplete());
    }

    public function testWriteAppendsBody()
    {
        $this->response->write("First\n");
        $this->assertContains('First', (string) $this->response->getBody());
        $this->response->write("Second\n");
        $this->assertContains('First', (string) $this->response->getBody());
        $this->assertContains('Second', (string) $this->response->getBody());
    }

    public function testCannotMutateResponseAfterCallingEnd()
    {
        $this->response->setStatusCode(201);
        $this->response->write("First\n");
        $this->response->end('DONE');

        $this->response->setStatusCode(200);
        $this->response->setHeader('X-Foo', 'Foo');
        $this->response->write('MOAR!');

        $this->assertEquals(201, $this->response->getStatusCode());
        $this->assertFalse($this->response->hasHeader('X-Foo'));
        $this->assertNotContains('MOAR!', (string) $this->response->getBody());
        $this->assertContains('First', (string) $this->response->getBody());
        $this->assertContains('DONE', (string) $this->response->getBody());
    }

    public function testSetBodyReturnsEarlyIfComplete()
    {
        $this->response->end('foo');

        $body = new Stream('php://memory', 'r+');
        $this->response->setBody($body);

        $this->assertEquals('foo', (string) $this->response->getBody());
    }

    public function testSetHeadersDoesNothingIfComplete()
    {
        $this->response->end('foo');
        $this->response->setHeaders([
            'Content-Type' => 'application/json',
        ]);
        $this->assertFalse($this->response->hasHeader('Content-Type'));
    }

    public function testAddHeaderDoesNothingIfComplete()
    {
        $this->response->end('foo');
        $this->response->addHeader('Content-Type', 'application/json');
        $this->assertFalse($this->response->hasHeader('Content-Type'));
    }

    public function testAddHeadersDoesNothingIfComplete()
    {
        $this->response->end('foo');
        $this->response->addHeaders([
            'Content-Type' => 'application/json',
        ]);
        $this->assertFalse($this->response->hasHeader('Content-Type'));
    }

    public function testCallingEndMultipleTimesDoesNothingAfterFirstCall()
    {
        $this->response->end('foo');
        $this->response->end('bar');
        $this->assertEquals('foo', (string) $this->response->getBody());
    }

    public function testCanAccessOriginalResponse()
    {
        $this->assertSame($this->original, $this->response->getOriginalResponse());
    }

    public function testDecoratorProxiesToAllMethods()
    {
        $this->assertEquals('1.1', $this->response->getProtocolVersion());

        $stream = $this->getMock('Psr\Http\Message\StreamableInterface');
        $this->response->setBody($stream);
        $this->assertSame($stream, $this->response->getBody());

        $this->assertSame($this->original->getHeaders(), $this->response->getHeaders());

        $this->response->setHeaders([
            'Accept'       => 'application/json',
            'Content-Type' => 'application/json',
        ]);
        $this->assertSame($this->original->getHeaders(), $this->response->getHeaders());

        $this->response->setHeader('Accept', 'application/xml');
        $this->assertTrue($this->response->hasHeader('Accept'));
        $this->assertEquals('application/xml', $this->response->getHeader('Accept'));

        $this->response->addHeader('X-URL', 'http://example.com/foo');
        $this->assertTrue($this->response->hasHeader('X-URL'));

        $this->response->addHeaders([
            'X-Url'  => 'http://example.com/bar',
            'X-Flag' => 'true',
        ]);
        $this->assertEquals('http://example.com/foo,http://example.com/bar', $this->response->getHeader('X-URL'));
        $this->assertTrue($this->response->hasHeader('X-Flag'));
        $this->assertTrue($this->response->hasHeader('Accept'));
        $this->assertTrue($this->response->hasHeader('Content-Type'));

        $this->response->removeHeader('X-Flag');
        $this->assertFalse($this->response->hasHeader('X-Flag'));

        $this->response->setReasonPhrase('FOOBAR');
        $this->assertEquals('FOOBAR', $this->response->getReasonPhrase());
    }

    public function testReasonPhraseMayBeUpdatedWhenResponseIsCompleteAndPhraseIsNull()
    {
        $this->response->setStatusCode(499);
        $this->response->end('foo');
        $this->response->setReasonPhrase('FOOBAR');
        $this->assertEquals('FOOBAR', $this->response->getReasonPhrase());
    }

    public function testReasonPhraseIsImmutableWhenAlreadySetAndResponseIsComplete()
    {
        $this->response->setStatusCode(499);
        $this->response->setReasonPhrase('FOOBAR');
        $this->response->end('foo');
        $this->response->setReasonPhrase('BARBAZ');
        $this->assertEquals('FOOBAR', $this->response->getReasonPhrase());
    }
}
