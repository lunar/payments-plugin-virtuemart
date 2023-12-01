<?php
 defined('_JEXEC') or die('Restricted access');

if ( ! class_exists(  'Lunar\\Lunar')) {
	include_once( __DIR__ .'/lib/vendor/autoload.php');
}

if ( ! class_exists( 'vmPSPlugin')) {
	require( JPATH_VM_PLUGINS . DS . 'vmpsplugin.php');
}

use Joomla\CMS\Factory;
use Joomla\CMS\Version;
use Joomla\CMS\Router\Route;

use Lunar\Lunar as ApiClient;
use Lunar\Payment\LunarPluginTrait;

class plgVmPaymentLunar extends vmPSPlugin
{
	use LunarPluginTrait;

	public $version = '1.0.0';

	private $app;
	private $method;
	private ApiClient $apiClient;
	private string $currencyCode;
	private array $args = [];
	private string $intentIdKey = '_lunar_intent_id';
	private bool $testMode = false;

	static $IDS = array();

	protected $_isInList = false;

	public function __construct(& $subject, $config) {
		parent::__construct($subject, $config);
		// vmdebug('Plugin stuff',$subject, $config);
		$this->_loggable = true;
		$this->tableFields = array_keys($this->getTableSQLFields());
		$this->_tablepkey = 'id';
		$this->_tableId = 'id';
		$varsToPush = $this->getVarsToPush();
		$this->addVarsToPushCore($varsToPush,1);
		$this->setConfigParameterable($this->_configTableFieldName, $varsToPush);
		$this->setConvertable(array('min_amount','max_amount','cost_per_transaction','cost_min_transaction'));
		$this->setConvertDecimal(array('min_amount','max_amount','cost_per_transaction','cost_min_transaction','cost_percent_total'));
		
		$this->app = Factory::getApplication();
		$this->testMode = !!$this->app->input->cookie->get('lunar_testmode'); // same with !!$_COOKIE['lunar_testmode']
	}

	private function init()
	{
		$this->method;

	}

	private function setApiClient()
	{
		$this->apiClient = new ApiClient($this->method->api_key, null, $this->testMode);
	}

