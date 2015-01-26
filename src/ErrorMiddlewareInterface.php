<?php
namespace Phly\Conduit;

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;

/**
 * Middleware for handling errors.
 *
 * Error middleware is essentially the same as the `MiddlewareInterface`, with
 * one key distinction: it has an additional argument prepended, representing
 * an error condition.
 *
 * `Next` will skip error middleware if called without an error; conversely,
 * if called with an error, it will skip normal middleware.
 *
 * Error middleware does something with the arguments passed, and then
 * either returns a response, or calls `$out`, with or without the error.
 */
interface ErrorMiddlewareInterface
{
    /**
     * Process an incoming error, along with associated request and response.
     *
     * Accepts an error, a server-side request, and a response instance, and
     * does something with them; if further processing can be done, it can
     * delegate to `$out`.
     *
     * @see MiddlewareInterface
     * @param mixed $error
     * @param Request $request
     * @param Response $response
     * @param null|callable $out
     * @return null|Response
     */
    public function __invoke($error, Request $request, Response $response, callable $out = null);
}
