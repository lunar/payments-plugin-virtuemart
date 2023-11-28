<?php
defined ('_JEXEC') or die();

/**
 * @package VirtueMart
 * @subpackage payment
 * @license http://www.gnu.org/copyleft/gpl.html GNU/GPL
 */
$method = $viewData["method"];
$cart = $viewData["cart"];
$billingDetail = $viewData["billingDetails"];
$lunarCurrency = new LunarCurrency();
$this->getPaymentCurrency( $method );

$orderTotal = $billingDetail->order_total;
$price = vmPSPlugin::getAmountValueInCurrency($orderTotal, $method->payment_currency);

$currency = $method->payment_currency;
// backward compatibility
if (is_numeric($method->payment_currency)) {
	$currency = shopFunctions::getCurrencyByID($method->payment_currency, 'currency_code_3');
}

$precision = $lunarCurrency->getLunarCurrency($currency)['exponent'] ?? 2;
$priceInCents = (int) ceil( round($price * $lunarCurrency->getLunarCurrencyMultiplier($currency), $precision));

$lang = Factory::getLanguage();
$languages = JLanguageHelper::getLanguages( 'lang_code' );
$languageCode = $languages[ $lang->getTag() ]->sef;

$data = new stdClass;

$session = Factory::getSession();
$lunarID = uniqid('lunar_');
$session->set( 'lunar.uniqid', $lunarID);
$data->lunarID = $lunarID; // this is session ID to secure the transaction, it's fetch after to validate

$data->publicKey = $this->setKey($method);
$data->testMode = $method->test_mode;

$data->popup_title = JText::_($method->popup_title);
$data->description = JText::_($method->description);
$data->orderId = $billingDetail->virtuemart_order_id;
$data->virtuemart_paymentmethod_id = $billingDetail->virtuemart_paymentmethod_id;
$data->orderNo = $billingDetail->order_number;
$data->products = array();
foreach ( $cart->products as $product ) {
	$data->products[] = array(
		"Id" => $product->virtuemart_product_id,
		"Name" => $product->product_name,
		"Qty" => $product->quantity,
	);
}

$data->amount = $priceInCents;
$data->currency = $currency;
$data->exponent = $lunarCurrency->getLunarCurrency($currency)['exponent'];

$data->locale = $languageCode;
$data->customer = new stdClass();
$data->customer->name = $billingDetail->first_name . " " . $billingDetail->last_name ;
$data->customer->email = $billingDetail->email ;
$data->customer->phoneNo = $billingDetail->phone_1 ;
$data->customer->IP = $_SERVER["REMOTE_ADDR"];
$data->platform = array(
	'name' => 'Joomla',
	'version' => $this->getJoomlaVersions(),
	);
$data->ecommerce = array(
	'name' => 'VirtueMart',
	'version' => $this->getVirtuemartVersions(),
	);
$data->version = array(
	'name' => 'Lunar',
	'version' => $this->version,
);

$data->ajaxUrl = juri::root(true).'/index.php?option=com_virtuemart&view=plugin&vmtype=vmpayment&name=lunar';
?>
<style>
	.lunar-info-hide{display:none;}
</style>
<script src="https://sdk.paylike.io/a.js"></script>


<div id="lunar-temp-info">
	<button type="button" class="btn btn-success btn-large btn-lg" id="lunar-pay"><?php echo jText::_('LUNAR_BTN'); ?></button>
	<br>
</div>
<div id="lunar-after-info" class="lunar-info-hide">
<div class="post_payment_payment_name" style="width: 100%">
	<?php echo  $viewData["payment_name"]; ?>
</div>

<div class="post_payment_order_number" style="width: 100%">
	<span class="post_payment_order_number_title"><?php echo vmText::_ ('COM_VIRTUEMART_ORDER_NUMBER'); ?> </span>
	<?php echo  $billingDetail->order_number; ?>
</div>

<div class="post_payment_order_total" style="width: 100%">
	<span class="post_payment_order_total_title"><?php echo vmText::_ ('COM_VIRTUEMART_ORDER_PRINT_TOTAL'); ?> </span>
	<?php echo  $viewData['displayTotalInPaymentCurrency']; ?>
</div>
<?php
if($viewData["orderlink"]){
?>
<a class="vm-button-correct" href="<?php echo JRoute::_($viewData["orderlink"], false)?>"><?php echo vmText::_('COM_VIRTUEMART_ORDER_VIEW_ORDER'); ?></a>
<?php
}
?>
</div>
<script>
jQuery(document).ready(function() {
	var datas = <?php echo json_encode($data) ?>;

	var publicKey = {
		key: datas.publicKey
	};

	lunar = Paylike({key: datas.publicKey});

	jQuery('#lunar-pay').on('click',function(){
		pay();
	});
	function pay(){
		lunar.pay({
			test: ('1' == datas.testMode) ? (true) : (false),
			title: datas.popup_title,
			description: datas.description,
			amount: {
				currency: datas.currency,
				exponent: datas.exponent,
				value:	datas.amount
			},
			locale: datas.locale,
			custom: {
				lunarID: datas.lunarID,
				orderId: datas.orderId,
				orderNo: datas.orderNo,
				products: datas.products,
				customer: datas.customer,
				platform: datas.platform,
				ecommerce: datas.ecommerce,
				lunarPluginVersion: datas.version
				}
			}, function(err, r) {
				if (r != undefined) {
					var payData = {
							'paymentType' : 'captureTransactionFull',
							'transactionId' : r.transaction.id,
							'virtuemart_paymentmethod_id' : datas.virtuemart_paymentmethod_id,
							'format' : 'json'
						};
					jQuery.ajax({
						type: "POST",
						url: datas.ajaxUrl,
						async: false,
						data: payData,
						success: function(data) {
							if(data.success =='1') {
								jQuery('#lunar-after-info').toggleClass('lunar-info-hide');
								jQuery('#lunar-temp-info').remove();
							} else {
								alert(data.error);
								//callback(r,datas);
							}
						},
						dataType :'json'
					});
				}
			}
		);
	}
	pay();
});
</script>
