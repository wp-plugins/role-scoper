<?php

if( basename(__FILE__) == basename($_SERVER['SCRIPT_FILENAME']) )
	die();

require_once( 'admin_lib_rs.php' );
	
/**
 * ScoperAdminFilters PHP class for the WordPress plugin Role Scoper
 * filters-admin_rs.php
 * 
 * @author 		Kevin Behrens
 * @copyright 	Copyright 2009
 * 
 */
class ScoperAdminFilters
{
	var $role_levels; 	// NOTE: user/role levels are used only for optional limiting of user edit, not for content filtering
	var $user_levels;	// this is only populated for performance as a buffer for currently queried / listed users
	var $last_post_status = array();
	var $logged_post_update = array();
	
	function ScoperAdminFilters() {
		global $scoper;
		
		// --------- CUSTOMIZABLE HOOK WRAPPING ---------
		//
		// Make all source-specific operation hooks trigger equivalent abstracted hook:
		// 		create_object_rs, edit_object_rs, save_object_rs, delete_object_rs,
		//   	create_term_rs, edit_term_rs, delete_term_rs (including custom non-WP taxonomies)
		//
		// These will be used by Role Scoper for role maintenance, but are also available for external use.
		$rs_filters = array();
		$rs_actions = array();
		
		// Register our abstract handlers to save_post, edit_post, delete_post and corresponding hooks from other data sources.
		// see core_default_data_sources() and Scoped_Data_Sources::process() for default hook names
		foreach ( $scoper->data_sources->get_all() as $src_name => $src ) {
			if ( ! empty($src->admin_actions) )
				foreach ( $src->admin_actions as $rs_hook => $original_hook )
					$rs_actions[$original_hook] = (object) array( 'name' => "{$rs_hook}_rs", 'rs_args' => "'$src_name', '' " );	
			
			if ( ! empty($src->admin_filters) )
				foreach ( $src->admin_filters as $rs_hook => $original_hook )
					$rs_filters[$original_hook] = (object) array( 'name' => "{$rs_hook}_rs", 'rs_args' => "'$src_name', '' " );	

			// also register hooks that are specific to one object type
			foreach ( $src->object_types as $object_type => $otype_def ) {
				if ( ! empty($otype_def->admin_actions) ) {
					foreach ( $otype_def->admin_actions as $rs_hook => $original_hook )
						$rs_actions[$original_hook] = (object) array( 'name' => "{$rs_hook}_rs", 'rs_args' => "'$src_name', array( 'object_type' => '$object_type' ) " );	
				}
				
				if ( ! empty($otype_def->admin_filters) )
					foreach ( $otype_def->admin_filters as $rs_hook => $original_hook )
						$rs_filters[$original_hook] = (object) array( 'name' => "{$rs_hook}_rs", 'rs_args' => "'$src_name', array( 'object_type' => '$object_type' ) " );	
			}
		} //foreach data_sources
		
		// Register our abstract handlers to create_category, edit_category, delete_category and corresponding hooks from other taxonomies.
		// (supports WP taxonomies AND custom taxonomies)
		// see core_default_taxonomies() and Scoped_Taxonomies::process() for default hook names
		foreach ( $scoper->taxonomies->get_all() as $taxonomy => $tx ) {
			if ( ! empty($tx->admin_actions) )
				foreach ( $tx->admin_actions as $rs_hook => $original_hook )
					$rs_actions[$original_hook] = (object) array( 'name' => "{$rs_hook}_rs", 'rs_args' => "'$taxonomy', '' " );

			if ( ! empty($tx->admin_filters) )
				foreach ( $tx->admin_filters as $rs_hook => $original_hook )
					$rs_filters[$original_hook] = (object) array( 'name' => "{$rs_hook}_rs", 'rs_args' => "'$taxonomy', '' " );
		}
		
		// call our abstract handlers with a lambda function that passes in original hook name
		$hook_order = ( defined('WPCACHEHOME') ) ? -1 : 50; // WP Super Cache's early create_post / edit_post handlers clash with Role Scoper
		
		foreach ( $rs_actions as $original_hook => $rs_hook ) {
			if ( ! $original_hook ) continue;
			$orig_hook_numargs = 1;
			$arg_str = agp_get_lambda_argstring($orig_hook_numargs);
			$comma = ( $rs_hook->rs_args ) ? ',' : '';
			$func = "do_action( '$rs_hook->name', $rs_hook->rs_args $comma $arg_str );";
			//echo "adding action: $original_hook -> $func <br />";
			add_action( $original_hook, create_function( $arg_str, $func ), $hook_order, $orig_hook_numargs );	
		}
		
		foreach ( $rs_filters as $original_hook => $rs_hook ) {
			if ( ! $original_hook ) continue;
			$orig_hook_numargs = 1;
			$arg_str = agp_get_lambda_argstring($orig_hook_numargs);
			$comma = ( $rs_hook->rs_args ) ? ',' : '';
			$func = "return apply_filters( '$rs_hook->name', $arg_str $comma $rs_hook->rs_args );";
			//echo "adding filter: $original_hook -> $func <br />";
			add_filter( $original_hook, create_function( $arg_str, $func ), 50, $orig_hook_numargs );	
		}
		
		
		// WP 2.5 throws a notice if plugins add their own hooks without prepping the global array
		// Source or taxonomy-specific hooks are mapped to these based on config member properties
		// in WP_Scoped_Data_Sources::process() and WP_Scoped_Taxonomies:process()
		$setargs = array( 'is_global' => true );
		$setkeys = array (
			'create_object_rs',	'edit_object_rs',	'save_object_rs',	'delete_object_rs',
			'create_term_rs',	'edit_term_rs',		'save_term_rs',		'delete_term_rs'
		);
		
		add_action('create_object_rs', array(&$this, 'mnt_create_object'), 10, 4);
		add_action('edit_object_rs', array(&$this, 'mnt_edit_object'), 10, 4);
		add_action('save_object_rs', array(&$this, 'mnt_save_object'), 10, 4);
		add_action('delete_object_rs', array(&$this, 'mnt_delete_object'), 10, 3);
		
		// these will be used even if the taxonomy in question is not a WP core taxonomy (i.e. even if uses a custom schema)
		add_action('create_term_rs', array(&$this, 'mnt_create_term'), 10, 4);
		add_action('edit_term_rs', array(&$this, 'mnt_edit_term'), 10, 4);
		add_action('delete_term_rs', array(&$this, 'mnt_delete_term'), 10, 3);
		
		
		// -------- Predefined WP User/Post/Page admin actions / filters ----------
		// user maintenace
		add_action('profile_update', array('ScoperAdminLib', 'sync_wproles') );
		add_filter('user_has_cap', array(&$this, 'flt_has_edit_user_cap'), 99, 3 );
		add_filter('editable_roles', array(&$this, 'flt_editable_roles'), 99 );

		if ( IS_MU_RS ) {
			add_action('add_user_to_blog', array(&$this, 'act_add_user_to_blog'), 10, 3);
			add_action('remove_user_from_blog', array('ScoperAdminLib', 'delete_users'), 10, 2 );
		} else {
			add_action('user_register', array(&$this, 'act_user_register') ); // applies default group(s), calls sync_wproles
			add_action('delete_user', array('ScoperAdminLib', 'delete_users') );
		}
		
		if ( GROUP_ROLES_RS )
			add_action('profile_update',  array(&$this, 'act_update_user_groups'));
				
		// log post status transition to recognize new posts and status change to/from private
		add_action( 'transition_post_status', array(&$this, 'act_log_post_status'), 10, 3 );

		add_action( 'edit_post', array(&$this, 'act_log_updated_post') );

		// Filtering of Page Parent selection:
		add_filter('pre_post_status', array(&$this, 'flt_post_status'), 50, 1);
		add_filter('pre_post_parent', array(&$this, 'flt_page_parent'), 50, 1);
		
		add_filter( 'pre_post_tax_input', array(&$this, 'flt_tax_input'), 50, 1);
		
		// Filtering of terms selection:
		add_action('check_admin_referer', array(&$this, 'act_detect_post_presave')); // abuse referer check to work around a missing hook

		add_filter('pre_object_terms_rs', array(&$this, 'flt_pre_object_terms'), 50, 3);
		
		if ( awp_ver( '3.0' ) )
			add_filter( 'save_post', array(&$this, 'custom_taxonomies_helper'), 5, 2); 
		
		// As of 2.7, QuickPress does not call pre_post_category  TODO: "pre_option_default_{$taxonomy}"
		if ( ( ! strpos($_SERVER['SCRIPT_NAME'], 'p-admin/options-writing.php') ) && ! is_content_administrator_rs() )
			add_filter('pre_option_default_category', array(&$this, 'flt_default_term') );

		// Follow up on role creation / deletion by Role Manager, Capability Manager or other equivalent plugin
		// Role Manager / Capability Manager don't actually modify the stored role def until after the option update we're hooking on, so defer our maintenance operation
		global $wpdb;
		add_action( "update_option_{$wpdb->prefix}user_roles", array('ScoperAdminLib', 'schedule_role_sync') );
		
		add_filter( 'posts_fields', array(&$this, 'flt_posts_fields') );
		
		if ( scoper_get_option( 'group_ajax' ) ) {
			add_action( 'add_group_user_rs', array(&$this, 'new_group_user_notification'), 10, 3 );
			add_action( 'update_group_user_rs', array(&$this, 'edit_group_user_notification'), 10, 4 );
		}

		// TODO: make this optional
		// include private posts in the post count for each term
		global $wp_taxonomies;
		foreach ( $wp_taxonomies as $key => $t ) {
			if ( isset($t->update_count_callback) && ( '_update_post_term_count' == $t->update_count_callback ) )
				$wp_taxonomies[$key]->update_count_callback = 'scoper_update_post_term_count';
		}
	}
	
