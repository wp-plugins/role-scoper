<?php
if( basename(__FILE__) == basename($_SERVER['SCRIPT_FILENAME']) )
	die();

require_once('agapetry_wp_core_lib.php');

// deprecated
function awp_user_has_caps($reqd_caps, $object_id = 0, $user_id = 0) {
	return awp_user_can($reqd_caps, $object_id, $user_id);
}

// equivalent to current_user_can, 
// except it supports array of reqd_caps, supports non-current user, and does not support numeric reqd_caps
//
// set object_id to 'blog' to suppress any_object_check and any_term_check
function awp_user_can($reqd_caps, $object_id = 0, $user_id = 0 ) {
	$user = wp_get_current_user();
	if ( empty($user) )
		return false;
		
	if ($user_id && ($user_id != $user->ID) ) {
		$user = new WP_User($user_id);  // don't need Scoped_User because only using allcaps property (which contain WP blogcaps)
		if ( empty($user) )
			return false;
	}

	$reqd_caps = (array) $reqd_caps;
	$check_caps = $reqd_caps;
	foreach ( $check_caps as $cap_name ) {
		if ( $meta_caps = map_meta_cap($cap_name, $user->ID) ) {
			$reqd_caps = array_diff( $reqd_caps, array($cap_name) );
			$reqd_caps = array_unique( array_merge( $reqd_caps, $meta_caps ) );
		}
	}
	
	if ( 'blog' == $object_id ) {
		global $scoper;
		if ( isset($scoper) ) {	// if this is being called with Scoper loaded, any_object_check won't be called anyway
			$scoper->cap_interceptor->skip_any_object_check = true;
			$scoper->cap_interceptor->skip_any_term_check = true;
			$scoper->cap_interceptor->skip_id_generation = true;
		}
	}
	
	$args = ( 'blog' == $object_id ) ? array( $reqd_caps, $user->ID, 0 ) : array( $reqd_caps, $user->ID, $object_id );
	
	$capabilities = apply_filters('user_has_cap', $user->allcaps, $reqd_caps, $args);
	
	if ( ('blog' == $object_id) && isset($scoper) ) {
		$scoper->cap_interceptor->skip_any_object_check = false;
		$scoper->cap_interceptor->skip_any_term_check = false;
		$scoper->cap_interceptor->skip_id_generation = false;
	}
		
	foreach ($reqd_caps as $cap_name) {
		if( empty($capabilities[$cap_name]) || ! $capabilities[$cap_name] ) {
			// if we're about to fail due to a missing create_child_pages cap, honor edit_pages cap as equivalent
			// TODO: abstract this with cap_defs property
			if ( 'create_child_pages' == $cap_name ) {
				$alternate_cap_name = 'edit_pages';
				$args = array( array($alternate_cap_name), $user->ID, $object_id );
				$capabilities = apply_filters('user_has_cap', $user->allcaps, array($alternate_cap_name), $args);
				
				if( empty($capabilities[$alternate_cap_name]) || ! $capabilities[$alternate_cap_name] )
					return false;
			} else
				return false;
		}
	}

	return true;
}

// (moved from taxonomy.php / hardway_rs.php)
// Renamed for clarity (was _get_term_hierarchy)
// Removed option buffering since hierarchy is user-specific (get_terms query will be wp-cached anyway)
// Also adds support for taxonomies that don't use wp_term_taxonomy schema
function rs_get_terms_children( $taxonomy, $option_value = '' ) {
	if ( ! is_taxonomy_hierarchical($taxonomy) )
		return array();
	
	$children = get_option("{$taxonomy}_children_rs");
	
	if ( is_array($children) )
		return $children;
				
	global $scoper;
	if ( ! $tx = $scoper->taxonomies->get($taxonomy) )
		return $option_value;
	
	if ( empty($tx->source->cols->parent) || empty($tx->source->cols->id) )
		return $option_value;
		
	$col_id = $tx->source->cols->id;	
	$col_parent = $tx->source->cols->parent;
	
	$children = array();
	
	$terms = $scoper->get_terms($taxonomy, UNFILTERED_RS);
	
	foreach ( $terms as $term )
		if ( $term->$col_parent )
			$children[$term->$col_parent][] = $term->$col_id;

	update_option("{$taxonomy}_children_rs", $children);
			
	return $children;
}

