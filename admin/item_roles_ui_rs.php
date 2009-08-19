<?php
if( basename(__FILE__) == basename($_SERVER['SCRIPT_FILENAME']) )
	die();

require_once('role_assignment_lib_rs.php');

class ScoperItemRolesUI {
	var $scoper;
	
	var $loaded_src_name;
	var $loaded_object_type;
	var $loaded_object_id;
	
	var $no_object_roles;
	var $indicate_blended_roles;
	var $do_propagation_cboxes;
	
	var $role_handles;
	var $all_groups;
	var $group_members = array();
	var $agent_captions;
	var $agent_captions_plural;
	var $all_agents;
	var $eligible_agent_ids;
	var $current_roles;
	var $blog_term_roles;
	var $object_strict_roles;
	var $child_strict_roles;
	
	var $drew_objroles_marker;
	
	function ScoperItemRolesUI() {
		global $scoper;
		$this->scoper = $scoper;
	}
	
	function load_roles($src_name, $object_type, $object_id) {
		// TODO: any more changes to deal with bulk edit?
		if ( strpos( $_SERVER['SCRIPT_NAME'], 'p-admin/edit.php') || strpos( $_SERVER['SCRIPT_NAME'], 'p-admin/edit-pages.php') )
			return;
	
		if ( ! scoper_get_otype_option('use_object_roles', $src_name, $object_type) )
			return;
			
		if ( ! $src = $this->scoper->data_sources->get($src_name) )
			return;
			
		$this->loaded_src_name = $src_name;
		$this->loaded_object_type = $object_type;
		$this->loaded_object_id = $object_id;
			
		$this->indicate_blended_roles = scoper_get_option( 'indicate_blended_roles' );
		
		$this->all_agents = array();
		$this->agent_captions = array();
		$this->agent_captions_plural = array();
		$this->eligible_agent_ids = array();
		
		if ( GROUP_ROLES_RS ) {
			$this->all_groups = ScoperAdminLib::get_all_groups(UNFILTERED_RS);
			
			if ( ! empty( $this->all_groups) ) {
				$this->agent_captions [ROLE_BASIS_GROUPS] = __('Group', 'scoper');
				$this->agent_captions_plural [ROLE_BASIS_GROUPS] = __('Groups', 'scoper');
			
				$this->all_agents[ROLE_BASIS_GROUPS] = $this->all_groups;
				$this->all_agents[ROLE_BASIS_GROUPS] = $this->all_groups;
			}
		}
		
		if ( USER_ROLES_RS ) {
			$this->agent_captions [ROLE_BASIS_USER] = __('User', 'scoper');
			$this->agent_captions_plural [ROLE_BASIS_USER] = __('Users', 'scoper');
			
			// all users are eligible for a reading role assignment
			$this->all_agents[ROLE_BASIS_USER] = $this->scoper->users_who_can( '', COLS_ID_DISPLAYNAME_RS);
			
			//users eligible for an editing role assignments are those who have the basic edit cap via taxonomy or blog role
			if ( scoper_get_otype_option( 'limit_object_editors', $src_name, $object_type ) ) {
				// Limit eligible page contribs/editors based on blog ownership of "edit_posts"
				// Otherwise, since pages are generally not categorized, only Blog Editors and Admins are eligible for object role ass'n
				// It's more useful to exclude Blog Subscribers while including all others
				$role_object_type = ( 'page' == $object_type ) ? 'post' : $object_type;
				
				$reqd_caps = $this->scoper->cap_defs->get_matching($src_name, $role_object_type, OP_EDIT_RS, '', BASE_CAPS_RS);	// status-specific and 'others' caps will not be returned
				$args = array( 'ignore_strict_terms' => true, 'ignore_group_roles' => true, 'skip_object_roles' => true );
				$this->eligible_agent_ids[ROLE_BASIS_USER][OP_EDIT_RS] = $this->scoper->users_who_can( array_keys($reqd_caps), COL_ID_RS, '',  0, $args );
			}
		}

		// note: if object_id = 0, default roles will be retrieved
		$get_defaults = ! $object_id;
		$obj_roles = array();
		
		$role_defs = $this->scoper->role_defs->get_matching(SCOPER_ROLE_TYPE, $src_name, $object_type);
		$this->role_handles = array_keys($role_defs);
		
		// for default roles, distinguish between various object types
		$filter_role_handles = ( $object_id ) ? '' : array_keys($role_defs);
		
		if ( GROUP_ROLES_RS )
			$this->current_roles[ROLE_BASIS_GROUPS] = ScoperRoleAssignments::organize_assigned_roles(OBJECT_SCOPE_RS, $src_name, $object_id, $filter_role_handles, ROLE_BASIS_GROUPS, $get_defaults);
			
		if ( USER_ROLES_RS )
			$this->current_roles[ROLE_BASIS_USER] = ScoperRoleAssignments::organize_assigned_roles(OBJECT_SCOPE_RS, $src_name, $object_id, $filter_role_handles, ROLE_BASIS_USER, $get_defaults);

		$this->blog_term_roles = array();
		
		// Pull object and blog/term role assignments for all roles
		// Do this first so contained / containing roles can be accounted for in UI
		foreach ($role_defs as $role_handle => $role_def) {
			if ( $this->indicate_blended_roles && isset($role_def->valid_scopes[OBJECT_SCOPE_RS])  ) {
				
				// might need to check term/blog assignment of a different role to reflect object's current status
				if ( ! empty( $role_def->other_scopes_check_role) && ! empty($src->cols->status) ) {
					$status = $this->scoper->data_sources->detect('status', $src, $object_id);
				
					if ( isset($role_def->other_scopes_check_role[$status]) ) 
						$blog_term_role_handle = $role_def->other_scopes_check_role[$status];
					elseif ( isset($role_def->other_scopes_check_role['']) )
						$blog_term_role_handle = $role_def->other_scopes_check_role[''];
					else
						$blog_term_role_handle = $role_handle;
				} else
					$blog_term_role_handle = $role_handle;
				
				$this_args = array('skip_object_roles' => true, 'object_type' => $object_type, 'ignore_group_roles' => true );
				$this->blog_term_roles[ROLE_BASIS_USER][$role_handle] = $this->scoper->users_who_can($blog_term_role_handle, COL_ID_RS, $src_name, $object_id, $this_args );
				
				$this->blog_term_roles[ROLE_BASIS_GROUPS][$role_handle] = $this->scoper->groups_who_can($blog_term_role_handle, COL_ID_RS, $src_name, $object_id, $this_args );
			}
		}

		$this->do_propagation_cboxes = ( ! empty($src->cols->parent) && ! $this->scoper->data_sources->member_property($src_name, 'object_types', $object_type, 'ignore_object_hierarchy') );
	
		$this->object_strict_roles = array();
		$this->child_strict_roles = array();

		$args = array( 'id' => $object_id, 'include_child_restrictions' => true  );
		if ( $restrictions = $this->scoper->get_restrictions(OBJECT_SCOPE_RS, $src_name, $args) ) {
			foreach ( $this->role_handles as $role_handle ) {
				// defaults for this role
				if ( isset($restrictions['unrestrictions'][$role_handle]) && is_array($restrictions['unrestrictions'][$role_handle]) ) {
					$this->object_strict_roles[$role_handle] = true;
					$this->child_strict_roles[$role_handle] = true;
				} else {
					$this->object_strict_roles[$role_handle] = false;
					$this->child_strict_roles[$role_handle] = false;
				}
			
				// role is not default strict, and a restriction is set
				if ( isset($restrictions['restrictions'][$role_handle][$object_id]) ) {
					switch ( $restrictions['restrictions'][$role_handle][$object_id] ) {
						case ASSIGN_FOR_ENTITY_RS:
							$this->object_strict_roles[$role_handle] = true;
							$this->child_strict_roles[$role_handle] = false;
						break;
						case ASSIGN_FOR_CHILDREN_RS:
							$this->object_strict_roles[$role_handle] = false;
							$this->child_strict_roles[$role_handle] = true;
						break;
						case ASSIGN_FOR_BOTH_RS:
							$this->object_strict_roles[$role_handle] = true;
							$this->child_strict_roles[$role_handle] = true;
					} // end switch
				
				// role IS default strict, and no unrestriction is set
				} elseif ( isset($restrictions['unrestrictions'][$role_handle][$object_id]) ) {
					switch ( $restrictions['unrestrictions'][$role_handle][$object_id] ) {
						case ASSIGN_FOR_ENTITY_RS:
							$this->object_strict_roles[$role_handle] = false;
							$this->child_strict_roles[$role_handle] = true;
						break;
						case ASSIGN_FOR_CHILDREN_RS:
							$this->object_strict_roles[$role_handle] = true;
							$this->child_strict_roles[$role_handle] = false;
						break;
						case ASSIGN_FOR_BOTH_RS:
							$this->object_strict_roles[$role_handle] = false;
							$this->child_strict_roles[$role_handle] = false;
					} // end switch
				}
				
			} // end foreach Role Handle
		}
	}
	
