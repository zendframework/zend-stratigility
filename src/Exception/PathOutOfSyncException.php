<?php
/**
 * @see       https://github.com/zendframework/zend-stratigility for the canonical source repository
 * @copyright Copyright (c) 2018 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-stratigility/blob/master/LICENSE.md New BSD License
 */

namespace Zend\Stratigility\Exception;

use RuntimeException;

class PathOutOfSyncException extends RuntimeException
{
    /**
     * @param string $pathPrefix
     * @param string $path
     * @return self
     */
    public static function forPath($pathPrefix, $path)
    {
        return new self(sprintf(
            'Layer path "%s" and request path "%s" are out of sync; cannot dispatch'
            . ' middleware layer',
            $pathPrefix,
            $path
        ));
    }
}
