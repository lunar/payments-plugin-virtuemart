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

use Lunar\Exception\ApiException;
use Lunar\Lunar as ApiClient;
use Lunar\Payment\LunarPluginTrait;

class plgVmPaymentLunar extends vmPSPlugin
{
	use LunarPluginTrait;

	const REMOTE_URL = 'https://pay.lunar.money/?id=';
    const TEST_REMOTE_URL = 'https://hosted-checkout-git-develop-lunar-app.vercel.app/?id=';
	
	static $IDS = array();
	protected $_isInList = false;

	private $app;
	private $method;
	private VirtueMartCart $cart;
	private VirtueMartModelOrders $vmOrderModel;
	private ApiClient $apiClient;
	private int $currencyId;
	private string $currencyCode;
	private string $totalAmount;
	private string $emailCurrency;
	private array $args = [];
	private string $intentIdKey = '_lunar_intent_id';
	private bool $testMode = false;


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
		$this->cart = VirtueMartCart::getCart(false);
		$this->cart->prepareCartData();

		$this->apiClient = new ApiClient($this->method->api_key, null, $this->testMode);

		$this->getPaymentCurrency($this->method);

		$this->maybeSetPaymentInfo();

		$this->currencyId = $this->getCurrencyId();
		$this->currencyCode = $this->getCurrencyCode();
		$this->totalAmount = (string) $this->cart->cartPrices['billTotal'];

		$emailCurrencyId = $this->getEmailCurrency($this->method);
		$this->emailCurrency = shopFunctions::getCurrencyByID($emailCurrencyId, 'currency_code_3');

		vmLanguage::loadJLang('com_virtuemart', true);
		vmLanguage::loadJLang('com_virtuemart_orders', true);

