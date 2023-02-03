<?php

namespace Wisa\Gcdg\App;

use Wisa\Gcdg\App;
use Wisa\Gcdg\ParsedURI;
use Wisa\Gcdg\App\SVN;
use Wisa\Gcdg\Exceptions\CommonException;
use Zeuxisoo\Core\Validator;
use Symfony\Component\HttpClient\HttpClient;

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
        $validator = Validator::factory($_POST);
        $validator->add('svn_id', 'svn 아이디를 입력해주세요.')->rule('required');
        $validator->add('svn_pw', 'svn 패스워드를 입력해주세요.')->rule('required');
        $validator->add('branch', 'svn 아이디를 입력해주세요.')->rule('required');

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
        $validator = Validator::factory($_GET);
        $validator->add('url', '배포 URL을 입력해주세요.')->rule('required');
        $validator->add('svn_id', 'svn 아이디를 입력해주세요.')->rule('required');

        if ($validator->inValid()) {
            throw new CommonException($validator->firstError());
        }

        // 인증 파일 생성
        $host_ip = gethostbyname(preg_replace('/https?:\/\//', '', $_GET['url']));
        $secret_file = __HOME_DIR__.'/resource/key/'.$_GET['svn_id'].'_'.md5($host_ip).'.secret';
        $secret = hash('sha256', $_GET['svn_id'].time().rand(0,9999));
        $fp = fopen($secret_file, 'w');
        fwrite($fp, $secret);
        fclose($fp);

        // 배포
        $url = $_GET['url'].'/weagleEye/welease2.php';
        $_GET['secret'] = $secret;

        $client = HttpClient::create();
        $response = $client->request('POST', $url, [
            'body' => $_GET
        ]);

        if (file_exists($secret_file) == true) {
            unlink($secret_file);
        }

        $_result = json_decode($result);
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
            'result' => $info['http_code'],
            'branch' => $_GET['branch'],
            'revision' => $_GET['rev'],
            'data' => $result
        ]);
    }

    public function auth(ParsedURI $parsedURI) {
        $svn_id = $parsedURI->getParameter('svn_id');
        $host_ip = $parsedURI->getParameter('host_ip');
        $secret = $parsedURI->getParameter('secret');
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