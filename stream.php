<?php
set_time_limit(0);

require("dingtalk_stream_client.php");

run();

function run() {
	
    $config = [
	'clientId' => 'client id',
	'clientSecret' => 'client secret',
	'debug' => true
    ];
	
    $client = new DingTalkStreamClient($config);
    
    // 注册事件处理器
    $client->registerHandler('EVENT', function($message) {
	// 处理事件消息
	return ['status' => 'SUCCESS', 'message' => 'success'];
    });
	    
    $client->registerHandler('CALLBACK', function($message) {
	// 处理回调消息
	return ['response' => ['message' => 'Callback processed']];
    });
	    
    // 连接并开始接收消息
    $client->connect();
}