	function draw_object_roles_content($src_name, $object_type, $role_handle, $object_id = '', $skip_user_validation = false, $object = false ) {
		if ( ! $object_id )
			$object_id = $this->scoper->data_sources->detect('id', $src_name, '', $object_type);

		if ( ! isset($this->all_agents) || ( $src_name != $this->loaded_src_name ) || ( $object_type != $this->loaded_object_type ) || ( $object_id != $this->loaded_object_id ) )
			$this->load_roles($src_name, $object_type, $object_id);
			
		if ( ! $otype_def = $this->scoper->data_sources->member_property($src_name, 'object_types', $object_type) )
			return;
			
		if ( ! $skip_user_validation && ! $this->scoper->admin->user_can_admin_role($role_handle, $object_id, $src_name, $object_type) )
			return;
		
		// since we may be dumping a lot of hidden user <li> into the page, enumerate role names to shorten html
		$role_code = 'r' . array_search($role_handle, $this->role_handles);
		
		$role_def = $this->scoper->role_defs->get($role_handle);
		
		if ( ! isset($role_def->valid_scopes[OBJECT_SCOPE_RS]) )
			return;
		
		if ( empty( $this->drew_objroles_marker ) ) {
			echo "<input type='hidden' name='rs_object_roles' value='true' />";
			$this->drew_objroles_marker = true;
		}
		
		// ========== OBJECT RESTRICTION CHECKBOX(ES) ============
		//todo: label class ( WP core uses "selectit" )
		// checkbox to control object role scoping (dictates whether taxonomy and blog role assignments also be honored for operations on this object )
		$display_name = ( ! empty($role_def->abbrev_for_object_ui) ) ? $role_def->abbrev_for_object_ui : $role_def->abbrev;

		$checked = ( empty($this->object_strict_roles[$role_handle]) ) ? '' : 'checked="checked"';
		
		$val = ( $checked ) ? '1' : '0';
		echo "<input type='hidden' name='last_objscope_{$role_code}' value='$val' id='last_objscope_{$role_code}' />";
			
		echo "\r\n<p style='margin-bottom:0.8em;'>"
			. "<span class='alignright'><a href='#wphead'>" . __('top', 'scoper') . '</a></span>'
			. "<label for='objscope_{$role_code}'>"
			. "<input type='checkbox' class='rs-check' name='objscope_{$role_code}' value='1' id='objscope_{$role_code}' $checked />"
			. sprintf(__('Restrict for %1$s (<strong>only</strong> selected users/groups are %2$s)', 'scoper'), $otype_def->display_name, $display_name)
			. '</label></p>';
			
		if ( $this->do_propagation_cboxes ) {
			$checked = ( empty($this->child_strict_roles[$role_handle]) ) ? '' : 'checked="checked"';
			$val = ( $checked ) ? '1' : '0';
			echo "<input type='hidden' name='last_objscope_children_{$role_code}' value='$val' id='last_objscope_children_{$role_code}' />";
			
			echo "<p style='margin-top: 0.5em;'>"
			. "<label for='objscope_children_{$role_code}'>"
			. "<input type='checkbox' class='rs-check' name='objscope_children_{$role_code}' value='1' id='objscope_children_{$role_code}' $checked />"
			. sprintf(__('Restrict for Sub-%1$s', 'scoper'), $otype_def->display_name_plural)
			. '</label></p>';
		}
			
			
		// ========== OBJECT ROLE ASSIGNMENT CHECKBOX(ES) ============
		// toggle groups / users view if both are enabled
		//echo "<p style='margin: 1em 0 0.2em 0;'><strong>" . sprintf(__('Assign %s Role:', 'scoper'), $display_name ) . '</strong></p>';
		//echo '<br />';
		$toggle_agents = USER_ROLES_RS && GROUP_ROLES_RS && ! empty($this->all_groups);
		if ( $toggle_agents ) {
			if ( ! empty($this->current_roles[ROLE_BASIS_USER][$role_handle]) )
				$default_role_basis = ROLE_BASIS_USER;
			else
				$default_role_basis = ROLE_BASIS_GROUPS;

			$class_selected = 'agp-selected_agent_colorized agp-selected_agent agp-agent';
			$class_unselected = 'agp-unselected_agent_colorized agp-unselected_agent agp-agent';

			$class = ( ROLE_BASIS_GROUPS == $default_role_basis ) ? "class='$class_selected'" : "class='$class_unselected'";
			$js_call = "agp_swap_display('{$role_code}_groups', '{$role_code}_user', '{$role_code}_show_group_roles', '{$role_code}_show_user_roles', '$class_selected', '$class_unselected')";
			echo "\r\n" 
				. '<div class="agp_js_show" style="display:none;margin:0">'
				. "<ul class='rs-list_horiz' style='margin-bottom:-0.1em'><li $class>"
				. "<a href='javascript:void(0)' id='{$role_code}_show_group_roles' onclick=\"$js_call\">" 
				. __('Groups', 'scoper') . '</a></li>';

			$class = ( ROLE_BASIS_USER == $default_role_basis ) ? "class='$class_selected'" : "class='$class_unselected'";
			$js_call = "agp_swap_display('{$role_code}_user', '{$role_code}_groups', '{$role_code}_show_user_roles', '{$role_code}_show_group_roles', '$class_selected', '$class_unselected')";
			echo "\r\n" 
				. "<li $class><a href='javascript:void(0)' id='{$role_code}_show_user_roles' onclick=\"$js_call\">" 
				. __('Users', 'scoper') . '</a></li>'
				. '</ul></div>';
		}
		
		$class = "class='rs-agents'";
		
		//need effective line break here if not IE
		echo "<div style='clear:both;margin:0 0 0.3em 0' $class>";
		
		$role_ops = $this->scoper->role_defs->get_role_ops($role_handle);
		$agents_reqd_op = (isset($role_ops[OP_EDIT_RS]) ) ? OP_EDIT_RS : OP_READ_RS;
		
		// account for Read vs. Read Private in indication of explicit role ownership
		// (possible TODO: abstract this for other data sources, but not crucial as it doesn't affect actual access)
		$contained_role_handle = $role_handle;
		$fudge_objscope_equiv_role = false;
		if ( ('rs_private_post_reader' == $role_handle) || ('rs_private_page_reader' == $role_handle) ) {
			if ( ! scoper_get_option( "{$role_handle}_role_objscope" ) ) {
				$fudge_objscope_equiv_role = true;
				if ( $object && ! empty($object->post_status) && ( 'private' != $object->post_status ) )
					$contained_role_handle = str_replace( 'private_', '', $contained_role_handle );
			}
		}
		
		$containing_roles = $this->scoper->role_defs->get_containing_roles($role_handle);

		// ... but we don't want assigned Post Readers marked up as "has via other role" because their actual stored object role is Private Post Reader
		if ( $fudge_objscope_equiv_role )
			$containing_roles = array_diff_key( $containing_roles, array( 'rs_private_post_reader' => true, 'rs_private_page_reader' => true ) );

		require_once('agents_checklist_rs.php');
		
		$args = array( 'suppress_extra_prefix' => true, 'default_hide_threshold' => 20, 'propagation' => $this->do_propagation_cboxes,
				'objtype_display_name' => $otype_def->display_name, 'objtype_display_name_plural' => $otype_def->display_name_plural);
		
		$args['via_other_role_ids'] = array(); // must set this here b/c subsequent for loop is set up for users iteration to recall via_other_role_ids from groups iteration
				
		foreach ( $this->agent_captions as $role_basis => $agent_caption ) {
			if ( ! is_array( $this->blog_term_roles[$role_basis][$role_handle] ) )
				$this->blog_term_roles[$role_basis][$role_handle] = array();
		
			// for the purpose of indicating implicit role ownership, we will consider any assignment of a containing role as equivalent
			foreach ( array_keys($containing_roles) as $containing_role_handle )
				if ( isset($this->blog_term_roles[$role_basis][$containing_role_handle]) && is_array($this->blog_term_roles[$role_basis][$containing_role_handle]) )
					$this->blog_term_roles[$role_basis][$role_handle] = array_merge( $this->blog_term_roles[$role_basis][$role_handle], $this->blog_term_roles[$role_basis][$containing_role_handle] );

			$this->blog_term_roles[$role_basis][$role_handle] = array_unique($this->blog_term_roles[$role_basis][$role_handle]);

			$hide_class = ( $toggle_agents && ( $role_basis != $default_role_basis ) ) ? ' class="agp_js_hide"' : '';
			
			echo "\r\n<div id='{$role_code}_{$role_basis}' $hide_class>";

			// also abbreviate "groups" to 'g', 'user' to 'u'
			$id_prefix = $role_code . substr($role_basis, 0, 1);
			
			if ( $this->indicate_blended_roles && $object_id && GROUP_ROLES_RS && (ROLE_BASIS_USER == $role_basis) ) {
				$args['via_other_basis_ids'] = array();
			
				// note users who are in a group that has this role object-assigned
				if ( ! empty($this->current_roles[ROLE_BASIS_GROUPS][$role_handle]['assigned']) ) {
					foreach ( array_keys($this->current_roles[ROLE_BASIS_GROUPS][$role_handle]['assigned']) as $group_id ) {
						if ( ! isset($this->group_members[$group_id]) )
							$this->group_members[$group_id] = ScoperAdminLib::get_group_members($group_id, COL_ID_RS, true); //arg: maybe WP role metagroup
	
						$args['via_other_basis_ids'] = array_merge($args['via_other_basis_ids'], $this->group_members[$group_id]);
					}
				}
			
				// note users who are in a group that has this role term-assigned or blog-assigned (and not restricted)
				foreach ( $this->blog_term_roles[ROLE_BASIS_GROUPS][$role_handle] as $group_id ) {
					if ( ! isset($this->group_members[$group_id]) )
						$this->group_members[$group_id] = ScoperAdminLib::get_group_members($group_id, COL_ID_RS, true); //arg: maybe WP role metagroup

					$args['via_other_basis_ids'] = array_merge($args['via_other_basis_ids'], $this->group_members[$group_id]);
				}
				
				// note users who are in a group that has a containing role object-assigned
				// (note: via_other_role_ids element was set in previous iteration since ROLE_BASIS_GROUPS is first element in agents_caption array
				foreach ( $args['via_other_role_ids'] as $group_id ) {
					if ( ! isset($this->group_members[$group_id]) )
						$this->group_members[$group_id] = ScoperAdminLib::get_group_members($group_id, COL_ID_RS, true); //arg: maybe WP role metagroup

					$args['via_other_basis_ids'] = array_merge($args['via_other_basis_ids'], $this->group_members[$group_id]);
				}
				
				$args['via_other_basis_ids'] = array_unique($args['via_other_basis_ids']);
			}

			if ( $this->indicate_blended_roles )
				$args['via_other_scope_ids'] = $this->blog_term_roles[$role_basis][$role_handle];
			
			$args['via_other_role_ids'] = array();
			if ( $this->indicate_blended_roles && $containing_roles ) {
				foreach ( array_keys($containing_roles) as $containing_role_handle ) {
					if ( isset($this->current_roles[$role_basis][$containing_role_handle]['assigned']) )
						$args['via_other_role_ids'] = array_merge($args['via_other_role_ids'], array_keys($this->current_roles[$role_basis][$containing_role_handle]['assigned']) );
				}
				$args['via_other_role_ids'] = array_unique($args['via_other_role_ids']);
			}

			if ( $object_id && $this->do_propagation_cboxes ) {
				$args['for_entity_ids'] = ( isset($this->current_roles[$role_basis][$role_handle]['entity']) ) ? array_keys($this->current_roles[$role_basis][$role_handle]['entity']) : array();
				$args['for_children_ids'] = ( isset($this->current_roles[$role_basis][$role_handle]['children']) ) ? array_keys($this->current_roles[$role_basis][$role_handle]['children']) : '';
			}
			
			$args['eligible_ids'] = isset($this->eligible_agent_ids[$role_basis][$agents_reqd_op]) ? $this->eligible_agent_ids[$role_basis][$agents_reqd_op]: '';
			
			if ( ! empty($this->current_roles[$role_basis][$role_handle]['assigned']) ) {
				ScoperAgentsChecklist::agents_checklist( $role_basis, $this->all_agents[$role_basis], $id_prefix, $this->current_roles[$role_basis][$role_handle]['assigned'], $args );
			} else
				ScoperAgentsChecklist::agents_checklist( $role_basis, $this->all_agents[$role_basis], $id_prefix, '', $args );

			echo "\r\n</div>";
			
		} // end foreach role basis caption (user or group)
		
		echo '</div>'; // class rs-agents
	}
	