// note: rs_get_page_children() is no longer used internally by Role scoper
function rs_get_page_children() {
	$children = array();

	global $wpdb;
	if ( $pages = scoper_get_results("SELECT ID, post_parent FROM $wpdb->posts WHERE post_type != 'revision'") ) {
		foreach ( $pages as $page )
			if ( $page->post_parent )
				$children[$page->post_parent][] = $page->ID;
	}

	return $children;
}

function rs_get_page_ancestors() {
	$ancestors = get_option("scoper_page_ancestors");

	if ( is_array($ancestors) )
		return $ancestors;

	$ancestors = array();
	
	global $wpdb;
	if ( $pages = scoper_get_results("SELECT ID, post_parent FROM $wpdb->posts WHERE post_type != 'revision'") ) {
		$parents = array();
		foreach ( $pages as $page )
			if ( $page->post_parent )
				$parents[$page->ID] = $page->post_parent;

		foreach ( $pages as $page ) {
			$ancestors[$page->ID] = _rs_walk_ancestors($page->ID, array(), $parents);
			if ( empty( $ancestors[$page->ID] ) )
				unset( $ancestors[$page->ID] );
		}
		
		update_option("scoper_page_ancestors", $ancestors);
	}
	
	return $ancestors;
}

function _rs_walk_ancestors($child_id, $ancestors, $parents) {
	if ( isset($parents[$child_id]) ) {
		$ancestors []= $parents[$child_id];
		$ancestors = _rs_walk_ancestors($parents[$child_id], $ancestors, $parents);
	}
	return $ancestors;
}


function rs_get_term_ancestors($taxonomy) {
	$ancestors = get_option("{$taxonomy}_ancestors_rs");

	if ( is_array($ancestors) )
		return $ancestors;

	global $scoper;

	$tx = $scoper->taxonomies->get($taxonomy);	
	$col_id = $tx->source->cols->id;	
	$col_parent = $tx->source->cols->parent;
	
	$terms = $scoper->get_terms($taxonomy, UNFILTERED_RS);

	$ancestors = array();

	if ( $terms ) {
		$parents = array();
		
		foreach ( $terms as $term )
			if ( $term->$col_parent )
				$parents[$term->$col_id] = $term->$col_parent;

		foreach ( $terms as $term ) {
			$term_id = $term->$col_id;
			$ancestors[$term_id] = _rs_walk_ancestors($term_id, array(), $parents);
			if ( empty( $ancestors[$term_id] ) )
				unset( $ancestors[$term_id] );
		}
		
		update_option("{$taxonomy}_ancestors_rs", $ancestors);
	}
	
	return $ancestors;
}


function rs_get_post_revisions($post_id, $use_memcache = true) {
	global $wpdb;
	static $last_results;
	
	if ( ! isset($last_results) )
		$last_results = array();

	elseif ( isset($last_results[$post_id]) )
		return $last_results[$post_id];
		
	if ( $revisions = scoper_get_col("SELECT ID FROM $wpdb->posts WHERE post_type = 'revision' AND post_parent = '$post_id'") )
		$revisions = array_fill_keys($revisions, true);

	$last_results[$post_id] = $revisions;
	
	return $revisions;
}

function awp_blend_option_array( $option_prefix = '', $option_name, $defaults, $key_dimensions = 1, $user_opt_val = -1 ) {
	if ( ! is_array($defaults) )
		$defaults = array();
	
	if ( -1 == $user_opt_val )
		$user_opt_val = get_option( $option_prefix . $option_name );
	
	if ( ! is_array($user_opt_val) )
		$user_opt_val = array();
	
	if ( isset( $defaults[$option_name] ) )
		$user_opt_val = agp_merge_md_array($defaults[$option_name], $user_opt_val, $key_dimensions );
	
	return $user_opt_val;
}

