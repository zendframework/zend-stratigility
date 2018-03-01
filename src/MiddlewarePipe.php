<?php
/**
 * @see       https://github.com/zendframework/zend-stratigility for the canonical source repository
 * @copyright Copyright (c) 2015-2018 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-stratigility/blob/master/LICENSE.md New BSD License
 */

namespace Zend\Stratigility;

use Closure;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use ReflectionFunction;
use ReflectionMethod;
use SplQueue;
use Webimpress\HttpMiddlewareCompatibility\HandlerInterface as DelegateInterface;
use Webimpress\HttpMiddlewareCompatibility\MiddlewareInterface as ServerMiddlewareInterface;
use Zend\Stratigility\Exception\InvalidMiddlewareException;

/**
 * Pipe middleware like unix pipes.
 *
 * This class implements a pipeline of middleware, which can be attached using
 * the `pipe()` method, and is itself middleware.
 *
 * It creates an instance of `Next` internally, invoking it with the provided
 * request and response instances, passing the original request and the returned
 * response to the `$next` argument when complete.
 *
 * Inspired by Sencha Connect.
 *
 * @see https://github.com/senchalabs/connect
 */
class MiddlewarePipe implements ServerMiddlewareInterface
{
    /**
     * @var Middleware\CallableMiddlewareWrapperFactory
     */
    private $callableMiddlewareDecorator;

    /**
     * @var SplQueue
     */
    protected $pipeline;

    /**
     * @var Response
     */
    protected $responsePrototype;

    /**
     * Constructor
     *
     * Initializes the queue.
     */
    public function __construct()
    {
        $this->pipeline = new SplQueue();
    }

    /**
     * Handle a request
     *
     * Takes the pipeline, creates a Next handler, and delegates to the
     * Next handler.
     *
     * $delegate will be invoked if the internal queue is exhausted without
     * returning a response; in such situations, $delegate will then be
     * responsible for creating and returning the final response.
     *
     * $delegate may be either a DelegateInterface instance, or a callable
     * accepting at least a request instance (in such cases, the delegate
     * will be decorated using Delegate\CallableDelegateDecorator).
     *
     * @deprecated since 2.2.0; to be removed in version 3.0. Use process() instead.
     * @param Request $request
     * @param Response $response
     * @param callable|DelegateInterface $delegate
     * @return Response
     */
    public function __invoke(Request $request, Response $response, $delegate)
    {
        if (! $delegate instanceof DelegateInterface && is_callable($delegate)) {
            $delegate = new Delegate\CallableDelegateDecorator($delegate, $response);
        }

        return $this->process($request, $delegate);
    }

    /**
     * http-interop invocation: single-pass with delegate.
     *
     * Executes the internal pipeline, passing $delegate as the "final
     * handler" in cases when the pipeline exhausts itself.
     *
     * @param Request $request
     * @param DelegateInterface $delegate
     * @return Response
     */
    public function process(Request $request, DelegateInterface $delegate)
    {
        $next = new Next($this->pipeline, $delegate);
        return $next->process($request);
    }

    /**
     * Attach middleware to the pipeline.
     *
     * Each middleware can be associated with a particular path; if that
     * path is matched when that middleware is invoked, it will be processed;
     * otherwise it is skipped.
     *
     * No path means it should be executed every request cycle.
     *
     * A handler CAN implement MiddlewareInterface, but MUST be callable.
     *
     * @see MiddlewareInterface
     * @see Next
     * @param string|callable|object $path Either a URI path prefix, or middleware.
     *     Note: since v2.2.0, we have deprecated usage of any argument type other
     *     than a middleware implementation.
     * @param null|callable|object $middleware Middleware. Note: since v2.2.0, we
     *     have deprecated usage of this argument. Use the PathMiddlewareDecorator
     *     to segregate middleware by path.
     * @return self
     */
    public function pipe($path, $middleware = null)
    {
        $legacyArguments = null !== $middleware;

        if (null === $middleware
            && ($path instanceof ServerMiddlewareInterface || is_callable($path))
        ) {
            $middleware = $path;
            $path       = '/';
        }

        // Decorate callable middleware as http-interop middleware
        if (is_callable($middleware)
            && ! $middleware instanceof ServerMiddlewareInterface
        ) {
            $middleware = $this->decorateCallableMiddleware($middleware);
        }

        // Ensure we have a valid handler
        if (! $middleware instanceof ServerMiddlewareInterface) {
            throw InvalidMiddlewareException::fromValue($middleware);
        }

        // Trigger an error if we received two arguments
        if ($legacyArguments) {
            trigger_error(sprintf(
                'Providing a path to the %s method is deprecated; please use the'
                . ' %s to decorate your path-segregated middleware instead.',
                __CLASS__,
                Middleware\PathMiddlewareDecorator::class
            ), E_USER_DEPRECATED);
        }

        $path = $this->normalizePipePath($path);

        // Decorate path-segregated middleware if we have a non-root path and
        // the middleware is not already decorated
        if ($path !== '/' && ! $middleware instanceof Middleware\PathMiddlewareDecorator) {
            $middleware = new Middleware\PathMiddlewareDecorator($path, $middleware);
        }

        $this->pipeline->enqueue(new Route($path, $middleware));

        // @todo Trigger event here with route details?
        return $this;
    }