	// make sure pre filter is applied for all custom taxonomies regardless of term selection
	function custom_taxonomies_helper( $post_id, $post ) {
		require_once( 'filters-admin-save_rs.php' );
		scoper_force_custom_taxonomy_filters( $post_id, $post );	
	}	

	function act_log_post_status( $new_status, $old_status, $post ) {
		$this->last_post_status[$post->ID] = $old_status;
	}

	function act_log_updated_post( $post_id ) {
		$this->logged_post_update[$post_id] = true;
	}	
	
	function new_group_user_notification ( $user_id, $group_id, $status ) {
		if ( 'active' == $status )
			return;
			
		require_once( 'group-notification_rs.php' );
			
		if ( 'requested' == $status )
			return ScoperGroupNotification::membership_request_notify( $user_id, $group_id );
		elseif ( 'recommended' == $status )
			return ScoperGroupNotification::membership_recommendation_notify( $user_id, $group_id );
	}
	
	function edit_group_user_notification ( $user_id, $group_id, $status, $prev_status ) {
		if ( $status == $prev_status )
			return;
			
		require_once( 'group-notification_rs.php' );

		if ( ! $prev_status )
			return $this->new_group_user_notification( $user_id, $group_id, $status );
	 	elseif ( 'active' == $status )
			return ScoperGroupNotification::membership_activation_notify( $user_id, $group_id, true );
		elseif ( 'recommended' == $status )
			return ScoperGroupNotification::membership_recommendation_notify( $user_id, $group_id, true );
	}
	
