<?php

namespace Lunar\Tests;

use Lunar\Lunar;
use PHPUnit\Framework\TestCase;

abstract class BaseTest extends TestCase
{
	/**
	 * @var Lunar
	 */
	protected $lunar;
	protected $payment_id;

	protected $public_key;


	public function setUp(): void
	{
		$this->lunar        = new Lunar("a61437c5-1043-443b-ac3a-fe49c2b58481");
		$this->public_key		 = "5fb82649-fe30-4e63-985e-2cf48e925291";
		$this->payment_id = "80cb751b-12cd-5c93-b90e-cd7606c80c6c";
	}

}
