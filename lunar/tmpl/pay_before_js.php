<?php 
	defined ('_JEXEC') or die();

	// set default paymentmethod_id and add script that dont need instance
	$vponepagecheckout = JPluginHelper::isEnabled('system', 'vponepagecheckout');

if ($vponepagecheckout) {
	echo '<input style="display:none;" class="required" required="required" type="text" value="" id="vponepagecheckout">';
}
?>

<script>

<?php if ($vponepagecheckout) { ?>

	jQuery(document).ready(function() {
	  var bindCheckoutForm = function() {
        var form = jQuery('#checkoutForm');

            form.on('submit', function(e) {

                if (!form.data('vmLunar-verified')) {
                    
					var payData = {
						'lunarTask' : 'saveInSession',
						'transactionId' : r.transaction.id,
						'virtuemart_paymentmethod_id' : data.virtuemart_paymentmethod_id
					};
					jQuery.ajax({
						type: "POST",
						url: 'index.php?option=com_ajax&plugin=redirect',
						async: false,
						data: {},
						dataType :'json',
						success: function(data) {
							if(data.success =='1') {
								validate = true;
								form.data('vmLunar-verified', true);
								form.submit();
							} else {
								ProOPC.setmsg(data.error);

								// cancel
								var form = jQuery('#checkoutForm');
								validate = form.data('vmLunar-verified', false);
								ProOPC.removePageLoader();
								ProOPC.enableSubmit();
								document.location.reload(true);
							}
						}
					});
                }
            });
      };
	 bindCheckoutForm();
	
	jQuery(document).on('vpopc.event', function(event, type) {
		var form = jQuery('#checkoutForm');
			if(type == 'checkout.updated.shipmentpaymentcartlist'
				|| type == 'checkout.updated.cartlist'
				|| type == 'prepare.data.payment') form.data('vmLunar-verified', false);
		if(type == 'checkout.finalstage') {
			validate = form.data('vmLunar-verified', false);
		}
	});

     // Bind on ajaxStop
     jQuery(document).ajaxStop(function() {
        bindCheckoutForm();
     });

	});
<?php } else { ?>

jQuery(document).ready(function() {
	var $container = jQuery(Virtuemart.containerSelector),
		paymentDone = false;
		var form = jQuery('#checkoutForm');
	// on submit
	$container.find('#checkoutForm').on('submit',function(e) {

		// if(paymentDone === true) return;

		// var $selects = jQuery("[name=virtuemart_paymentmethod_id]"),
		// 	methodId  = $selects.length ? jQuery("[name=virtuemart_paymentmethod_id]:checked").val() : 0,
		// 	id = 0,
		// 	data = {'lunarTask' : 'cartData'},
		// 	confirm = jQuery(this).find('input[name="confirm"]').length,
		// 	$btn = jQuery('#checkoutForm').find('button[name="confirm"]'),
		// 	checkout = $btn.attr('task');
		
		// var payData = {
		// 	'lunarTask' : 'saveInSession',
		// 	'transactionId' : r.transaction.id,
		// 	'virtuemart_paymentmethod_id' : data.virtuemart_paymentmethod_id
		// };

		jQuery.ajax({
			type: "POST",
			url: 'index.php?option=com_ajax&plugin=lunar&method=redirect',
			async: false,
			data: {},
			dataType :'json',
			success: function(data) {
				console.log('done')
				console.log(data)
				if(data.success =='1') {
					validate = true;
					// form.data('vmLunar-verified', true);
					form.submit();
				} else {
					console.log('error')
					// ProOPC.setmsg(data.error);

					// cancel
					// var form = jQuery('#checkoutForm');
					// validate = form.data('vmLunar-verified', false);
					// ProOPC.removePageLoader();
					// ProOPC.enableSubmit();
					// document.location.reload(true);
				}
			}
		});

		return false;
	});
	// TODO jQuery(this).attr('disabled', 'false');
	// CheckoutBtn = Virtuemart.bCheckoutButton ;
	// if(Virtuemart.container
	// Virtuemart.bCheckoutButton = function(e) {
		// e.preventDefault();
	// }
});

<?php } ?>

</script>