	// optional filter for WP role edit based on user level
	function flt_editable_roles( $roles ) {
		if ( defined( 'DISABLE_QUERYFILTERS_RS' ) || ! scoper_get_option('limit_user_edit_by_level') )
			return $roles;
		
		require_once( 'user_lib_rs.php' );
		return ScoperUserEdit::editable_roles( $roles );
	}	
	

	function flt_has_edit_user_cap($wp_blogcaps, $orig_reqd_caps, $args) {
		// Optionally, prevent anyone from editing a user whose level is higher than their own
		if ( ! defined( 'DISABLE_QUERYFILTERS_RS' ) && in_array( 'edit_users', $orig_reqd_caps ) && ! empty($args[2]) ) {
			if ( scoper_get_option('limit_user_edit_by_level') ) {
				require_once( 'user_lib_rs.php' );
				$wp_blogcaps = ScoperUserEdit::has_edit_user_cap( $wp_blogcaps, $orig_reqd_caps, $args );
			}
		}
	
		return $wp_blogcaps;
	}
	
	function flt_posts_fields($cols) {
		if ( defined( 'DISABLE_QUERYFILTERS_RS' ) )
			return $cols;
		
		if (   // possible TODO: reinstate support for separate activation of pages / posts lean (as of WP 2.9, all post types us edit.php)
		( ( defined( 'SCOPER_EDIT_PAGES_LEAN' ) || defined( 'SCOPER_EDIT_POSTS_LEAN' ) ) && 
		  ( strpos(urldecode($_SERVER['REQUEST_URI']), 'wp-admin/edit-pages.php') || strpos(urldecode($_SERVER['REQUEST_URI']), 'wp-admin/edit.php') ) )
	    ) {
			global $wpdb;
			$cols = "$wpdb->posts.ID, $wpdb->posts.post_author, $wpdb->posts.post_date, $wpdb->posts.post_date_gmt, $wpdb->posts.post_title, $wpdb->posts.post_status, $wpdb->posts.comment_status, $wpdb->posts.ping_status, $wpdb->posts.post_password, $wpdb->posts.post_name, $wpdb->posts.to_ping, $wpdb->posts.pinged, $wpdb->posts.post_parent, $wpdb->posts.post_modified, $wpdb->posts.post_modified_gmt, $wpdb->posts.guid, $wpdb->posts.post_type, $wpdb->posts.post_mime_type, $wpdb->posts.menu_order, $wpdb->posts.comment_count";
		}

		return $cols;
	}
	
