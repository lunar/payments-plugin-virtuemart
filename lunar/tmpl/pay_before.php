<?php 
defined ('_JEXEC') or die();

$virtuemart_paymentmethod_id = $viewData['method']->virtuemart_paymentmethod_id ?? 0;
$payment_element = $viewData['method']->payment_element ?? 'lunar';

?>

<script type="text/javascript">

    jQuery(document).ready(function() {

        let methodId = '<?php echo $virtuemart_paymentmethod_id; ?>';
        let methodIdChecked = jQuery("[name=virtuemart_paymentmethod_id]:checked").val() ?? 0;
        let $container = Virtuemart.containerSelector ? jQuery(Virtuemart.containerSelector) : jQuery('#cart-view');
        let paymentInitialized = false;

        $container.find('#checkoutForm').on('submit',function(e) {
        
            let $selects = jQuery("[name=virtuemart_paymentmethod_id]"),
                confirm = jQuery(this).find('input[name="confirm"]').length,
                $btn = jQuery('#checkoutForm').find('button[name="confirm"]'),
                btnTask = $btn.attr('task');
            
            if (confirm === 0 || btnTask === 'checkout' || paymentInitialized) {
                return true;
            }
            
            e.preventDefault();
         
            jQuery.ajax({
                type: 'POST',
                url: Virtuemart.vmSiteurl + 
                    'index.php?option=com_virtuemart&view=plugin&type=vmpayment' +
                    '&name=' + '<?php echo $payment_element; ?>' +
                    '&action=redirect&format=json&pm=' + methodIdChecked,
                async: false,
                dataType: 'json',
                success: function(response) {
                    if (response.hasOwnProperty('redirectUrl')) {
                        paymentInitialized = true;
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

            // e.preventDefault();
            return false;
        });
    });
</script>
