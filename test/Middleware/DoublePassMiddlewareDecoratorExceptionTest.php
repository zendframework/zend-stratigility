<?php
/**
 * @see       https://github.com/zendframework/zend-stratigility for the canonical source repository
 * @copyright Copyright (c) 2018 Zend Technologies USA Inc. (https://www.zend.com)
 * @license   https://github.com/zendframework/zend-stratigility/blob/master/LICENSE.md New BSD License
 */

declare(strict_types=1);

namespace ZendTest\Stratigility\Middleware;

use PHPUnit\Framework\TestCase;
use Zend\Stratigility\Exception\MissingResponsePrototypeException;
use Zend\Stratigility\Middleware\DoublePassMiddlewareDecorator;

use function class_exists;
use function spl_autoload_functions;
use function spl_autoload_register;
use function spl_autoload_unregister;

class DoublePassMiddlewareDecoratorExceptionTest extends TestCase
{
    /** @var array */
    private $autoloadFunctions = [];

    protected function setUp() : void
    {
        class_exists(MissingResponsePrototypeException::class);
        class_exists(DoublePassMiddlewareDecorator::class);

        $this->autoloadFunctions = spl_autoload_functions();
        foreach ($this->autoloadFunctions as $func) {
            spl_autoload_unregister($func);
        }
    }

    private function reloadAutoloaders() : void
    {
        foreach ($this->autoloadFunctions as $autoloader) {
            spl_autoload_register($autoloader);
        }
    }

    public function testDiactorosIsNotAvailableAndResponsePrototypeIsNotSet()
    {
        $middleware = function ($request, $response, $next) {
            return $response;
        };

        $this->expectException(MissingResponsePrototypeException::class);
        $this->expectExceptionMessage(
            'no response prototype provided, and zendframework/zend-diactoros is not installed'
        );

        try {
            new DoublePassMiddlewareDecorator($middleware);
        } finally {
            $this->reloadAutoloaders();
        }
    }
}
