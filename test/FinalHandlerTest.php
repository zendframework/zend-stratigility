<?php
namespace PhlyTest\Conduit;

use Exception;
use Phly\Conduit\FinalHandler;
use Phly\Conduit\Http\Request;
use Phly\Conduit\Http\Response;
use Phly\Http\ServerRequest as PsrRequest;
use Phly\Http\Response as PsrResponse;
use Phly\Http\Uri;
use PHPUnit_Framework_TestCase as TestCase;
use Zend\Escaper\Escaper;

class FinalHandlerTest extends TestCase
{
    public function setUp()
    {
        $psrRequest     = new PsrRequest([], [], 'http://example.com/', 'GET', 'php://memory');
        $this->escaper  = new Escaper();
        $this->request  = new Request($psrRequest);
        $this->response = new Response(new PsrResponse());
        $this->final    = new FinalHandler();
    }

    public function testInvokingWithErrorAndNoStatusCodeSetsStatusTo500()
    {
        $error    = 'error';
        $response = call_user_func($this->final, $this->request, $this->response, $error);
        $this->assertEquals(500, $response->getStatusCode());
    }

    public function testInvokingWithExceptionWithValidCodeSetsStatusToExceptionCode()
    {
        $error    = new Exception('foo', 400);
        $response = call_user_func($this->final, $this->request, $this->response, $error);
        $this->assertEquals(400, $response->getStatusCode());
    }

    public function testInvokingWithExceptionWithInvalidCodeSetsStatusTo500()
    {
        $error    = new Exception('foo', 32001);
        $response = call_user_func($this->final, $this->request, $this->response, $error);
        $this->assertEquals(500, $response->getStatusCode());
    }

    public function testInvokingWithErrorInNonProductionModeSetsResponseBodyToError()
    {
        $error    = 'error';
        $response = call_user_func($this->final, $this->request, $this->response, $error);
        $this->assertEquals($error, (string) $response->getBody());
    }

    public function testInvokingWithExceptionInNonProductionModeIncludesExceptionMessageInResponseBody()
    {
        $error    = new Exception('foo', 400);
        $response = call_user_func($this->final, $this->request, $this->response, $error);
        $expected = $this->escaper->escapeHtml($error->getMessage());
        $this->assertContains($expected, (string) $response->getBody());
    }

    public function testInvokingWithExceptionInNonProductionModeIncludesTraceInResponseBody()
    {
        $error    = new Exception('foo', 400);
        $response = call_user_func($this->final, $this->request, $this->response, $error);
        $expected = $this->escaper->escapeHtml($error->getTraceAsString());
        $this->assertContains($expected, (string) $response->getBody());
    }

    public function testInvokingWithErrorInProductionSetsResponseToReasonPhrase()
    {
        $final = new FinalHandler([
            'env' => 'production',
        ]);
        $error    = new Exception('foo', 400);
        $response = $final($this->request, $this->response, $error);
        $this->assertEquals($response->getReasonPhrase(), (string) $response->getBody());
    }

    public function testTriggersOnErrorCallableWithErrorWhenPresent()
    {
        $error     = (object) ['error' => true];
        $triggered = null;
        $callback  = function ($error, $request, $response) use (&$triggered) {
            $triggered = func_get_args();
        };

        $final = new FinalHandler([
            'env' => 'production',
            'onerror' => $callback,
        ]);
        $response = $final($this->request, $this->response, $error);
        $this->assertInternalType('array', $triggered);
        $this->assertEquals(3, count($triggered));
        $this->assertSame($error, array_shift($triggered));
        $this->assertSame($this->request, array_shift($triggered));
        $this->assertSame($response, array_shift($triggered));
    }

    public function testCreates404ResponseWhenNoErrorIsPresent()
    {
        $response = call_user_func($this->final, $this->request, $this->response, null);
        $this->assertEquals(404, $response->getStatusCode());
    }

    public function test404ResponseIncludesOriginalRequestUri()
    {
        $originalUrl = 'http://local.example.com/bar/foo';
        $psrRequest  = new PsrRequest([], [], $originalUrl, 'GET', 'php://memory');
        $request     = new Request($psrRequest);
        $request     = $request->withUri(new Uri('http://local.example.com/foo'));

        $final    = new FinalHandler();
        $response = call_user_func($final, $request, $this->response, null);
        $this->assertContains($originalUrl, (string) $response->getBody());
    }
}
