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

class DoublePassMiddlewareDecoratorExceptionTest extends TestCase
{
    private $autoloadFunctions = [];

    protected function setUp() : void
    {
        $this->autoloadFunctions = spl_autoload_functions();
        foreach ($this->autoloadFunctions as $func) {
            spl_autoload_unregister($func);
        }
    }

    protected function tearDown() : void
    {
        foreach ($this->autoloadFunctions as $func) {
            spl_autoload_register($func);
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
        include_once __DIR__ . '/../../src/Middleware/DoublePassMiddlewareDecorator.php';
        new DoublePassMiddlewareDecorator($middleware);
    }
}