	/**
	 * This function is triggered when the user click on the Confirm Purchase button on cart view.
     * You can store the transaction/order related details using this function.
     * You can set your html with a variable name html at the end of this function, to be shown on thank you message.
	 */
	public function plgVmConfirmedOrder($cart, $order)
	{
		$billingDetails = $order['details']['BT'];
		$this->method = $this->getVmPluginMethod($billingDetails->virtuemart_paymentmethod_id);

		if (!$this->method) {
			return null;
		}
		if (!$this->selectedThisElement($this->method->payment_element)) {
			return false;
		}

		$this->init();

// file_put_contents(dirname(__DIR__, 2) . "/zzz.log", json_encode(__METHOD__, JSON_PRETTY_PRINT) . PHP_EOL, FILE_APPEND);
		vmLanguage::loadJLang('com_virtuemart', true);
		vmLanguage::loadJLang('com_virtuemart_orders', true);

		$this->getPaymentCurrency($method);

		$currencyId = $this->getCurrencyId($method);
		
		$emailCurrencyId = $this->getEmailCurrency($method);
		$emailCurrency = shopFunctions::getCurrencyByID($emailCurrencyId, 'currency_code_3');

		$orderTotal = $billingDetails->order_total;
		$price = vmPSPlugin::getAmountValueInCurrency($orderTotal, $currencyId);

		$currency = $this->getCurrencyCode($method);

		if (!empty($method->payment_info)) {
			$lang = Factory::getLanguage();
			if ($lang->hasKey($method->payment_info)) {
				$method->payment_info = vmText::_($method->payment_info);
			}
		}

		$transactionId ='';

		if ($method->checkout_mode === 'before') {
			$hasError = true;
			if ($transactionId) {
				
				$response = $this->apiClient->payments()->fetch( $transactionId);
				
				$transactionAmount = $response['amount']['decimal'];
				$transactionCurrency = $response['amount']['currency'];
				
				if ($transactionAmount == $orderTotal && $transactionCurrency == $currency) {
					$hasError = false;
				}
			}

			// return to cart and don't save transaction values, if we don't get the right values;
			if ($hasError) {
				$msg = 'Lunar Transaction not found '.$transactionId;
				$this->app->enqueueMessage($msg, 'error');
				$this->app->redirect(Route::_('index.php?option=com_virtuemart&view=cart'), 301);
				return;
			}
		}

		// $dbValues['payment_method'] = $this->paymentMethod;
		$dbValues['transaction_id'] = $transactionId;
		$dbValues['payment_order_total'] = $price;
		$dbValues['payment_currency'] = $currency;
		$dbValues['email_currency'] = $emailCurrency;
		$dbValues['order_number'] = $billingDetails->order_number;
		$dbValues['virtuemart_paymentmethod_id'] = $billingDetails->virtuemart_paymentmethod_id;
		$dbValues['payment_name'] = $this->renderPluginName($method);
		$dbValues['cost_per_transaction'] = $method->cost_per_transaction;
		$dbValues['cost_min_transaction'] = $method->cost_min_transaction;
		$dbValues['cost_percent_total'] = $method->cost_percent_total;
		$dbValues['tax_id'] = $method->tax_id;

		$this->storePSPluginInternalData($dbValues);

		$orderlink='';
		$tracking = VmConfig::get('ordertracking','guests');

		if ($tracking !='none' and !($tracking =='registered' and empty($billingDetails->virtuemart_user_id))) {

			$orderlink = 'index.php?option=com_virtuemart&view=orders&layout=details&order_number=' . $billingDetails->order_number;
			if ($tracking == 'guestlink' or($tracking == 'guests' and empty($billingDetails->virtuemart_user_id))) {
				$orderlink .= '&order_pass=' . $billingDetails->order_pass;
			}
		}

		$currencyInstance = CurrencyDisplay::getInstance($currencyId, $billingDetails->virtuemart_vendor_id);
		$priceDisplayWithCurrency = $price . ' ' . $currencyInstance->getSymbol();

		//after-payment need specific render and scripts
		if ($method->checkout_mode === 'after') {
			$html = $this->renderByLayout('pay_after', array(
				'method'=> $method,
				'cart'=> $cart,
				'billingDetails' => $billingDetails,
				'payment_name' => $dbValues['payment_name'],
				'displayTotalInPaymentCurrency' => $priceDisplayWithCurrency,
				'orderlink' => $orderlink
			));

		} else {
			$html = $this->renderByLayout('order_done', array(
				'method'=> $method,
				'cart'=> $cart,
				'billingDetails' => $billingDetails,
				'payment_name' => $dbValues['payment_name'],
				'displayTotalInPaymentCurrency' => $priceDisplayWithCurrency,
				'orderlink' => $orderlink
			));

			//before payment display
			//We delete the cart content
			$cart->emptyCart();
			// and send Status email if needed
			$details = $order['details']['BT'];
			$order['order_status'] = $this->getNewStatus($method);
			$order['customer_notified'] = 1;
			$order['comments'] = '';

			/**
			 * There is no VM config setting for os_trigger_paid
			 * In the future, we must set the status for capture on vmConfig
			 * Add the additional info here
			 */
			if ($method->capture_mode === 'instant') {
				$date = Factory::getDate();
				$today = $date->toSQL();
				$order['paid_on'] = $today;
				$order['paid'] = $orderTotal;
			}

			$modelOrder = VmModel::getModel('orders');
			$modelOrder->updateStatusForOneOrder($details->virtuemart_order_id, $order, true);
		}

		vRequest::setVar('html', $html);

		return true;

		// return $this->processConfirmedOrderPaymentResponse($returnValue, $cart, $order, $html, $payment_name, $new_status);
	}

