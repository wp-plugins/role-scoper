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
 */
class WP_Scoped_User extends WP_User {
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
		
		$defaults = array( 'disable_user_roles' => false, 'disable_group_roles' => false, 'disable_wp_roles' => false );
		$args = array_merge( $defaults, (array) $args );
		extract($args);
		
		global $scoper;
		if ( empty($scoper) || empty($scoper->role_defs) ) {
			require_once('role-scoper_main.php');
			
			// todo: review this
			$temp = new Scoper();
			$scoper =& $temp;
		}
		
		if ( $this->ID ) {
			if ( ! $disable_wp_roles ) {
				// include both WP roles and custom caps, which are treated as a hidden single-cap role capable of satisfying single-cap current_user_can calls
				$this->assigned_blog_roles = $this->caps;
			
				// prepend role_type prefix to wp rolenames
				global $wp_roles;
				foreach ( array_keys($this->assigned_blog_roles) as $name) {
					if ( isset($wp_roles->role_objects[$name]) ) {
						$this->assigned_blog_roles['wp_' . $name] = $this->assigned_blog_roles[$name];
						unset($this->assigned_blog_roles[$name]);
					}
				}
			}
			
			if ( defined('USER_ROLES_RS') && USER_ROLES_RS && ! $disable_user_roles )
					$this->has_user_roles = $this->check_for_user_roles();
			
			if ( defined('DEFINE_GROUPS_RS') && ! $disable_group_roles ) {
				$this->groups = $this->_get_usergroups();

				if ( ! empty($args['filter_usergroups']) )  // assist group admin
					$this->groups = array_intersect_key($this->groups, $args['filter_usergroups']);
			}
			
			if ( 'rs' == SCOPER_ROLE_TYPE && RS_BLOG_ROLES ) {
				if ( $rs_blogroles = $this->get_blog_roles( SCOPER_ROLE_TYPE ) ) {
					$this->assigned_blog_roles = array_merge($this->assigned_blog_roles, $rs_blogroles);
				}
			}
			
			$this->blog_roles = $scoper->role_defs->add_contained_roles( $this->assigned_blog_roles );
			
			// note: The allcaps property still governs current_user_can calls when the cap requirements do not pertain to a specific object.
			// If WP roles fail to provide all required caps, the Role Scoper has_cap filter validate the current_user_can check 
			// if any RS blogrole has all the required caps.
			//
			// The blog_roles array also comes into play for object permission checks such as page or post listing / edit.  
			// In such cases, roles in the Scoper_User::blog_roles array supplement any pertinent taxonomy or role assignments,
			// as long as the object or its terms are not configured to require that role to be term-assigned or object-assigned.
		
			// If a user can activate plugins, they can do anything!
			$this->is_administrator = ! empty( $this->allcaps['activate_plugins'] );
		}
	}

	function check_for_user_roles() {
		global $wpdb;
		
		$role_type = SCOPER_ROLE_TYPE;
		return scoper_get_var("SELECT assignment_id FROM $wpdb->user2role2object_rs WHERE role_type = '$role_type' AND user_id = '$this->ID' LIMIT 1");
	}
	
	function get_user_clause($table_alias) {
		$table_alias = ( $table_alias ) ? "$table_alias." : '';
		
		$arr = array();
		
		if ( GROUP_ROLES_RS && $this->groups )
			$arr []= "{$table_alias}group_id IN ('" . implode("', '", array_keys($this->groups) ) . "')";
		
		if ( ( USER_ROLES_RS && $this->has_user_roles ) || empty($arr) ) // too risky to allow query with no user or group clause
			$arr []= "{$table_alias}user_id = '$this->ID'";
			
		$clause = implode( ' OR ', $arr );
		
		if ( count($arr) > 1 )
			$clause = "( $clause )";
		
		if ( $clause )
			return " AND $clause";
	}
	
	function cache_get($cache_flag) {
		if ( GROUP_ROLES_RS && $this->groups && $this->has_user_roles ) {
			$cache_id = $this->ID;	
			$cache_flag = $cache_flag . '_for_' . ROLE_BASIS_USER_AND_GROUPS;
			
		} elseif ( GROUP_ROLES_RS && $this->groups ) {
			$cache_id = md5( serialize($this->groups) );	
			$cache_flag = $cache_flag . '_for_' . ROLE_BASIS_GROUPS;
		
		} else {
			$cache_id = $this->ID;
			$cache_flag = $cache_flag . '_for_' . ROLE_BASIS_USER;
		}
		
		return wpp_cache_get($cache_id, $cache_flag);
	}
	
	function cache_set($entry, $cache_flag) {
		if ( GROUP_ROLES_RS && $this->groups && $this->has_user_roles ) {
			$cache_id = $this->ID;	
			$cache_flag = $cache_flag . '_for_' . ROLE_BASIS_USER_AND_GROUPS;
			
		} elseif ( GROUP_ROLES_RS && $this->groups ) {
			$cache_id = md5( serialize($this->groups) );	
			$cache_flag = $cache_flag . '_for_' . ROLE_BASIS_GROUPS;
		
		} else {
			$cache_id = $this->ID;
			$cache_flag = $cache_flag . '_for_' . ROLE_BASIS_USER;
		}
		
		return wpp_cache_set($cache_id, $entry, $cache_flag);
	}
		

	// can be called statically by external modules
	function get_groups_for_user( $user_id, $args = '' ) {
		$cache = wpp_cache_get($user_id, 'group_membership_for_user');
		if ( is_array($cache) )
			return $cache;
		
		global $wpdb;
		
		if ( ! $wpdb->user2group_rs )
			return array();

		$query = "SELECT $wpdb->user2group_gid_col FROM $wpdb->user2group_rs WHERE $wpdb->user2group_uid_col = '$user_id' ORDER BY $wpdb->user2group_gid_col";
		if ( ! $user_groups = scoper_get_col($query) )
			$user_groups = array();
		
		// include WP metagroup(s) for WP blogrole(s)
		$metagroup_ids = array();
		if ( ! empty($args['metagroup_roles']) ) {
			foreach ( array_keys($args['metagroup_roles']) as $role_handle )
				$metagroup_ids []= 'wp_role_' . str_replace( 'wp_', '', $role_handle );
		}
		
		if ( $metagroup_ids ) {
			$meta_id_in = "'" . implode("', '", $metagroup_ids) . "'";

			$query = "SELECT $wpdb->groups_id_col FROM $wpdb->groups_rs"
			. " WHERE {$wpdb->groups_rs}.{$wpdb->groups_meta_id_col} IN ($meta_id_in)"
			. " ORDER BY $wpdb->groups_id_col";
		
			if ( $meta_groups = scoper_get_col($query) )
				$user_groups = array_merge( $user_groups, $meta_groups );
		}
	
		if ( $user_groups )
			$user_groups = array_fill_keys($user_groups, 1);
			
		wpp_cache_set($user_id, $user_groups, 'group_membership_for_user');
		
		return $user_groups;
	}
	
	// return group_id as array keys
	function _get_usergroups($args = '') {
		if ( ! $this->ID )
			return array();
		
		if ( ! is_array($args) )
			$args = array();
		
		if ( isset($this->assigned_blog_roles) )
			$args['metagroup_roles'] = $this->assigned_blog_roles;

		$user_groups = WP_Scoped_User::get_groups_for_user( $this->ID, $args );
		
		return $user_groups;
	}
	
	function get_blog_roles( $role_type = 'rs' ) {
		$cache_flag = "{$role_type}_blog_roles";
		$cache = $this->cache_get( $cache_flag );
		if ( is_array($cache) )
			return $cache;
	
		global $wpdb;
		
		$u_g_clause = $this->get_user_clause('uro');
		
		$qry = "SELECT role_name FROM $wpdb->user2role2object_rs AS uro WHERE uro.scope = 'blog' AND uro.role_type = '$role_type' $u_g_clause";
		$role_names =  scoper_get_col($qry);
		
		$role_handles = scoper_role_names_to_handles($role_names, $role_type, true);  //arg: return as array keys
		
		$this->cache_set($role_handles, $cache_flag);
		
		return $role_handles;
	}
	
	// returns array[role name] = array of term ids for which user has the role assigned (based on current role basis)
	function get_term_roles( $taxonomy = 'category', $role_type = 'rs' ) {
		global $wpdb;
		
		$cache_flag = "{$role_type}_term_roles_{$taxonomy}";
		
		$tx_term_roles = $this->cache_get($cache_flag);
		
		if ( ! is_array($tx_term_roles) ) {
			// no need to check for this on cache retrieval, since a role_type change results in a rol_defs change, which triggers a full scoper cache flush
			$role_type = SCOPER_ROLE_TYPE;
			
			$tx_term_roles = array();
			
			$u_g_clause = $this->get_user_clause('uro');

			$qry = "SELECT uro.obj_or_term_id, uro.role_name FROM $wpdb->user2role2object_rs AS uro ";
			$qry .= "WHERE uro.scope = 'term' AND uro.assign_for IN ('entity', 'both') AND uro.role_type = '$role_type' AND uro.src_or_tx_name = '$taxonomy' $u_g_clause";
							
			if ( $results = scoper_get_results($qry) ) {
				foreach($results as $termrole) {
					$role_handle = SCOPER_ROLE_TYPE . '_' . $termrole->role_name;
					$tx_term_roles[$role_handle] []= $termrole->obj_or_term_id;
				}
			}
			
			$this->cache_set($tx_term_roles, $cache_flag);
		}
		
		$this->assigned_term_roles[$taxonomy] = $tx_term_roles;
		
		global $scoper;
		$this->term_roles[$taxonomy] = $scoper->role_defs->add_contained_roles( $this->assigned_term_roles[$taxonomy], true );  //arg: is term array
		
		return $tx_term_roles;
	}
} // end class WP_Scoped_User


