<?php

namespace Wisa\Gcdg;

use Symfony\Component\HttpClient\HttpClient;

Class weagleEyeClientMini {

	private $config;
	private $db_connect;
	private $server;
	private $service;
	private $result;
	private $error;

	public function __construct($config, $service = null) {
		$this->config = $config;
		$this->server = 'http://wes.wisa.re.kr';
		$this->service = $service;
	}

	public function call($action) {
		$args = func_get_args();
        $add_args = '';
		if (is_array($args[1])) {
			foreach($args[1] as $key => $val) {
				if ($key == 'service' || $key == 'action') continue;
				$add_args .= "&$key=$val";
			}
		}

		$param  = "service=".$this->service;
		$param .= "&action=".$action;
		$param .= "&keycode=".$this->config['wm_key_code'];
		$param .= "&apikey=".$this->config['api_key'];

		return $this->send($param.$add_args);
	}

	private function send($param) {
		$this->result = $this->comm($this->server, $param);
		$this->result = trim($this->result);

		if (preg_match('/^#ERROR/', $this->result)) {
			$this->result = preg_replace('/^#ERROR/', '', $this->result);
			$this->error = 1;
		} else {
			if (!$this->result) $this->result = 'OK';
		}

		if (preg_match('/^<\?xml/', $this->result)) $this->result = $this->parseXmlInfo($this->result); // 결과가 XML일 경우
		return $this->result;
	}

	function comm($url, $post_args = null, $protocol = null) {
		$post_args = preg_replace('/&+/', '&', 'account_idx='.$this->config['account_idx'].'&'.$post_args);
        parse_str($post_args, $post_args);

        $client = HttpClient::create();
        $response = $client->request('POST', $url, [
            'body' => $post_args
        ]);
        $result = $response->getContent();

		return $result;
	}

}