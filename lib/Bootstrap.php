<?php

namespace Wisa\Gcdg;

use Pecee\Pixie\Connection as DBcon;
use Wisa\Gcdg\App;
use Wisa\Gcdg\App\File;
use Wisa\Gcdg\Exceptions\CommonException;
use Wisa\Gcdg\Exceptions\FileNotFoundException;
use Wisa\Gcdg\Exceptions\AppNotExistsException;

class Bootstrap {

    public function __construct()
    {
    }


    /**
     * 이슈 트래커 메인 데이터베이스에 접속한다.
     *
     * @return Pecee\Pixie\QueryBuilder\QueryBuilderHandler
     **/
    public static function dbcon()
    {
        global $config;
        $connect = new DBcon('mysql', (array) $config->db);

        return $connect->getQueryBuilder();
    }

    /**
     * 스킨 파일을 출력한다.
     *
     * @param  ParsedURI $parsed_uri
     *
     * @throws FileNotFoundException
     **/
    public static function view(ParsedURI $parsed_uri)
    {
        $_uri = explode('/', trim($parsed_uri->get('path'), '/'));
        if (!$_uri[0]) {
            $view = sprintf('%s/view/index.html', __RES_DIR__);
        } else {
            if (!isset($_SERVER['HTTP_REFERER'])) exit('access_denined');
            $view = sprintf('%s/view/%s/%s', __RES_DIR__, $_uri[0], $_uri[1]);
        }
        if (is_file($view)) {
            $layout = self::fileGetContents($view);
            exit($layout);
        }

        throw new CommonException('view is not found. '.$_uri[0].'/'.$_uri[1]);
    }

    /**
     * css/js 리소스를 출력한다.
     *
     * @param  ParsedURI $parsed_uri
     *
     * @throws FileNotFoundException
     **/
    public static function resource($parsed_uri)
    {
        $resource = __RES_DIR__.'/'.$parsed_uri->get('path');

        if (is_file($resource)) {
            $fp = fopen($resource, 'r');
            while($file = fgets($fp, 1024)) {
                echo $file;
            }
            exit;
        }

        throw new CommonException('resource is not found.');
    }

    /**
     * 지정한 API를 실행한다.
     *
     * @param  ParsedURI $parsed_uri
     *
     * @throws AppNotExistsException
     **/
    public static function api(ParsedURI $parsed_uri)
    {
        $classname = $parsed_uri->getClassName();
        $method = $parsed_uri->getMethodName();

        if (class_exists($classname)) {
            if ($classname != 'staff' && $method != 'me' && $method != 'portrait') {
                $api = new App(self::dbcon(), $GLOBALS['config']);
                $api->currentStaffIdx();
            }

            $app = new $classname(self::dbcon(), $GLOBALS['config']);
            if (method_exists($app, $method)) {
                $app->$method($parsed_uri);
                exit;
            }
            throw new AppNotExistsException('api not exists : '.$parsed_uri->getPath(null));
        }
        throw new AppNotExistsException('api not exists : '.$parsed_uri->getPath(0));
    }

    /*
    public static function image(ParsedURI $parsed_uri)
    {
        global $config;

        $dbcon = self::dbcon();
        $file = new File($dbcon, $config);
        $file->download($parsed_uri);
    }
    */

    /**
     * 파일 내용을 출력한다
     *
     * @param  string $path
     *
     * @return string
     *
     * @throws FileNotFoundException
     **/
    private static function fileGetContents($path)
    {
        if (file_exists($path)) {
            return file_get_contents($path);
        }

        throw new FileNotFoundException($path);
    }

}