    /**
     * Inject a factory for decorating callable middleware.
     *
     * @deprecated Since 2.2.0; this feature will be removed in version 3.0.0.
     * @param Middleware\CallableMiddlewareWrapperFactory $decorator
     * @return void
     */
    public function setCallableMiddlewareDecorator(Middleware\CallableMiddlewareWrapperFactory $decorator)
    {
        $this->callableMiddlewareDecorator = $decorator;
    }

    /**
     * Enable the "raise throwables" flag.
     *
     * @deprecated Since 2.0.0; this feature is now a no-op.
     * @return void
     */
    public function raiseThrowables()
    {
    }

    /**
     * @deprecated Since 2.2.0; this feature will be removed in version 3.0.0.
     * @param Response $prototype
     * @return void
     */
    public function setResponsePrototype(Response $prototype)
    {
        $this->responsePrototype = $prototype;
    }

    /**
     * @deprecated Since 2.2.0; this feature will be removed in version 3.0.0.
     * @return bool
     */
    public function hasResponsePrototype()
    {
        return $this->responsePrototype instanceof Response;
    }

    /**
     * Normalize a path used when defining a pipe
     *
     * Strips trailing slashes, and prepends a slash.
     *
     * @param string $path
     * @return string
     */
    private function normalizePipePath($path)
    {
        // Prepend slash if missing
        if (empty($path) || $path[0] !== '/') {
            $path = '/' . $path;
        }

        // Trim trailing slash if present
        if (strlen($path) > 1 && '/' === substr($path, -1)) {
            $path = rtrim($path, '/');
        }

        return $path;
    }

    /**
     * @param callable $middleware
     * @return ServerMiddlewareInterface
     */
    private function decorateCallableMiddleware(callable $middleware)
    {
        $r = $this->getReflectionFunction($middleware);
        $paramsCount = $r->getNumberOfParameters();

        if ($paramsCount !== 2) {
            trigger_error(sprintf(
                'Direct piping of double-pass middleware is deprecated and will'
                . ' no longer be supported starting in version 3. Please decorate'
                . ' such middleware in a %s instance before passing to %s::pipe().',
                Middleware\DoublePassMiddlewareDecorator::class,
                __CLASS__
            ), E_USER_DEPRECATED);

            return $this->decorateDoublePassMiddleware($middleware);
        }

        $params = $r->getParameters();
        $type = $params[1]->getClass();
        if (! $type || ! is_a($type->getName(), DelegateInterface::class, true)) {
            trigger_error(sprintf(
                'Direct piping of double-pass middleware is deprecated and will'
                . ' no longer be supported starting in version 3. Please decorate'
                . ' such middleware in a %s instance before passing to %s::pipe().',
                Middleware\DoublePassMiddlewareDecorator::class,
                __CLASS__
            ), E_USER_DEPRECATED);

            return $this->decorateDoublePassMiddleware($middleware);
        }

        trigger_error(sprintf(
            'Direct piping of callable interop/PSR-15 middleware is deprecated'
            . ' and will no longer be supported starting in version 3. Please'
            . ' decorate such middleware in a %s instance before passing to'
            . ' %s::pipe().',
            Middleware\CallableMiddlewareDecorator::class,
            __CLASS__
        ), E_USER_DEPRECATED);

        return new Middleware\CallableMiddlewareDecorator($middleware);
    }

    /**
     * @return ServerMiddlewareInterface Generally one of Middleware\CallableMiddlewareWrapper
     *      or Middleware\DoublePassMiddlewareDecorator.
     * @throws Exception\MissingResponsePrototypeException if no middleware
     *     decorator and no response prototype are present.
     */
    private function decorateDoublePassMiddleware(callable $middleware)
    {
        if ($this->callableMiddlewareDecorator) {
            return $this->callableMiddlewareDecorator
                ->decorateCallableMiddleware($middleware);
        }

        if (! $this->responsePrototype) {
            throw new Exception\MissingResponsePrototypeException(sprintf(
                'Cannot wrap callable middleware; no %s or %s instances composed '
                . 'in middleware pipeline; use setCallableMiddlewareDecorator() or '
                . 'setResponsePrototype() on your %s instance to provide one or the '
                . 'other, or decorate callable middleware manually before piping.',
                Middleware\CallableMiddlewareWrapperFactory::class,
                Response::class,
                get_class($this)
            ));
        }

        return new Middleware\DoublePassMiddlewareDecorator(
            $middleware,
            $this->responsePrototype
        );
    }

    /**
     * @param callable $middleware
     * @return \ReflectionFunctionAbstract
     */
    private function getReflectionFunction(callable $middleware)
    {
        if (is_array($middleware)) {
            $class = array_shift($middleware);
            $method = array_shift($middleware);
            return new ReflectionMethod($class, $method);
        }

        if ($middleware instanceof Closure || ! is_object($middleware)) {
            return new ReflectionFunction($middleware);
        }

        return new ReflectionMethod($middleware, '__invoke');
    }
}