function awp_force_set($setvar, $val, $args = '', $keys = '', $subkeys = '') {
	$defaults = array( 'is_global' => true );
	$args = array_merge( $defaults, (array) $args );
	extract($args);

	if ( ! $is_global )
		return;  // only support globals now
	
	if ( ! isset( $GLOBALS[$setvar] ) )
		$GLOBALS[$setvar] = array();

	if ( empty($keys) )
		return; 	// no array keys specified

	if ( ! is_array($keys) )
		$keys = (array) $keys;
	
	if ( $subkeys ) {
		if ( ! is_array($subkeys) )
			$subkeys = (array) $subkeys;
	} else
		$subkeys = array();
		
	foreach ( $keys as $key ) {
		if ( ! isset($GLOBALS[$setvar]) ) {
			$GLOBALS[$setvar] = $val;
			
			foreach ( $subkeys as $subkey )
				$GLOBALS[$setvar][$subkey] = $val;
		}
	}

}

function awp_notice($message, $plugin_name) {
	// slick method copied from NextGEN Gallery plugin			// TODO: why isn't there a class that can turn this text black?
	add_action('admin_notices', create_function('', 'echo \'<div id="message" class="error fade" style="color: black">' . $message . '</div>\';'));
	trigger_error("$plugin_name internal notice: $message");
	$err = new WP_Error($plugin_name, $message);
}

// written because WP function is_plugin_active() requires plugin folder in arg
function awp_is_plugin_active($check_plugin_file) {
	if ( ! $check_plugin_file )
		return false;

	$plugins = get_option('active_plugins');

	foreach ( $plugins as $plugin_file ) {
		if ( false !== strpos($plugin_file, $check_plugin_file) )
			return $plugin_file;
	}
}

function is_attachment_rs() {
	global $wp_query;
	return ! empty($wp_query->query_vars['attachment_id']) || ! empty($wp_query->query_vars['attachment']);
}

function awp_usage_message( $translate = true ) {
	if ( function_exists('memory_get_usage') ) {
		if ( $translate )
			return sprintf( __('%1$s queries in %2$s seconds. %3$s MB used.', 'scoper'), get_num_queries(), round(timer_stop(0), 1), round( memory_get_usage() / (1024 * 1024), 3), 'scoper' ) . ' ';
		else
			return get_num_queries() . ' queries in ' . round(timer_stop(0), 1) . ' seconds. ' . round( memory_get_usage() / (1024 * 1024), 3) . ' MB used. ';
	}
}

function awp_echo_usage_message( $translate = true ) {
	echo awp_usage_message( $translate );
}

function post_exists_rs( $post_id_arg = '' ) {
	global $wp_query, $wpdb;

	if ( ! $post_id_arg && ! empty($wp_query->query_vars['p']) )
		$post_id_arg = $wp_query->query_vars['p'];
		
	if ( $post_id_arg ) {
		if ( get_post($post_id_arg) )
			return true;

	} elseif ( ! empty($wp_query->query_vars['name']) ) {
		if ( $wpdb->get_var( sprintf("SELECT ID FROM $wpdb->posts WHERE post_name = '%s'", $wp_query->query_vars['name'] ) ) )
			return true;
	}
}

function awp_get_user_by_name( $name, $display_or_username = true ) {
	global $wpdb;
	
	if ( ! $user = $wpdb->get_row("SELECT * FROM $wpdb->users WHERE user_login = '$name'") )
		if ( $display_or_username )
			$user = $wpdb->get_row("SELECT * FROM $wpdb->users WHERE display_name = '$name'");
	
	return $user;
}

function awp_get_user_by_id( $id ) {
	global $wpdb;
	
	if ( $user = $wpdb->get_row("SELECT * FROM $wpdb->users WHERE ID = '$id'") )

	return $user;
}

?>