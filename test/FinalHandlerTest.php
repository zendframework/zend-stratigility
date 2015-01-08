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
        $psrRequest  = new PsrRequest('php://memory');
        $psrRequest->setMethod('GET');
        $psrRequest->setAbsoluteUri('http://example.com/');

        $this->escaper  = new Escaper();
        $this->request  = new Request($psrRequest);
        $this->response = new Response(new PsrResponse());
        $this->final    = new FinalHandler($this->request, $this->response);
    }

    public function testInvokingWithErrorAndNoStatusCodeSetsStatusTo500()
    {
        $error = 'error';
        call_user_func($this->final, $error);
        $this->assertEquals(500, $this->response->getStatusCode());
    }

    public function testInvokingWithExceptionWithValidCodeSetsStatusToExceptionCode()
    {
        $error = new Exception('foo', 400);
        call_user_func($this->final, $error);
        $this->assertEquals(400, $this->response->getStatusCode());
    }

    public function testInvokingWithExceptionWithInvalidCodeSetsStatusTo500()
    {
        $error = new Exception('foo', 32001);
        call_user_func($this->final, $error);
        $this->assertEquals(500, $this->response->getStatusCode());
    }

    public function testInvokingWithErrorInNonProductionModeSetsResponseBodyToError()
    {
        $error = 'error';
        call_user_func($this->final, $error);
        $this->assertEquals($error, (string) $this->response->getBody());
    }

    public function testInvokingWithExceptionInNonProductionModeIncludesExceptionMessageInResponseBody()
    {
        $error = new Exception('foo', 400);
        call_user_func($this->final, $error);
        $expected = $this->escaper->escapeHtml($error->getMessage());
        $this->assertContains($expected, (string) $this->response->getBody());
    }

    public function testInvokingWithExceptionInNonProductionModeIncludesTraceInResponseBody()
    {
        $error = new Exception('foo', 400);
        call_user_func($this->final, $error);
        $expected = $this->escaper->escapeHtml($error->getTraceAsString());
        $this->assertContains($expected, (string) $this->response->getBody());
    }

    public function testInvokingWithErrorInProductionSetsResponseToReasonPhrase()
    {
        $final = new FinalHandler($this->request, $this->response, [
            'env' => 'production',
        ]);
        $error = new Exception('foo', 400);
        $final($error);
        $this->assertEquals($this->response->getReasonPhrase(), (string) $this->response->getBody());
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
        $final($error);
        $this->assertInternalType('array', $triggered);
        $this->assertEquals(3, count($triggered));
        $this->assertSame($error, array_shift($triggered));
        $this->assertSame($this->request, array_shift($triggered));
        $this->assertSame($this->response, array_shift($triggered));
    }

    public function testCreates404ResponseWhenNoErrorIsPresent()
    {
        call_user_func($this->final);
        $this->assertEquals(404, $this->response->getStatusCode());
    }

    public function test404ResponseIncludesOriginalRequestUrl()
    {
        $originalUrl = 'http://local.example.com/bar/foo';
        $psrRequest = new PsrRequest('php://memory');
        $psrRequest->setMethod('GET');
        $psrRequest->setAbsoluteUri($originalUrl);
        $request = new Request($psrRequest);
        $request->setUrl('http://local.example.com/foo');

        $final = new FinalHandler($request, $this->response);
        call_user_func($final);
        $this->assertContains($originalUrl, (string) $this->response->getBody());
    }
}
