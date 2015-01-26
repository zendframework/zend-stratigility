<?php
namespace PhlyTest\Conduit;

use Phly\Conduit\Dispatch;
use Phly\Conduit\Route;
use PHPUnit_Framework_TestCase as TestCase;
use RuntimeException;

class DispatchTest extends TestCase
{
    public function setUp()
    {
        $this->request  = $this->getMockBuilder('Phly\Conduit\Http\Request')
            ->disableOriginalConstructor()
            ->getMock();
        $this->response = $this->getMockBuilder('Phly\Conduit\Http\Response')
            ->disableOriginalConstructor()
            ->getMock();
    }

    public function testHasErrorAndHandleArityIsFourTriggersHandler()
    {
        $phpunit   = $this;
        $triggered = false;

        $handler = function ($err, $req, $res, $next) use (&$triggered) {
            $triggered = $err;
        };
        $next = function ($req, $res, $err) use ($phpunit) {
            $phpunit->fail('Next was called; it should not have been');
        };

        $route = new Route('/foo', $handler);
        $dispatch = new Dispatch();
        $err = (object) ['error' => true];
        $dispatch($route, $err, $this->request, $this->response, $next);
        $this->assertSame($err, $triggered);
    }

    public function testHasErrorAndHandleArityLessThanFourTriggersNextWithError()
    {
        $phpunit   = $this;
        $triggered = false;

        $handler = function ($req, $res, $next) use ($phpunit) {
            $phpunit->fail('Handler was called; it should not have been');
        };
        $next = function ($req, $res, $err) use (&$triggered) {
            $triggered = $err;
        };

        $route = new Route('/foo', $handler);
        $dispatch = new Dispatch();
        $err = (object) ['error' => true];
        $dispatch($route, $err, $this->request, $this->response, $next);
        $this->assertSame($err, $triggered);
    }

    public function testNoErrorAndHandleArityGreaterThanThreeTriggersNext()
    {
        $phpunit   = $this;
        $triggered = false;

        $handler = function ($err, $req, $res, $next) use ($phpunit) {
            $phpunit->fail('Handler was called; it should not have been');
        };
        $next = function ($req, $res, $err) use (&$triggered) {
            $triggered = $err;
        };

        $route = new Route('/foo', $handler);
        $dispatch = new Dispatch();
        $err = null;
        $dispatch($route, $err, $this->request, $this->response, $next);
        $this->assertSame($err, $triggered);
    }

    public function testNoErrorAndHandleArityLessThanFourTriggersHandler()
    {
        $phpunit   = $this;
        $triggered = false;

        $handler = function ($req, $res, $next) use (&$triggered) {
            $triggered = $req;
        };
        $next = function ($req, $res, $err) use ($phpunit) {
            $phpunit->fail('Next was called; it should not have been');
        };

        $route = new Route('/foo', $handler);
        $dispatch = new Dispatch();
        $err = null;
        $dispatch($route, $err, $this->request, $this->response, $next);
        $this->assertSame($this->request, $triggered);
    }

    public function testThrowingExceptionInErrorHandlerTriggersNextWithException()
    {
        $phpunit   = $this;
        $exception = new RuntimeException;
        $triggered = null;

        $handler = function ($err, $req, $res, $next) use ($exception) {
            throw $exception;
        };
        $next = function ($req, $res, $err) use (&$triggered) {
            $triggered = $err;
        };

        $route = new Route('/foo', $handler);
        $dispatch = new Dispatch();
        $err = (object) ['error' => true];
        $dispatch($route, $err, $this->request, $this->response, $next);
        $this->assertSame($exception, $triggered);
    }

    public function testThrowingExceptionInNonErrorHandlerTriggersNextWithException()
    {
        $phpunit   = $this;
        $exception = new RuntimeException;
        $triggered = null;

        $handler = function ($req, $res, $next) use ($exception) {
            throw $exception;
        };
        $next = function ($req, $res, $err) use (&$triggered) {
            $triggered = $err;
        };

        $route = new Route('/foo', $handler);
        $dispatch = new Dispatch();
        $err = null;
        $dispatch($route, $err, $this->request, $this->response, $next);
        $this->assertSame($exception, $triggered);
    }

    public function testReturnsValueFromNonErrorHandler()
    {
        $phpunit = $this;
        $handler = function ($req, $res, $next) {
            return $res;
        };
        $next = function ($req, $res, $err) use ($phpunit) {
            $phpunit->fail('Next was called; it should not have been');
        };

        $route = new Route('/foo', $handler);
        $dispatch = new Dispatch();
        $err = null;
        $result = $dispatch($route, $err, $this->request, $this->response, $next);
        $this->assertSame($this->response, $result);
    }

    public function testIfErrorHandlerReturnsResponseDispatchReturnsTheResponse()
    {
        $phpunit = $this;
        $handler = function ($err, $req, $res, $next) {
            return $res;
        };
        $next = function ($req, $res, $err) use ($phpunit) {
            $phpunit->fail('Next was called; it should not have been');
        };

        $route = new Route('/foo', $handler);
        $dispatch = new Dispatch();
        $err = (object) ['error' => true];
        $result = $dispatch($route, $err, $this->request, $this->response, $next);
        $this->assertSame($this->response, $result);
    }
}
