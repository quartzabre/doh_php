<?php
$header = [];
if (!empty($_GET)) {
	$method = 'GET';
	$param = $_GET;
} elseif (!empty($_POST)) {
	$method = 'POST';
	$param = $_POST;
} elseif ($param = file_get_contents('php://input')) {
	$method = 'POST';
	$header[] = 'Content-type: application/dns-message';
} else {
	http_response_code(400);
	exit();
}

$redis_key = is_array($param) ? json_encode($param) : base64_encode($param) ;
$redis_key = 'DoH_' . $redis_key;
$redis = new Redis;
$redis->connect('127.0.0.1');
$data = $redis->get($redis_key);

if ($data) {
	$data = json_decode($data, TRUE);
	$response = base64_decode($data['response']);
	$info = $data['info'];
} else {
	$url = 'https://1.0.0.2/dns-query';
	if ($method === 'GET') {
		$url .= '?' . http_build_query($param);
	}

	$ch = curl_init($url);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
	curl_setopt($ch, CURLOPT_TIMEOUT, 1);
	curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 1);
	if ($method === 'POST') {
		curl_setopt($ch, CURLOPT_POSTFIELDS, $param);
	} elseif ($method === 'GET' && isset($param['name'])) {
		$header[] = 'Accept: application/dns-json';
	}
	if ($header) {
		curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
	}

	$response = curl_exec($ch);
	$info = curl_getinfo($ch);

	$redis_data = [
		'response' => base64_encode($response),
		'info' => $info,
	];
	if (!empty($info['http_code'])) {
		$redis->setEx($redis_key, 3600, json_encode($redis_data));
	}
}

$redis->close();
	
http_response_code($info['http_code']);
header('Content-type: ' . $info['content_type']);
header('Content-length: ' . strlen($response));
echo $response;

/* End of File : dns-query.php */