	// Filtering of Page Parent selection.  
	// This was a required after-the-fact operation for WP < 2.7 (due to inability to control inclusion of Main Page in UI dropdown)
	// Now only needes for submission filtering
	//
	function flt_post_status ($status) {
		if ( defined( 'DISABLE_QUERYFILTERS_RS' ) )
			return $status;
		
		require_once('filters-admin-save_rs.php');
		return scoper_flt_post_status($status);
	}
	
	// Enforce any page parent filtering which may have been dictated by the flt_post_status filter, which executes earlier.
	function flt_page_parent ($parent_id) {
		if ( defined( 'DISABLE_QUERYFILTERS_RS' ) )
			return $parent_id;
	
		require_once('filters-admin-save_rs.php');
		return scoper_flt_page_parent($parent_id);
	}
	
	
	function act_detect_post_presave($action) {
		// for post update with no post categories checked, insert a fake category so WP core doesn't force default category
		// (flt_pre_object_terms will first restore any existing postcats dropped due to user's lack of permissions)
		if ( 0 === strpos($action, 'update-post_') ) {
			if ( defined( 'DISABLE_QUERYFILTERS_RS' ) )
				return;

			if ( empty($_POST['post_category']) && ! is_content_administrator_rs() ) {
				$_POST['post_category'] = array(-1);
			}
		}
	}
	
	function flt_tax_input( $tax_input ) {
		if ( $tax_input && is_array($tax_input) ) {
			foreach( $tax_input as $taxonomy => $terms ) {
				if ( is_string($terms) )  // currently, don't restrict non-hierarchical tag assignments / removals per-user
					continue;
					//$terms = explode( ",", $terms );

				$tax_input[$taxonomy] = $this->flt_pre_object_terms( $terms, $taxonomy );
			}
		}
				
		return $tax_input;
	}
	
	function flt_pre_object_terms ($selected_terms, $taxonomy, $args = '') {
		if ( defined( 'DISABLE_QUERYFILTERS_RS' ) || did_action('tdomf_create_post_start') )  // don't filter out a category that was added by TDO Mini Forms
			return $selected_terms;
		
		require_once('filters-admin-save_rs.php');
		return scoper_flt_pre_object_terms($selected_terms, $taxonomy, $args);
	}
	
	function act_transition_post_status( $new_status, $old_status, $post ) {
		if ( 'auto-draft' == $old_status )
			$this->new_posts_log[ $post->ID ] = true;	
	}
	
	// This handler is meant to fire whenever an object is inserted or updated.
	// If the client does use such a hook, we will force it by calling internally from mnt_create and mnt_edit
	function mnt_save_object($src_name, $args, $object_id, $object = '') {
		//rs_errlog( 'mnt_save_object' );
		
		if ( defined( 'RVY_VERSION' ) ) {
			global $revisionary;
		
			if ( ! empty($revisionary->admin->revision_save_in_progress) ) {
				$revisionary->admin->revision_save_in_progress = false;
				return;
			}
		}

		require_once('filters-admin-save_rs.php');
		scoper_mnt_save_object($src_name, $args, $object_id, $object);
	}
	
	// This handler is meant to fire only on updates, not new inserts
	function mnt_edit_object($src_name, $args, $object_id, $object = '') {
		static $edited_objects;
		
		if ( ! isset($edited_objects) )
			$edited_objects = array();
	
		// so this filter doesn't get called by hook AND internally
		if ( isset($edited_objects[$src_name][$object_id]) )
			return;	
		
		$edited_objects[$src_name][$object_id] = 1;
			
		// call save handler directly in case it's not registered to a hook
		$this->mnt_save_object($src_name, $args, $object_id, $object);
	}
	
