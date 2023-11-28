<?php

namespace Lunar;

use Lunar\Endpoint\Payments;
use Lunar\HttpClient\CurlClient;
use Lunar\HttpClient\HttpClientInterface;

/**
 * Class Lunar
 *
 * @package Lunar
 */
class Lunar
{
	/**
	 * @var string
	 */
	const BASE_URL = 'https://api.prod.lunarway.com/merchant-payments/v1';
	const TEST_BASE_URL = 'https://api.dev.lunarway.com/merchant-payments/v1';

	/**
	 * @var HttpClientInterface
	 */
	public $client;

	/**
	 * @var string
	 */
	private $api_key;

	private $version = '1.0.0';


	/**
	 * Lunar constructor.
	 *
	 * @param                          $api_key
	 * @param HttpClientInterface $client
	 * @throws Exception\ApiException
	 */
	public function __construct($api_key, HttpClientInterface $client = null, $test = false)
	{
		$this->api_key = $api_key;
		$url = $test ? self::TEST_BASE_URL : self::BASE_URL;
		$this->client  = $client ? $client
			: new CurlClient($this->api_key, $url);
	}

	/**
	 * @return string
	 */
	public function getApiKey()
	{
		return $this->api_key;
	}


	/**
	 * @return Payments
	 */
	public function payments()
	{
		return new Payments($this);
	}

	/**
	 * @return string
	 */
	public function getVersion(){
		return $this->version;
	}
}
