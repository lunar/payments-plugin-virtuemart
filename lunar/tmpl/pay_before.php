<?php defined ('_JEXEC') or die(); ?>

<script type="text/javascript">
    jQuery(document).ready(function() {

        let methodId = '<?php echo $viewData['method']->virtuemart_paymentmethod_id; ?>';
        let methodIdChecked = jQuery("[name=virtuemart_paymentmethod_id]:checked").val();
        let doNothing = false;

        jQuery("[name=virtuemart_paymentmethod_id]").on('click', function(e) {
            doNothing = true;
            methodIdChecked = jQuery(this).val();
        });
        
        jQuery('#tos').on('click', function(e) {
            doNothing = true;
        });

        jQuery('#checkoutForm').on('submit', function(e) {

            if ((methodId !== methodIdChecked) || doNothing) {
                return;
            }

            e.preventDefault();

            jQuery.ajax({
                type: 'POST',
                url: Virtuemart.vmSiteurl + 
                    'index.php?option=com_virtuemart&view=plugin&type=vmpayment' +
                    '&name=lunar&action=redirect&format=json&pm=' + methodIdChecked,
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
