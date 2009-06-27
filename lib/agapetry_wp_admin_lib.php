<?php
if( basename(__FILE__) == basename($_SERVER['SCRIPT_FILENAME']) )
	die();
	
function awp_query_descendant_ids( $table_name, $col_id, $col_parent, $parent_id ) {
	global $wpdb;
	
	$descendant_ids = array();
	
	// todo: abstract this
	$type_clause = ( $table_name == $wpdb->posts ) ? "AND post_type != 'revision'" : '';
	
	$query = "SELECT $col_id FROM $table_name WHERE $col_parent = '$parent_id' $type_clause";
	if ( $results = scoper_get_col($query) ) {
		foreach ( $results as $id ) {
			if ( ! in_array( $id, $descendant_ids ) ) {
				$descendant_ids []= $id;
				$next_generation = awp_query_descendant_ids($table_name, $col_id, $col_parent, $id, $descendant_ids);
				$descendant_ids = array_merge($descendant_ids, $next_generation);
			}
		}
	}
	return $descendant_ids;
}

// Decipher the ever-changing meta/advanced action names into a version-insensitive question:
// "Has metabox drawing been initiated?"
function awp_metaboxes_started($object_type = '') {
	if ( awp_ver('2.7-dev') ) {
		if ( 'page' == $object_type )
			return did_action('edit_page_form');
		else
			return did_action('edit_form_advanced');
	} else {
		if ( 'page' == $object_type ) {
			return did_action('edit_page_form') && did_action('theme_root');  // WP 2.5.1 dropped dbx_page_advanced hook (but first theme_root firing after edit_page_form happens to occur at same location)
		} else
			return did_action('dbx_post_advanced');
	}
}

// added adjustable timeout to WP function
function awp_remote_fopen( $uri, $timeout = 10 ) {
	if ( ! awp_ver( '2.7' ) )
		return wp_remote_fopen($uri);

	$parsed_url = @parse_url( $uri );

	if ( !$parsed_url || !is_array( $parsed_url ) )
		return false;

	$options = array();
	$options['timeout'] = $timeout;

	$response = wp_remote_get( $uri, $options );

	if ( is_wp_error( $response ) )
		return false;

	return $response['body'];
}
?>