	/**
	 * This function is used If user redirection to the Payment gateway is required.
     * You can use this function as redirect URL for the payment gateway and receive response from payment gateway here.
     * YOUR_SITE/.'index.php?option=com_virtuemart&view=vmplg&task=pluginresponsereceived'
     * The task 'pluginresponsereceived' calls the function written in your payment plugin.
	 */
	public function plgVmOnPaymentResponseReceived(&$html)
	{

	}


    /**
     * SET ARGS
     */
    private function setArgs()
    {
		$session = Factory::getSession();
		// $session->set( 'lunar.transactionId', $transactionId);

		/** @var VirtueMartCart $cart */
		$cart = VirtueMartCart::getCart(false);
		$cart->prepareCartData();
		
		$this->getPaymentCurrency($this->method);
		
		$orderTotal = $cart->cartPrices['billTotal'];
		
		$billingDetail = $cart->BT;

        $this->args = [
            'integration' => [
                'key' => $this->paymentMethod->public_key,
                'name' => $this->getConfigValue('shop_title'),
                'logo' => $this->getConfigValue('logo_url'),
            ],
            'amount' => [
                'currency' => $this->currencyCode,
                'decimal' => (string) $orderTotal,
            ],
            'custom' => [
                // 'orderId' => '', // the order is not created at this point
                'products' => $this->getFormattedProducts(),
                'customer' => [
                    'name' => $billingDetail['first_name'] . " " . $billingDetail['last_name'],
                    'email' => $billingDetail['email'],
                    'telephone' => $billingDetail['phone_1'],
                    // 'address' => $address,
                    'ip' => ShopFunctions::getClientIP(),
                ],
                'platform' => [
                    'name' => 'Joomla',
                    'version' => (new Version())->getShortVersion(),
                ],
                'ecommerce' => [
                    'name' => 'VirtueMart',
                    'version' => vmVersion::$RELEASE,
                ],
                'lunarPluginVersion' => $this->version,
            ],
            'redirectUrl' => '',
            'preferredPaymentMethod' => $this->paymentMethod,
        ];

        // if ($this->getConfigValue('configuration_id')) {
        //     $this->args['mobilePayConfiguration'] = [
        //         'configurationID' => $this->getConfigValue('configuration_id'),
        //         'logo' => $this->getConfigValue('logo_url'),
        //     ];
        // }

        if ($this->testMode) {
            $this->args['test'] = $this->getTestObject();
        }
    }
	
    /** */
    private function getPaymentIntentCookie()
    {
        return $this->app->input->cookie->get($this->intentIdKey);
    }

    /** */
    private function savePaymentIntentCookie($paymentIntentId)
    {
        $this->app->input->cookie->set($this->intentIdKey, $paymentIntentId);
    }

	/**
	 * Keep backwards compatibility
	 * a new parameter has been added in the xml file
	 */
	function getNewStatus($method) {
		//instant payment directly capture
		if ($method->capture_mode === 'instant') {
			if (isset($method->status_capture) and $method->status_capture!="") {
				return $method->status_capture;
			} else {
				return 'S';
			}
		} else {
			if (isset($method->status_success) and $method->status_success!="") {
				return $method->status_success;
			} else {
				return 'C';
			}
		}
	}

	/**
	 * Display stored payment data for an order
	 *
	 */
	function plgVmOnShowOrderBEPayment($virtuemart_order_id, $virtuemart_payment_id) {

		if (!$this->selectedThisByMethodId($virtuemart_payment_id)) {
			return null; // Another method was selected, do nothing
		}

		if (!($paymentTable = $this->getDataByOrderId($virtuemart_order_id))) {
			return null;
		}
		vmLanguage::loadJLang('com_virtuemart');

		$orderTotalInPaymentCurrency = number_format($paymentTable->payment_order_total, 2);

		$html = '<table class="adminlist table">' . "\n";
		$html .= $this->getHtmlHeaderBE();
		$html .= $this->getHtmlRowBE('COM_VIRTUEMART_PAYMENT_NAME', $paymentTable->payment_name);
		$html .= $this->getHtmlRowBE('LUNAR_PAYMENT_TOTAL_CURRENCY', $orderTotalInPaymentCurrency . ' ' . $paymentTable->payment_currency);
		if ($paymentTable->email_currency) {
			$html .= $this->getHtmlRowBE('LUNAR_EMAIL_CURRENCY', $paymentTable->email_currency);
		}
		$html .= $this->getHtmlRowBE('Transaction', $paymentTable->transaction_id);
		$html .= '</table>' . "\n";
		return $html;
	}