	function mnt_delete_object($src_name, $args, $object_id) {
		$object = '';
		
		$defaults = array( 'object_type' => '', 'object' => '' );
		$args = array_intersect_key( $defaults, (array) $args );
		extract($args);
	
		if ( ! $object_id )
			return;

		// could defer role/cache maint to speed potential bulk deletion, but script may be interrupted before admin_footer
		$this->item_deletion_aftermath( OBJECT_SCOPE_RS, $src_name, $object_id );

		if ( empty($object_type) )
			$object_type = scoper_determine_object_type($src_name, $object_id);
			
		if ( 'page' == $object_type ) {
			delete_option('scoper_page_ancestors');
			scoper_flush_cache_groups('get_pages');
		}
		
		scoper_flush_roles_cache(OBJECT_SCOPE_RS);
	}
	
	function mnt_create_object($src_name, $args, $object_id, $object = '') {
		$defaults = array( 'object_type' => '' );
		$args = array_intersect_key( $defaults, (array) $args );
		extract($args);
	
		static $inserted_objects;
		
		if ( ! isset($inserted_objects) )
			$inserted_objects = array();
		
		// so this filter doesn't get called by hook AND internally
		if ( isset($inserted_objects[$src_name][$object_id]) )
			return;
	
			
		global $scoper;
			
		if ( empty($object_type) )
			if ( $col_type = $scoper->data_sources->member_property($src_name, 'cols', 'type') )
				$object_type = ( isset($object->$col_type) ) ? $object->$col_type : '';
			
		if ( empty($object_type) ) {
			if ( ! isset( $object ) )
				$object = '';
				
			$object_type = scoper_determine_object_type($src_name, $object_id, $object);
		}
			
		if ( $object_type == 'revision' )
			return;
			
		$inserted_objects[$src_name][$object_id] = 1;
		
		if ( 'page' == $object_type ) {
			delete_option('scoper_page_ancestors');
			scoper_flush_cache_groups('get_pages');
		}
	}
	
	function mnt_create_term($taxonomy, $args, $term_id, $term = '') {
		$this->mnt_save_term($taxonomy, $args, $term_id, $term);
		
		scoper_term_cache_flush();
		delete_option( "{$taxonomy}_children_rs" );
		delete_option( "{$taxonomy}_ancestors_rs" );
	}
	
	function mnt_edit_term($taxonomy, $args, $term_ids, $term = '') {
		static $edited_terms;
		
		if ( ! isset($edited_terms) )
			$edited_terms = array();
	
		// bookmark edit passes an array of term_ids
		if ( ! is_array($term_ids) )
			$term_ids = array($term_ids);
		
		foreach ( $term_ids as $term_id ) {
			// so this filter doesn't get called by hook AND internally
			if ( isset($edited_terms[$taxonomy][$term_id]) )
				return;	
			
			$edited_terms[$taxonomy][$term_id] = 1;
			
			// call save handler directly in case it's not registered to a hook
			$this->mnt_save_term($taxonomy, $term_id, $term);
		}
	}
	
	// This handler is meant to fire whenever a term is inserted or updated.
	// If the client does use such a hook, we will force it by calling internally from mnt_create and mnt_edit
	function mnt_save_term($taxonomy, $args, $term_id, $term = '') {
		require_once('filters-admin-save_rs.php');
		scoper_mnt_save_term($taxonomy, $args, $term_id, $term);
	}

	function mnt_delete_term($taxonomy, $args, $term_id) {
		global $wpdb;
	
		if ( ! $term_id )
			return;
		
		// could defer role/cache maint to speed potential bulk deletion, but script may be interrupted before admin_footer
		$this->item_deletion_aftermath( TERM_SCOPE_RS, $taxonomy, $term_id );

		delete_option( "{$taxonomy}_children_rs" );
		delete_option( "{$taxonomy}_ancestors_rs" );
		
		scoper_term_cache_flush();
		scoper_flush_roles_cache(TERM_SCOPE_RS, '', '', $taxonomy);
		scoper_flush_cache_flag_once("rs_$taxonomy");
	}

