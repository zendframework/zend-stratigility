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
        $this->response = new Response(new PsrResponse());
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
}