	/**
	 * 
	 */
	function plgVmgetPaymentCurrency($virtuemart_paymentmethod_id, &$paymentCurrencyId) {

		if (!($method = $this->getVmPluginMethod($virtuemart_paymentmethod_id))) {
			return null; // Another method was selected, do nothing
		}
		if (!$this->selectedThisElement($method->payment_element)) {
			return false;
		}
		$this->getPaymentCurrency($method);

		$paymentCurrencyId = $this->getCurrencyId($method);

		return;
	}



	/**
	 * @param $orderDetails
	 * @param $data
	 * @return null
	 */
	function plgVmOnUserInvoice($orderDetails, &$data) {

		if (!($method = $this->getVmPluginMethod($orderDetails['virtuemart_paymentmethod_id']))) {
			return null; // Another method was selected, do nothing
		}
		if (!$this->selectedThisElement($method->payment_element)) {
			return null;
		}
		//vmdebug('plgVmOnUserInvoice',$orderDetails, $method);

		if (!isset($method->send_invoice_on_order_null) or $method->send_invoice_on_order_null==1 or $orderDetails['order_total'] > 0.00) {
			return null;
		}

		if ($orderDetails['order_salesPrice']==0.00) {
			$data['invoice_number'] = 'reservedByPayment_' . $orderDetails['order_number']; // Never send the invoice via email
		}
	}

	/**
	 * @param $virtuemart_paymentmethod_id
	 * @param $virtuemart_order_id
	 * @param $emailCurrencyId
	 * @return bool|null
	 */
	function plgVmgetEmailCurrency($virtuemart_paymentmethod_id, $virtuemart_order_id, &$emailCurrencyId) {

		if (!($method = $this->getVmPluginMethod($virtuemart_paymentmethod_id))) {
			return null; // Another method was selected, do nothing
		}
		if (!$this->selectedThisElement($method->payment_element)) {
			return false;
		}

		if (empty($method->email_currency)) {

		} else if ($method->email_currency == 'vendor') {
			$vendor_model = VmModel::getModel('vendor');
			$vendor = $vendor_model->getVendor($method->virtuemart_vendor_id);
			$emailCurrencyId = $vendor->vendor_currency;
		} else if ($method->email_currency == 'payment') {
			$emailCurrencyId = $this->getPaymentCurrency($method);
		}
	}

	protected function renderPluginName( $plugin) {

		$return ='
		<style>.lunar-wrapper .payment_logo img {
				height: 30px;
				padding: 2px;
			}</style>';
		$return .= "<div class='lunar-wrapper' style='display: inline-block'><div class='lunar_title' >" . $plugin->title . "</div>";
		$return .= "<div class='payment_logo' >";

		$path = JURI::root().'plugins/vmpayment/lunar/images/' ;
		$allcards = array('mastercard' =>'mastercard','maestro' =>'maestro','visa' =>'visa','visaelectron' =>'visaelectron');

		if (empty($plugin->card)) {
			$cards = $allcards;
		} else {
			$cards = $plugin->card;
		}

		foreach($cards as $card) {
			if (isset($allcards[$card]) && isset($plugin->$card)) {
				$return .= "<img src='" . $path . $plugin->$card . "' />";
			}
		}

		$return .= "</div></div>";
		$return .= '<div class="lunar_desc" >' . $plugin->description . '</div>';

		$layout = vRequest::getCmd('layout', 'default');
		$view = vRequest::getCmd('view', '');

		if ($plugin->checkout_mode === 'before' && $view === 'cart') {
			if (!isset(plgVmPaymentLunar::$IDS[$plugin->virtuemart_paymentmethod_id])) {
				$return .= $this->renderByLayout('pay_before', array(
					'method'=>$plugin
				));
			//$return .="<pre>".print_r($plugin,true)."</pre>";
			}
			plgVmPaymentLunar::$IDS[$plugin->virtuemart_paymentmethod_id] = true;
		}
		return $return;
	}

