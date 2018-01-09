<?php
/**
 * @see       https://github.com/zendframework/zend-stratigility for the canonical source repository
 * @copyright Copyright (c) 2018 Zend Technologies USA Inc. (https://www.zend.com)
 * @license   https://github.com/zendframework/zend-stratigility/blob/master/LICENSE.md New BSD License
 */
declare(strict_types=1);

namespace Zend\Stratigility\Exception;

use RuntimeException;

class PathOutOfSyncException extends RuntimeException implements ExceptionInterface
{
    public static function forPath(string $pathPrefix, string $path) : self
    {
        return new self(sprintf(
            'Layer path "%s" and request path "%s" are out of sync; cannot dispatch'
            . ' middleware layer',
            $pathPrefix,
            $path
        ));
    }
}
