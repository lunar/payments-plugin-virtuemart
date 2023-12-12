<?php 
defined ('_JEXEC') or die();

$virtuemart_paymentmethod_id = $viewData['method']->virtuemart_paymentmethod_id ?? 0;
$payment_element = $viewData['method']->payment_element ?? 'lunar';
?>

<script type="text/javascript">

    jQuery(document).ready(function() {

        let methodId = '<?php echo $virtuemart_paymentmethod_id; ?>';
        let paymentElement = '<?php echo $payment_element; ?>';
        let methodIdChecked = jQuery("[name=virtuemart_paymentmethod_id]:checked").val() ?? 0;
        let doNothing = false;


        // @TODO rethink/remove bellow listeners

        jQuery("[name=virtuemart_paymentmethod_id]").on('click', function(e) {
            doNothing = true;
            methodIdChecked = jQuery(this).val();
        });
        
        jQuery('#tos').on('click', function(e) {
            doNothing = true;
        });
        
        jQuery('[name*="updatecart."]').on('click', function(e) {
            doNothing = true;
        });
        
        jQuery('[name*="delete."]').on('click', function(e) {
            doNothing = true;
        });


        jQuery('#checkoutForm').on('submit', function(e) {

            if (!paymentElement) {
                return true;
            }

            if ((methodId !== methodIdChecked) || doNothing) {
                return true;
            }

            e.preventDefault();

            jQuery.ajax({
                type: 'POST',
                url: Virtuemart.vmSiteurl + 
                    'index.php?option=com_virtuemart&view=plugin&type=vmpayment' +
                    '&name=' + paymentElement +
                    '&action=redirect&format=json&pm=' + methodIdChecked,
                async: false,
                dataType: 'json',
                success: function(response) {
                    if (response.hasOwnProperty('redirectUrl')) {
                        window.location.replace(response.redirectUrl);
                    }
                    if (response.hasOwnProperty('error')) {
                        alert(response.error)
                        window.location.reload()
                    }
                },
                error: function(error) {
                    alert(error);
                }
            });
        });

    });
</script>
