<?php

namespace Lunar\Payment;

use \VirtueMartCart;

/**
 * Used to have here the code with almost no custom logic
 */
trait LunarPluginTrait
{
	/**
	 * Create the table for this plugin if it does not yet exist.
	 */
	public function getVmPluginCreateTableSQL() {

		return $this->createTableSQL('Payment Lunar Table');
	}

	/**
	 * Fields to create the payment table
	 */
	public function getTableSQLFields() {

		return [
			'id'                          => 'int(1) UNSIGNED NOT NULL AUTO_INCREMENT',
			'virtuemart_order_id'         => 'int(1) UNSIGNED',
			'payment_method' 			  => 'char(50)',
			'transaction_id'              => 'varchar(1000)',
			'payment_order_total'         => 'decimal(15,5) NOT NULL DEFAULT \'0.00000\'',
			'payment_currency'            => 'char(3)',
			'email_currency'              => 'char(3)',
			'order_number'                => 'char(64)',
			'virtuemart_paymentmethod_id' => 'mediumint(1) UNSIGNED',
			'payment_name'                => 'varchar(5000)',
			'cost_per_transaction'        => 'decimal(10,2)',
			'cost_min_transaction'        => 'decimal(10,2)',
			'cost_percent_total'          => 'decimal(10,2)',
			'tax_id'                      => 'smallint(1)',

		];
	}

	/**
	 *  Used for many different purposes(Payment Capture, Refund, Half Refund and Void)
	 */
	public function plgVmOnSelfCallFE( $type, $name, &$render)
    {
        //
	}

    /**
	 * Create the table for this plugin if it does not yet exist.
	 * This functions checks if the called plugin is active one.
	 * When yes it is calling the standard method to create the tables
	 */
	public function plgVmOnStoreInstallPaymentPluginTable($jplugin_id)
    {
		return $this->onStoreInstallPluginTable($jplugin_id);
	}

	/**
	 * This function is called After plgVmOnCheckAutomaticSelectedPayment
	 * Checks if the payment conditions are fulfilled for the payment method.
	 * If you want to show/hide the payment plugin on some specific conditions
	 *(more conditions that it has in the parent)
	 *
	 * @param VirtueMartCart $cart
	 * @param int            $method
	 * @param array          $cart_prices
	 */
	protected function checkConditions($cart, $method, $cart_prices) 
    {
		return parent::checkConditions($cart, $method, $cart_prices);
	}

	/**
	 * This event is fired after the payment method has been selected. It can be used to store
	 * additional payment info in the cart.
	 *
	 * @param VirtueMartCart $cart: the actual cart
	 * @return null if the payment was not selected, true if the data is valid, error message if the data is not valid
	 *
	 */
	public function plgVmOnSelectCheckPayment(VirtueMartCart $cart, &$msg) 
    {
		return $this->OnSelectCheck($cart);
	}

	/**
	 * This event is fired to display the plugin methods in the cart(edit shipment/payment) for example
	 *
	 * @param object  $cart Cart object
	 * @param integer $selected ID of the method selected
	 * @return boolean True on success, false on failures, null when this plugin was not selected.
	 * On errors, JError::raiseWarning(or JError::raiseError) must be used to set a message.
	 *
	 */
	public function plgVmDisplayListFEPayment(VirtueMartCart $cart, $selected = 0, &$htmlIn)
    {
		$this->_isInList = true;
		return $this->displayListFE($cart, $selected, $htmlIn);

	}

	/**
	 * Calculate the price(value, tax_id) of the selected method
	 * It is called by the calculator
	 * This function does NOT to be reimplemented. If not reimplemented, then the default values from this function are taken.
	 *
	 * @return null if the method was not selected, false if the shipping rate is not valid any more, true otherwise
	 *
	 */
	public function plgVmOnSelectedCalculatePricePayment(VirtueMartCart $cart, array &$cart_prices, &$cart_prices_name)
    {
		return $this->onSelectedCalculatePrice($cart, $cart_prices, $cart_prices_name);
	}

    /**
	 * plgVmOnCheckAutomaticSelectedPayment
	 * Checks how many plugins are available. If only one, the user will not have the choice. Enter edit_xxx page
	 * The plugin must check first if it is the correct type
	 *
	 * @param VirtueMartCart cart: the cart object
	 * @return null if no plugin was found, 0 if more then one plugin was found,  virtuemart_xxx_id if only one plugin is found
	 *
	 */
	public function plgVmOnCheckAutomaticSelectedPayment(VirtueMartCart $cart, array $cart_prices = array(), &$paymentCounter)
    {
		return $this->onCheckAutomaticSelected($cart, $cart_prices, $paymentCounter);
	}

	/**
	 * This method is fired when showing the order details in the frontend.
	 * It displays the method-specific data.
	 *
	 * @param integer $order_id The order ID
	 * @return mixed Null for methods that aren't active, text(HTML) otherwise
	 */
	public function plgVmOnShowOrderFEPayment($virtuemart_order_id, $virtuemart_paymentmethod_id, &$payment_name)
    {
		$this->onShowOrderFE($virtuemart_order_id, $virtuemart_paymentmethod_id, $payment_name);
	}
    
	/**
	 * This method is fired when showing when printing an Order
	 * It displays the the payment method-specific data.
	 *
	 * @param integer $_virtuemart_order_id The order ID
	 * @param integer $method_id  method used for this order
	 * @return mixed Null when for payment methods that were not selected, text(HTML) otherwise
	 */
	public function plgVmonShowOrderPrintPayment($order_number, $method_id) 
    {
		return $this->onShowOrderPrint($order_number, $method_id);
	}

	public function plgVmDeclarePluginParamsPaymentVM3( &$data) 
    {
		return $this->declarePluginParams('payment', $data);
	}

	public function plgVmSetOnTablePluginParamsPayment($name, $id, &$table) 
    {
		return $this->setOnTablePluginParams($name, $id, $table);
	}

	/**
	 * @return string
	 */
	public function getPluginVersion() 
    {
		$xmlStr = file_get_contents(dirname(__DIR__).'/lunar.xml');
		$xmlObj = simplexml_load_string($xmlStr);
		return (string) $xmlObj->version;
	}

}