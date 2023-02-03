<?php

namespace Pecee\Pixie\Exceptions;

use Pecee\Pixie\Exception;

/**
 * Class DuplicateColumnException
 *
 * @package Pecee\Pixie\Exceptions
 */
class ColumnNotFoundException extends Exception
{

    public function __toString()
    {
        exit(json_encode([
            'status' => 'error',
            'message' => $this->getMessage()
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

}