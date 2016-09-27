<?php
/**
 * @link      http://github.com/zendframework/zend-stratigility for the canonical source repository
 * @copyright Copyright (c) 2016 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 */

namespace Zend\Stratigility\Middleware;

use ErrorException;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Throwable;
use Zend\Escaper\Escaper;
use Zend\Stratigility\Exception\MissingResponseException;
use Zend\Stratigility\Utils;

class ErrorHandler
{
    /**
     * @var bool
     */
    protected $isDevelopmentMode;

    /**
     * @var callable[]
     */
    private $listeners = [];

    /**
     * @var ResponseInterface
     */
    private $responsePrototype;

    /**
     * @param ResponseInterface $responsePrototype Empty/prototype response to
     *     update and return when returning an error response.
     * @param bool $isDevelopmentMode
     */
    public function __construct(ResponseInterface $responsePrototype, $isDevelopmentMode = false)
    {
        $this->responsePrototype = $responsePrototype;
        $this->isDevelopmentMode = (bool) $isDevelopmentMode;
    }

    /**
     * Attach an error listener.
     *
     * Each listener receives the following three arguments:
     *
     * - \Throwable|\Exception $error
     * - ServerRequestInterface $request
     * - ResponseInterface $response
     *
     * These instances are all immutable, and the return values of
     * listeners are ignored; use listeners for reporting purposes
     * only.
     *
     * @param callable $listener
     */
    public function attachListener(callable $listener)
    {
        if (in_array($listener, $this->listeners, true)) {
            return;
        }

        $this->listeners[] = $listener;
    }

    /**
     * Middleware to handle errors and exceptions in layers it wraps.
     *
     * Adds an error handler that will convert PHP errors to ErrorException
     * instances.
     *
     * Internally, wraps the call to $next() in a try/catch block, catching
     * all PHP Throwables (PHP 7) and Exceptions (PHP 5.6 and earlier).
     *
     * When an exception is caught, an appropriate error response is created
     * and returned instead; otherwise, the response returned by $next is
     * used.
     *
     * @param ServerRequestInterface $request
     * @return ResponseInterface
     */
    public function __invoke(ServerRequestInterface $request, ResponseInterface $response, callable $next)
    {
        set_error_handler($this->createErrorHandler());

        try {
            $response = $next($request, $response);

            if (! $response instanceof ResponseInterface) {
                throw new MissingResponseException('Application did not return a response');
            }
        } catch (Throwable $e) {
            $response = $this->handleThrowable($e, $request);
        } catch (\Exception $e) {
            $response = $this->handleThrowable($e, $request);
        }

        restore_error_handler();

        return $response;
    }

    /**
     * Create/update the response representing the error.
     *
     * This method may be overridden in order to allow an extending class to
     * update and return the response representing the error condition.
     *
     * The response provided is the response prototype injected during
     * instantiation; the error (an exception or throwable) and request are
     * also provided to allow providing details from each when creating the
     * error response.
     *
     * Classes overriding this method have access to $isDevelopmentMode in
     * order to vary their response.
     *
     * The method MUST return a response!
     *
     * @param Throwable|\Exception $e
     * @param ServerRequestInterface $request
     * @param ResponseInterface $response
     * @return ResponseInterface
     */
    protected function createErrorResponse($e, ServerRequestInterface $request, ResponseInterface $response)
    {
        $response = $response->withStatus(Utils::getStatusCode($e, $response));
        $body = $response->getBody();

        if ($this->isDevelopmentMode) {
             $body->write($this->createDevelopmentErrorMessage($e));
             return $response;
        }

        $body->write($response->getReasonPhrase() ?: 'Unknown Error');
        return $response;
    }

    /**
     * Handles all throwables/exceptions, generating and returning a response.
     *
     * Passes the error, request, and response prototype to createErrorResponse(),
     * triggers all listeners with the same arguments (but using the response
     * returned from createErrorResponse()), and then returns the response.
     *
     * @param Throwable|\Exception $e
     * @param ServerRequestInterface $request
     * @return ResponseInterface
     */
    private function handleThrowable($e, ServerRequestInterface $request)
    {
        $response = $this->createErrorResponse($e, $request, $this->responsePrototype);
        $this->triggerListeners($e, $request, $response);
        return $response;
    }

    /**
     * Creates and returns a callable error handler that raises exceptions.
     *
     * Only raises exceptions for errors that are within the error_reporting mask.
     *
     * @return callable
     */
    private function createErrorHandler()
    {
        /**
         * @param int $errno
         * @param string $errstr
         * @param string $errfile
         * @param int $errline
         * @return void
         * @throws ErrorException if error is not within the error_reporting mask.
         */
        return function ($errno, $errstr, $errfile, $errline) {
            if (! (error_reporting() & $errno)) {
                // error_reporting does not include this error
                return;
            }

            throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
        };
    }

    /**
     * Trigger all error listeners.
     *
     * @param Throwable|\Exception $error
     * @param ServerRequestInterface $request
     * @param ResponseInterface $response
     * @return void
     */
    private function triggerListeners($error, ServerRequestInterface $request, ResponseInterface $response)
    {
        array_walk($this->listeners, function ($listener) use ($error, $request, $response) {
            $listener($error, $request, $response);
        });
    }

    /**
     * Create a complete error message for development purposes.
     *
     * Creates an error message with the full exception backtrace, escaped
     * for use in HTML.
     *
     * @param Throwable|\Exception $exception
     * @return string
     */
    private function createDevelopmentErrorMessage($exception)
    {
        $escaper = new Escaper();
        return $escaper->escapeHtml((string) $exception);
    }
}
