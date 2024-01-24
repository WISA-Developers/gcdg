<?php

use Pecee\Pixie\Connection as DBcon;
use zardsama\session\MySQLSession;
use zardsama\pdo\PDODatabase;

require 'vendor/autoload.php';

// directory
define('__HOME_DIR__', __DIR__);
define('__INIT_DIR__', realpath(__DIR__.'/init'));
define('__RES_DIR__', realpath(__DIR__.'/resource'));
define('__URL__', (isset($_SERVER['HTTPS']) ? "https" : "http").'://'.$_SERVER['HTTP_HOST']);

// config
$config = json_decode(rtrim(ltrim(file_get_contents('init/config.json.php'),'<?php'), '?>'));
$define = json_decode(file_get_contents('init/define.json'));

define('__SVN_PATH__', $config->svn_path);

$request_extension = pathinfo($_SERVER['REQUEST_URI'], PATHINFO_EXTENSION);
if (!in_array($request_extension, array('js', 'vue', 'ico'))) {
    // session
    $connect = new DBcon('mysql', (array) $config->session_db);
    $connect->connect();

    if (preg_match('/^(118\.129\.243\.|172\.72\.72\.)/', $_SERVER['REMOTE_ADDR'])) {
        $gc_maxlifetime = 3600 * 4;
    } else {
        $gc_maxlifetime = 3600 * 2;
    }
    ini_set('session.cache_expire', 180);
    ini_set('session.gc_maxlifetime', $gc_maxlifetime);
    ini_set('session.name', 'ep_session');
    new MySQLSession(new PDODatabase($connect->getPdoInstance()));
}

// route
require __DIR__.'/routes/Route.php';