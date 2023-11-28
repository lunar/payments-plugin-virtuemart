<?php

namespace Lunar\Tests;

use Lunar\Endpoint\Payments;
use Lunar\Exception\NotFound;

class PaymentsTest extends BaseTest
{
	/**
	 * @var Payments
	 */
	protected $payments;

	/**
	 *
	 */
	public function setUp(): void
	{
		parent::setUp();
		$this->payments = $this->lunar->payments();
	}

	/**
	 *
	 */
	public function testCreate()
	{

		$new_payment_id = $this->payments->create(array(
			'test' => [
				"card" => [
					"scheme" => "supported",
					"code" => "valid",
					"status" => "valid",
					"limit" => [
						"decimal" => "500",
						"currency" => "DKK"
					],
					"balance" => [
						"decimal" => "500",
						"currency" => "DKK"
					]
				],
				"fingerprint" => "success",
				"tds" => array(
					"fingerprint" => "success",
					"challenge" => true,
					"status" => "authenticated"
				),
			],
			'integration' => [
				'key' => $this->public_key,
			],
			'amount' => array(
				'currency' => 'DKK',
				'decimal' => '20.50'
			),
			'custom' => array(
				'source' => 'php client test'
			),
			'mobilePayConfiguration' => array(
				'configurationID' => '00000000000000000000000000000000',
				'logo' => 'https://lunar.app/logo.img'
			),
			'redirectUrl' => 'https://google.com'
		));

		$this->assertNotEmpty($new_payment_id, 'primary key');
		$this->assertIsString($new_payment_id, 'primary key type');
	}

	/**
	 *
	 */
	public function testFetch()
	{

		$payment = $this->payments->fetch($this->payment_id);

		$this->assertEquals($this->payment_id, $payment['id']);
	}

	public function testUpdatePaymentMethod()
	{

		$payment_id = $this->payments->put($this->payment_id, [
			'mobilePay' => [
				'returnUrl' => 'https://payments.lunar.app/pay?referenceId={paymentId}&redirectUrl=https://webshop.dk',
			]
		]);

		$payment = $this->payments->fetch($payment_id);

		$this->assertEquals('mobilePay', $payment['selectedPaymentMethod']);
	}

	public function testIntent()
	{

		$payment = $this->payments->intent($this->payment_id);

		$this->assertEquals($this->payment_id, $payment['id']);
	}

	public function testSubmit()
	{
		$payment_id = $this->getNewPaymentId();

		$payment = $this->payments->submit($payment_id, [
			'mobilePay' => [
				'returnUrl' => 'https://payments.lunar.app/pay?referenceId={paymentId}&redirectUrl=https://webshop.dk',
			]
		]);

		$this->assertArrayHasKey('challenges', $payment);
	}

	public function testMethods()
	{

		$methods = $this->payments->methods($this->payment_id);

		$this->assertGreaterThan(1, count($methods));
	}

	public function testChallenge()
	{
		$challenges = $this->createPaymentAndGetChallenges();

		$this->assertNotNull($challenges);

		$response = $this->payments->challenge($this->payment_id, $challenges[0]['id']);

		$this->assertNotNull($response);

	}

	public function testHints()
	{

		$payment = $this->payments->submit($this->payment_id, [
			'mobilePay' => true
		]);

		$challenge = $this->payments->challenge($this->payment_id, $payment['challenges'][0]['id']);

		$payment = $this->payments->submit($this->payment_id, [
			'mobilePay' => true
		]);

		$challenge = $this->payments->challenge($this->payment_id, $payment['challenges'][0]['id']);

		$this->assertNotNull($challenge);
	}

	public function testCapture()
	{
		$payment_id = $this->getNewPaymentId();

		$payment = $this->payments->capture($payment_id, ['amount' => array(
			'currency' => 'EUR',
			'decimal' => '0.10'
		),]);

		$this->assertArrayHasKey('challenges', $payment);
	}

	/**
	 *
	 */
	public function testFailFetch()
	{
		$this->expectException(NotFound::class);
		$this->payments->fetch('wrong_id');
	}


	protected function createPaymentAndGetChallenges()
	{
		$payment_id = $this->getNewPaymentId();

		$payment = $this->payments->submit($payment_id, [
			'mobilePay' => [
				'returnUrl' => 'https://payments.lunar.app/pay?referenceId={paymentId}&redirectUrl=https://webshop.dk',
			]
		]);

		return $payment['challenges'];

	}

	private function getNewPaymentId(): string
	{
		return $this->payments->create(array(
				'test' => [
					"card" => [
						"scheme" => "supported",
						"code" => "valid",
						"status" => "valid",
						"limit" => [
							"decimal" => "399.95",
							"currency" => "EUR"
						],
						"balance" => [
							"decimal" => "399.95",
							"currency" => "EUR"
						]
					],
					"fingerprint" => "success",
					"tds" => array(
						"fingerprint" => "success",
						"challenge" => false,
						"status" => "authenticated"
					),
				],
				'integration' => [
					'key' => $this->public_key,
				],
				'amount' => array(
					'currency' => 'EUR',
					'decimal' => '399.95'
				),
				'custom' => array(
					'source' => 'php client test'
				),
				'mobilePayConfiguration' => array(
					'configurationID' => '00000000000000000000000000000000',
					'logo' => 'https://lunar.app/logo.img'
				)
			)
		);
	}


}
