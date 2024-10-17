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
			/*
			{
			  "specVersion" : "1.0",
			  "type" : "EVENT",
			  "headers" : {
				"topic" : "dingTalk",
				"messageId" : "213d841d_972_1898bb26334_70a7",
				"contentType" : "application/json",
				"time" : 167123345,
				"eventType" : "user_add_org",
				"eventId" : "c7c7120f2c07419***ebdba0318c8",
				"eventCorpId" : "ding9f50b15b***16741",
				"eventBornTime" : 1683533823336,
				"eventUnifiedAppId" : "bbb381b6-f01xxxxx58daac"  
			   }
			   "data" : "{\"timestamp\" : \"1685501863357\", \"userId\" : [\"015xxxx227\"]}"
			}
			*/
			
			//var_dump($message);
			
			
			
	        return ['status' => 'SUCCESS', 'message' => 'success'];
	    });
	    
	    $client->registerHandler('CALLBACK', function($message) {
	        // 处理回调消息
	        return ['response' => ['message' => 'Callback processed']];
	    });
	    
	    // 连接并开始接收消息
	    $client->connect();
}


