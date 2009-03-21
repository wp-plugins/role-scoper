<?php
if( basename(__FILE__) == basename($_SERVER['SCRIPT_FILENAME']) )
	die();

class ScoperProfileUI {

	function display_ui_user_roles($user, $groups_only = false) {
		foreach ( $this->scoper->taxonomies->get_all() as $taxonomy => $tx )
			if ( ! isset($user->term_roles[$taxonomy]) )
				$user->get_term_roles($taxonomy);
		
		if ( $groups_only ) {
			echo '<div><h3>';
			_e('Group Roles', 'scoper');	//TODO: hide this caption or display "none defined" where appropriate
			echo '</h3>';
		} else {
			echo "<div id='userprofile_rolesdiv_rs' class='rs-scoped_role_profile'>";
			echo "<h3>" . __('Scoped Roles', 'scoper') . "</h3>";
	
			if ( ! empty($user->assigned_blog_roles) ) {
				$display_names = array();
				if ( $wp_blog_roles = $this->scoper->role_defs->filter_roles_by_type($user->assigned_blog_roles, 'wp') ) {
					foreach (array_keys($wp_blog_roles) as $role_handle)
						$display_names []= $this->scoper->role_defs->member_property($role_handle, 'display_name');
					
					printf( __("<strong>Assigned Wordpress Role:</strong> %s", 'scoper'), implode(", ", $display_names) );
				
					if ( $contained_roles = $this->scoper->role_defs->get_contained_roles( array_keys($wp_blog_roles), false, SCOPER_ROLE_TYPE ) ) {
						$display_names = array();			
						foreach (array_keys($contained_roles) as $role_handle)
							$display_names []= $this->scoper->role_defs->member_property($role_handle, 'display_name');
						
						echo '<br /><span class="rs-gray">';
						printf( __("(contains %s)", 'scoper'), implode(", ", $display_names) );
						echo '</span>';
					}
				}
			}
			
			echo '<br /><br />';
		}
		
		if ( 'rs' == SCOPER_ROLE_TYPE ) {
			$display_names = array();
			if ( $rs_blog_roles = $this->scoper->role_defs->filter_roles_by_type($user->assigned_blog_roles, 'rs') ) {
				foreach (array_keys($rs_blog_roles) as $role_handle)
					$display_names []= $this->scoper->role_defs->member_property($role_handle, 'display_name');
				
				$url = SCOPER_ADMIN_URL . "/general_roles.php";
				$linkopen = "<b><a href='$url'>";
				$linkclose = "</a></b>";
				$list = implode(", ", $display_names);
				printf( __ngettext('<strong>Additional %1$sGeneral Role%2$s:</strong> %3$s', '<strong>Additional %1$sGeneral Roles%2$s</strong>: %3$s', count($display_names), 'scoper'), $linkopen, $linkclose, $list);
			
				if ( $contained_roles = $this->scoper->role_defs->get_contained_roles( array_keys($rs_blog_roles), false, 'rs' ) ) {
					$display_names = array();			
					foreach (array_keys($contained_roles) as $role_handle)
						$display_names []= $this->scoper->role_defs->member_property($role_handle, 'display_name');
					
					echo '<br /><span class="rs-gray">';
					printf( __("(contains %s)", 'scoper'), implode(", ", $display_names) );
					echo '</span>';
				}
			}
		}
		
		foreach ( $this->scoper->taxonomies->get_all() as $taxonomy => $tx ) {
			if ( empty($user->assigned_term_roles[$taxonomy]) )
				continue;
			
			if ( ! $terms = $this->scoper->get_terms($taxonomy, UNFILTERED_RS, COLS_ALL_RS, 0, ORDERBY_HIERARCHY_RS) )
				continue;
			
			$strict_terms = $this->scoper->get_restrictions(TERM_SCOPE_RS, $taxonomy);
				
			$object_types = array();
			foreach ( array_keys($tx->object_source->object_types) as $object_type)
				if ( scoper_get_otype_option('use_term_roles', $tx->object_source->name, $object_type) )
					$object_types []= $object_type;
			
			$object_types []= $taxonomy;
				
			$role_defs = $this->scoper->role_defs->get_matching(SCOPER_ROLE_TYPE, $tx->object_source->name, $object_types);

			$term_names = array();
			foreach ( $terms as $term )
				$term_names[$term->term_id] = $term->name;
				
			$url = SCOPER_ADMIN_URL . "/roles/$taxonomy";
			echo ("\n<h4><a href='$url'>" . sprintf(_c('%s Roles:|Category Roles', 'scoper'), $tx->display_name) . '</a></h4>' );

			echo '<ul class="rs-termlist" style="padding-left:0.1em;">';
			echo '<li>';
			echo '<table class="widefat"><thead><tr class="thead">';
			echo '<th class="rs-tightcol">' . __('Role', 'scoper') . '</th>';
			echo '<th>' . $tx->display_name_plural . '</th>';
			echo '</tr></thead><tbody>';
			
			$style = ' class="rs-backwhite"';
			foreach ( $role_defs as $role_handle => $role_def ) {
				if ( isset( $user->assigned_term_roles[$taxonomy][$role_handle] ) ) {

					$role_terms = $user->assigned_term_roles[$taxonomy][$role_handle];
					$role_display = $this->scoper->role_defs->member_property($role_handle, 'display_name');
					
					$term_role_list = array();
					foreach ( $role_terms as $term_id ) {
						if ( isset($strict_terms['restrictions'][$role_handle][$term_id]) 
						|| ( isset($strict_terms['unrestrictions'][$role_handle]) && is_array($strict_terms['unrestrictions'][$role_handle]) && ! isset($strict_terms['unrestrictions'][$role_handle][$term_id]) ) )
							$term_role_list []= "<span class='rs-backylw'><a href='$url#item-$term_id'>" . $term_names[$term_id] . '</a></span>';
						else
							$term_role_list []= "<a href='$url-item$term_id'>" . $term_names[$term_id] . '</a>';
					}
						
					echo "\r\n"
						. "<tr$style>"
						. "<td>" . str_replace(' ', '&nbsp;', $role_display) . "</td>"
						. '<td>' . implode(', ', $term_role_list) . '</td>'
						. "</tr>";
					$style = ( ' class="alternate"' == $style ) ? ' class="rs-backwhite"' : ' class="alternate"';
				}
			}

			echo '</tbody></table>';
			echo '</li></ul>';
			
		} // end foreach taxonomy
		
		require_once('object_roles_list.php');
		$got_obj_roles = scoper_object_roles_list($user, true);  // arg: suppress some output
		
		if ( $groups_only ) {
			if ( empty($rs_blog_roles) && empty($term_role_list) && empty($got_obj_roles) )
				echo '<p>' . __('No roles are assigned to this group.', 'scoper'), '</p>';
		} else {
			echo '</div>';
		}
		
		echo '</div>';
		
	} // end function ui_user_roles

} // end class