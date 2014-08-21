<?php
namespace Phly\Conduit;

use Exception;
use Phly\Conduit\Http\ResponseInterface as Response;
use Psr\Http\Message\RequestInterface as Request;
use Zend\Escaper\Escaper;

/**
 * Handle incomplete requests
 */
class FinalHandler
{
    /**
     * @var array
     */
    private $options;

    /**
     * @var Request
     */
    private $request;

    /**
     * @var Response
     */
    private $response;

    /**
     * @param Request $request
     * @param Response $response
     */
    public function __construct(Request $request, Response $response, array $options = [])
    {
        $this->request  = $request;
        $this->response = $response;
        $this->options  = $options;
    }

    /**
     * Handle incomplete requests
     *
     * This handler should only ever be invoked if Next exhausts its stack.
     * When that happens, we determine if an $err is present, and, if so,
     * create a 500 status with error details.
     *
     * Otherwise, a 404 status is created.
     *
     * @param null|mixed $err
     */
    public function __invoke($err = null)
    {
        if ($err) {
            $this->handleError($err);
            return;
        }

        $this->create404();
    }

    /**
     * Handle an error condition
     *
     * Use the $error to create details for the response.
     *
     * @param mixed $error
     */
    private function handleError($error)
    {
        $this->response->setStatusCode(
            $this->getStatusCode($error, $this->response)
        );

        $message = $this->response->getReasonPhrase() ?: 'Unknown Error';
        if (! isset($this->options['env'])
            || $this->options['env'] !== 'production'
        ) {
            $message = $this->createDevelopmentErrorMessage($error);
        }

        $this->triggerError($error, $this->request, $this->response);

        $this->response->end($message);
    }

    /**
     * Create a 404 status in the response
     */
    private function create404()
    {
        $this->response->setStatusCode(404);

        if ($this->request instanceof Http\Request && $this->request->originalUrl) {
            $url = $this->request->originalUrl;
        } else {
            $url = $this->request->getUrl();
        }

        $escaper = new Escaper();
        $message = sprintf(
            "Cannot %s %s\n",
            $escaper->escapeHtml($this->request->getMethod()),
            $escaper->escapeHtml((string) $url)
        );
        $this->response->end($message);
    }

    /**
     * Determine status code
     *
     * If the error is an exception with a code between 400 and 599, returns
     * the exception code.
     *
     * Otherwise, retrieves the code from the response; if not present, or
     * less than 400 or greater than 599, returns 500; otherwise, returns it.
     * 
     * @param mixed $error 
     * @param Http\ResponseInterface $response 
     * @return int
     */
    private function getStatusCode($error, Response $response)
    {
        if ($error instanceof Exception
            && ($error->getCode() >= 400 && $error->getCode() < 600)
        ) {
            return $error->getCode();
        }

        $status = $response->getStatusCode();
        if (! $status || $status < 400 || $status >= 600) {
            $status = 500;
        }
        return $status;
    }

    private function createDevelopmentErrorMessage($error)
    {
        if ($error instanceof Exception) {
            $message = $error->getTraceAsString();
        } elseif (is_object($error) && ! method_exists($error, '__toString')) {
            $message = sprintf('Error of type "%s" occurred', get_class($error));
        } else {
            $message = (string) $error;
        }

        $escaper = new Escaper();
        return $escaper->escapeHtml($message);
    }

    /**
     * Trigger the error listener, if present
     * 
     * @param mixed $error 
     * @param Request $request 
     * @param Response $response 
     */
    private function triggerError($error, Request $request, Response $response)
    {
        if (! isset($this->options['onerror'])
            || ! is_callable($this->options['onerror'])
        ) {
            return;
        }

        $onError = $this->options['onerror'];
        $onError($error, $request, $response);
    }
}
