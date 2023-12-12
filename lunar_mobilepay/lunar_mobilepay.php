<?php
 defined('_JEXEC') or die('Restricted access');

 
if ( ! class_exists( 'plgVmPaymentLunar')) {
	require( JPATH_VM_PLUGINS . DS . 'vmpayment' . DS . 'lunar' . DS . 'lunar.php');
}

/**
 * 
 */
class plgVmPaymentLunar_Mobilepay extends plgVmPaymentLunar
{
	
}
