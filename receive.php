<?php

use Pecee\Pixie\Connection as DBcon;
use Zeuxisoo\Core\Validator;
use Wisa\Gcdg\Exceptions\CommonException;

define('__HOME_DIR__', __DIR__);

require 'vendor/autoload.php';

$validator = Validator::factory($_POST);
$validator->add('host', '호스트정보를 입력해주세요.')->rule('required');
$validator->add('exception', '예외타입을 입력해주세요.')->rule('required');
$validator->add('message', '에러메시지를 입력해주세요.')->rule('required');
$validator->add('code', '에러코드를 입력해주세요.')->rule('required');
$validator->add('trace', 'trace 정보를 입력해주세요.')->rule('required');
$validator->add('server_ip', '서버 아이피 정보를 입력해주세요.')->rule('required');
if ($validator->inValid()) {
    throw new CommonException($validator->firstError());
}

$config = [
    'driver'    => 'mysql',
    'host'      => '121.254.159.44',
    'database'  => 'gcdg',
    'username'  => 'ep',
    'password'  => 'dnltkahfeoqkr',
    'charset'   => 'utf8mb4',
    'collation' => 'utf8mb4_general_ci',
];

$qb = (new DBcon('mysql', $config))->getQueryBuilder();

$host = trim($_POST['host']);
$exception = trim($_POST['exception']);
$message = trim($_POST['message']);
$code = trim($_POST['code']);
$trace = trim($_POST['trace']);
$query  = trim($_POST['query']);
$server_ip = trim($_POST['server_ip']);
$client_ip = trim($_POST['client_ip']);
$referer = trim($_POST['referer']);
$account_idx = 0;

// trace by base64 encode
if (preg_match('/^Base64,/', $trace)) {
	$trace = base64_decode(preg_replace('/^Base64,/', '', $trace));
}

$type = 'FATAL';
if ($query) $type = 'Query';
if ($exception == 'Injection' || $exception == 'BadQuery') $type = 'Injection';

$host = parse_url($host);
parse_str($host['query'], $params);
$host_ep = $host['host'];
$host_ep = preg_replace('/^(m|www)\./', '', $host_ep);

if (preg_match('/(.*)\.mywisa\.(com|co\.kr)$/', $host_ep, $account_id)) {
    $account = $qb->table('ep.account')
        ->select('idx')
        ->where('account_id', $account_id[1])
        ->orWhere('domains', 'like', "%<{$host_ep}>%")
        ->first();
} else {
    $account = $qb->table('ep.account')
        ->select('idx')
        ->where('domain', $host_ep)
        ->orWhere('domains', 'like', "%<{$host_ep}>%")
        ->first();
}
if ($account) {
    $account_idx = $account->idx;
}

$insert_id = $qb->table('error_report')
    ->insert([
        'account_idx' => $account_idx,
        'host' => ($host['host']) ? $_SERVER['REQUEST_SCHEME'] . '://' . $host['host'] : '',
        'path' => $host['path'],
        'params' => json_encode($params, JSON_UNESCAPED_UNICODE),
        'type' => $type,
        'exception' => $exception,
        'message' => $message,
        'code' => $code,
        'trace' => $trace,
        'query' => $query,
        'server_ip' => $server_ip,
		'client_ip' => $client_ip,
		'referer' => $referer
    ]);

exit(json_encode([
    'status' => 'success',
    'insert_id' => $insert_id
]));