	function get_rolecount_caption($role_handle) {			
		$count_sfx = '';

		$agents_caption = array();

		// indicate object role scoping in container caption
		if ( ! empty($this->object_strict_roles[$role_handle]) )
			$agents_caption[] = __( 'restricted role', 'scoper' );
			
		if ( USER_ROLES_RS && ! empty($this->current_roles[ROLE_BASIS_USER][$role_handle]) ) {
			$count = count(array_keys($this->current_roles[ROLE_BASIS_USER][$role_handle]['assigned']));
			$agents_caption[] = ( GROUP_ROLES_RS ) ? sprintf(__ngettext('%s user', '%s users', $count, 'scoper'), $count) : $count;
			//$any_roles_assigned = true;
		}
		
		if ( GROUP_ROLES_RS && ! empty($this->current_roles[ROLE_BASIS_GROUPS][$role_handle]) ) {
			$count = count(array_keys($this->current_roles[ROLE_BASIS_GROUPS][$role_handle]['assigned']));
			$agents_caption[] = ( USER_ROLES_RS ) ? sprintf(__ngettext('%s group', '%s groups', $count, 'scoper'), $count) : $count;
			//$any_roles_assigned = true;
		}
		
		if ( $agents_caption ) {
			$count_sfx = "<span class='rs-dbx_headline_populated'>";
			$count_sfx .= ( $agents_caption ) ? ' (' . implode(', ', $agents_caption) . ')' : '';
			$count_sfx .= "</span>";
		}
		
		return $count_sfx;
	}
	
