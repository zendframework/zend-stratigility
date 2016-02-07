<?php
/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @see       http://github.com/zendframework/zend-stratigility for the canonical source repository
 * @copyright Copyright (c) 2015 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-stratigility/blob/master/LICENSE.md New BSD License
 */

namespace Zend\Stratigility\Http;

/**
 * Response convenience methods
 *
 * Defines the following responses capabilities:
 *
 * - Write to the content
 * - End the response (mark it complete)
 * - Determine if the response is complete
 */
interface ResponseInterface
{
    /**
     * Write data to the response body
     *
     * Proxies to the underlying stream and writes the provided data to it.
     *
     * @param string $data
     */
    public function write($data);

    /**
     * Mark the response as complete
     *
     * A completed response should no longer allow manipulation of either
     * headers or the content body.
     *
     * If $data is passed, that data should be written to the response body
     * prior to marking the response as complete.
     *
     * @param string $data
     */
    public function end($data = null);

    /**
     * Indicate whether or not the response is complete.
     *
     * I.e., if end() has previously been called.
     *
     * @return bool
     */
    public function isComplete();

    /**
     * Write data to the response body with JSON encode and
     * prepare to return an HTTP JSON response to the client.
     *
     * Note: This method is not part of the PSR-7 standard.
     *
     * @param  mixed  $data   The data
     * @param  int    $status The HTTP status code.
     * @param  int    $encodingOptions Json encoding options
     *
     * @return self
     */
    public function writeJson($data, $status = null, $encodingOptions = 0);
}
