<?php

namespace Wisa\Gcdg\App;

use Wisa\Gcdg\App;
use Wisa\Gcdg\ParsedURI;
use Wisa\Gcdg\App\SVN;
use Wisa\Gcdg\Exceptions\CommonException;
use Zeuxisoo\Core\Validator;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpClient\Exception\ClientException;

class Welease extends App
{

    protected $db;
    protected $config;
    private $ep;

    public function __construct($db, $config)
    {
        $this->db = $db;
        $this->config = $config;
    }

    public function otp(ParsedURI $parsedURI)
    {
        $code = $parsedURI->getParameter('code');
        $mode = $parsedURI->getParameter('mode', '');

        $this->output([
            'status' => 'success',
            'auth' => 'true'
        ]);

        $this->TwoFactorAuthentication($mode, 'welease', $code);
    }

    public function servers() {
        $this->ProjectPermission('developers', 1);

        $servers = [];
        $res = $this->db
            ->table('wing_servers')
            ->orderBy('idx')
            ->get();

        foreach ($res as $data) {
            array_push($servers, [
                'name' => $data->name,
                'ip' => $data->ip,
                'url' => $data->url
            ]);
        }

        $this->output([
           'status' => 'success',
           'data' => $servers
        ]);
    }

    public function getSVNLog(ParsedURI $parsedURI)
    {
        $this->ProjectPermission('developers', 1);

        $validator = Validator::factory($_POST);
        $validator->add('svn_id', 'svn 아이디를 입력해주세요.')->rule('required');
        $validator->add('svn_pw', 'svn 패스워드를 입력해주세요.')->rule('required');
        $validator->add('branch', 'svn 브랜치를 선택해주세요.')->rule('required');

        if ($validator->inValid()) {
            throw new CommonException($validator->firstError());
        }

        switch($_POST['branch']) {
            case 'dev' :
                $repository = 'http://dev.wisa.co.kr/svn/WingRTM/trunk/wm_engine_SW_dev';
                break;
            case 'stable' :
                $repository = 'http://dev.wisa.co.kr/svn/WingRTM/branches/smartwing/wm_engine_SW';
                break;
        }

        $svn = new SVN($_POST['svn_id'], $_POST['svn_pw'], $repository);
        $log = $svn->log(date('Y-m-d', strtotime('-3 months')), date('Y-m-d'), '');

        $this->output([
            'status' => 'success',
            'log' => $log
        ]);
    }

    public function release()
    {
        $this->ProjectPermission('developers', 1);

        $validator = Validator::factory($_GET);
        $validator->add('url', '배포 URL을 입력해주세요.')->rule('required');
        $validator->add('svn_id', 'svn 아이디를 입력해주세요.')->rule('required');

        if ($validator->inValid()) {
            throw new CommonException($validator->firstError());
        }

        // 로그 저장
        $fp = fopen(__HOME_DIR__.'/resource/key/welease.log', 'a+');
        fputcsv($fp, [
            date('Y-m-d H:i:s'),
            $this->currentStaffIdx(),
            $_GET['url'],
            $_GET['svn_id']
        ]);
        fclose($fp);

        // 인증 파일 생성
        $host_ip = gethostbyname(preg_replace('/https?:\/\//', '', $_GET['url']));
        $secret_file = __HOME_DIR__.'/resource/key/'.$_GET['svn_id'].'_'.md5($host_ip).'.secret';
        $secret = hash('sha256', $_GET['svn_id'].time().rand(0,9999));
        $fp = fopen($secret_file, 'w');
        fwrite($fp, $secret);
        fclose($fp);

        // svn 비밀번호 암호화
        $_GET['svn_pw'] = base64_encode(openssl_encrypt(
            $_GET['svn_pw'],
            'aes-128-cbc',
            $this->config->aes_key,
            OPENSSL_RAW_DATA,
            base64_decode($this->config->aes_iv)
        ));

        // 배포
        $url = $_GET['url'].'/weagleEye/welease2.php';
        $_GET['secret'] = $secret;

        try {
            $client = HttpClient::create();
            $response = $client->request('POST', $url, [
                'body' => $_GET
            ]);
            $_result = $response->toArray();
        } catch(\Exception $e) {
            $this->output([
                'status' => 'error',
                'branch' => $_GET['branch'],
                'revision' => $_GET['rev'],
                'data' => [
                    ['kind' => 'server', 'text' => $_GET['url']],
                    ['name' => 'error', 'text' => $e->getMessage()]
                ],
                'message' => $e->getMessage()
            ]);
        }

        if (file_exists($secret_file) == true) {
            unlink($secret_file);
        }

        if (is_array($_result) == true) {
            $result = $_result;
        } else {
            $result = array(
                array(
                    'kind' => 'server',
                    'text' => $host_ip
                ),
                array(
                    'kind' => 'error',
                    'text' => $result
                )
            );
        }

        $this->output([
            'status' => 'success',
            'branch' => $_GET['branch'],
            'revision' => $_GET['rev'],
            'data' => $result
        ]);
    }

    public function auth(ParsedURI $parsedURI) {
        $svn_id = $_POST['svn_id'];
        $host_ip = $_POST['host_ip'];
        $secret = $_POST['secret'];
        $svn_id = preg_replace('/\.|\/|\\\/', '', $svn_id);
        $host_ip = md5($host_ip);

        // check exists secret
        $file = __HOME_DIR__.'/resource/key/'.$svn_id.'_'.$host_ip.'.secret';

        if (file_exists($file) == false) {
            exit('secret_not_found');
        }

        // check same secret
        if (file_get_contents($file) != $secret) {
            exit('wrong_secret');
        }
        unlink($file);

        exit('ok');
    }

}