	// This is now called only with WP < 2.5, for direct single-object role edit via the bulk admin form,
	// and by any non-post data sources which define admin_actions->object_edit_ui
	function single_object_roles_ui($src_name, $object_type, $object_id, $args = '') {
		$defaults = array( 'html_inserts' => '' );
		$args = array_merge( $defaults, (array) $args );
		extract($args);

		if ( ! scoper_get_otype_option('use_object_roles', $src_name, $object_type) )
			return;
		
		if ( ! $html_inserts ) {
			if ( ! $otype_def = $this->scoper->data_sources->member_property($src_name, 'object_types', $object_type) )
				return;
		
			if ( isset($otype_def->admin_inserts->bottom) )
				$html_inserts = $otype_def->admin_inserts->bottom;
			
			elseif ( ! $html_inserts = $src->admin_inserts->bottom ) {
				// TODO: CSS
				$html_inserts->open = (object) array(
					'container' => '<br />', 
					'headline' => '<h3 style="margin-bottom: 0">', 
					'content' => '<div style="border:2px solid #ccc; margin-top: 0; padding: 0 0.2em 0 0.2em;">' );
				
				$html_inserts->close = (object) array( 
					'container' => '', 'headline' => '</h3>', 'content' => '</div>' );
			}
		}
		
		if ( ! isset($this->all_agents) || ( $src_name != $this->loaded_src_name ) || ( $object_type != $this->loaded_object_type ) || ( $object_id != $this->loaded_object_id ) )
			$this->load_roles($src_name, $object_type, $object_id);
		
		$group_members = array();
		
		$role_defs = $this->scoper->role_defs->get_matching(SCOPER_ROLE_TYPE, $src_name, $object_type);
		
		foreach ($role_defs as $role_handle => $role_def) {
			if ( ! isset($role_def->valid_scopes[OBJECT_SCOPE_RS]) || ! $this->scoper->admin->user_can_admin_role($role_handle, $object_id, $src_name, $object_type) )
				continue;

			echo( "\r\n" . sprintf($html_inserts->open->container, "objrole_$role_handle") );

			$count_sfx = $this->get_rolecount_caption($role_handle);

			$abbrev = ( empty($role_def->abbrev_for_object_ui) ) ? $role_def->abbrev : $role_def->abbrev_for_object_ui;
			
			echo $html_inserts->open->headline 
			. "{$abbrev}{$count_sfx}" 
			. $html_inserts->close->headline
			. $html_inserts->open->content;
			
			$this->draw_object_roles_content($src_name, $object_type, $role_handle, $object_id, true); // arg: skip_user_validation

			echo "\r\n" . $html_inserts->close->content . "\r\n" . $html_inserts->close->container . "\r\n";
		} // end foreach role
	}

