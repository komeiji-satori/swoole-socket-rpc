<?php
$redis = new Redis();
$redis->connect('127.0.0.1', 6379);

$http_server = new swoole_http_server('0.0.0.0', 9998);
$http_server->set(array('daemonize' => false));
$tcp_server = $http_server->addListener('0.0.0.0', 9999, SWOOLE_SOCK_TCP);
$http_server->on('request', function ($request, $response) use ($http_server, $tcp_server, $redis) {
	$_GET = $request->get;
	switch ($_GET['method']) {
	case 'stats':
		$response->end(json_encode($http_server->stats()));
		break;
	case 'send':
		$http_server->send($_GET['fd'], $_GET['data']);
		$response->end(json_encode(['status' => 200]));
		break;
	case 'broadcast':
		foreach ($http_server->connections as $fd) {
			if ($redis->exists("socket_fd_" . $fd)) {
				$http_server->send($fd, $_GET['data']);
			}
		}
		$response->end(json_encode(['status' => 200]));
		break;
	}
});
$tcp_server->set(array());
$tcp_server->on("connect", function ($server, $fd) use ($redis) {
	$redis->set("socket_fd_" . $fd, time());
	echo "connection open: {$fd}\n";
});
$tcp_server->on("receive", function ($serv, $fd, $threadId, $data) {
	echo $data;
});
$tcp_server->on('close', function ($server, $fd) use ($redis) {
	$redis->del("socket_fd_" . $fd);
	echo "Connection close: {$fd}\n";
});
$http_server->start();