<?php

namespace UPush;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Exception;

abstract class UmengNotification 
{
	// The host
	protected $host = "http://msg.umeng.com";

	// The upload path
	protected $uploadPath = "/upload";

	// The post path
	protected $postPath = "/api/send";

	// The app master secret
	protected $appMasterSecret = NULL;

	// Set key/value for $data array, for the keys which can be set please see $DATA_KEYS, $PAYLOAD_KEYS, $BODY_KEYS, $POLICY_KEYS
	abstract function setPredefinedKeyValue($key, $value);

	/*
	 * $data is designed to construct the json string for POST request. Note:
	 * 1)The key/value pairs in comments are optional.  
	 * 2)The value for key 'payload' is set in the subclass(AndroidNotification or IOSNotification), as their payload structures are different.
	 */ 
	protected $data = array(
			"appkey"           => NULL,
			"timestamp"        => NULL,
			"type"             => NULL,
			//"device_tokens"  => "xx",
			//"alias"          => "xx",
			//"file_id"        => "xx",
			//"filter"         => "xx",
			//"policy"         => array("start_time" => "xx", "expire_time" => "xx", "max_send_num" => "xx"),
			"production_mode"  => "true",
			//"feedback"       => "xx",
			//"description"    => "xx",
			//"thirdparty_id"  => "xx"
	);

	protected $DATA_KEYS    = array("appkey", "timestamp", "type", "device_tokens", "alias", "alias_type", "file_id", "filter", "production_mode",
								    "feedback", "description", "thirdparty_id");
	protected $POLICY_KEYS  = array("start_time", "expire_time", "max_send_num");

	function __construct() {

	}

	function setAppMasterSecret($secret) {
		$this->appMasterSecret = $secret;
	}
	
	/**
	* 检测所有参数是否准备就绪
	* 成功返回true，否则抛出异常
	*/
	function isComplete() 
	{
		if (is_null($this->appMasterSecret))
		{
			throw new Exception('请设置APP Master Secret，用于生成签名');
		}
		$this->checkArrayValues($this->data);
		return TRUE;
	}

	private function checkArrayValues($arr) 
	{
		foreach ($arr as $key => $value) 
		{
			if (is_null($value))
				throw new Exception($key . " is NULL!");
			else if (is_array($value)) 
			{
				$this->checkArrayValues($value);
			}
		}
	}

	

	/**
	* 向友盟发送推送请求
	* 如果成功，返回true；否则抛出异常
	*/
	function send() 
	{
		// 检测所传的值是否可用
    	$this->isComplete();

		// 创建客户端
		$client = new Client([
			// Base URI is used with relative requests
			'base_uri' => $this->host,
			// You can set any number of default request options.
			'timeout'  => 2.0,
		]);
		
		try
		{
			// 请求的数据
			$postBody = json_encode($this->data);
			// 对请求数据进行签名
			$sign = md5("POST{$this->host}{$this->postPath}{$postBody}{$this->appMasterSecret}");
			
			// 进行网络请求
			$client->request('POST', $this->postPath, [
				'query' => ['sign' => $sign],
				'body' => $postBody
			]);
			
			return true;
		}
		catch (RequestException $exception)
		{
			// 如果返回的异常存在回执报文，则解析报文内容
			if ($exception->hasResponse())
			{
				// 获取报文内容
				$response = $exception->getResponse()->getBody()->getContents();
				
				// 对报文内容进行JSON解析
				$response = json_decode($response, true);
				
				// 错误信息
				$message = "错误编码：{$response['data']['error_code']}；错误信息：{$response['data']['error_msg']}";
				
				// 将错误作为异常抛出
				throw new Exception($message);
			}
			else
			{
				throw new Exception($exception->getMessage());
			}
		}
    }
	
}
