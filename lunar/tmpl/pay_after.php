<?php
defined ('_JEXEC') or die();

/**
 * @package VirtueMart
 * @subpackage payment
 * @license http://www.gnu.org/copyleft/gpl.html GNU/GPL
 */
?>

<style>
	.lunar-info-hide{display:none;}
</style>

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

<?php if($viewData["orderlink"]) : ?>
	<a class="vm-button-correct" href="<?php echo JRoute::_($viewData["orderlink"], false)?>">
		<?php echo vmText::_('COM_VIRTUEMART_ORDER_VIEW_ORDER'); ?>
	</a>
<?php endif ?>

</div>

<script>
jQuery(document).ready(function() {

	jQuery('#lunar-pay').on('click',function(){
		jQuery.ajax({
			type: "POST",
			url: 'index.php?option=com_ajax&plugin=redirect',
			async: false,
			data: payData,
			dataType :'json',
			success: function(response) {
				if(response.success == '1') {
					jQuery('#lunar-after-info').toggleClass('lunar-info-hide');
					jQuery('#lunar-temp-info').remove();
				} else {
					alert(response.error);
				}
			},
			error: function(error) {
				alert(error);
			}
		});
	});

});
</script>