	/**
	 * @return string
	 */
	private function getCurrencyCode($method)
	{
		$currency = $method->payment_currency;
		// backward compatibility
		if (is_numeric($method->payment_currency)) {
			$currency = shopFunctions::getCurrencyByID($method->payment_currency, 'currency_code_3');
		}

		return $currency;
	}

	/**
	 * @return int
	 */
	private function getCurrencyId($method)
	{
		// backward compatibility
		if (is_numeric($method->payment_currency)) {
			return $method->payment_currency;
		} else {
			return shopFunctions::getCurrencyIDByName($method->payment_currency);
		}
	}

	/**
	 *
	 */
	function updateTransactionId($transactionId,$orderid) {
			$data = new stdClass();
			$data->transaction_id = $transactionId;
			$data->virtuemart_order_id = $orderid;
			$db	= Factory::getDBO();
			$db->updateObject($this->_tablename, $data, 'virtuemart_order_id');
	}

	/**
	 * Update lunar on update status
	 */
	function plgVmOnUpdateOrderPayment( $order, $old_order_status) {
		$this->method = $this->getVmPluginMethod($order->virtuemart_paymentmethod_id);

		if (!$this->method) {
			return null;
		}
		// Another method was selected, do nothing
		if ( ! $this->selectedThisElement( $this->method->payment_element)) {
			return null;
		}

		//@TODO half refund $order->order_status != $method->status_half_refund

		if ($order->order_status != $this->method->status_capture
			&& $order->order_status != $this->method->status_success
			&& $order->order_status != $this->method->status_refunded
			) {
			// vminfo('Order_status not found '.$order->order_status.' in '.$method->status_capture.', '.$method->status_success.', '.$method->status_refunded);
			return null;
		}

		// order exist for lunar ?
		if (!($paymentTable = $this->getDataByOrderId($order->virtuemart_order_id))) {
			return null;
		}

		$this->setApiClient();
		$transactionid = $paymentTable->transaction_id;
		$response = \Lunar\Transaction::fetch( $transactionid);
		vmdebug('Lunar Transaction::fetch',$response);

		if ($order->order_status == $this->method->status_refunded) {
			/* refund payment if already captured */
			if ( !empty($response['transaction']['capturedAmount'])) {
				$amount = $response['transaction']['capturedAmount'];
				$data = array(
					'amount'     => $amount,
					'descriptor' => ""
				);
				$response = \Lunar\Transaction::refund($transactionid, $data);
				vmdebug('Lunar Transaction::refund',$response);
			} else {
				/* void payment if not already captured */
				$data = array(
					'amount' => $response['transaction']['amount']
				);
				$response = \Lunar\Transaction::void( $transactionid, $data);
				vmdebug('Lunar Transaction::void',$response);
			}
		} elseif ($order->order_status == $this->method->status_capture) {
			if ( empty($response['transaction']['capturedAmount'])) {
				$amount = $response['transaction']['amount'];
				$data = array(
					'amount'     => $amount,
					'descriptor' => ""
				);

				$response = \Lunar\Transaction::capture( $transactionid, $data);

				vmdebug('Lunar Transaction::capture',$response);
			}
		}
	}
	
    /**
     *
     */
    private function getTestObject(): array
    {
        return [
            "card"        => [
                "scheme"  => "supported",
                "code"    => "valid",
                "status"  => "valid",
                "limit"   => [
                    "decimal"  => "25000.99",
                    "currency" => $this->currencyCode,
                    
                ],
                "balance" => [
                    "decimal"  => "25000.99",
                    "currency" => $this->currencyCode,
                    
                ]
            ],
            "fingerprint" => "success",
            "tds"         => array(
                "fingerprint" => "success",
                "challenge"   => true,
                "status"      => "authenticated"
            ),
        ];
    }
}
