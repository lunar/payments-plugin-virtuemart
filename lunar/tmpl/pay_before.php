<?php defined ('_JEXEC') or die(); ?>

<script type="text/javascript">
    jQuery(document).ready(function() {

        let $container = Virtuemart.containerSelector ? jQuery(Virtuemart.containerSelector) : jQuery('#cart-view');

        jQuery('#checkoutForm').on('submit', function(e) {
            
            // the form is submitted when tos accepted (& the page is reloaded)
            // submit button has task attribute if tos not accepted
            if('checkout' === this.find('#checkoutFormSubmit').attr('task')) {
                console.log('tos accepted');
                return;
            }

            e.preventDefault();

            jQuery.ajax({
                type: 'POST',
                url: Virtuemart.vmSiteurl + 
                    'index.php?option=com_virtuemart&view=plugin&type=vmpayment&name=lunar&action=redirect&format=json' +
                    '&pm=' + jQuery("[name=virtuemart_paymentmethod_id]:checked").val(),
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