<?php
namespace PhlyTest\Conduit\Http;

use Phly\Conduit\Http\Response;
use PHPUnit_Framework_TestCase as TestCase;

class ResponseTest extends TestCase
{
    public function setUp()
    {
        $this->response = new Response();
    }

    public function testStatusCodeIs200ByDefault()
    {
        $this->assertEquals(200, $this->response->getStatusCode());
    }

    public function testStatusCodeIsMutable()
    {
        $this->response->setStatusCode(400);
        $this->assertEquals(400, $this->response->getStatusCode());
    }

    public function invalidStatusCodes()
    {
        return [
            'too-low' => [99],
            'too-high' => [600],
            'null' => [null],
            'bool' => [true],
            'string' => ['100'],
            'array' => [[200]],
            'object' => [(object) [200]],
        ];
    }

    /**
     * @dataProvider invalidStatusCodes
     */
    public function testCannotSetInvalidStatusCode($code)
    {
        $this->setExpectedException('InvalidArgumentException');
        $this->response->setStatusCode($code);
    }

    public function testReasonPhraseDefaultsToStandards()
    {
        $this->response->setStatusCode(422);
        $this->assertEquals('Unprocessable Entity', $this->response->getReasonPhrase());
    }

    public function testCanSetCustomReasonPhrase()
    {
        $this->response->setStatusCode(422);
        $this->response->setReasonPhrase('FOO BAR!');
        $this->assertEquals('FOO BAR!', $this->response->getReasonPhrase());
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
}
