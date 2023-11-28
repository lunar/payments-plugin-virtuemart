<?php

namespace Lunar\Endpoint;

/**
 * Class Endpoint
 *
 * @package Lunar\Endpoint
 */
abstract class Endpoint
{
	/**
	 * @var \Lunar\Lunar
	 */
	protected $lunar;

	/**
	 * Endpoint constructor.
	 *
	 * @param $lunar
	 */
	function __construct($lunar)
	{
		$this->lunar = $lunar;
	}
}
