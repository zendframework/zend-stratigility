<?php
/**
 * @see       https://github.com/zendframework/zend-stratigility for the canonical source repository
 * @copyright Copyright (c) 2015-2018 Zend Technologies USA Inc. (https://www.zend.com)
 * @license   https://github.com/zendframework/zend-stratigility/blob/master/LICENSE.md New BSD License
 */

declare(strict_types=1);

namespace Zend\Stratigility;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class EmptyPipelineHandler implements RequestHandlerInterface
{
    private $class;

    public function __construct(string $class)
    {
        $this->class = $class;
    }
    public function handle(ServerRequestInterface $request) : ResponseInterface
    {
        throw Exception\EmptyPipelineException::forClass($this->class);
    }
}