function is_administrator_rs( $src_or_tx = '' ) {
	global $current_user;
	static $admin_caps;
	
	if ( empty($current_user->ID) )
		return false;
	
	$return = '';
		
	if ( $src_or_tx ) {
		if ( ! is_object($src_or_tx) ) {
			global $scoper;
			if ( ! $obj = $scoper->data_sources->get($src_or_tx) )
				$obj = $scoper->taxonomies->get($src_or_tx);
				
			if ( $obj )
				$src_or_tx = $obj;
		}
		
		if ( ! empty($src_or_tx->defining_module_name) ) {
			$defining_module_name = $src_or_tx->defining_module_name;
			if ( ('wordpress' != $defining_module_name) && ('role-scoper' != $defining_module_name) ) {
				if ( ! isset($admin_caps) )
					$admin_caps = apply_filters( 'define_administrator_caps_rs', array() );
	
				if ( ! empty( $admin_caps[$defining_module_name] ) ) {
					$use_admin_cap = $admin_caps[$defining_module_name];
					if ( $return = current_user_can($use_admin_cap) )
						$current_user->is_module_administrator[$src_or_tx->name] = true;
				}
			}
		}
	}
	
	if ( empty($return) ) {
		if ( isset($current_user->is_administrator) ) {
			return $current_user->is_administrator;
		} else {
			$return = current_user_can('activate_plugins');
			$current_user->is_administrator = $return;
		}
	}
	
	return $return;
}

?>