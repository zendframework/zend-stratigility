<?php
namespace Phly\Conduit;

use Exception;
use Psr\Http\Message\RequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use Zend\Escaper\Escaper;

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
    private $reponse;

    /**
     * @param Request $request 
     * @param Response $response 
     */
    public function __construct(Request $request, Response $response, array $options = array())
    {
        $this->request  = $request;
        $this->response = $response;
        $this->options  = $options;
    }

    /**
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

    private function handleError($error)
    {
        $status = $this->response->getStatusCode();
        if (! $status || $status < 400) {
            $this->response->setStatusCode(500);
        }

        if ($error instanceof Exception
            && ($error->getCode() >= 400 && $error->getCode() < 600)
        ) {
            $this->response->setStatusCode($error->getCode());
        }

        $escaper = new Escaper();
        $message = $this->response->getReasonPhrase() ?: 'Unknown Error';
        if (isset($this->options['env'])
            && $this->options['env'] !== 'production'
        ) {
            if ($error instanceof Exception) {
                $message = $error->getTraceAsString();
            } else {
                $message = (string) $error;
            }
            $message = $escaper->escapeHtml($message);
        }

        if (is_callable($this->options['onerror'])) {
            $onError = $options['onerror'];
            $onError($error, $this->request, $this->response);
        }

        $this->response->end($message);
    }

    private function create404()
    {
        $this->response->setStatusCode(404);

        $url     = $this->request->originalUrl ?: $this->request->getUrl();
        $escaper = new Escaper();
        $message = sprintf(
            "Cannot %s %s\n",
            $escaper->escapeHtml($this->request->getMethod()),
            $escaper->escapeHtml((string) $url)
        );
        $this->response->end($message);
    }
}
