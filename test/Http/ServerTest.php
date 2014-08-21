<?php
namespace PhlyTest\Conduit\Http;

use Phly\Conduit\Http\Output; // test asset
use Phly\Conduit\Http\Server;
use Phly\Conduit\Middleware;
use PHPUnit_Framework_TestCase as TestCase;

require_once __DIR__ . '/TestAsset/Functions.php';

class ServerTest extends TestCase
{
    public function setUp()
    {
        Output::$headers = array();
        Output::$body    = null;

        $this->middleware = $this->getMock('Phly\Conduit\Middleware');
        $this->request    = $this->getMock('Psr\Http\Message\RequestInterface');
        $this->response   = $this->getMock('Phly\Conduit\Http\ResponseInterface');
    }

    public function tearDown()
    {
        Output::$headers = array();
        Output::$body    = null;
    }

    public function testCreateServerFromRequestReturnsServerInstanceWithProvidedObjects()
    {
        $server = Server::createServerFromRequest(
            $this->middleware,
            $this->request,
            $this->response
        );
        $this->assertInstanceOf('Phly\Conduit\Http\Server', $server);
        $this->assertSame($this->middleware, $server->middleware);
        $this->assertSame($this->request, $server->request);
        $this->assertSame($this->response, $server->response);
    }

    public function testCreateServerFromRequestWillCreateResponseIfNotProvided()
    {
        $server = Server::createServerFromRequest(
            $this->middleware,
            $this->request
        );
        $this->assertInstanceOf('Phly\Conduit\Http\Server', $server);
        $this->assertSame($this->middleware, $server->middleware);
        $this->assertSame($this->request, $server->request);
        $this->assertInstanceOf('Phly\Conduit\Http\Response', $server->response);
    }

    public function testCannotAccessArbitraryProperties()
    {
        $server = new Server(
            $this->middleware,
            $this->request,
            $this->response
        );
        $prop = uniqid();
        $this->setExpectedException('OutOfBoundsException');
        $server->$prop;
    }

    public function testCreateServerWillCreateDefaultInstancesForRequestAndResponse()
    {
        $server = [
            'HTTP_HOST' => 'example.com',
            'HTTP_ACCEPT' => 'application/json',
            'REQUEST_METHOD' => 'POST',
            'REQUEST_URI' => '/foo/bar',
            'QUERY_STRING' => 'bar=baz',
        ];
        $server = Server::createServer($this->middleware, $server);
        $this->assertInstanceOf('Phly\Conduit\Http\Server', $server);
        $this->assertSame($this->middleware, $server->middleware);

        $this->assertInstanceOf('Phly\Conduit\Http\Request', $server->request);
        $request = $server->request;
        $this->assertEquals('POST', $request->getMethod());
        $this->assertEquals('/foo/bar', $request->getUrl()->path);
        $this->assertTrue($request->hasHeader('Accept'));

        $this->assertInstanceOf('Phly\Conduit\Http\Response', $server->response);
    }

    public function testListenInvokesMiddlewareAndSendsResponse()
    {
        $server = [
            'HTTP_HOST' => 'example.com',
            'HTTP_ACCEPT' => 'application/json',
            'REQUEST_METHOD' => 'POST',
            'REQUEST_URI' => '/foo/bar',
            'QUERY_STRING' => 'bar=baz',
        ];

        $middleware = new Middleware();
        $middleware->pipe(function ($req, $res) {
            $res->addHeader('Content-Type', 'text/plain');
            $res->end('FOOBAR');
        });
        $server = Server::createServer($middleware, $server);
        $server->listen();

        $this->assertContains('HTTP/1.1 200 OK', Output::$headers);
        $this->assertContains('Content-Type: text/plain', Output::$headers);
        $this->assertEquals('FOOBAR', Output::$body);
    }

    public function testListenEmitsStatusHeaderWithoutReasonPhraseIfNoReasonPhrase()
    {
        $server = [
            'HTTP_HOST' => 'example.com',
            'HTTP_ACCEPT' => 'application/json',
            'REQUEST_METHOD' => 'POST',
            'REQUEST_URI' => '/foo/bar',
            'QUERY_STRING' => 'bar=baz',
        ];

        $middleware = new Middleware();
        $middleware->pipe(function ($req, $res) {
            $res->setStatusCode(299);
            $res->addHeader('Content-Type', 'text/plain');
            $res->end('FOOBAR');
        });
        $server = Server::createServer($middleware, $server);
        $server->listen();

        $this->assertContains('HTTP/1.1 299', Output::$headers);
        $this->assertContains('Content-Type: text/plain', Output::$headers);
        $this->assertEquals('FOOBAR', Output::$body);
    }
}
