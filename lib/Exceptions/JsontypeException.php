<?php

namespace Wisa\Gcdg\Exceptions;

class JsontypeException extends \Exception implements \Throwable
{
    public function __toString()
    {
        $trace = [
            [
                'file' => str_replace(__HOME_DIR__, '', $this->getFile()),
                'line' => $this->getLine()
            ]
        ];
        foreach ($this->getTrace() as $data) {
            $trace[] = [
                'file' => str_replace(__HOME_DIR__, '', $data['file']),
                'line' => $data['line']
            ];
        }

        header('Content-type: application/json');
        exit(json_encode([
            'status' => 'error',
            'message' => $this->getMessage(),
            'code' => $this->getCode(),
            'trace' => $trace
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }
}