	function item_deletion_aftermath( $scope, $src_or_tx_name, $obj_or_term_id ) {
		global $wpdb;

		// delete role assignments for deleted term
		if ( $ass_ids = scoper_get_col("SELECT assignment_id FROM $wpdb->user2role2object_rs WHERE src_or_tx_name = '$src_or_tx_name' AND scope = '$scope' AND obj_or_term_id = '$obj_or_term_id'") ) {
			$id_in = "'" . implode("', '", $ass_ids) . "'";
			scoper_query("DELETE FROM $wpdb->user2role2object_rs WHERE assignment_id IN ($id_in)");
			
			// Propagated roles will be converted to direct-assigned roles if the original progenetor goes away.  Removal of a "link" in the parent/child propagation chain has no effect.
			scoper_query("UPDATE $wpdb->user2role2object_rs SET inherited_from = '0' WHERE inherited_from IN ($id_in)");
		}
		
		if ( $req_ids = scoper_get_col("SELECT requirement_id FROM $wpdb->role_scope_rs WHERE topic = '$scope' AND src_or_tx_name = '$src_or_tx_name' AND obj_or_term_id = '$obj_or_term_id'") ) {
			$id_in = "'" . implode("', '", $req_ids) . "'";
		
			scoper_query("DELETE FROM $wpdb->role_scope_rs WHERE requirement_id IN ($id_in)");
			
			// Propagated requirements will be converted to direct-assigned roles if the original progenetor goes away.  Removal of a "link" in the parent/child propagation chain has no effect.
			scoper_query("UPDATE $wpdb->role_scope_rs SET inherited_from = '0' WHERE inherited_from IN ($id_in)");
		}
	}
	
	function act_update_user_groups($user_id) {
		if ( empty( $_POST['rs_editing_user_groups'] ) ) // otherwise we'd delete group assignments if another plugin calls do_action('profile_update') unexpectedly
			return;

		global $current_user;
		
		$editable_group_ids = array();
		$stored_groups = array();
		
		if ( $user_id == $current_user->ID )
			$stored_groups['active'] = $current_user->groups;
		else {
			$user = new WP_Scoped_User($user_id, '', array( 'skip_role_merge' => 1 ) );
			$stored_groups['active'] = $user->groups;
		}
		
		// by retrieving filtered groups here, user will only modify membership for groups they can administer
		$editable_group_ids['active'] = ScoperAdminLib::get_all_groups(FILTERED_RS, COL_ID_RS, array( 'reqd_caps' => 'manage_groups' ) );
		
		if( scoper_get_option( 'group_ajax' ) ) {
			$this->update_user_groups_multi_status( $user_id, $stored_groups, $editable_group_ids );
			return;
		} else {
			$stored_groups = $stored_groups['active'];
			$editable_group_ids = $editable_group_ids['active'];
		}
			
		if ( ! empty($_POST['groups_csv']) ) {
			if ( $csv_for_item = ScoperAdminLib::agent_ids_from_csv( 'groups_csv', 'groups' ) )
				$posted_groups = array_merge($posted_groups, $csv_for_item);
		} else
			$posted_groups = ( isset($_POST['group']) ) ? $_POST['group'] : array();
			
		$posted_groups = array_unique( $posted_groups );
		
		foreach ($editable_group_ids as $group_id) {
			if( in_array($group_id, $posted_groups) ) { // checkbox is checked
				if( ! isset($stored_groups[$group_id]) )
					ScoperAdminLib::add_group_user($group_id, $user_id);

			} elseif( isset($stored_groups[$group_id]) ) {
				ScoperAdminLib::remove_group_user($group_id, $user_id);
			}
		}
	}
	
