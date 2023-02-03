<?php

namespace Wisa\Gcdg;

use Wisa\Gcdg\Exceptions\CommonException;

class ParsedURI {

    private $uri;
    private $path;
    private $params;
    private $fragment;
    private $fragment_query;

    /**
     * URL을 분석하여 파라메터를 분리한다.
     **/
    public function __construct()
    {
        $this->uri = parse_url($_SERVER['REQUEST_URI']);

        $this->checkInjection($this->uri['path']);

        $this->path = explode('/', trim($this->uri['path'], '/'));
        $this->parseParameter();
    }

    /**
     * 경로의 첫번째 단락을 제거한다.
     **/
    public function shift()
    {
        array_shift($this->path);
    }

    /**
     * 분석된 URL에서 지정된 component 값을 반환한다
     *
     * @param  string $component
     *
     * @return string
     *
     * @throws CommonException
     **/
    public function get(string $component)
    {
        if (array_key_exists($component, $this->uri)) {
            return $this->uri[$component];
        }

        throw new CommonException('ParsedURI component is not found.');
    }

    public function getPath(?int $depth)
    {
        if (is_null($depth)) return implode('/', $this->path);
        if (array_key_exists($depth, $this->path)) return $this->path[$depth];

        throw new CommonException('Path of ParsedURI is not found');
    }

    /**
     * API 호출 시 호출해야할 classname을 반환한다.
     *
     * @return string
     **/
    public function getClassName()
    {
        return 'Wisa\Gcdg\App\\'.ucfirst($this->path[0]);
    }

    /**
     * API 호출 시 호출해야할 method를 반환한다.
     *
     * @return string
     **/
    public function getMethodName()
    {
        if (isset($this->path[1]) == false) {
            return 'index';
        }
        return $this->path[1];
    }

    /**
     * 주소를 분석하여 파라메터 값들을 구성한다.
     *
     * @return \ArrayObject
     **/
    private function parseParameter()
    {
        $params = [];
        $tmp = $this->path;
        if ($tmp[0] == 'api') array_shift($tmp);
        foreach ($tmp as $key => $value) {
            $params['args'.$key] = $value;
        }

        if (isset($this->uri['query'])) {
            parse_str($this->uri['query'], $tmp);
            $params = array_merge($params, $tmp);
        }

        $this->params = new \ArrayObject($params);
    }

    /**
     * API 호출 시 요청 파라메터를 분석하여 반환한다
     *
     * @param  string $key
     * @param  string $default
     *
     * @return string
     **/
    public function getParameter($key, $default = null)
    {
        if ($this->params->offsetExists($key)) {
            return $this->params->offsetGet($key);
        }
        return $default;
    }

    /**
     * API 호출 시 injection 시도 여부를 체크한다.
     *
     * @param  string $uri
     *
     * @throws SecurityException
     **/
    private function checkInjection($uri)
    {
        if (preg_match('/\.{2,}/', $uri)) {
            throw new CommonException('path is not available');
        }
    }

}