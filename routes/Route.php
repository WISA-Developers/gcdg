<?php

use Wisa\Gcdg\Bootstrap;
use Wisa\Gcdg\Router;
use Wisa\Gcdg\ParsedURI;

$uri = $_SERVER['REQUEST_URI'];

Router::get('/api/', function (ParsedURI $parsed_uri) {
    $parsed_uri->shift();
    exit(Bootstrap::api($parsed_uri));
});

Router::get('/style/', function (ParsedURI $parsed_uri) {
    header('Content-type: text/css');
    exit(Bootstrap::resource($parsed_uri));
});

Router::get('/js/', function (ParsedURI $parsed_uri) {
    header('Content-type: text/javascript');
    exit(Bootstrap::resource($parsed_uri));
});

Router::get('/', function (ParsedURI $parsed_uri) {
    exit(Bootstrap::view($parsed_uri));
});