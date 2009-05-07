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
	var $revision_save_in_progress;
	
	var $scoper;
	
	function ScoperAdminFilters() {
		global $scoper;
		$this->scoper =& $scoper;
		
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
		foreach ( $this->scoper->data_sources->get_all() as $src_name => $src ) {
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
		foreach ( $this->scoper->taxonomies->get_all() as $taxonomy => $tx ) {
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
		awp_force_set('wp_filter', array(), $setargs, $setkeys, 10);
		
		add_action('create_object_rs', array(&$this, 'mnt_create_object'), 10, 3);
		add_action('edit_object_rs', array(&$this, 'mnt_edit_object'), 10, 3);
		add_action('save_object_rs', array(&$this, 'mnt_save_object'), 10, 3);
		add_action('delete_object_rs', array(&$this, 'mnt_delete_object'), 10, 3);
		
		// these will be used even if the taxonomy in question is not a WP core taxonomy (i.e. even if uses a custom schema)
		add_action('create_term_rs', array(&$this, 'mnt_create_term'), 10, 3);
		add_action('edit_term_rs', array(&$this, 'mnt_edit_term'), 10, 3);
		add_action('delete_term_rs', array(&$this, 'mnt_delete_term'), 10, 3);
		
		
		// -------- Predefined WP User/Post/Page admin actions / filters ----------
		// user maintenace
		add_action('user_register', array(&$this, 'act_user_register') ); // applies default group(s), calls sync_wproles
		add_action('profile_update', array('ScoperAdminLib', 'sync_wproles') );
		add_action('delete_user', array('ScoperAdminLib', 'delete_users') );

		if ( GROUP_ROLES_RS )
			add_action('profile_update',  array(&$this, 'act_update_user_groups'));
		
		if ( awp_ver('2.6') ) {
			// log this action so we know when to ignore the save_post action
			add_action('inherit_revision', array(&$this, 'act_log_revision_save') );

			add_action('pre_post_type', array(&$this, 'flt_detect_revision_save') );
		}
			
		// Filtering of Page Parent selection:
		add_filter('pre_post_status', array(&$this, 'flt_post_status'), 50, 1);
		add_filter('pre_post_parent', array(&$this, 'flt_page_parent'), 50, 1);
			
		// Filtering of terms selection:
		add_action('check_admin_referer', array(&$this, 'act_detect_post_presave')); // abuse referer check to work around a missing hook

		awp_force_set('wp_filter', array(), $setargs, 'pre_object_terms_rs', 50);
		add_filter('pre_object_terms_rs', array(&$this, 'flt_pre_object_terms'), 50, 3);
		
		// added this with WP 2.7 because QuickPress does not call pre_post_category
		if ( awp_ver('2.7-dev') )
			add_filter('pre_option_default_category', array(&$this, 'flt_default_category') );
	}
	
	function act_log_revision_save() {
		$this->revision_save_in_progress = true;
	}
	
	// Filtering of Page Parent selection.  
	// This is a required after-the-fact operation for WP < 2.7 (due to inability to control inclusion of Main Page in UI dropdown)
	// For WP >= 2.7, it is an anti-hacking precaution
	//
	// There is currently no way to explictly restrict or grant Page Association rights to Main Page (root). Instead:
	// 	* Require blog-wide edit_others_pages cap for association of a page with Main
	//  * If an unqualified user tries to associate or un-associate a page with Main Page,
	//	  revert page to previously stored parent if possible. Otherwise set status to "unpublished".
	function flt_post_status ($status) {
		if ( isset($_POST['post_type']) && ( $_POST['post_type'] == 'page' ) && ('autosave' != $_POST['action']) ) {
			// user can't associate / un-associate a page with Main page unless they have edit_pages blog-wide
			global $current_user;

			if ( isset($_POST['post_ID']) ) {
				$post = $this->scoper->data_sources->get_object( 'post', $_POST['post_ID'] );

				// if neither the stored nor selected parent is Main, we have no beef with it		// is it actually saved (if just auto-saved draft, don't provide these exceptions)
				if ( ! empty($_POST['parent_id']) && ( ! empty($post->post_parent) || ( ('publish' != $post->post_status) && ('private' != $post->post_status) ) ) )
					return $status;
				
				$already_published = ( ('publish' == $post->post_status) || ('private' == $post->post_status) );

				// if the page is and was associated with Main Page, don't mess
				if ( empty($_POST['parent_id']) && empty( $post->post_parent ) && $already_published )
					return $status;
			} else
				$already_published = false;
			
			
			if ( is_administrator_rs() )
				$can_associate_main = true;
	
			elseif ( ! scoper_get_option( 'lock_top_pages' ) ) {
				$reqd_caps = array('edit_others_pages');
				$roles = $this->scoper->role_defs->qualify_roles($reqd_caps, '');

				$can_associate_main = array_intersect_key($roles, $current_user->blog_roles);
			} else
				$can_associate_main = false;


			if ( ! $can_associate_main ) {
				// If post was previously published to another parent, allow subsequent page_parent filter to revert it
				if ( $already_published ) {
					if ( ! isset($this->scoper->revert_post_parent) )
						$this->scoper->revert_post_parent = array();
						
					$this->scoper->revert_post_parent[ $_POST['post_ID'] ] = $post->post_parent;
					
					// message display should not be necessary with legitimate WP 2.7+ usage, since the Main Page item is filtered out of UI dropdown as necessary
					if ( ! awp_ver('2.7-dev') && empty($this->scoper->filters_admin_ui->impose_pending_rev) ) {
						$src = $this->scoper->data_sources->get('post');
						$src_edit_url = sprintf($src->edit_url, $_POST['post_ID']);
						
						if ( empty($post->post_parent) )
							$msg = __('The page %s was saved, but the new Page Parent setting was discarded. You do not have permission to disassociate it from the Main Page.', 'scoper');
						else
							$msg = __('The Page Parent setting for %s was reverted to the previously stored value. You do not have permission to associate it with the Main Page.', 'scoper');
						
						$msg = sprintf($msg, '&quot;<a href="' . $src_edit_url . '">' . $_POST['post_title'] . '</a>&quot;');
						update_option("scoper_notice_{$current_user->ID}", $msg );
					}
					
				} elseif ( empty($_POST['parent_id']) && ( ('publish' == $_POST['post_status']) || ('private' == $_POST['post_status']) ) ) {
					// This should only ever happen with WP < 2.7 or if the POST data is manually fudged
					$status = 'draft';

					global $current_user;
					$src = $this->scoper->data_sources->get('post');
					$src_edit_url = sprintf($src->edit_url, $_POST['post_ID']);

					$msg = sprintf(__('The page %s cannot be published because you do not have permission to associate it with the Main Page. Please select a different Page Parent and try again.', 'scoper'), '&quot;<a href="' . $src_edit_url . '">' . $_POST['post_title'] . '</a>&quot;');
					
					update_option("scoper_notice_{$current_user->ID}", $msg );
				}
			}
		}
		return $status;
	}
	
	function flt_detect_revision_save( $post_type ) {
		if ( 'revision' == $post_type )
			$this->revision_save_in_progress = true;
	
		return $post_type;
	}
	
	// Enforce any page parent filtering which may have been dictated by the flt_post_status filter, which executes earlier.
	function flt_page_parent ($parent_id) {
		if ( ! empty($this->revision_save_in_progress) )
			return $parent_id;

		if ( isset($_POST['post_ID']) && isset($this->scoper->revert_post_parent) && isset( $this->scoper->revert_post_parent[ $_POST['post_ID'] ] ) )
			return $this->scoper->revert_post_parent[ $_POST['post_ID'] ];

		// Page parent will not be reverted due to Main Page (un)association with insufficient blog role
		// ... but make sure the selected parent is valid.  Merely an anti-hacking precaution to deal with manually fudged POST data
		if ( $parent_id && isset($_POST['post_ID']) && isset($_POST['post_type']) && ( 'page' == $_POST['post_type']) ) {
			global $wpdb;
			$args = array();
			$args['alternate_reqd_caps'][0] = array('create_child_pages');
		
			$qry_parents = "SELECT DISTINCT ID FROM $wpdb->posts WHERE post_type = 'page'";
			$qry_parents = apply_filters('objects_request_rs', $qry_parents, 'post', 'page', $args);
			$valid_parents = scoper_get_col($qry_parents);
			if ( ! in_array($parent_id, $valid_parents) ) {
				$post = $this->scoper->data_sources->get_object( 'post', $_POST['post_ID'] );
				$parent_id = $post->post_parent;
			}
		}
			
		return $parent_id;
	}
	
	
	function act_detect_post_presave($action) {
		// for post update with no post categories checked, insert a fake category so WP core doesn't force default category
		// (flt_pre_object_terms will first restore any existing postcats dropped due to user's lack of permissions)
		if ( 0 === strpos($action, 'update-post_') ) {
			if ( empty($_POST['post_category']) && ! is_administrator_rs() ) {
				$_POST['post_category'] = array(-1);
			}
		}
	}
	
	function flt_pre_object_terms ($selected_terms, $taxonomy, $args = '') {
		// strip out fake term_id -1 (if applied)
		if ( $selected_terms )
			$selected_terms = array_diff($selected_terms, array(-1));

		if ( is_administrator_rs() || defined('DISABLE_QUERYFILTERS_RS') )
			return $selected_terms;
			
		if ( ! $src = $this->scoper->taxonomies->member_property($taxonomy, 'object_source') )
			return $selected_terms;
		
		if ( ! empty($this->scoper->filters_admin_ui->impose_pending_rev) )
			return $selected_terms;
			
		$orig_selected_terms = $selected_terms;

		if ( ! is_array($selected_terms) )
			$selected_terms = array();

		$user_terms = array(); // will be returned by filter_terms_for_status
		$selected_terms = $this->filter_terms_for_status($taxonomy, $selected_terms, $user_terms);

		if ( $object_id = $this->scoper->data_sources->detect('id', $src) ) {
			if ( ! $selected_terms = $this->reinstate_hidden_terms($taxonomy, $selected_terms) ) {
				if ( $orig_selected_terms )
					return $orig_selected_terms;
			}
		}

		if ( empty($selected_terms) ) {
			// if array empty, insert default term (wp_create_post check is only subverted on updates)
			if ( $option_name = $this->scoper->taxonomies->member_property($taxonomy, 'default_term_option') ) {
				$default_terms = get_option($option_name);
			} else
				$default_terms = 0;

			// but if the default term is not defined or is not in user's subset of usable terms, substitute first available
			if ( $user_terms ) {
				if ( ! is_array($default_terms) )
					$default_terms = (array) $default_terms;
			
				$default_terms = array_intersect($default_terms, $user_terms);

				if ( empty($default_terms) )
					$default_terms = $user_terms[0];
			}

			$selected_terms = (array) $default_terms;
		}

		//rs_errlog('filtered obj terms:');
		//rs_errlog(serialize($selected_terms));
		
		return $selected_terms;
	}
	
	// Removes terms for which the user has edit cap, but not edit_[status] cap
	// If the removed terms are already stored to the post (by a user who does have edit_[status] cap), they will be reinstated by reinstate_hidden_terms
	function filter_terms_for_status($taxonomy, $selected_terms, &$user_terms) {
		if ( ! $src = $this->scoper->taxonomies->member_property($taxonomy, 'object_source') )
			return $selected_terms;

		if ( ! isset($src->statuses) || (count($src->statuses) < 2) )
			return $selected_terms;
		
		$object_id = $this->scoper->data_sources->detect('id', $src);

		if ( ! $status = $this->scoper->data_sources->get_from_http_post('status', $src) )
			$status = $this->scoper->data_sources->get_from_db('status', $src, $object_id);
		
		if ( ! $object_type = $this->scoper->data_sources->detect('type', $src, $object_id) )
			return $selected_terms;

		// make sure _others caps are required only for objects current user doesn't own
		$base_caps_only = false;
		if ( ! empty($src->cols->owner) ) {
			$col_owner = $src->cols->owner;
			if ( $object = $this->scoper->data_sources->get_object($src->name, $object_id) ) {
				global $current_user;
				if ( ! empty($object->$col_owner) && ( $object->$col_owner == $current_user->ID) )
					$base_caps_only = true;
			}
		}
		
		if ( $reqd_caps = $this->scoper->cap_defs->get_matching($src->name, $object_type, OP_EDIT_RS, $status, $base_caps_only) ) {
			$user_terms = $this->scoper->qualify_terms(array_keys($reqd_caps), $taxonomy);
			$selected_terms = array_intersect($selected_terms, $user_terms);
		}

		return $selected_terms;
	}
	
	// Reinstate any object terms which the object already has, but were hidden from the user due to lack of edit caps
	// (if a user does not have edit cap within some term, he can neither add nor remove them from an object)
	function reinstate_hidden_terms($taxonomy, $object_terms) {
		// strip out any fake placeholder IDs which may have been applied
		if ( $object_terms )
			$object_terms = array_diff($object_terms, array(-1));
			
		if ( ! $src = $this->scoper->taxonomies->member_property($taxonomy, 'object_source') )
			return $object_terms;
			
		if ( ! $object_id = $this->scoper->data_sources->get_from_http_post('id', $src) )
			return $object_terms;
		
		if ( ! $object_type = $this->scoper->data_sources->detect('type', $src, $object_id) )
			return $object_terms;
		
		$orig_object_terms = $object_terms;
			
		// make sure _others caps are required only for objects current user doesn't own
		$base_caps_only = false;
		if ( ! empty($src->cols->owner) ) {
			$col_owner = $src->cols->owner;
			if ( $object = $this->scoper->data_sources->get_object($src->name, $object_id) ) {
				global $current_user;
				if ( ! empty($object->$col_owner) && ( $object->$col_owner == $current_user->ID) )
					$base_caps_only = true;
			}
		}
			
		$reqd_caps = array();
		if ( ! empty($src->statuses) ) {
			// determine object's previous status so we know what terms were hidden
			if ( $stored_status = $this->scoper->data_sources->get_from_db('status', $src, $object_id) )
				$reqd_caps = $this->scoper->cap_defs->get_matching($src->name, $object_type, OP_EDIT_RS, $stored_status, $base_caps_only);
		}
		
		// if no status-specific caps are defined, or if this source doesn't define statuses...
		if ( ! $reqd_caps )
			if ( ! $reqd_caps = $this->scoper->cap_defs->get_matching($src->name, $object_type, OP_EDIT_RS, STATUS_ANY_RS, $base_caps_only) )
				return $object_terms;
				
		$user_terms = $this->scoper->qualify_terms(array_keys($reqd_caps), $taxonomy);
		
		// this is a security precaution
		$object_terms = array_intersect($object_terms, $user_terms);
		
		// current object terms which were hidden from user's admin UI must be retained
		if ( $stored_object_terms = $this->scoper->get_terms($taxonomy, UNFILTERED_RS, COL_ID_RS, $object_id) ) {
			$dropped_terms = array_diff($stored_object_terms, $object_terms);
			
			//terms which were dropped due to being filtered out of user UI should be reinstated
			$object_terms = array_merge($object_terms, array_diff($dropped_terms, $user_terms) );
			
			return array_unique($object_terms);
		} else
			return $orig_object_terms;
	}
	
	
	// This handler is meant to fire whenever an object is inserted or updated.
	// If the client does use such a hook, we will force it by calling internally from mnt_create and mnt_edit
	// todo: register hook to optionally accept object in 3rd arg (WP 2.3+ passes post object)
	function mnt_save_object($src_name, $args, $object_id, $object = '') {
		if ( ! empty($this->revision_save_in_progress) ) {
			$this->revision_save_in_progress = false;
			return;
		}

		require_once('filters-admin-save_rs.php');
		scoper_mnt_save_object($src_name, $args, $object_id, $object);
	}
	
	// This handler is meant to fire only on updates, not new inserts
	// todo: register hook to optionally accept object as 3rd arg (WP 2.3+ passes post object)
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
		$defaults = array( 'object_type' => '' );
		$args = array_intersect_key( $defaults, (array) $args );
		extract($args);
	
		if ( ! $object_id )
			return;

		// could defer role/cache maint to speed potential bulk deletion, but script may be interrupted before admin_footer
		$this->item_deletion_aftermath( OBJECT_SCOPE_RS, $src_name, $object_id );

		if ( empty($object_type) )
			$object_type = scoper_determine_object_type($src_name, $object_id, $object);
			
		if ( 'page' == $object_type ) {
			delete_option('scoper_page_children');
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
	
		if ( empty($object_type) )
			if ( $col_type = $this->scoper->data_sources->member_property($src_name, 'cols', 'type') )
				$object_type = ( isset($object->$col_type) ) ? $object->$col_type : '';
			
		if ( empty($object_type) )
			$object_type = scoper_determine_object_type($src_name, $object_id, $object);
		
		if ( $object_type == 'revision' )
			return;
			
		$inserted_objects[$src_name][$object_id] = 1;
		
		if ( 'page' == $object_type ) {
			delete_option('scoper_page_children');
			delete_option('scoper_page_ancestors');
			scoper_flush_cache_groups('get_pages');
		}
	}
	
	function mnt_create_term($taxonomy, $args, $term_id, $term = '') {
		$this->mnt_save_term($taxonomy, $args, $term_id, $term);
		
		scoper_term_cache_flush();
		delete_option( "{$taxonomy}_children_rs" );
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
	// todo: register hook to optionally accept term objectvar in 3rd arg
	function mnt_save_term($taxonomy, $args, $term_id, $term = '') {
		static $saved_terms;
		
		if ( ! isset($saved_terms) )
			$saved_terms = array();
	
		// so this filter doesn't get called by hook AND internally
		if ( isset($saved_terms[$taxonomy][$term_id]) )
			return;
			
		// parent settings can affect the auto-assignment of propagating roles/restrictions
		$set_parent = 0;
		
		if ( $col_parent = $this->scoper->taxonomies->member_property($taxonomy, 'source', 'cols', 'parent') ) {
			$tx_src_name = $this->scoper->taxonomies->member_property($taxonomy, 'source', 'name');
			
			$set_parent = $this->scoper->data_sources->get_from_http_post('parent', $tx_src_name);
		}

		if ( empty($term_id) )
			$term_id = $this->scoper->data_sources->get_from_http_post('id', $tx_src_name);
		
		$saved_terms[$taxonomy][$term_id] = 1;
		
		// Determine whether this object is new (first time this RS filter has run for it, though the object may already be inserted into db)
		$last_parent = 0;
		
		$last_parents = get_option( "scoper_last_{$taxonomy}_parents" );
		if ( ! is_array($last_parents) )
			$last_parents = array();
		
		if ( ! isset($last_parents[$term_id]) ) {
			$is_new_term = true;
			$last_parents = array();
		} else
			$is_new_term = false;
		
		if ( isset( $last_parents[$term_id] ) )
			$last_parent = $last_parents[$term_id];

		if ( ($set_parent != $last_parent) && ($set_parent || $last_parent) ) {
			$last_parents[$term_id] = $set_parent;
			update_option( "scoper_last_{$taxonomy}_parents", $last_parents);
		}
		
		$roles_customized = false;
		if ( ! $is_new_term )
			if ( $custom_role_objects = get_option( "scoper_custom_{$taxonomy}" ) )
				$roles_customized = isset( $custom_role_objects[$term_id] );
		
		// Inherit parent roles / restrictions, but only for new terms, 
		// or if a new parent is set and no roles have been manually assigned to this term
		if ( $is_new_term || ( ! $roles_customized && ($set_parent != $last_parent) ) ) {
			// apply default roles for new term
			if ( $is_new_term )
				scoper_inherit_parent_roles($term_id, TERM_SCOPE_RS, $taxonomy, 0);
			else {
				$args = array( 'inherited_only' => true, 'clear_propagated' => true );
				ScoperAdminLib::clear_restrictions(TERM_SCOPE_RS, $taxonomy, $term_id, $args);
				ScoperAdminLib::clear_roles(TERM_SCOPE_RS, $taxonomy, $term_id, $args);
			}
			
			// apply propagating roles,restrictions from specific parent
			if ( $set_parent ) {
				scoper_inherit_parent_roles($term_id, TERM_SCOPE_RS, $taxonomy, $set_parent);
				scoper_inherit_parent_restrictions($term_id, TERM_SCOPE_RS, $taxonomy, $set_parent);
			}
		} // endif new parent selection (or new object)
		
		scoper_term_cache_flush();
		delete_option( "{$taxonomy}_children_rs" );
	}

	function mnt_delete_term($taxonomy, $args, $term_id) {
		global $wpdb;
	
		if ( ! $term_id )
			return;
		
		// could defer role/cache maint to speed potential bulk deletion, but script may be interrupted before admin_footer
		$this->item_deletion_aftermath( TERM_SCOPE_RS, $taxonomy, $term_id );

		delete_option( "{$taxonomy}_children_rs" );
		
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
		//check_admin_referer('scoper-edit_usergroups');	
	
		global $current_user;
		
		if ( $user_id == $current_user->ID )
			$stored_groups = $current_user->groups;
		else {
			$user = new WP_Scoped_User($user_id, '', array( 'skip_role_merge' => 1 ) );
			$stored_groups = $user->groups;
		}
			
		// by retrieving filtered groups here, user will only modify membership for groups they can administer
		$editable_group_ids = ScoperAdminLib::get_all_groups(FILTERED_RS, COL_ID_RS);
		
		$posted_groups = ( isset($_POST['group']) ) ? $_POST['group'] : array();
		
		if ( ! empty($_POST['groups_csv']) ) {
			if ( $csv_for_item = ScoperAdminLib::agent_ids_from_csv( 'groups_csv', 'groups' ) )
				$posted_groups = array_merge($posted_groups, $csv_for_item);
		}
		
		foreach ($editable_group_ids as $group_id) {
			if( in_array($group_id, $posted_groups) ) { // checkbox is checked
				if( ! isset($stored_groups[$group_id]) )
					ScoperAdminLib::add_group_user($group_id, $user_id);

			} elseif( isset($stored_groups[$group_id]) ) {
				ScoperAdminLib::remove_group_user($group_id, $user_id);
			}
		}
	}

	function act_user_register( $user_id ) {
		// enroll user in default group(s)
		if ( $default_groups = get_option( 'scoper_default_groups' ) )
			foreach ($default_groups as $group_id)
				ScoperAdminLib::add_group_user($group_id, $user_id);
		
		wpp_cache_flush_group("{$role_type}_users_who_can");
		wpp_cache_flush_group("{$role_type}_groups_who_can");

		ScoperAdminLib::sync_wproles();
	}

	// added this with WP 2.7 because QuickPress does not call pre_post_category
	function flt_default_category($default_cat_id) {
		$user_terms = array(); // will be returned by filter_terms_for_status
		$okay_terms = $this->filter_terms_for_status('category', array($default_cat_id), $user_terms);

		if ( ! $okay_terms ) {
			// if the default term is not in user's subset of usable terms, substitute first available
			if ( $user_terms ) {
				if ( ! in_array($default_cat_id, $user_terms) )
					$default_cat_id = $user_terms[0];
			}
		}
		
		return $default_cat_id;
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

function scoper_get_parent_restrictions($obj_or_term_id, $scope, $src_or_tx_name, $parent_id, $object_type = '') {
	global $wpdb, $scoper;
	
	$role_clause = '';
		
	if ( ! $parent_id && (OBJECT_SCOPE_RS == $scope) ) {
		// for default restrictions, need to distinguish between otype-specific roles 
		// (note: this only works w/ RS role type. Default object restrictions are disabled for WP role type because we'd be stuck setting all default restrictions to both post & page.)
		$src = $scoper->data_sources->get($src_or_tx_name);
		if ( ! empty($src->cols->type) ) {
			if ( ! $object_type )
				$object_type = scoper_determine_object_type($src_name, $object_id);
				
			if ( $object_type ) {
				$role_type = SCOPER_ROLE_TYPE;
				$role_defs = $scoper->role_defs->get_matching(SCOPER_ROLE_TYPE, $src_or_tx_name, $object_type);
				if ( $role_names = scoper_role_handles_to_names( array_keys($role_defs) ) )
					$role_clause = "AND role_type = '$role_type' AND role_name IN ('" . implode("', '", $role_names) . "')";
			}
		}
	}
		
	// Since this is a new object, propagate restrictions from parent (if any are marked for propagation)
	$qry = "SELECT * FROM $wpdb->role_scope_rs WHERE topic = '$scope' AND require_for IN ('children', 'both') $role_clause AND src_or_tx_name = '$src_or_tx_name' AND obj_or_term_id = '$parent_id' ORDER BY role_type, role_name";
	$results = scoper_get_results($qry);
	return $results;
}

function scoper_inherit_parent_restrictions($obj_or_term_id, $scope, $src_or_tx_name, $parent_id, $object_type = '', $parent_restrictions = '') {
	global $scoper;

	if ( ! $parent_restrictions )
		$parent_restrictions = scoper_get_parent_restrictions($obj_or_term_id, $scope, $src_or_tx_name, $parent_id); 
	
	if ( $parent_restrictions ) {
		$role_assigner = init_role_assigner();

		$role_defs = $scoper->role_defs->get_matching(SCOPER_ROLE_TYPE, $src_or_tx_name, $object_type);
		
		foreach ( $parent_restrictions as $row ) {
			$role_handle = scoper_get_role_handle($row->role_name, $row->role_type);
			if ( isset($role_defs[$role_handle]) ) {
				$inherited_from = ( $row->obj_or_term_id ) ? $row->requirement_id : 0;
			
				$args = array ( 'is_auto_insertion' => true, 'inherited_from' => $inherited_from );
				$role_assigner->insert_role_restrictions ($scope, $row->max_scope, $role_handle, $src_or_tx_name, $obj_or_term_id, 'both', $row->requirement_id, $args);
				$did_insert = true;
			}
		}
		
		if ( ! empty($did_insert) )
			$role_assigner->role_restriction_aftermath( $scope );
	}
}

function scoper_get_parent_roles($obj_or_term_id, $scope, $src_or_tx_name, $parent_id, $object_type = '') {
	global $wpdb, $scoper;

	$role_clause = '';
		
	if ( ! $parent_id && (OBJECT_SCOPE_RS == $scope) ) {
		// for default roles, need to distinguish between otype-specific roles 
		// (note: this only works w/ RS role type. Default object roles are disabled for WP role type because we'd be stuck assigning all default roles to both post & page.)
		$src = $scoper->data_sources->get($src_or_tx_name);
		if ( ! empty($src->cols->type) ) {
			if ( ! $object_type )
				$object_type = scoper_determine_object_type($src_name, $object_id);
				
			if ( $object_type ) {
				$role_type = SCOPER_ROLE_TYPE;
				$role_defs = $scoper->role_defs->get_matching(SCOPER_ROLE_TYPE, $src_or_tx_name, $object_type);
				if ( $role_names = scoper_role_handles_to_names( array_keys($role_defs) ) )
					$role_clause = "AND role_type = '$role_type' AND role_name IN ('" . implode("', '", $role_names) . "')";
			}
		}
	}
	
	// Since this is a new object, propagate roles from parent (if any are marked for propagation)
	$qry = "SELECT * FROM $wpdb->user2role2object_rs WHERE scope = '$scope' AND assign_for IN ('children', 'both') $role_clause AND src_or_tx_name = '$src_or_tx_name' AND obj_or_term_id = '$parent_id' ORDER BY role_type, role_name";
	$results = scoper_get_results($qry);
	return $results;
}

function scoper_inherit_parent_roles($obj_or_term_id, $scope, $src_or_tx_name, $parent_id, $object_type = '', $parent_roles = '') {
	global $scoper;

	if ( ! $parent_roles )
		$parent_roles = scoper_get_parent_roles($obj_or_term_id, $scope, $src_or_tx_name, $parent_id, $object_type); 

	if ( $parent_roles ) {
		$role_assigner = init_role_assigner();
		
		if ( OBJECT_SCOPE_RS == $scope )
			$role_defs = $scoper->role_defs->get_matching(SCOPER_ROLE_TYPE, $src_or_tx_name, $object_type);
		else
			$role_defs = $scoper->role_defs->get_all();
			
		$role_handles = array_keys($role_defs);
		
		$role_bases = array();
		if ( GROUP_ROLES_RS )
			$role_bases []= ROLE_BASIS_GROUPS;
		if ( USER_ROLES_RS )
			$role_bases []= ROLE_BASIS_USER;
		
		foreach ( $role_bases as $role_basis ) {
			$col_ug_id = ( ROLE_BASIS_GROUPS == $role_basis ) ? 'group_id' : 'user_id';

			foreach ( $role_handles as $role_handle ) {
				$agents = array();
				$inherited_from = array();
				
				foreach ( $parent_roles as $row ) {
					$ug_id = $row->$col_ug_id;
					$row_role_handle = scoper_get_role_handle($row->role_name, $row->role_type);
					if ( $ug_id && ($row_role_handle == $role_handle) ) {

						$agents[$ug_id] = 'both';
					
						// Default roles for new objects are stored as direct assignments with no inherited_from setting.
						// 1) to prevent them from being cleared when page parent is changed with no custom role settings in place
						// 2) to prevent them from being cleared when the default for new pages is changed
						if ( $row->obj_or_term_id )
							$inherited_from[$ug_id] = $row->assignment_id;
					}
				}
				
				if ( $agents ) {
					$args = array ( 'is_auto_insertion' => true, 'inherited_from' => $inherited_from );
					$role_assigner->insert_role_assignments ($scope, $role_handle, $src_or_tx_name, $obj_or_term_id, $col_ug_id, $agents, array(), $args);
				}
			}
		}
	}
}

?>