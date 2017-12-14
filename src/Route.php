<?php
/**
 * @see       https://github.com/zendframework/zend-stratigility for the canonical source repository
 * @copyright Copyright (c) 2015-2017 Zend Technologies USA Inc. (https://www.zend.com)
 * @license   https://github.com/zendframework/zend-stratigility/blob/master/LICENSE.md New BSD License
 */
declare(strict_types=1);

namespace Zend\Stratigility;

use Interop\Http\Server\MiddlewareInterface;

/**
 * Value object representing route-based middleware
 *
 * Details the subpath on which the middleware is active, and the
 * handler for the middleware itself.
 *
 * @codeCoverageIgnore
 */
final class Route
{
    /**
     * @var MiddlewareInterface
     */
    protected $handler;

    /**
     * @var string
     */
    protected $path;

    public function __construct(string $path, MiddlewareInterface $handler)
    {
        $this->path    = $path;
        $this->handler = $handler;
    }

    public function getHandler(): MiddlewareInterface
    {
        return $this->handler;
    }

    public function getPath(): string
    {
        return $this->path;
    }
}
