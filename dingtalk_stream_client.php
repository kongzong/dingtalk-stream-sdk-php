<?php
/**
 * 钉钉 Stream PHP SDK
 * PHP 版本要求：7.0+
 */
require("websocket_class.php");

class DingTalkStreamClient {
    private $clientId;
    private $clientSecret;
    private $subscriptions;
    private $ua;
    private $localIp;
    private $debug = false;
    private $ws;
    private $endpoint;
    private $ticket;
    private $handlers = [];
    private $isConnected = false;
    private $lastPingTime = 0;
	
    private $wsc;
    
    const API_URL = 'https://api.dingtalk.com/v1.0/gateway/connections/open';
    
    /**
     * 构造函数
     * @param array $config 配置信息
     */
    public function __construct(array $config) {
        $this->clientId = $config['clientId'] ?? '';
        $this->clientSecret = $config['clientSecret'] ?? '';
        $this->subscriptions = $config['subscriptions'] ?? [
            ['type' => 'EVENT', 'topic' => '*']
        ];
        $this->ua = $config['ua'] ?? 'dingtalk-stream-php/1.0.0';
        $this->localIp = $config['localIp'] ?? '';
        $this->debug = $config['debug'] ?? false;
    }
    
    /**
     * 注册事件处理器
     * @param string $type 事件类型：EVENT, CALLBACK
     * @param callable $handler 处理函数
     */
    public function registerHandler($type, callable $handler) {
        $this->handlers[$type] = $handler;
    }
    
    /**
     * 开始连接
     */
    public function connect() {
        $this->log("Starting connection process...");
        
        // 1. 获取连接凭证
        if (!$this->getConnectionCredentials()) {
            throw new Exception("Failed to get connection credentials");
        }
        
        // 2. 建立 WebSocket 连接
        $this->establishWebSocketConnection();
        
        // 3. 开始消息循环
        $this->messageLoop();
    }
    
    /**
     * 获取连接凭证
     * @return bool
     */
    private function getConnectionCredentials() {
	$data = [
		'clientId' => $this->clientId,
		'clientSecret' => $this->clientSecret,
		'subscriptions' => $this->subscriptions,
		'ua' => $this->ua
	];
	if ($this->localIp) {
		$data['localIp'] = $this->localIp;
	}
	
	$ch = curl_init(self::API_URL);
	
	curl_setopt_array($ch, [
		CURLOPT_POST => true,
		CURLOPT_POSTFIELDS => json_encode($data),
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_HTTPHEADER => [
			'Content-Type: application/json',
			'Accept: application/json'
		]
	]);
	
	$result = curl_exec($ch);
	
	if ($result === false) {
		$this->log("Failed to get connection credentials: " . curl_error($ch));
		curl_close($ch);
		return false;
	}
	
	curl_close($ch);
	
	$response = json_decode($result, true);
	$this->endpoint = $response['endpoint'] ?? '';
	$this->ticket = $response['ticket'] ?? '';
	
	return !empty($this->endpoint) && !empty($this->ticket);
    }
    
    /**
     * 建立 WebSocket 连接
     */
    private function establishWebSocketConnection() {
	$url = "{$this->endpoint}?ticket={$this->ticket}";
	
	$this->wsc = new WebSocket\Client($url);
	
	$this->isConnected = true;
	$this->log("WebSocket connection established");

    }
	
    
    /**
     * 消息循环
     */
    private function messageLoop() {
        while ($this->isConnected) {
            $data = $this->receiveData();
            if ($data === false) continue;
            
            $this->handleMessage($data);
            
        }
    }
	
    
    /**
     * 接收数据
     * @return string|false
     */
    private function receiveData() {
	$message = $this->wsc->receive();
	return $message;
    }
    
    /**
     * 处理消息
     * @param string $data
     */
    private function handleMessage($data) {
		
	$this->log("handleMessage...");
        $message = json_decode($data, true);
        if (!$message) return;
        
        $type = $message['type'] ?? '';
        $headers = $message['headers'] ?? [];
        $topic = $headers['topic'] ?? '';
        
        switch ($type) {
            case 'SYSTEM':
		$this->log("handleSystemMessage...");
                $this->handleSystemMessage($topic, $message);
                break;
                
            case 'EVENT':
            case 'CALLBACK':
                $this->handleBusinessMessage($type, $message);
                break;
        }
    }
    
    /**
     * 处理系统消息
     * @param string $topic
     * @param array $message
     */
    private function handleSystemMessage($topic, $message) {
        switch ($topic) {
            case 'ping':
		$this->log("handlePingMessage...");
                $this->lastPingTime = time();
                $this->sendPong($message);
                break;
                
            case 'disconnect':
                $this->handleDisconnect();
                break;
        }
    }
    
    /**
     * 处理业务消息
     * @param string $type
     * @param array $message
     */
    private function handleBusinessMessage($type, $message) {
        if (isset($this->handlers[$type])) {
            $result = call_user_func($this->handlers[$type], $message);
            $this->sendResponse($message['headers']['messageId'], $result);
        }
    }
    
    /**
     * 发送 pong 响应
     * @param array $pingMessage
     */
    private function sendPong($pingMessage) {
        $response = [
            'code' => 200,
            'headers' => [
                'contentType' => 'application/json',
                'messageId' => $pingMessage['headers']['messageId']
            ],
            'message' => 'OK',
            'data' => $pingMessage['data']
        ];
		
        $this->sendData(json_encode($response));
    }
    
    /**
     * 处理断开连接
     */
    private function handleDisconnect() {
        $this->log("Received disconnect request from server");
        $this->isConnected = false;

	fclose($this->wsc->socket);
		
        // 重新连接
        $this->connect();
    }
    
    /**
     * 发送响应
     * @param string $messageId
     * @param array $result
     */
    private function sendResponse($messageId, $result) {
        $response = [
            'code' => 200,
            'headers' => [
                'contentType' => 'application/json',
                'messageId' => $messageId
            ],
            'message' => 'OK',
            'data' => json_encode($result)
        ];
		
        $this->sendData(json_encode($response));
    }
    
    /**
     * 发送数据
     * @param string $data
     */
    private function sendData($data) {
	$this->wsc->send($data);		
    }
    
    
    /**
     * 重新连接
     */
    private function reconnect() {
        $this->log("Reconnecting...");
        $this->isConnected = false;
        $this->connect();
    }
    
    /**
     * 日志记录
     * @param string $message
     */
    private function log($message) {
        if ($this->debug) {
            echo "[" . date('Y-m-d H:i:s') . "] {$message}\n";
        }
    }
}


