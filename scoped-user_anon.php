<?php
if( basename(__FILE__) == basename($_SERVER['SCRIPT_FILENAME']) )
	die();

/**
 * WP_Scoped_User PHP class for the WordPress plugin Role Scoper
 * role-scoper.php
 * 
 * @author 		Kevin Behrens
 * @copyright 	Copyright 2009
 * 
 * NOTE: this skeleton edition of WP_Scoped_User is loaded for anonymous users to reduce mem usage
 *
 */

if ( ! class_exists('WP_Scoped_User') ) {
class WP_Scoped_User extends WP_User { // Special skeleton class for ANONYMOUS USERS

	// note: these arrays are flipped (data stored in key) for better searching performance
	var $groups = array(); 				// 	$groups[group id] = 1
	var $has_user_roles = false;
	var $blog_roles = array(); 			//  $blog_roles[role_handle] = 1
	var $term_roles = array();			//	$term_roles[taxonomy][role_handle] = array of term ids 
	var $assigned_blog_roles = array(); //  $assigned_blog_roles[role_handle] = 1
	var $assigned_term_roles = array();	//	$assigned_term_roles[taxonomy][role_handle] = array of term ids 
	var $qualified_terms = array();		//  $qualified_terms[taxonomy][$capreqs_key] = previous result for qualify_terms call on this set of capreqs
	var $is_administrator;				//  cut down on unnecessary filtering by assuming that if a user can activate plugins, they can do anything
	var $is_module_administrator = array();
	
	function WP_Scoped_User($id = 0, $name = '', $args = '') {
		$this->WP_User($id, $name);
		
		global $scoper;
		if ( empty($scoper) || empty($scoper->role_defs) ) {
			require_once('role-scoper_main.php');
			
			// todo: review this
			$temp = new Scoper();
			$scoper =& $temp;
		}
	}

	// should not be used for anon user, but leave to maintain API
	function get_user_clause($table_alias) {
		$table_alias = ( $table_alias ) ? "$table_alias." : '';
		return " AND {$table_alias}user_id = '0'";
	}
	
	function cache_get($cache_flag) {
		$cache_id = 0;
		$cache_flag = $cache_flag . '_for_' . ROLE_BASIS_USER;

		return wpp_cache_get($cache_id, $cache_flag);
	}
	
	function cache_set($entry, $cache_flag) {
		$cache_id = 0;
		$cache_flag = $cache_flag . '_for_' . ROLE_BASIS_USER;
		
		return wpp_cache_set($cache_id, $entry, $cache_flag);
	}
		

	function get_groups_for_user( $user_id, $args = '' ) {
		return array();
	}
	
	// return group_id as array keys
	function _get_usergroups($args = '') {
		return array();
	}
	
	function get_blog_roles( $role_type = 'rs' ) {
		return array();
	}
	
	// returns array[role name] = array of term ids for which user has the role assigned (based on current role basis)
	function get_term_roles( $taxonomy = 'category', $role_type = 'rs' ) {
		$this->term_roles[$taxonomy] = array();
		return array();
	}
} // end class WP_Scoped_User
}

if ( ! function_exists('is_administrator_rs') ) {
function is_administrator_rs( $src_or_tx = '' ) {
	return false;
}
}

?>