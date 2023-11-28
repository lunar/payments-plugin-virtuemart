<?php

namespace Lunar\Endpoint;

use Lunar\Utils\Cursor;

/**
 * Class Payments
 * @link https://github.com/lunarway/merchant-payments/blob/master/api/swagger.yaml
 * @link https://github.com/lunarway/squad-juno-docs/blob/master/docs/payments/hosted-checkout.md
 * @package Lunar\Endpoint
 */
class Payments extends Endpoint
{
	/**
	 *
	 * @param $args array
	 *
	 * @return string
	 */
	public function create($args)
	{
		$url = 'payments';

		$api_response = $this->lunar->client->request('POST', $url, $args);

		return $api_response->json['paymentId'];
	}

	/**
	 *
	 * @param $payment_id
	 *
	 * @return array
	 */
	public function fetch($payment_id)
	{
		$url = 'payments/' . $payment_id;

		$api_response = $this->lunar->client->request('GET', $url);

		return $api_response->json['payment'];
	}

	/**
	 *
	 * @param $payment_id
	 *
	 * @return array
	 */
	public function put($payment_id, $args)
	{
		$url = 'payments/' . $payment_id;

		$api_response = $this->lunar->client->request('PUT', $url, $args);

		return $api_response->json['paymentId'];
	}

	/**
	 *
	 * @param $payment_id
	 *
	 * @return array
	 */
	public function intent($payment_id)
	{
		$url = 'payments/' . $payment_id.'/intent';

		$api_response = $this->lunar->client->request('GET', $url);

		return $api_response->json;
	}

	public function submit($payment_id, $args)
	{
		$url = 'payments/' . $payment_id.'/submit';

		$api_response = $this->lunar->client->request('POST', $url, $args);

		return $api_response->json;
	}

	public function capture($payment_id, $args)
	{
		$url = 'payments/' . $payment_id.'/capture';

		$api_response = $this->lunar->client->request('POST', $url, $args);

		return $api_response->json;
	}

	public function methods($payment_id)
	{
		$url = 'payments/' . $payment_id.'/methods';

		$api_response = $this->lunar->client->request('GET', $url);

		return $api_response->json;
	}

	public function challenge($payment_id, $challenge_id)
	{
		$url = 'payments/' . $payment_id.'/challenge/'.$challenge_id;

		$api_response = $this->lunar->client->request('POST', $url);

		return $api_response->json;
	}

	public function hints($payment_id, $args)
	{
		$url = 'payments/' . $payment_id.'/hints';

		$api_response = $this->lunar->client->request('POST', $url, $args);

		return $api_response->json;
	}

	public function refund($payment_id, $args)
	{
		$url = 'payments/' . $payment_id.'/refund';

		$api_response = $this->lunar->client->request('POST', $url, $args);

		return $api_response->json;
	}


	public function cancel($payment_id, $args)
	{
		$url = 'payments/' . $payment_id.'/cancel';

		$api_response = $this->lunar->client->request('POST', $url, $args);

		return $api_response->json;
	}


}
