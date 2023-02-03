<?php

namespace Wisa\Gcdg;

use Wisa\Gcdg\ParsedURI;

class Router {

    public function __construct() {}

    public static function get($uri, $callback)
    {
        $parsed_uri = new ParsedURI();
        $__uri = preg_quote($uri, '/');

        if (preg_match("/^$__uri/", $parsed_uri->get('path'))) {
            $callback($parsed_uri);
        }
        return false;
    }

}