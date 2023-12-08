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
	private ?bool $check;
	private $billingDetails;
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

	private function setApiClient()
	{
		$this->apiClient = new ApiClient($this->method->api_key, null, $this->testMode);
	}

	private function init()
	{
		$this->cart = VirtueMartCart::getCart(false);
		$this->cart->prepareCartData();

		$this->setApiClient();

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
	 * Used for ajax actions
	 */
	public function plgVmOnSelfCallFE($type, $name, &$render)
    {
file_put_contents(dirname(__DIR__, 3) . "/zzz.log", json_encode(__METHOD__.'-->'.__LINE__, JSON_PRETTY_PRINT) . PHP_EOL, FILE_APPEND);	
file_put_contents(dirname(__DIR__, 3) . "/zzz.log", json_encode($type, JSON_PRETTY_PRINT) . PHP_EOL, FILE_APPEND);	
file_put_contents(dirname(__DIR__, 3) . "/zzz.log", json_encode($name, JSON_PRETTY_PRINT) . PHP_EOL, FILE_APPEND);	
		if('redirect' == vRequest::getCmd('action')) {
			if (!$this->checkMethodIsSelected(vRequest::getVar('pm'))) {
				echo $this->getJsonErrorResponse('Wrong payment method ID');
				jexit();
			}

			try {
				$this->initializePaymentBeforeOrder();

			} catch(ApiException $e) {
				$errorMessage = $e->getMessage();

			} catch(\Exception $e) {
				$errorMessage = 'Server error. Please try again.';
			}

			echo $this->getJsonErrorResponse($errorMessage);
			jexit();
		}
	}
	
	/**
	 * Used for ajax actions
	 */
	private function initializePaymentBeforeOrder()
    {
		$this->init();

		$this->setArgs();

		$paymentIntentId = $this->getPaymentIntentCookie();

		if ($paymentIntentId) {
			// check if retrieved payment intent is for the current cart/order
			$this->fetchApiTransaction($paymentIntentId);
		} else {
			$paymentIntentId = $this->apiClient->payments()->create($this->args);
			$this->setPaymentIntentCookie($paymentIntentId);
		}

		if (!$paymentIntentId) {
			echo $this->getJsonErrorResponse('An error occurred creating payment intent. Please try again or contact system administrator.');
			jexit();
		}

		echo json_encode([
			'redirectUrl' => ($this->testMode ? self::TEST_REMOTE_URL : self::REMOTE_URL) . $paymentIntentId
		]);
		jexit();
	}

	/**
	 * This function is triggered when the user click on the Confirm Purchase button on cart view.
     * You can store the transaction/order related details using this function.
     * You can set your html with a variable name html at the end of this function, to be shown on thank you message.
	 */
	public function plgVmConfirmedOrder($cart, $order)
	{
		$this->billingDetails = $order['details']['BT'];

		if (!$this->checkMethodIsSelected($cart->virtuemart_paymentmethod_id)) {
			return $this->check;
		}

		$this->init();

		$this->setArgs($this->billingDetails->virtuemart_order_id);
		
		$paymentIntentId = $this->getPaymentIntentCookie();

		try {
			if ($paymentIntentId) {
				// check if retrieved payment intent is for the current cart/order
				$this->fetchApiTransaction($paymentIntentId);
			} else {
				$paymentIntentId = $this->apiClient->payments()->create($this->args);
				$this->setPaymentIntentCookie($paymentIntentId);
			}
		} catch(ApiException $e) {
			$this->redirectBackWithNotification($e->getMessage());
		}

		if (! $paymentIntentId) {
			$this->redirectBackWithNotification('An error occurred creating payment intent. Please try again or contact system administrator.');
		}

		$this->storeDbLunarTransaction($paymentIntentId);

		$this->app->redirect(($this->testMode ? self::TEST_REMOTE_URL : self::REMOTE_URL) . $paymentIntentId, 302);
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
			// return $this->check;
			$this->redirectBackWithNotification('Bad payment method');
		}

		if (!$paymentMethod) {
			$this->redirectBackWithNotification('No payment method name provided.');
		}

		$this->init();

		$paymentIntentId = $this->getPaymentIntentCookie();

		if (! $paymentIntentId) {
			$this->redirectBackWithNotification('No payment intent id found.');
		}

		try {
			$this->fetchApiTransaction($paymentIntentId);
		} catch(ApiException $e) {
			$this->redirectBackWithNotification($e->getMessage());
		}

		if (!$orderNumber && 'before' == $this->method->checkout_mode) {
			$orderId = $this->vmOrderModel->createOrderFromCart($this->cart);
			/** @var VirtueMartModelOrders $order */
			$order = $this->vmOrderModel->getOrder($orderId);
			
			if (!isset($order['details'])) {
				$this->redirectBackWithNotification('Invalid order');
			}

			$this->billingDetails = $order['details']['BT'];
			
			$this->storeDbLunarTransaction($paymentIntentId);
		}
		
		if ('instant' === $this->method->capture_mode) {
			try {
				$this->apiClient->payments()->capture($paymentIntentId, [
					'amount' => [
						'currency' => $this->currencyCode,
						'decimal' => $this->totalAmount,
					],
				]);
			} catch(ApiException $e) {
				$this->redirectBackWithNotification($e->getMessage());
			}
		}

		$order = $this->vmOrderModel->getOrder($this->cart->virtuemart_order_id);
		$this->billingDetails = $order['details']['BT'];

		$this->finalizeOrder($html);

		return true;
	}
	
	/** */
	private function finalizeOrder(&$html)
	{
		$order['order_status'] = $this->getNewStatus();
		$order['customer_notified'] = 1;
		$order['comments'] = '';

		/**
		 * There is no VM config setting for os_trigger_paid
		 * In the future, we must set the status for capture on vmConfig
		 * Add the additional info here
		 */
		if ('instant' === $this->method->capture_mode) {
			$date = Factory::getDate();
			$today = $date->toSQL();
			$order['paid_on'] = $today;
			$order['paid'] = $this->totalAmount;
		}

		$this->vmOrderModel->updateStatusForOneOrder($this->billingDetails->virtuemart_order_id, $order, true);

		$html = $this->renderByLayout('order_done', [
			'method'=> $this->method,
			'cart'=> $this->cart,
			'billingDetails' => $this->billingDetails,
			'payment_name' => $this->renderPluginName($this->method),
			'displayTotalInPaymentCurrency' => $this->getPriceWithCurrency(),
			'orderlink' => $this->getOrderLink(),
		]);

		$this->cart->emptyCart();

		$this->setPaymentIntentCookie('', 1);
	}

	/**
	 * 
	 */
	private function getPriceWithCurrency()
	{
		/** @var CurrencyDisplay $currencyInstance */
		$currencyInstance = CurrencyDisplay::getInstance($this->currencyId, $this->billingDetails->virtuemart_vendor_id);
		return $this->totalAmount . ' ' . $currencyInstance->getSymbol();

	}

	/**
	 * 
	 */
	private function getOrderLink()
	{
		$orderLink='';
		$tracking = VmConfig::get('ordertracking','guests');

		if ($tracking !='none' and !($tracking =='registered' and empty($this->billingDetails->virtuemart_user_id))) {

			$orderLink = 'index.php?option=com_virtuemart&view=orders&layout=details&order_number=' . $this->billingDetails->order_number;
			if ($tracking == 'guestlink' or ($tracking == 'guests' and empty($this->billingDetails->virtuemart_user_id))) {
				$orderLink .= '&order_pass=' . $this->billingDetails->order_pass;
			}
		}

		return $orderLink;
	}

    /**
     * SET ARGS
     */
    private function setArgs($orderId = null)
    {
		$this->getPaymentCurrency($this->method);
		
		$billingDetails = $this->cart->BT;

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
                'orderId' => $orderId ?? $billingDetails['email'],
                'products' => $this->getFormattedProducts(),
                'customer' => [
                    'name' => $billingDetails['first_name'] . " " . $billingDetails['last_name'],
                    'email' => $billingDetails['email'],
                    'telephone' => $billingDetails['phone_1'],
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

    /** */
    private function getJsonErrorResponse($errorMessage)
    {
		$this->setPaymentIntentCookie('', 1);
		return json_encode(['error' => $errorMessage]);
    }

	/**
	 * 
	 */
	private function fetchApiTransaction($paymentIntentId)
	{
		$apiResponse = $this->apiClient->payments()->fetch($paymentIntentId);

		if (!$this->parseApiTransactionResponse($apiResponse)) {
			throw new ApiException('Amount or currency doesn\'t match ');
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
	private function storeDbLunarTransaction($paymentIntentId)
	{
		$this->storePSPluginInternalData([
			// 'payment_method'              => $this->paymentMethod,
			'transaction_id'              => $paymentIntentId,
			'payment_order_total'         => vmPSPlugin::getAmountValueInCurrency($this->totalAmount, $this->currencyId),
			'payment_currency'            => $this->currencyCode,
			'email_currency'              => $this->emailCurrency,
			'order_number'                => $this->billingDetails->order_number,
			'virtuemart_paymentmethod_id' => $this->billingDetails->virtuemart_paymentmethod_id,
			'payment_name'                => $this->renderPluginName($this->method),
			'cost_per_transaction'        => $this->method->cost_per_transaction,
			'cost_min_transaction'        => $this->method->cost_min_transaction,
			'cost_percent_total'          => $this->method->cost_percent_total,
			'tax_id'                      => $this->method->tax_id,
		]);
	}

	/**
	 * Keep backwards compatibility
	 * a new parameter has been added in the xml file
	 */
	function getNewStatus() {
		if ('instant' === $this->method->capture_mode) {
			return $this->method->status_capture ?? 'S';
		} else {
			return $this->method->status_success ?? 'C';
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
			return $this->check;
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
			return $this->check = null;
		}
		if (!$this->selectedThisElement($this->method->payment_element)) {
			return $this->check = false;
		}

		return true;
	}

	/**
	 * @param $orderDetails
	 * @param $data
	 * @return null
	 */
	public function plgVmOnUserInvoice($orderDetails, &$data)
	{
		if (!$this->checkMethodIsSelected($orderDetails['virtuemart_paymentmethod_id'])) {
			return $this->check;
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

	protected function renderPluginName($plugin)
	{
		$html ='
			<style>
				.lunar-wrapper .payment_logo img {
					height: 30px;
					padding: 2px;
				}
			</style>
			<div class="lunar-wrapper" style="display: inline-block">
				<div class="lunar_title" >' . $this->method->title . '</div>
				<div class="payment_logo" >';

		$allcards = ['mastercard'=>'mastercard','maestro'=>'maestro','visa'=>'visa','visaelectron'=>'visaelectron'];

		if (empty($this->method->accepted_cards)) {
			$cards = $allcards;
		} else {
			$cards = $this->method->accepted_cards;
		}

		foreach($cards as $image) {
			if (isset($allcards[$image])) {
				// $logoPath = JURI::root().'plugins/vmpayment/lunar/images/'.$image.'.sgv';
				$logoPath = JURI::root().'plugins/vmpayment/lunar/images/'.$image.'.png'; // use png files because TCPDF doesn't like svg 
				$html .= sprintf('<img src="%s" alt="logo" />', $logoPath);
			}
		}

		$html .= '</div></div>';
		$html .= '<div class="lunar_desc" >' . $this->method->description . '</div>';

		// $layout = vRequest::getCmd('layout', 'default');
		$view = vRequest::getCmd('view', '');

		if ($this->method->checkout_mode === 'before' && $view === 'cart') {
			if (!isset(self::$IDS[$this->method->virtuemart_paymentmethod_id])) {
				$html .= $this->renderByLayout('pay_before', array(
					'method'=> $this->method
				));
			//$html .="<pre>".print_r($this->method,true)."</pre>";
			}
			self::$IDS[$this->method->virtuemart_paymentmethod_id] = true;
		}
		return $html;
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
	 * Update lunar on update status
	 */
	public function plgVmOnUpdateOrderPayment( $order, $old_order_status) {

		if (!$this->checkMethodIsSelected($order->virtuemart_paymentmethod_id)) {
			return $this->check;
		}

		// //@TODO half refund $order->order_status != $method->status_half_refund

		if (
			$order->order_status != $this->method->status_capture
			&& $order->order_status != $this->method->status_success
			&& $order->order_status != $this->method->status_refunded
			&& $order->order_status != $this->method->status_canceled
		) {
			// vminfo('Order_status not found '.$order->order_status.' in '.$method->status_capture.', '.$method->status_success.', '.$method->status_refunded);
			return null;
		}

		$orderId = $order->virtuemart_order_id;

		if (!($lunarTransaction = $this->getDataByOrderId($orderId))) {
			return null;
		}

		$this->setApiClient();
		$this->getPaymentCurrency($this->method);

		$paymentIntentId = $lunarTransaction->transaction_id;
		$this->currencyCode = $this->getCurrencyCode();
		$this->totalAmount = $order->order_total;
		$action = '';

		try {
			$this->fetchApiTransaction($paymentIntentId);

			$data = [
				'amount' => [
					'currency' => $this->currencyCode,
					'decimal' => $this->totalAmount,
				]
			];

			switch ($order->order_status) {
				case $this->method->status_capture:
					$action = 'capture';
					break;
				case $this->method->status_refunded:
					$action = 'refund';
					break;
				case $this->method->status_canceled:
					$action = 'cancel';
					break;
				}

			$this->processTransaction($paymentIntentId, $data, $action);

		} catch(\Exception $e) {
			$orderLink = JURI::root() . ('/administrator/index.php?option=com_virtuemart&view=orders&task=edit&virtuemart_order_id=' . $orderId);
			$this->app->enqueueMessage($e->getMessage(), 'error');
			$this->app->redirect($orderLink, 302);
		}

		$this->app->enqueueMessage("Lunar API action - $action : completed successfully");
	}

	/**
	 * 
	 */
	private function processTransaction($paymentIntentId, $data, $action)
	{
		$apiResponse = $this->apiClient->payments()->{$action}($paymentIntentId, $data);

		if ('completed' != ($apiResponse["{$action}State"] ?? '')) {
			throw new \Exception($this->getResponseError($apiResponse));
		}

		vmdebug("Lunar Transaction::$action", $apiResponse);

		return $apiResponse;
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
