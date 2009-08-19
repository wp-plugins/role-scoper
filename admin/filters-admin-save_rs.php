<?php

if( basename(__FILE__) == basename($_SERVER['SCRIPT_FILENAME']) )
	die();

	// called by ScoperAdminFilters::mnt_save_object
	// This handler is meant to fire whenever an object is inserted or updated.
	// If the client does use such a hook, we will force it by calling internally from mnt_create and mnt_edit
	function scoper_mnt_save_object($src_name, $args, $object_id, $object = '') {
		global $scoper;

		static $saved_objects;
		
		if ( ! isset($saved_objects) )
			$saved_objects = array();

		if ( isset($saved_objects[$src_name][$object_id]) )
			return;

		$defaults = array( 'object_type' => '' );
		$args = array_intersect_key( $defaults, (array) $args );
		extract($args);
			
		if ( empty($object_type) )
			$object_type = scoper_determine_object_type($src_name, $object_id, $object);

		$saved_objects[$src_name][$object_id] = 1;

		// parent settings can affect the auto-assignment of propagating roles/restrictions
		$set_parent = 0;
		if ( $col_parent = $scoper->data_sources->member_property($src_name, 'cols', 'parent') )
			if ( isset($_POST[$col_parent]) ) 
				$set_parent = $_POST[$col_parent];
		
		// Determine whether this object is new (first time this RS filter has run for it, though the object may already be inserted into db)
		if ( 'post' == $src_name ) {
			$last_parent = get_post_meta($object_id, '_scoper_last_parent', true);
			$is_new_object = ( '' === $last_parent );
			
			// update last_parent meta to indicate non-new object, even if posts aren't configured to define parent
			if ( $is_new_object || ($set_parent != $last_parent) )
				update_post_meta($object_id, '_scoper_last_parent', (int) $set_parent);
		} else {
			$last_parent = 0;
		
			// for other data sources, we have to assume object is new unless it has a role or restriction stored already.
			$is_new_object = true;
			
			$qry = "SELECT assignment_id FROM $wpdb->user2role2object_rs WHERE scope = 'object' AND src_or_tx_name = '$src_name' AND obj_or_term_id = '$object_id'";
			if ( $assignment_ids = scoper_get_col($qry) )
				$is_new_object = false;
			else {	
				$qry = "SELECT requirement_id FROM $wpdb->role_scope_rs WHERE topic = 'object' AND src_or_tx_name = '$src_name' AND obj_or_term_id = '$object_id'";
				if ( $requirement_ids = scoper_get_col($qry) )
					$is_new_object = false;
			}
			
			if ( $col_parent ) {
				if ( ! $is_new_object ) {
					$last_parents = get_option( "scoper_last_parents_{$src_name}");
					if ( ! is_array($last_parents) )
						$last_parents = array();
					
					if ( isset( $last_parents[$object_id] ) )
						$last_parent = $last_parents[$object_id];
				}
			
				if ( ($set_parent != $last_parent) && ($set_parent || $last_parent) ) {
					$last_parents[$object_id] = $set_parent;
					update_option( "scoper_last_parents_{$src_name}", $last_parents);
				}
			}
		}
		
		// used here and in UI display to enumerate role definitions
		$role_defs = $scoper->role_defs->get_matching(SCOPER_ROLE_TYPE, $src_name, $object_type);
		$role_handles = array_keys($role_defs);
		

		// Were roles / restrictions previously customized by direct edit?
		if ( 'post' == $src_name )
			$roles_customized = ( $is_new_object ) ? false : get_post_meta($object_id, '_scoper_custom', true);
		else {
			$roles_customized = false;
			if ( ! $is_new_object )
				if ( $custom_role_objects = get_option( "scoper_custom_{$src_name}" ) )
					$roles_customized = isset( $custom_role_objects[$object_id] );
				
			if ( ! is_array($custom_role_objects) )
				$custom_role_objects = array();
		}
		
		// Were roles / restrictions custom-edited just now?
		if ( ! defined('XMLRPC_REQUEST') ) {
			$new_role_settings = false;

			// Now determine if roles/restrictions have changed since the edit form load
			foreach ( $role_defs as $role_handle => $role_def) {
				$role_code = 'r' . array_search($role_handle, $role_handles);
				if ( ! empty( $_POST["{$role_code}u_csv"] ) || ! empty( $_POST["{$role_code}g_csv"] ) || ! empty( $_POST["p_{$role_code}u_csv"] ) || ! empty( $_POST["p_{$role_code}g_csv"] ) ) {
					$new_role_settings = true;
					break;
				}

				// did user change roles?
				$compare_vars = array( 
				"{$role_code}u" => "last_{$role_code}u", 
				"{$role_code}g" => "last_{$role_code}g"
				);
				
				if ( $col_parent ) {
					$compare_vars ["p_{$role_code}u"] = "last_p_{$role_code}u";
					$compare_vars ["p_{$role_code}g"] = "last_p_{$role_code}g";
				}
				
				foreach ( $compare_vars as $var => $var_last ) {
					$agents = ( isset($_POST[$var]) ) ? $_POST[$var] : array();
					$last_agents = ( ! empty($_POST[$var_last]) ) ? explode("~", $_POST[$var_last]) : array();
					
					sort($agents);
					sort($last_agents);

					if ( $last_agents != $agents ) {
						$new_role_settings = true;
						break 2;
					}
				}
				
				// did user change restrictions?
				$compare_vars = array(
				"objscope_{$role_code}" => "last_objscope_{$role_code}"
				);
				
				if ( $col_parent )
					$compare_vars ["objscope_children_{$role_code}"] = "last_objscope_children_{$role_code}";
				
				foreach ( $compare_vars as $var => $var_last ) {
					$val = ( isset($_POST[$var]) ) ? $_POST[$var] : 0;
					$last_val = ( isset($_POST[$var_last]) ) ? $_POST[$var_last] : 0;
					
					if ( $val != $last_val ) {
						$new_role_settings = true;
						break 2;
					}
				}
			}
		
			if ( $new_role_settings ) {
				if ( 'post' == $src_name )
					update_post_meta($object_id, '_scoper_custom', true);
				else {
					$custom_role_objects [$object_id] = true;
					update_option( "scoper_custom_{$src_name}", $custom_role_objects );
				}
			}
		} // endif user-modified roles/restrictions weren't already saved
		
		
		// Inherit parent roles / restrictions, but only for new objects, 
		// or if a new parent is set and roles haven't been manually edited for this object
		if ( ! $roles_customized && ! $new_role_settings && ( $is_new_object || ($set_parent != $last_parent) ) ) {
			// apply default roles for new object
			if ( $is_new_object )
				scoper_inherit_parent_roles($object_id, OBJECT_SCOPE_RS, $src_name, 0, $object_type);
			else {
				$args = array( 'inherited_only' => true, 'clear_propagated' => true );
				ScoperAdminLib::clear_restrictions(OBJECT_SCOPE_RS, $src_name, $object_id, $args);
				ScoperAdminLib::clear_roles(OBJECT_SCOPE_RS, $src_name, $object_id, $args);
			}
			
			// apply propagating roles,restrictions from specific parent
			if ( $set_parent ) {
				scoper_inherit_parent_roles($object_id, OBJECT_SCOPE_RS, $src_name, $set_parent, $object_type);
				scoper_inherit_parent_restrictions($object_id, OBJECT_SCOPE_RS, $src_name, $set_parent, $object_type);
			}
		} // endif new parent selection (or new object)

		
		// Roles/Restrictions were just edited manually, so store role settings (which may contain default roles even if no manual settings were made)
		if ( $new_role_settings && ! empty($_POST['rs_object_roles']) && ( empty($_POST['action']) || ( 'autosave' != $_POST['action'] ) ) && ! defined('XMLRPC_REQUEST') ) {
			$role_assigner = init_role_assigner();
		
			$require_blogwide_editor = scoper_get_option('role_admin_blogwide_editor_only');
			if ( is_administrator_rs() || ( 'admin' != $require_blogwide_editor ) ) {
			
				if ( $object_type && $scoper->admin->user_can_admin_object($src_name, $object_type, $object_id) ) {
					// store any object role (read/write/admin access group) selections
					$role_bases = array();
					if ( GROUP_ROLES_RS )
						$role_bases []= ROLE_BASIS_GROUPS;
					if ( USER_ROLES_RS )
						$role_bases []= ROLE_BASIS_USER;
					
					$set_roles = array_fill_keys( $role_bases, array() );
					$set_restrictions = array();
					
					$default_restrictions = $scoper->get_default_restrictions(OBJECT_SCOPE_RS);
					
					foreach ( $role_defs as $role_handle => $role_def) {
						if ( ! isset($role_def->valid_scopes[OBJECT_SCOPE_RS]) )
							continue;
	
						$role_code = 'r' . array_search($role_handle, $role_handles);
							
						$role_ops = $scoper->role_defs->get_role_ops($role_handle);
				
						// user can't view or edit role assignments unless they have all rolecaps
						// however, if this is a new post, allow read role to be assigned even if contributor doesn't have read_private cap blog-wide
						if ( ! is_administrator_rs() && ( ! $is_new_object || $role_ops != array( 'read' => 1 ) ) ) {
							$reqd_caps = $scoper->role_defs->role_caps[$role_handle];
							if ( ! awp_user_can(array_keys($reqd_caps), $object_id) )
								continue;
		
							// a user must have a blog-wide edit cap to modify editing role assignments (even if they have Editor role assigned for some current object)
							if ( isset($role_ops[OP_EDIT_RS]) || isset($role_ops[OP_ASSOCIATE_RS]) ) 
								if ( $require_blogwide_editor ) {
									$required_cap = ( 'page' == $object_type ) ? 'edit_others_pages' : 'edit_others_posts';
									
									global $current_user;
									if ( empty( $current_user->allcaps[$required_cap] ) )
										continue;
								}
						}
		
						foreach ( $role_bases as $role_basis ) {
							$id_prefix = $role_code . substr($role_basis, 0, 1);
							
							$for_entity_agent_ids = (isset( $_POST[$id_prefix]) ) ? $_POST[$id_prefix] : array();
							$for_children_agent_ids = ( isset($_POST["p_$id_prefix"]) ) ? $_POST["p_$id_prefix"] : array();
							
	
							// handle csv-entered agent names
							$csv_id = "{$id_prefix}_csv";
							
							if ( $csv_for_item = ScoperAdminLib::agent_ids_from_csv( $csv_id, $role_basis ) )
								$for_entity_agent_ids = array_merge($for_entity_agent_ids, $csv_for_item);
							
							if ( $csv_for_children = ScoperAdminLib::agent_ids_from_csv( "p_$csv_id", $role_basis ) )
								$for_children_agent_ids = array_merge($for_children_agent_ids, $csv_for_children);
								
							$set_roles[$role_basis][$role_handle] = array();
		
							if ( $for_both_agent_ids = array_intersect($for_entity_agent_ids, $for_children_agent_ids) )
								$set_roles[$role_basis][$role_handle] = $set_roles[$role_basis][$role_handle] + array_fill_keys($for_both_agent_ids, ASSIGN_FOR_BOTH_RS);
							
							if ( $for_entity_agent_ids = array_diff( $for_entity_agent_ids, $for_children_agent_ids ) )
								$set_roles[$role_basis][$role_handle] = $set_roles[$role_basis][$role_handle] + array_fill_keys($for_entity_agent_ids, ASSIGN_FOR_ENTITY_RS);
					
							if ( $for_children_agent_ids = array_diff( $for_children_agent_ids, $for_entity_agent_ids ) )
								$set_roles[$role_basis][$role_handle] = $set_roles[$role_basis][$role_handle] + array_fill_keys($for_children_agent_ids, ASSIGN_FOR_CHILDREN_RS);
								
							
						}
						
						if ( isset($default_restrictions[$src_name][$role_handle]) ) {
							$max_scope = BLOG_SCOPE_RS;
							$item_restrict = empty($_POST["objscope_{$role_code}"]);
							$child_restrict = empty($_POST["objscope_children_{$role_code}"]);
						} else {
							$max_scope = OBJECT_SCOPE_RS;
							$item_restrict = ! empty($_POST["objscope_{$role_code}"]);
							$child_restrict = ! empty($_POST["objscope_children_{$role_code}"]);
						}
						
						$set_restrictions[$role_handle] = array( 'max_scope' => $max_scope, 'for_item' => $item_restrict, 'for_children' => $child_restrict );
					}
					
					$args = array('implicit_removal' => true, 'object_type' => $object_type);
					
					// don't record first-time storage of default roles as custom settings
					if ( ! $new_role_settings )
						$args['is_auto_insertion'] = true;
					
					// Add or remove object role restrictions as needed (no DB update in nothing has changed)
					$role_assigner->restrict_roles(OBJECT_SCOPE_RS, $src_name, $object_id, $set_restrictions, $args );
					
					// Add or remove object role assignments as needed (no DB update in nothing has changed)
					foreach ( $role_bases as $role_basis )
						$role_assigner->assign_roles(OBJECT_SCOPE_RS, $src_name, $object_id, $set_roles[$role_basis], $role_basis, $args );
				} // endif object type is known and user can admin this object
			} // end if current user is an Administrator, or doesn't need to be
		} //endif roles were manually edited by user (and not autosave)
		
		if ( 'page' == $object_type ) {
			delete_option('scoper_page_ancestors');
			scoper_flush_cache_groups('get_pages');
		}
		
		// need this to make metabox captions update in first refresh following edit & save
		if ( is_admin() && isset( $scoper->filters_admin_item_ui ) )
			$scoper->filters_admin_item_ui->act_tweak_metaboxes();
		
		// possible TODO: remove other conditional calls since we're doing it here on every save
		scoper_flush_results_cache();
	}

?>