		$this->vmOrderModel = VmModel::getModel('orders');
	}

	/**
	 * This function is triggered when the user click on the Confirm Purchase button on cart view.
     * You can store the transaction/order related details using this function.
     * You can set your html with a variable name html at the end of this function, to be shown on thank you message.
	 */
	public function plgVmConfirmedOrder($cart, $order)
	{
		$billingDetails = $order['details']['BT'];

		if (!$this->checkMethodIsSelected($cart->virtuemart_paymentmethod_id)) {
			return false;
		}

		$this->init();

		if ($this->method->checkout_mode === 'before') {

			$this->setArgs();

			$paymentIntentId = $this->getPaymentIntentCookie();

			// check if retrieved payment intent is for the current cart/order
			if ($paymentIntentId) {
				$this->fetchApiTransaction($paymentIntentId);
			} else {
				try {
					$paymentIntentId = $this->apiClient->payments()->create($this->args);
					$this->setPaymentIntentCookie($paymentIntentId);
				} catch(ApiException $e) {
					$this->redirectBackWithNotification($e->getMessage());
				}
			}
	
			if (! $paymentIntentId) {
				$this->redirectBackWithNotification('An error occurred creating payment intent. Please try again or contact system administrator.');
			}

			$redirectUrl = ($this->testMode ? self::TEST_REMOTE_URL : self::REMOTE_URL) . $paymentIntentId;
			$this->app->redirect($redirectUrl);			
		}

		$this->storeDbLunarTransaction($paymentIntentId, $billingDetails);

		$this->finalizeOrder($billingDetails);

		return true;
	}

	/**
	 * This function is used If user redirection to the Payment gateway is required.
     * You can use this function as redirect URL for the payment gateway and receive response from payment gateway here.
     * YOUR_SITE/.'index.php?option=com_virtuemart&view=vmplg&task=pluginresponsereceived'
     * The task 'pluginresponsereceived' calls the function written in your payment plugin.
	 */
	public function plgVmOnPaymentResponseReceived(&$html, &$paymentResponse)
	{
		$orderNumber = vRequest::getVar('order_number');
		$paymentMethod = vRequest::getVar('lunar_method');
		$methodId = vRequest::getVar('pm');

		if (!$this->checkMethodIsSelected($methodId)) {
			$this->redirectBackWithNotification('Bad payment method');
		}

		if (!($orderNumber || $paymentMethod)) {
			$this->redirectBackWithNotification('No order Id or payment name provided.');
		}

		$this->init();

		$paymentIntentId = $this->getPaymentIntentCookie();

		if (! $paymentIntentId) {
			$this->redirectBackWithNotification('No payment intent id found.');
		}

		$this->fetchApiTransaction($paymentIntentId);

		$orderId = VirtueMartModelOrders::getOrderIdByOrderNumber($orderNumber);
		/** @var VirtueMartModelOrders $order */
		$order = $this->vmOrderModel->getOrder($orderId);

		$this->finalizeOrder($order['details']['BT']);

		return true;

	}


    /**
     * SET ARGS
     */
    private function setArgs()
    {
		$this->getPaymentCurrency($this->method);
		
		$billingDetail = $this->cart->BT;

        $this->args = [
            'integration' => [
                'key' => $this->method->public_key,
                'name' => $this->method->shop_title,
                'logo' => $this->method->logo_url,
            ],
            'amount' => [
                'currency' => $this->currencyCode,
                'decimal' => $this->totalAmount,
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
                'lunarPluginVersion' => $this->getPluginVersion(),
            ],
            'redirectUrl' => JURI::root()
							.'index.php?option=com_virtuemart&view=vmplg&task=pluginresponsereceived' 
							. '&order_number=' . $this->cart->order_number 
							. '&pm=' . $this->method->virtuemart_paymentmethod_id 
							. '&lunar_method=' . 'card', //$this->paymentMethod
            // 'preferredPaymentMethod' => $this->paymentMethod,
            'preferredPaymentMethod' => 'card',
        ];

        // if ('mobilePay' == $this->paymentMethod) {
        //     $this->args['mobilePayConfiguration'] = [
        //         'configurationID' => $this->method->configuration_id,
        //         'logo' => $this->method->logo_url,
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
    private function setPaymentIntentCookie($paymentIntentId = null, $expire = 0)
    {
        $this->app->input->cookie->set($this->intentIdKey, $paymentIntentId, $expire, '/', '', false, true);
    }

    /** */
    private function redirectBackWithNotification($errorMessage)
    {
		$this->setPaymentIntentCookie('', 1);
		$this->app->enqueueMessage($errorMessage, 'error');
		$this->app->redirect(Route::_('index.php?option=com_virtuemart&view=cart'), 302);
    }

	/**
	 * 
	 */
	private function fetchApiTransaction($transaction_id)
	{
		try {
			$apiResponse = $this->apiClient->payments()->fetch($transaction_id);
		} catch(ApiException $e) {
			$this->redirectBackWithNotification($e->getMessage());
		}

		if (!$this->parseApiTransactionResponse($apiResponse)) {
			$this->redirectBackWithNotification('Failed to get transaction with provided payment id.');
		}

		return $apiResponse;
	}

	
	/**
     * Parses api transaction response for errors
     */
    private function parseApiTransactionResponse($transaction): bool
    {
        if (! $this->isTransactionSuccessful($transaction)) {
            return false;
        }

        return true;
    }

    /**
     * Checks if the transaction was successful and
     * the data was not tempered with.
     */
    private function isTransactionSuccessful($transaction): bool
    {
        $matchCurrency = $this->currencyCode == ($transaction['amount']['currency'] ?? '');
        $matchAmount = $this->totalAmount == ($transaction['amount']['decimal'] ?? '');

        return (true == $transaction['authorisationCreated'] && $matchCurrency && $matchAmount);
    }

    /**
     * Gets errors from a failed api request
     * @param array $result The result returned by the api wrapper.
     */
    private function getResponseError($result): string
    {
        $error = [];
        // if this is just one error
        if (isset($result['text'])) {
            return $result['text'];
        }

        if (isset($result['code']) && isset($result['error'])) {
            return $result['code'] . '-' . $result['error'];
        }

        // otherwise this is a multi field error
        if ($result) {
			if (isset($result['declinedReason'])) {
				return $result['declinedReason']['error'];
			}

            foreach ($result as $fieldError) {
				if (isset($fieldError['field']) && isset($fieldError['message'])) {
					$error[] = $fieldError['field'] . ':' . $fieldError['message'];
				} else {
					$error = $fieldError;
				}
            }
        }

        return implode(' ', $error);
    }


	/** */
	private function storeDbLunarTransaction($paymentIntentId, $billingDetails)
	{
		$this->storePSPluginInternalData([
			// 'payment_method'              => $this->paymentMethod,
			'transaction_id'              => $paymentIntentId,
			'payment_order_total'         => vmPSPlugin::getAmountValueInCurrency($this->totalAmount, $this->currencyId),
			'payment_currency'            => $this->currencyCode,
			'email_currency'              => $this->emailCurrency,
			'order_number'                => $billingDetails->order_number,
			'virtuemart_paymentmethod_id' => $billingDetails->virtuemart_paymentmethod_id,
			'payment_name'                => $this->renderPluginName($this->method),
			'cost_per_transaction'        => $this->method->cost_per_transaction,
			'cost_min_transaction'        => $this->method->cost_min_transaction,
			'cost_percent_total'          => $this->method->cost_percent_total,
			'tax_id'                      => $this->method->tax_id,
		]);
	} 


	/** */
	private function finalizeOrder($billingDetails)
	{
		/** @var CurrencyDisplay $currencyInstance */
		$currencyInstance = CurrencyDisplay::getInstance($this->currencyId, $billingDetails->virtuemart_vendor_id);
		$priceDisplayWithCurrency = $this->totalAmount . ' ' . $currencyInstance->getSymbol();

		$orderlink='';
		$tracking = VmConfig::get('ordertracking','guests');

		if ($tracking !='none' and !($tracking =='registered' and empty($billingDetails->virtuemart_user_id))) {

			$orderlink = 'index.php?option=com_virtuemart&view=orders&layout=details&order_number=' . $billingDetails->order_number;
			if ($tracking == 'guestlink' or($tracking == 'guests' and empty($billingDetails->virtuemart_user_id))) {
				$orderlink .= '&order_pass=' . $billingDetails->order_pass;
			}
		}

		$html = $this->renderByLayout('order_done', array(
			'method'=> $this->method,
			'cart'=> $this->cart,
			'billingDetails' => $billingDetails,
			'payment_name' => $this->renderPluginName($this->method),
			'displayTotalInPaymentCurrency' => $priceDisplayWithCurrency,
			'orderlink' => $orderlink,
		));

		$this->cart->emptyCart();

		$order['order_status'] = $this->getNewStatus($this->method);
		$order['customer_notified'] = 1;
		$order['comments'] = '';

		/**
		 * There is no VM config setting for os_trigger_paid
		 * In the future, we must set the status for capture on vmConfig
		 * Add the additional info here
		 */
		if ($this->method->capture_mode === 'instant') {
			$date = Factory::getDate();
			$today = $date->toSQL();
			$order['paid_on'] = $today;
			$order['paid'] = $this->totalAmount;
		}

		$this->vmOrderModel->updateStatusForOneOrder($billingDetails->virtuemart_order_id, $order, true);

		vRequest::setVar('display_title', false);
		vRequest::setVar('html', $html);

		$this->setPaymentIntentCookie('', 1);
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
	function plgVmOnShowOrderBEPayment($virtuemart_order_id, $virtuemart_paymentmethod_id) {

		if (!$this->selectedThisByMethodId($virtuemart_paymentmethod_id)) {
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

		if (!$this->checkMethodIsSelected($virtuemart_paymentmethod_id)) {
			return;
		}

		$this->getPaymentCurrency($this->method);

		$paymentCurrencyId = $this->getCurrencyId();
	}

	/**
	 * 
	 */
	private function checkMethodIsSelected($methodId)
	{
		$this->method = $this->getVmPluginMethod($methodId);

		if (!$this->method) {
			return false;
		}
		if (!$this->selectedThisElement($this->method->payment_element)) {
			return false;
		}

		return true;
	}

	/**
	 * @param $orderDetails
	 * @param $data
	 * @return null
	 */
	function plgVmOnUserInvoice($orderDetails, &$data) {

		if (!$this->checkMethodIsSelected($orderDetails['virtuemart_paymentmethod_id'])) {
			return null;
		}

		if (
			!isset($this->method->send_invoice_on_order_null) 
			|| $this->method->send_invoice_on_order_null==1 
			|| $orderDetails['order_total'] > 0.00
		) {
			return null;
		}

		if ($orderDetails['order_salesPrice']==0.00) {
			$data['invoice_number'] = 'reservedByPayment_' . $orderDetails['order_number']; // Never send the invoice via email
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
			if (!isset(self::$IDS[$plugin->virtuemart_paymentmethod_id])) {
				$return .= $this->renderByLayout('pay_before', array(
					'method'=> $plugin
				));
			//$return .="<pre>".print_r($plugin,true)."</pre>";
			}
			self::$IDS[$plugin->virtuemart_paymentmethod_id] = true;
		}
		return $return;
	}

	/**
	 * @return string
	 */
	private function getCurrencyCode()
	{
		$currency = $this->method->payment_currency;
		// backward compatibility
		if (is_numeric($this->method->payment_currency)) {
			$currency = shopFunctions::getCurrencyByID($this->method->payment_currency, 'currency_code_3');
		}

		return $currency;
	}

	/**
	 * @return int
	 */
	private function getCurrencyId()
	{
		// backward compatibility
		if (is_numeric($this->method->payment_currency)) {
			return $this->method->payment_currency;
		} else {
			return shopFunctions::getCurrencyIDByName($this->method->payment_currency);
		}
	}

	/**
	 *
	 */
	function updateTransactionId($transactionId, $orderid) {
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

		if (!$this->checkMethodIsSelected($order->virtuemart_paymentmethod_id)) {
			return false;
		}

		// //@TODO half refund $order->order_status != $method->status_half_refund

		// if ($order->order_status != $this->method->status_capture
		// 	&& $order->order_status != $this->method->status_success
		// 	&& $order->order_status != $this->method->status_refunded
		// 	) {
		// 	// vminfo('Order_status not found '.$order->order_status.' in '.$method->status_capture.', '.$method->status_success.', '.$method->status_refunded);
		// 	return null;
		// }

		// // order exist for lunar ?
		// if (!($paymentTable = $this->getDataByOrderId($order->virtuemart_order_id))) {
		// 	return null;
		// }

		// $this->setApiClient();
		// $transactionid = $paymentTable->transaction_id;
		// $response = \Lunar\Transaction::fetch( $transactionid);
		// vmdebug('Lunar Transaction::fetch',$response);

		// if ($order->order_status == $this->method->status_refunded) {
		// 	/* refund payment if already captured */
		// 	if ( !empty($response['transaction']['capturedAmount'])) {
		// 		$amount = $response['transaction']['capturedAmount'];
		// 		$data = array(
		// 			'amount'     => $amount,
		// 			'descriptor' => ""
		// 		);
		// 		$response = \Lunar\Transaction::refund($transactionid, $data);
		// 		vmdebug('Lunar Transaction::refund',$response);
		// 	} else {
		// 		/* void payment if not already captured */
		// 		$data = array(
		// 			'amount' => $response['transaction']['amount']
		// 		);
		// 		$response = \Lunar\Transaction::void( $transactionid, $data);
		// 		vmdebug('Lunar Transaction::void',$response);
		// 	}
		// } elseif ($order->order_status == $this->method->status_capture) {
		// 	if ( empty($response['transaction']['capturedAmount'])) {
		// 		$amount = $response['transaction']['amount'];
		// 		$data = array(
		// 			'amount'     => $amount,
		// 			'descriptor' => ""
		// 		);

		// 		$response = \Lunar\Transaction::capture( $transactionid, $data);

		// 		vmdebug('Lunar Transaction::capture',$response);
		// 	}
		// }
	}
	
    /**
     * 
     */
    private function getFormattedProducts()
    {
		$products_array = [];
        foreach ($this->cart->products as $product) {
			$products_array[] = [
				'ID' => $product->virtuemart_product_id,
				'name' => $product->product_name,
				'quantity' => $product->quantity,
            ];
		}
        return str_replace("\u0022","\\\\\"", json_encode($products_array, JSON_HEX_QUOT));
    }
    /**
     * 
     */
    private function maybeSetPaymentInfo()
	{
		if (!empty($this->method->payment_info)) {
			$lang = Factory::getLanguage();
			if ($lang->hasKey($this->method->payment_info)) {
				$this->method->payment_info = vmText::_($this->method->payment_info);
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
