<?php
if( basename(__FILE__) == basename($_SERVER['SCRIPT_FILENAME']) )
	die();

/**
 * functions for the WordPress plugin Revisionary
 * defaults_rvy.php
 * 
 *
 * @author 		Kevin Behrens
 * @copyright 	Copyright 2009
 * 
 */

 
// indicates, for MU installations, which of the RS options (and OType options) should be controlled site-wide
function rvy_default_options_sitewide() {
	$def = array(
		'pending_revisions' => true,
		'scheduled_revisions' => true,
		'diff_display_strip_tags' => true,
		'async_scheduled_publish' => true,
		'pending_rev_notify_admin' => true,
		'pending_rev_notify_author' => true,
		'rev_approval_notify_author' => true,
		'rev_approval_notify_revisor' => true,
		'publish_scheduled_notify_admin' => true,
		'publish_scheduled_notify_author' => true,
		'publish_scheduled_notify_revisor' => true,
		'async_email' => true,
		'display_hints' => true
	);
	return $def;	
}
 

function rvy_default_options() {
	$def = array(
		'pending_revisions' => 1,
		'scheduled_revisions' => 1,
		'diff_display_strip_tags' => 1,
		'async_scheduled_publish' => 1,
		'pending_rev_notify_admin' => 1,
		'pending_rev_notify_author' => 1,
		'rev_approval_notify_author' => 1,
		'rev_approval_notify_revisor' => 1,
		'publish_scheduled_notify_admin' => 1,
		'publish_scheduled_notify_author' => 1,
		'publish_scheduled_notify_revisor' => 1,
		'async_email' => 1,
		'display_hints' => 1
	);

	return $def;
}

function rvy_po_trigger( $string ) {
	return $string;	
}
?>