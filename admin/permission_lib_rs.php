<?php

	function user_can_admin_role_rs($role_handle, $item_id, $src_name = '', $object_type = '', $user = '' ) {
		if ( is_user_administrator_rs() )
			return true;

		global $scoper;
			
		static $require_blogwide_editor;
			
		if ( ! isset($require_blogwide_editor) )
			$require_blogwide_editor = scoper_get_option('role_admin_blogwide_editor_only');
			
		if ( 'admin' == $require_blogwide_editor )
			return false;  // User Admins already returned true
		
		if ( ( 'admin_content' == $require_blogwide_editor ) && ! is_content_administrator_rs() )
			return false;

		static $role_ops;

		if ( ! isset($role_ops) )
			$role_ops = array();
		
		if ( ! isset($role_ops[$role_handle]) )
			$role_ops[$role_handle] = $scoper->role_defs->get_role_ops($role_handle);

		// user can't view or edit role assignments unless they have all rolecaps
		// however, if this is a new post, allow read role to be assigned even if contributor doesn't have read_private cap blog-wide
		if ( $item_id || ( $role_ops[$role_handle] != array( 'read' => 1 ) ) ) {
			static $reqd_caps;
			
			if ( ! isset($reqd_caps) )
				$reqd_caps = array();
				
			if ( ! isset($reqd_caps[$role_handle]) )
				$reqd_caps[$role_handle] = $scoper->role_defs->role_caps[$role_handle];

			if ( ! awp_user_can(array_keys($reqd_caps[$role_handle]), $item_id) )
				return false;

			
			// are we also applying the additional requirement (based on RS Option setting) that the user is a blog-wide editor?
			if ( $require_blogwide_editor ) {
				static $can_edit_blogwide;

				if ( ! isset($can_edit_blogwide) )
					$can_edit_blogwide = array();
					
				if ( ! isset($can_edit_blogwide[$src_name][$object_type]) )
					$can_edit_blogwide[$src_name][$object_type] = user_can_edit_blogwide_rs($src_name, $object_type, array( 'require_others_cap' => true ) );
	
				if ( ! $can_edit_blogwide[$src_name][$object_type] )
					return false;
			}
		}
		
		return true;
	}
	
	function user_can_admin_object_rs($src_name, $object_type, $object_id = false, $any_obj_role_check = false, $user = '' ) {
		if ( is_content_administrator_rs() )
			return true;

		global $scoper;
		
		if ( ! is_object($user) ) {
			global $current_user;
			$user = $current_user;
		}
			
		if ( $new_object = ! $object_id && ( false !== $object_id ) ) {
			//for new objects, default to requiring caps for 1st defined status (=published for posts)
			$src = $scoper->data_sources->get($src_name);
			reset ($src->statuses);
			$status_name = key($src->statuses);
		} else {
			$status_name = $scoper->data_sources->detect('status', $src_name, $object_id);
		}
		
		// insert_role_assignments passes array from get_role_attributes
		if ( is_array($object_type) ) {
			if ( count($object_type) == 1 )
				$object_type = reset($object_type);
			else
				// only WP roles should ever have multiple sources / otypes
				$object_type = $scoper->data_sources->get_from_db('type', $src_name, $object_id);
		}

		if ( ! $new_object && isset($src->reqd_caps[OP_ADMIN_RS][$object_type][$status_name]) )
			$reqd_caps = $src->reqd_caps[OP_ADMIN_RS][$object_type][$status_name];
		else {
			$base_caps_only = $new_object;
			$admin_caps = $scoper->cap_defs->get_matching($src_name, $object_type, OP_ADMIN_RS, $status_name, $base_caps_only);
			$delete_caps = $scoper->cap_defs->get_matching($src_name, $object_type, OP_DELETE_RS, $status_name, $base_caps_only);
			$reqd_caps = array_merge( array_keys($admin_caps), array_keys($delete_caps) );
		}

		if ( ! $reqd_caps )
			return true;	// apparantly this src/otype has no admin caps, so no restriction to apply
			
		// pass this parameter the ugly way because I'm afraid to include it in user_has_cap args array
		// Normally we want to disregard "others" cap requirements if a role is assigned directly for an object
		// This is an exception - we need to retain a "delete_others" cap requirement in case it is the
		// distinguishing cap of an object administrator

		$scoper->cap_interceptor->require_full_object_role = true;
		
		if ( defined( 'RVY_VERSION' ) ) {
			global $revisionary;
			$revisionary->skip_revision_allowance = true;
		}
			
		$return = awp_user_can($reqd_caps, $object_id);
		
		$scoper->cap_interceptor->require_full_object_role = false;
		
		if ( defined( 'RVY_VERSION' ) )
			$revisionary->skip_revision_allowance = false;

		if ( ! $return && ! $object_id && $any_obj_role_check ) {
			// No object ID was specified, and current user does not have the cap blog-wide.  Credit user for capability on any individual object.
			
			$admin_caps = $scoper->cap_defs->get_matching($src_name, $object_type, OP_ADMIN_RS, STATUS_ANY_RS);
			$delete_caps = $scoper->cap_defs->get_matching($src_name, $object_type, OP_DELETE_RS, STATUS_ANY_RS);
			
			if ( $reqd_caps = array_merge( array_keys($admin_caps), array_keys($delete_caps) ) ) {
				if ( ! defined('DISABLE_QUERYFILTERS_RS') && $scoper->cap_interceptor->user_can_for_any_object( $reqd_caps ) )
					$return = true;
			}
		}
		
		return $return;
	}
	
	function user_can_admin_terms_rs($taxonomy = '', $term_id = '', $user = '') {
		if ( is_user_administrator_rs() )
			return true;

		global $scoper;
			
		if ( ! is_object($user) ) {
			global $current_user;
			$user = $current_user;
		}
		
		$qualifying_caps = array();
		
		$taxonomies = array();
		foreach ( $scoper->cap_defs->get_all() as $cap_name => $capdef )
			if ( (OP_ADMIN_RS == $capdef->op_type) && $scoper->taxonomies->is_member($capdef->object_type) ) {
				if ( ! $taxonomy || ( $capdef->object_type == $taxonomy ) ) {
					$qualifying_caps[$cap_name] = 1;
					$taxonomies[$capdef->object_type] = 1;
				}
			}

		if ( empty($qualifying_caps) )
			return false;

		// does current user have any blog-wide admin caps for term admin?
		$qualifying_roles = $scoper->role_defs->qualify_roles(array_flip($qualifying_caps), SCOPER_ROLE_TYPE);
		
		if ( $user_blog_roles = array_intersect_key( $user->blog_roles[ANY_CONTENT_DATE_RS], $qualifying_roles) ) {
			if ( $term_id ) {
				$strict_terms = $scoper->get_restrictions(TERM_SCOPE_RS, $taxonomy);
			
				foreach ( array_keys($user_blog_roles) as $role_handle ) {
					// can't blend in blog role if term requires term role assignment
					if ( isset($strict_terms['unrestrictions'][$role_handle][$term_id])
					|| ( ! is_array($strict_terms['unrestrictions'][$role_handle]) && ! isset($strict_terms['restrictions'][$role_handle][$term_id]) ) )
						return true;
				}
			} else {
				// todo: more precision by checking whether ANY terms are non-strict for the qualifying role(s)
				return true;
			}
		}
		
		// does current user have any term-specific admin caps for term admin?
		if ( $taxonomies ) {
			foreach ( array_keys($taxonomies) as $taxonomy ) {
				if ( ! isset($current_user->term_roles[$taxonomy]) )
					$user->get_term_roles_daterange($taxonomy);		// call daterange function populate term_roles property - possible perf enhancement for subsequent code even though we don't conider content_date-limited roles here
				
				if ( ! empty($user->term_roles[$taxonomy][ANY_CONTENT_DATE_RS]) ) {
					foreach ( array_keys( $user->term_roles[$taxonomy][ANY_CONTENT_DATE_RS] ) as $role_handle ) {
						if ( ! empty($scoper->role_defs->role_caps[$role_handle]) ) {
							if ( array_intersect_key($qualifying_caps, $scoper->role_defs->role_caps[$role_handle]) ) {
								if ( ! $term_id || in_array($term_id, $user->term_roles[$taxonomy][ANY_CONTENT_DATE_RS][$role_handle]) )
									return true;
							}
						}
					}
				}
			}
		} // endif any taxonomies have cap defined
	} // end function
	
	
	function user_can_edit_blogwide_rs( $src_name = '', $object_type = '', $args = '' ) {
		if ( is_administrator_rs($src_name) )
			return true;

		global $scoper, $current_user;
		
		$defaults = array( 'qualifying_ops' => array( 'edit' ),  'require_others_cap' => false, 'status' => '' );
		$args = array_merge( $defaults, (array) $args );
		extract($args);
		
		if ( ! is_array($qualifying_ops) )
			$qualifying_ops = (array) $qualifying_ops;
		

		// if no admin/delete/publish caps are defined for this object type, accept blog-wide possession of an edit cap instead 
		if ( ( array('edit') !== $qualifying_ops ) && ! array_intersect( array_keys($role_ops), $qualifying_ops ) ) {
			foreach ( $qualifying_ops as $op_type ) {
				if ( $cap_defs = $scoper->cap_defs->get_matching($src_name, $object_type, $op_type, $status) )
					break;
			}
			
			if ( ! $cap_defs )
				$qualifying_ops = array( 'edit' );
		}
		
		
		$op_match = false;
		$others_cap_defined = false;

		foreach ( $qualifying_ops as $op_type ) {
			$cap_defs = $scoper->cap_defs->get_matching($src_name, $object_type, $op_type);
			
			foreach ( $cap_defs as $cap_name => $cap_def ) {
				
		    	if ( $require_others_cap ) {
				   	$is_others_cap = ! empty($cap_def->attributes) && in_array('others', $cap_def->attributes);
				   	$others_cap_defined = $others_cap_defined || $is_others_cap;
				}
				   
				// is this capability in any of the current user's roles?
				foreach ( array_keys( agp_array_flatten( $current_user->blog_roles, false ) ) as $role_handle ) {				// okay to include content_date-limited terms here since it's only used for secondary measures such as the enforcement of Limited Editing Elements
					
					if ( ! empty( $scoper->role_defs->role_caps[$role_handle][$cap_name] ) ) {
						if ( ! $require_others_cap || $is_others_cap )
							return true;
						else
							$op_match = true;
		 			}
				}

			}
		}	
			

		// We matched the op type but not others requirement.  If no others caps are defined for this object type, call it good.
		if ( $op_match  && ! $others_cap_defined )
			return true;
	}


?>