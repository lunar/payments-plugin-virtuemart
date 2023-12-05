<?php
defined ('_JEXEC') or die();

?>

<style>
	.lunar-info-hide{display:none;}
</style>

<div id="lunar-temp-info">
	<a href="<?php echo $viewData['redirectUrl']; ?>" id="lunar-pay" type="button" class="btn btn-success btn-large btn-lg">
		<?php echo jText::_('LUNAR_BTN'); ?>
	</a>
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

<?php if($viewData["orderlink"]) : ?>
	<a class="vm-button-correct" href="<?php echo JRoute::_($viewData["orderlink"], false)?>">
		<?php echo vmText::_('COM_VIRTUEMART_ORDER_VIEW_ORDER'); ?>
	</a>
<?php endif ?>

</div>
