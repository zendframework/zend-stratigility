<?php
namespace PhlyTest\Conduit;

use Exception;
use Phly\Conduit\FinalHandler;
use Phly\Conduit\Http\Request;
use Phly\Conduit\Http\Response;
use Phly\Http\ServerRequest as PsrRequest;
use Phly\Http\Response as PsrResponse;
use PHPUnit_Framework_TestCase as TestCase;
use Zend\Escaper\Escaper;

class FinalHandlerTest extends TestCase
{
    public function setUp()
    {
        $psrRequest = new PsrRequest('php://memory');
        $psrRequest = $psrRequest->setMethod('GET');
        $psrRequest = $psrRequest->setAbsoluteUri('http://example.com/');

        $this->escaper  = new Escaper();
        $this->request  = new Request($psrRequest);
        $this->response = new Response(new PsrResponse());
        $this->final    = new FinalHandler($this->request, $this->response);
    }

    public function testInvokingWithErrorAndNoStatusCodeSetsStatusTo500()
    {
        $error    = 'error';
        $response = call_user_func($this->final, $error);
        $this->assertEquals(500, $response->getStatusCode());
    }

    public function testInvokingWithExceptionWithValidCodeSetsStatusToExceptionCode()
    {
        $error    = new Exception('foo', 400);
        $response = call_user_func($this->final, $error);
        $this->assertEquals(400, $response->getStatusCode());
    }

    public function testInvokingWithExceptionWithInvalidCodeSetsStatusTo500()
    {
        $error    = new Exception('foo', 32001);
        $response = call_user_func($this->final, $error);
        $this->assertEquals(500, $response->getStatusCode());
    }

    public function testInvokingWithErrorInNonProductionModeSetsResponseBodyToError()
    {
        $error    = 'error';
        $response = call_user_func($this->final, $error);
        $this->assertEquals($error, (string) $response->getBody());
    }

    public function testInvokingWithExceptionInNonProductionModeIncludesExceptionMessageInResponseBody()
    {
        $error    = new Exception('foo', 400);
        $response = call_user_func($this->final, $error);
        $expected = $this->escaper->escapeHtml($error->getMessage());
        $this->assertContains($expected, (string) $response->getBody());
    }

    public function testInvokingWithExceptionInNonProductionModeIncludesTraceInResponseBody()
    {
        $error    = new Exception('foo', 400);
        $response = call_user_func($this->final, $error);
        $expected = $this->escaper->escapeHtml($error->getTraceAsString());
        $this->assertContains($expected, (string) $response->getBody());
    }

    public function testInvokingWithErrorInProductionSetsResponseToReasonPhrase()
    {
        $final = new FinalHandler($this->request, $this->response, [
            'env' => 'production',
        ]);
        $error    = new Exception('foo', 400);
        $response = $final($error);
        $this->assertEquals($response->getReasonPhrase(), (string) $response->getBody());
    }

    public function testTriggersOnErrorCallableWithErrorWhenPresent()
    {
        $error     = (object) ['error' => true];
        $triggered = null;
        $callback  = function ($error, $request, $response) use (&$triggered) {
            $triggered = func_get_args();
        };

        $final = new FinalHandler($this->request, $this->response, [
            'env' => 'production',
            'onerror' => $callback,
        ]);
        $response = $final($error);
        $this->assertInternalType('array', $triggered);
        $this->assertEquals(3, count($triggered));
        $this->assertSame($error, array_shift($triggered));
        $this->assertSame($this->request, array_shift($triggered));
        $this->assertSame($response, array_shift($triggered));
    }

    public function testCreates404ResponseWhenNoErrorIsPresent()
    {
        $response = call_user_func($this->final);
        $this->assertEquals(404, $response->getStatusCode());
    }

    public function test404ResponseIncludesOriginalRequestAbsoluteUri()
    {
        $originalUrl = 'http://local.example.com/bar/foo';
        $psrRequest  = new PsrRequest('php://memory');
        $psrRequest  = $psrRequest->setMethod('GET');
        $psrRequest  = $psrRequest->setAbsoluteUri($originalUrl);
        $request     = new Request($psrRequest);
        $request     = $request->setAbsoluteUri('http://local.example.com/foo');

        $final    = new FinalHandler($request, $this->response);
        $response = call_user_func($final);
        $this->assertContains($originalUrl, (string) $response->getBody());
    }
}
