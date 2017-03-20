<?php
/**
 * @see       https://github.com/zendframework/zend-stratigility for the canonical source repository
 * @copyright Copyright (c) 2015-2017 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-stratigility/blob/master/LICENSE.md New BSD License
 */

namespace Zend\Stratigility;

use Closure;
use Interop\Http\ServerMiddleware\DelegateInterface;
use Interop\Http\ServerMiddleware\MiddlewareInterface as ServerMiddlewareInterface;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use ReflectionFunction;
use ReflectionMethod;
use SplQueue;
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
 * @see https://github.com/sencha/connect
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
     * @param null|callable|object $middleware Middleware
     * @return self
     */
    public function pipe($path, $middleware = null)
    {
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

        $this->pipeline->enqueue(new Route(
            $this->normalizePipePath($path),
            $middleware
        ));

        // @todo Trigger event here with route details?
        return $this;
    }

    /**
     * Inject a factory for decorating callable middleware.
     *
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
     * @param Response $prototype
     * @return void
     */
    public function setResponsePrototype(Response $prototype)
    {
        $this->responsePrototype = $prototype;
    }

    /**
     * @return bool
     */
    public function hasResponsePrototype()
    {
        return $this->responsePrototype instanceof Response;
    }

    /**
     * Returns array of path names that have been passed to `pipe()`
     *
     * @param bool $includeRoot  If true, '/' path(s) will be included
     * @return array
     */
    public function getPipedPaths($includeRoot = false)
    {
        $paths = [];
        /* @var Route $route */
        foreach ($this->pipeline as $route) {
            if ($route->path != '/' || $includeRoot) {
                $paths[] = $route->path;
            }
        };

        return $paths;
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
     * @return ServerMiddlewareInterface|callable Callable, if unable to
     *     decorate the middleware; ServerMiddlewareInterface if it can.
     */
    private function decorateCallableMiddleware(callable $middleware)
    {
        $r = $this->getReflectionFunction($middleware);
        $paramsCount = $r->getNumberOfParameters();

        if ($paramsCount !== 2) {
            return $this->getCallableMiddlewareDecorator()
                ->decorateCallableMiddleware($middleware);
        }

        $params = $r->getParameters();
        $type = $params[1]->getClass();
        if (! $type || $type->getName() !== DelegateInterface::class) {
            return $this->getCallableMiddlewareDecorator()
                ->decorateCallableMiddleware($middleware);
        }

        return new Middleware\CallableInteropMiddlewareWrapper($middleware);
    }

    /**
     * @return Middleware\CallableMiddlewareWrapperFactory
     * @throws Exception\MissingResponsePrototypeException if no middleware
     *     decorator and no response prototype are present.
     */
    private function getCallableMiddlewareDecorator()
    {
        if ($this->callableMiddlewareDecorator) {
            return $this->callableMiddlewareDecorator;
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

        $this->setCallableMiddlewareDecorator(
            new Middleware\CallableMiddlewareWrapperFactory($this->responsePrototype)
        );

        return $this->callableMiddlewareDecorator;
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