	function single_term_roles_ui($taxonomy, $args, $term) {
		if ( ! $taxonomy )
			return;
			
		if ( ! $tx = $this->scoper->taxonomies->get($taxonomy) )
			return;

		$term_id = is_object($term) ? $term->{$tx->source->cols->id} : $term;
		if ( ! $term_id )
			return;

		if ( ! $role_defs_by_otype = $this->scoper->role_defs->get_for_taxonomy($tx->object_source, $taxonomy) )
			return;
			
		require_once('admin-bulk_rs.php');
		
		$all_terms = array( (object) array( $tx->source->cols->id => $term_id, $tx->source->cols->name => '', $tx->source->cols->parent => 0 ) );

		$admin_terms = array( $term_id => true );

		$role_bases = array();
		$term_roles = array();
		if ( USER_ROLES_RS ) {
			$term_roles[ROLE_BASIS_USER] = ScoperRoleAssignments::get_assigned_roles(TERM_SCOPE_RS, ROLE_BASIS_USER, $taxonomy, array( 'id' => $term_id) );
			$role_bases []= ROLE_BASIS_USER;
		}
		if ( GROUP_ROLES_RS ) {
			$term_roles[ROLE_BASIS_GROUPS] = ScoperRoleAssignments::get_assigned_roles(TERM_SCOPE_RS, ROLE_BASIS_GROUPS, $taxonomy, array( 'id' => $term_id) );
			$role_bases []= ROLE_BASIS_GROUPS;
		}
		
		$strict_terms = $this->scoper->get_restrictions(TERM_SCOPE_RS, $taxonomy );

		$agents = ScoperAdminBulk::get_agents($role_bases);
		$agent_names = ScoperAdminBulk::agent_names($agents);
		$agent_list_prefix = ScoperAdminBulk::agent_list_prefixes();
		$agent_caption_plural = ScoperAdminBulk::agent_captions_plural($role_bases);
		
		$default_restrictions = $this->scoper->get_default_restrictions(TERM_SCOPE_RS);
		$default_strict_roles = ( ! empty($default_restrictions[$taxonomy] ) ) ? array_flip(array_keys($default_restrictions[$taxonomy])) : array();

		require_once('admin_ui_lib_rs.php');
		$table_captions = ScoperAdminUI::restriction_captions(TERM_SCOPE_RS, $tx, $tx->display_name, $tx->display_name_plural);

		echo '<br />';
		$url = SCOPER_ADMIN_URL . "/restrictions/category#item-$term_id";
		echo "\n<h3><a href='$url'>" . __('Category Restrictions', 'scoper') . '</a></h3>';
		
		$args = array( 
		'admin_items' => $admin_terms, 	'editable_roles' => array(),			'default_strict_roles' => $default_strict_roles,
		'ul_class' => 'rs-termlist', 	'table_captions' => $table_captions,	'single_item' => true
		);
		ScoperAdminBulk::item_tree( TERM_SCOPE_RS, ROLE_RESTRICTION_RS, $tx->source, $tx, $all_terms, '', $strict_terms, $role_defs_by_otype, array(), $args);
		
		
		$url = SCOPER_ADMIN_URL . "/roles/category#item-$term_id";
		echo "\n<h3><a href='$url'>" . __('Category Roles', 'scoper') . '</a></h3>';
		
		$args = array( 
		'admin_items' => $admin_terms, 	'editable_roles' => array(),						'role_bases' => $role_bases,
		'agent_names' => $agent_names,	'agent_caption_plural' => $agent_caption_plural,	'agent_list_prefix' => $agent_list_prefix,
		'ul_class' => 'rs-termlist', 	'single_item' => true
		);
		ScoperAdminBulk::item_tree(TERM_SCOPE_RS, ROLE_ASSIGNMENT_RS, $tx->source, $tx, $all_terms, $term_roles, $strict_terms, $role_defs_by_otype, array(), $args);

		if ( ! empty($term_roles) ) {
			$args = array( 'display_links' => false, 'display_restriction_key' => false);
			ScoperAdminUI::role_owners_key($tx, $args);
		}
	} // end function ui_single_term_roles
	
} // end class ScoperObjectRolesUI
?>