	function update_user_groups_multi_status( $user_id, $stored_groups, $editable_group_ids ) {
		global $current_user;
		
		$posted_groups = array();
		
		$is_administrator = is_user_administrator_rs();

		$can_manage = $is_administrator || current_user_can( 'manage_groups' );
		$can_moderate = $can_manage || current_user_can( 'recommend_group_membership' );
		
		if ( ! $can_moderate && ! current_user_can( 'request_group_membership' ) )
			return;
		
		if ( $can_manage )
			$posted_groups['active'] = explode( ',', trim($_POST['current_agents_rs_csv'], '') );
		else
			$stored_groups = array_diff_key( $stored_groups, array( 'active' => true ) );
			
		if ( $can_moderate ) {
			$posted_groups['recommended'] = ! empty($_POST['recommended_agents_rs_csv']) ? explode( ',', trim($_POST['recommended_agents_rs_csv'], '') ) : array();

			$stored_groups['recommended'] = $current_user->get_groups_for_user( $current_user->ID, array( 'status' => 'recommended' ) );
			
			$editable_group_ids['recommended'] = ScoperAdminLib::get_all_groups(FILTERED_RS, COL_ID_RS, array( 'reqd_caps' => 'recommend_group_membership' ) );
		
			if ( isset($editable_group_ids['active']) )
				$editable_group_ids['recommended'] = array_unique( $editable_group_ids['recommended'] + $editable_group_ids['active'] );
		}

		$stored_groups['requested'] = $current_user->get_groups_for_user( $current_user->ID, array( 'status' => 'requested' ) );

		$editable_group_ids['requested'] = ScoperAdminLib::get_all_groups(FILTERED_RS, COL_ID_RS, array( 'reqd_caps' => 'request_group_membership' ) );

		if ( isset($editable_group_ids['recommended']) )
			$editable_group_ids['requested'] = array_unique( $editable_group_ids['requested'] + $editable_group_ids['recommended'] );

		$posted_groups['requested'] = ! empty($_POST['requested_agents_rs_csv']) ? explode( ',', trim($_POST['requested_agents_rs_csv'], '') ) : array();
		
		$all_posted_groups = agp_array_flatten( $posted_groups );
		
		$all_stored_groups = array();
		foreach ( array_keys($stored_groups) as $status )
			$all_stored_groups = $all_stored_groups + $stored_groups[$status];
		
		/*
		dump($_POST);	
		dump($editable_group_ids);
		dump($stored_groups);
		dump($all_stored_groups);
		dump($posted_groups);
		dump($all_posted_groups);
		die;
		*/

		foreach ( $stored_groups as $status => $stored ) {
			if ( ! $editable_group_ids[$status] )
				continue;
			
			// remove group memberships which were not posted for any status, if logged user can edit the group
			foreach ( array_keys($stored) as $group_id ) {
				if ( ! in_array( $group_id, $all_posted_groups ) )
					if ( in_array( $group_id, $editable_group_ids[$status] ) )
						ScoperAdminLib::remove_group_user($group_id, $user_id);
			}
		}

		foreach ( $posted_groups as $status => $posted ) {
			if ( ! $editable_group_ids[$status] )
				continue;

			// insert or update group memberships as specified, if logged user can edit the group
			foreach ( $posted as $group_id ) {
				if ( in_array( $group_id, $editable_group_ids[$status] ) ) {
					if ( ! in_array( $group_id, $all_stored_groups ) )
						ScoperAdminLib::add_group_user($group_id, $user_id, $status);
					elseif ( ! in_array( $group_id, $stored_groups[$status] ) )
						ScoperAdminLib::update_group_user($group_id, $user_id, $status);
				}
			}
		}
	}

	function act_add_user_to_blog( $user_id, $role_name = '', $blog_id = '' ) {
		// enroll user in default group(s)
		if ( $default_groups = scoper_get_option( 'default_groups' ) )
			foreach ($default_groups as $group_id)
				ScoperAdminLib::add_group_user($group_id, $user_id);
		
		global $scoper_role_types;
	
		foreach ( $scoper_role_types as $role_type ) {	
			wpp_cache_flush_group("{$role_type}_users_who_can");
			wpp_cache_flush_group("{$role_type}_groups_who_can");
		}
	
		ScoperAdminLib::sync_wproles( $user_id, $role_name, $blog_id );
	}
	
	function act_user_register( $user_id ) {
		$this->act_add_user_to_blog( $user_id );
	}

	// Original purpose: as of WP 2.7, QuickPress does not call pre_post_category
	function flt_default_term( $default_term_id, $taxonomy = 'category' ) {
		require_once('filters-admin-term-selection_rs.php');

		// support an array of default IDs (but don't require it)
		$filtered_default_term_ids = (array) $default_term_id;
		
		$user_terms = array(); // will be returned by filter_terms_for_status
		
		$filtered_default_term_ids = scoper_filter_terms_for_status($taxonomy, $filtered_default_term_ids, $user_terms);

		// if the default term is not in user's subset of usable terms, substitute first available	
		if ( ( ( ! $filtered_default_term_ids ) || ! $filtered_default_term_ids[0] ) && $user_terms )
			return $user_terms[0];
		
		if ( count($filtered_default_term_ids) > 1 )	// won't return an array unless an array was passed in and more than one of its elements is usable by this user
			return $filtered_default_term_ids;
		else
			return reset($filtered_default_term_ids);	// if a single term ID was passed in and is permitted, it is returned here
	}
	
