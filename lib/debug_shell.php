<?php // avoid bombing out if the actual debug file is not loaded
if( basename(__FILE__) == basename($_SERVER['SCRIPT_FILENAME']) )
	die();

if ( ! function_exists('d_echo') ) {
function d_echo($str) {
	return;
}
}

if ( ! function_exists('rs_errlog') ) {
	function rs_errlog($message, $line_break = true) {
		return;
	}
}

if ( ! function_exists('agp_bt_die') ) {
function agp_bt_die() {
	return;
}
}

if ( ! function_exists('dump') ) {
function dump(&$var, $info = FALSE, $display_objects = true)
{ 
	return; 
}
}
?>