	function user_can_associate_main( $post_type ) {
		if ( is_content_administrator_rs() )
			return true;

		if ( awp_ver( '3.0' ) ) {
			if ( ! $post_type_obj = get_post_type_object($post_type) )
				return true;
			
			if ( ! $post_type_obj->hierarchical )
				return true;
				
			$edit_cap = $post_type_obj->cap->edit_others_posts;
			$publish_cap = $post_type_obj->cap->publish_posts;
		} else {
			if ( 'page' != $post_type )
				return true;
				
			$edit_cap = 'edit_others_pages';
			$publish_cap = 'publish_pages';						
		}
			
		// currently used only for page type, or for all if constant is set
		$top_pages_locked = scoper_get_option( 'lock_top_pages' );
			
		if ( ( 'page' == $post_type ) || defined( 'SCOPER_LOCK_OPTION_ALL_TYPES' ) ) {
			if ( '1' === $top_pages_locked ) {
				// only administrators can change top level structure
				return false;
			} else {
				$reqd_caps = ( 'author' == $top_pages_locked ) ? array( $publish_cap ) : array( $edit_cap );
				$roles = $GLOBALS['scoper']->role_defs->qualify_roles($reqd_caps);
				return array_intersect_key($roles, $GLOBALS['current_user']->blog_roles[ANY_CONTENT_DATE_RS]);
			}
		}
	}	
} // end class


function init_role_assigner() {
	global $scoper_role_assigner;

	if ( ! isset($scoper_role_assigner) ) {
		require_once('role_assigner_rs.php');
		$scoper_role_assigner = new ScoperRoleAssigner();
	}
	
	return $scoper_role_assigner;
}

function scoper_flush_cache_flag_once ($cache_flag) {
	static $flushed_wpcache_flags;

	if ( ! isset($flushed_wpcache_flags) )
		$flushed_wpcache_flags = array();

	if ( ! isset( $flushed_wpcache_flags[$cache_flag]) ) {
		wpp_cache_flush_group($cache_flag);
		$flushed_wpcache_flags[$cache_flag] = true;
	}
}

// flush a specified portion of Role Scoper's persistant cache
function scoper_flush_cache_groups($base_cache_flag) {
	global $scoper_role_types;
	
	foreach ( $scoper_role_types as $role_type ) {
		scoper_flush_cache_flag_once($role_type . '_' . $base_cache_flag . '_for_groups' );
		scoper_flush_cache_flag_once($role_type . '_' . $base_cache_flag . '_for_user' );
		scoper_flush_cache_flag_once($role_type . '_' . $base_cache_flag . '_for_ug' );
	}
}

function scoper_term_cache_flush() {
	// flush_cache_groups will expand this base flag to "rs_get_terms_for_user", etc.
	scoper_flush_cache_groups('get_terms');
	scoper_flush_cache_groups('scoper_get_terms');

	scoper_flush_cache_flag_once('all_terms');
		
	// TODO: don't flush get_pages cache on modification of taxonomies other than category
	if ( scoper_get_otype_option( 'use_term_roles', 'post', 'page' ) ) 
		scoper_flush_cache_groups('get_pages');
}

function scoper_determine_object_type($src_name, $object_id, $object = '') {
	global $scoper;
	
	if ( is_object($object) ) {
		$col_type = $scoper->data_sources->member_property($src_name, 'cols', 'type');
		if ( $col_type && isset($object->$col_type) ) {
			$object_type_val = $object->$col_type;
			$object_type = $scoper->data_sources->get_from_val('type', $object_type_val, $src_name);
		}
	}
	
	if ( empty($object_type) )
		$object_type = $scoper->data_sources->detect('type', $src_name, $object_id);
		
	return $object_type;
}

// modifies WP core _update_post_term_count to include private posts in the count, since RS roles can grant access to them
function scoper_update_post_term_count( $terms ) {
	global $wpdb;

	foreach ( (array) $terms as $term ) {
		$count = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $wpdb->term_relationships, $wpdb->posts WHERE $wpdb->posts.ID = $wpdb->term_relationships.object_id AND post_status IN ('publish', 'private') AND term_taxonomy_id = %d", $term ) );
		$wpdb->update( $wpdb->term_taxonomy, compact( 'count' ), array( 'term_taxonomy_id' => $term ) );
	}
}

?>