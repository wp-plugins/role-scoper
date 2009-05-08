<?php
if( basename(__FILE__) == basename($_SERVER['SCRIPT_FILENAME']) )
	die();
	
if ( awp_ver('2.7-dev') )
	add_filter('wp_dropdown_pages', array('ScoperAdminUI', 'flt_dropdown_pages') );  // WP < 2.7 must parse low-level query

/**
 * ScoperAdminUI PHP class for the WordPress plugin Role Scoper
 * scoper_admin_ui_lib.php
 * 
 * @author 		Kevin Behrens
 * @copyright 	Copyright 2009
 * 
 * Used by Role Scoper Plugin as a container for statically-called functions
 * These function can be used during activation, deactivation, or other 
 * scenarios where no Scoper or WP_Scoped_User object exists
 *
 */
class ScoperAdminUI {
	function role_assignment_list($roles, $agent_names, $checkbox_base_id = '', $role_basis = 'user') {
		$agent_grouping = array();
		$agent_list = array();
		$role_propagated = array();

		 if ( ! $checkbox_base_id )
			$link_end = '';
		
		// This would sort entire list (currently grouping by assign_for and alphabetizing each grouping)
		//$sorted_roles = array();
		//uasort($agent_names, 'strnatcasecmp');
		//foreach ( $agent_names as $agent_id => $agent_name )
		//	$sorted_roles[$agent_id] = $roles[$agent_id];
		
		foreach( $roles as $agent_id => $val ) {
			if ( is_array($val) && ! empty($val['inherited_from']) )
				$role_propagated[$agent_id] = true;
		
			if ( is_array($val) && ( 'both' == $val['assign_for'] ) )
				$agent_grouping[ASSIGN_FOR_BOTH_RS] [$agent_id]= $agent_names[$agent_id];
			
			elseif ( is_array($val) && ( 'children' == $val['assign_for'] ) )
				$agent_grouping[ASSIGN_FOR_CHILDREN_RS] [$agent_id]= $agent_names[$agent_id];
				
			else
				$agent_grouping[ASSIGN_FOR_ENTITY_RS] [$agent_id]= $agent_names[$agent_id];
		}
		
		
		// display for_entity assignments first, then for_both, then for_children
		$assign_for_order = array( 'entity', 'both', 'children');
		
		$use_agents_csv = scoper_get_option("{$role_basis}_role_assignment_csv");

		foreach ( $assign_for_order as $assign_for ) {
			if ( ! isset($agent_grouping[$assign_for]) )
				continue;
				
			// sort each assign_for grouping alphabetically
			uasort($agent_grouping[$assign_for], 'strnatcasecmp');
			
			foreach ( $agent_grouping[$assign_for] as $agent_id => $agent_name ) {
				// surround rolename with bars to indicated it was inherited
				$pfx = ( isset($role_propagated[$agent_id]) ) ? '{' : '';
				$sfx = '';
				
				if ( $checkbox_base_id ) {
					if ( $use_agents_csv )
						$js_call = "agp_append('{$role_basis}_csv', ', $agent_name');";
					else
						$js_call = "agp_check_it('{$checkbox_base_id}{$agent_id}');";
					
					$link_end = " href='javascript:void(0)' title='select' onclick=\"$js_call\">";
					$sfx = '</a>';
				}
					
				// surround rolename with braces to indicated it was inherited
				if ( $pfx )
					$sfx .= '}';
				
				switch ( $assign_for ) {
					case ASSIGN_FOR_BOTH_RS:
						//roles which are assigned for entity and children will be bolded in list
						$link = ( $link_end ) ? "<a class='rs-link_plain'" . $link_end : '';
						$agent_list[ASSIGN_FOR_BOTH_RS] [$agent_id]= $pfx . $link . $agent_name . $sfx;
				
					break;
					case ASSIGN_FOR_CHILDREN_RS:
						//roles with are assigned only to children will be grayed
						$link = ( $link_end ) ? "<a class='rs-link_plain rs-gray'" . $link_end : '';
						$agent_list[ASSIGN_FOR_CHILDREN_RS] [$agent_id]= $pfx . "<span class='rs-gray'>" . $link . $agent_names[$agent_id] . $sfx . '</span>';
						
					break;
					case ASSIGN_FOR_ENTITY_RS:
						$link = ( $link_end ) ? "<a class='rs-link_plain'" . $link_end : '';
						$agent_list[ASSIGN_FOR_ENTITY_RS] [$agent_id]= $pfx . $link . $agent_names[$agent_id] . $sfx;
				}
			} // end foreach agents
			
			$agent_list[$assign_for] = implode(', ', $agent_list[$assign_for]);
			
			if ( ASSIGN_FOR_ENTITY_RS != $assign_for )
				$agent_list[$assign_for] = "<span class='rs-bold'>" .  $agent_list[$assign_for] . '</span>';
		} // end foreach assign_for
		
		if ( $agent_list )
			return implode(', ', $agent_list);
	}
	
	function restriction_captions( $scope, $tx = '', $display_name = '', $display_name_plural = '') {
		$table_captions = array();
	
		if ( TERM_SCOPE_RS == $scope ) {
			if ( ! $display_name_plural ) 
				$display_name_plural = ( 'link_category' == $tx->name ) ? strtolower( $this->scoper->taxonomies->member_property('category', 'display_name_plural') ) : strtolower($tx->display_name_plural);
			if ( ! $display_name ) 
				$display_name = ( 'link_category' == $tx->name ) ? strtolower( $this->scoper->taxonomies->member_property('category', 'display_name') ) : strtolower($tx->display_name);
		}
		
		$table_captions = array();
		$table_captions['restrictions'] = array(	// captions for roles which are NOT default strict
			ASSIGN_FOR_ENTITY_RS => sprintf(__('Restricted for %s', 'scoper'), $display_name), 
			ASSIGN_FOR_CHILDREN_RS => sprintf(__('Unrestricted for %1$s, Restricted for sub-%2$s', 'scoper'), $display_name, $display_name_plural), 
			ASSIGN_FOR_BOTH_RS => sprintf(__('Restricted for selected and sub-%s', 'scoper'), $display_name_plural),
			false => sprintf(__('Unrestricted by default', 'scoper'), $display_name),
			'default' => sprintf(__('Unrestricted', 'scoper'), $display_name)
		);
		$table_captions['unrestrictions'] = array( // captions for roles which are default strict
			ASSIGN_FOR_ENTITY_RS => sprintf(__('Unrestricted for %s', 'scoper'), $display_name), 
			ASSIGN_FOR_CHILDREN_RS => sprintf(__('Unrestricted for sub-%s', 'scoper'), $display_name_plural), 
			ASSIGN_FOR_BOTH_RS => sprintf(__('Unrestricted for selected and sub-%s', 'scoper'), $display_name_plural),
			false => sprintf(__('Restricted by default', 'scoper'), $display_name),
			'default' => sprintf(__('Restricted', 'scoper'), $display_name)
		);
		
		return $table_captions;
	}
	
	function role_owners_key($tx_or_otype, $args = '') {
		$defaults = array( 'display_links' => true, 'display_restriction_key' => true, 'restriction_caption' => '',
							'role_basis' => '', 'agent_caption' => '' );
		$args = array_merge( $defaults, (array) $args);
		extract($args);
	
		$display_name_plural = strtolower($tx_or_otype->display_name_plural);
		$display_name = strtolower($tx_or_otype->display_name);

		if ( $role_basis ) {
			if ( ! $agent_caption && $role_basis )
				$agent_caption = ( ROLE_BASIS_GROUPS == $role_basis ) ? __('Group', 'scoper') : __('User', 'scoper');
				$generic_name = ( ROLE_BASIS_GROUPS == $role_basis ) ? __('Groupname', 'scoper') : __('Username', 'scoper');
		} else
			$generic_name = __('Name', 'scoper');
			
		$agent_caption = strtolower($agent_caption);
		
		echo '<h4 style="margin-bottom:0.1em"><a name="scoper_key"></a>' . __("Users / Groups Key", 'scoper') . ':</h4><ul class="rs-agents_key">';	
		
		$link_open = ( $display_links ) ? "<a class='rs-link_plain' href='javascript:void(0)'>" : '';
		$link_close = ( $display_links ) ? '</a>' : '';
		
		echo '<li>';
		echo "{$link_open}$generic_name{$link_close}: ";
		printf (__('%1$s has role assigned for the specified %2$s.', 'scoper'), $agent_caption, $display_name);
		echo '</li>';
		
		echo '<li>';
		echo "<span class='rs-bold'>{$link_open}$generic_name{$link_close}</span>: ";
		printf (__('%1$s has role assigned for the specified %2$s and, by default, for all its sub-%3$s. (Propagated roles can also be explicitly removed).', 'scoper'), $agent_caption, $display_name, $display_name_plural);
		echo '</li>';
		
		echo '<li>';
		echo "<span class='rs-bold rs-gray'>{$link_open}$generic_name{$link_close}</span>: ";
		printf (__('%1$s does NOT have role assigned for the specified %2$s, but has it by default for sub-%3$s.', 'scoper'), $agent_caption, $display_name, $display_name_plural);
		echo '</li>';
		
		echo '<li>';
		echo '<span class="rs-bold">{' . "{$link_open}$generic_name{$link_close}" . '}</span>: ';
		printf (__('%1$s has this role via propagation from parent %2$s, and by default for sub-%3$s.', 'scoper'), $agent_caption, $display_name, $display_name_plural);
		echo '</li>';
		
		if ( $display_restriction_key ) {
			echo '<li>';
			echo "<span class='rs-bold rs-backylw' style='padding-left:0.5em;padding-right:0.5em'>" . __('Role Name', 'scoper') . "</span>: ";
			echo "<span>" . sprintf(__('role is restricted for specified %s.', 'scoper'), $display_name) . "</span>";
			echo '</li>';
		}
		
		echo '</ul>';
	}
	
	function taxonomy_scroll_links($tx, $terms, $admin_terms = '') {
		if ( empty($terms) || ( is_array($admin_terms) && empty($admin_terms) ) )
			return;
		
		echo '<b>' . __('Scroll to current settings:','scoper') . '</b><br />';	
			
		if ( $admin_terms && ! is_array($admin_terms) )
			$admin_terms = '';
	
		$col_id = $tx->source->cols->id;
		$col_name = $tx->source->cols->name;
		$col_parent = $tx->source->cols->parent;

		$font_ems = 1.2;
		$text = '';
		$term_num = 0;

		$parent_id = 0;
		$last_id = -1;
		$last_parent_id = -1;
		$parents = array();
		$depth = 0;
		
		foreach( $terms as $term ) {
			$term_id = $term->$col_id;
			
			if ( isset($term->$col_parent) )
				$parent_id = $term->$col_parent;

			if ( ! $admin_terms || ! empty($admin_terms[$term_id]) ) {
				if ( $parent_id != $last_parent_id ) {
					if ( ($parent_id == $last_id) && $last_id ) {
						$parents[] = $last_id;
						$depth++;
					} elseif ($depth) {
						do {
							array_pop($parents);
							$depth--;
						} while ( $parents && ( end($parents) != $parent_id ) && $depth);
					}
					
					$last_parent_id = $parent_id;
				}

				//echo "term {$term->$col_name}: depth $depth, current parents: ";
				//dump($parents);
				
				if ( $term_num )
					$text .= ( $parent_id ) ? ' - ' : ' . ';
					
				if ( ! $parent_id )
					$depth = 0;
				
				$color_level_b = ($depth < 4) ? 220 - (60 * $depth) : 0;
				$hexb = dechex($color_level_b);
				if ( strlen($hexb) < 2 )
					$hexb = "0" . $hexb;
				
				$color_level_g = ($depth < 4) ? 80 + (40 * $depth) : 215;
				$hexg = dechex($color_level_g);
				
				$font_ems = ($depth < 5) ? 1.2 - (0.12 * $depth) : 0.6; 
				$text .= "<span style='font-size: {$font_ems}em;'><a class='rs-link_plain' href='#item-$term_id'><span style='color: #00{$hexg}{$hexb};'>{$term->$col_name}</span></a></span>";
			}
			
			$last_id = $term_id;
			$term_num++;
		}
		
		$text .= '<br />';
		
		return $text;
	}
	
	function common_ui_msg( $msg_id ) {
		if ( 'pagecat_plug' == $msg_id ) {
			$msg = __('Category Roles for Wordpress pages are <a %s>disabled for this blog</a>. Object Roles can be assigned to individual pages, and optionally propagated to sub-pages.', 'scoper');
			echo '<li>';
			printf( $msg, 'href="' . SCOPER_ADMIN_URL . '/options.php"');
			
			$msg = __('Another option is to categorise pages via the <a %s>Page&nbsp;Category&nbsp;Plus</a>&nbsp;plugin.', 'scoper');
			$href = 'href="http://www.stuff.yellowswordfish.com/page-category-plus"';
			
			echo ' ' . sprintf( $msg, $href);
			echo '</li>';
		}
	}
	
	// make use of filter provided by WP 2.7
	function flt_dropdown_pages($orig_options_html) {
		global $scoper, $post_ID;

		if ( strpos( $_SERVER['SCRIPT_NAME'], 'p-admin/options-' ) )
			return $orig_options_html;

		if ( empty($post_ID) )
			$object_id = $scoper->data_sources->detect('id', 'post', 0, 'post');
		else
			$object_id = $post_ID;
		
		if ( $object_id )
			$stored_parent_id = $scoper->data_sources->detect('parent', 'post', $object_id);
		else
			$stored_parent_id = 0;
			
		//if ( is_administrator_rs() )	// WP 2.7 excludes private pages from Administrator's parent dropdown
		//	return $orig_options_html;

		if ( is_administrator_rs() ) {
			$can_associate_main = true;
			
		} elseif ( ! scoper_get_option( 'lock_top_pages' ) ) {
			global $current_user;
			$reqd_caps = array('edit_others_pages');
			$roles = $scoper->role_defs->qualify_roles($reqd_caps, '');
	
			$can_associate_main = array_intersect_key($roles, $current_user->blog_roles);
		} else
			$can_associate_main = false;
		
		// Generate the filtered page parent options, but only if user can de-associate with main page, or if parent is already non-Main
		if ( $can_associate_main || ! $object_id || $stored_parent_id )
			$options_html = ScoperAdminUI::dropdown_pages($object_id, $stored_parent_id);
		else
			$options_html = '';

		// User can't associate or de-associate a page with Main page unless they have edit_pages blog-wide.
		// Prepend the Main Page option if appropriate (or, to avoid submission errors, if we generated no other options)
		if ( $can_associate_main || ( $object_id && ! $stored_parent_id ) || empty($options_html) ) {
			$current = ( $stored_parent_id ) ? '' : ' selected="selected"';
			$option_main = "\n\t<option value='0'$current> " . __('Main Page (no parent)') . "</option>";
		} else
			$option_main = '';
		
		return "<select name='parent_id' id='parent_id'>\n" . $option_main . $options_html . '</select>';
	}
	
	function dropdown_pages($object_id = '', $stored_parent_id = '') {
		global $scoper, $wpdb;
		$args = array();

		if ( ! is_numeric($object_id) ) {
			global $post_ID;
			
			if ( empty($post_ID) )
				$object_id = $scoper->data_sources->detect('id', 'post', 0, 'post');
			else
				$object_id = $post_ID;
		}
		
		if ( $object_id && ! is_numeric($stored_parent_id) )
			$stored_parent_id = $scoper->data_sources->detect('parent', 'post', $object_id);
		
		// make sure the currently stored parent page remains in dropdown regardless of current user roles
		if ( $stored_parent_id ) {
			$preserve_or_clause = " $wpdb->posts.ID = '$stored_parent_id' ";
			$args['preserve_or_clause'] = array();
			foreach (array_keys( $scoper->data_sources->member_property('post', 'statuses') ) as $status_name )
				$args['preserve_or_clause'][$status_name] = $preserve_or_clause;
		}
		
		// alternate_caps is a 2D array because objects_request / objects_where filter supports multiple alternate sets of qualifying caps
		$args['force_reqd_caps']['page'] = array();
		foreach (array_keys( $scoper->data_sources->member_property('post', 'statuses') ) as $status_name )
			$args['force_reqd_caps']['page'][$status_name] = array('edit_others_pages');
			
		$args['alternate_reqd_caps'][0] = array('create_child_pages');
		
		$all_pages_by_id = array();
		if ( $results = scoper_get_results( "SELECT ID, post_parent, post_title FROM $wpdb->posts WHERE post_type = 'page'" ) )
			foreach ( $results as $row )
				$all_pages_by_id[$row->ID] = $row;

		$qry_parents = "SELECT DISTINCT ID, post_parent, post_title FROM $wpdb->posts WHERE post_type = 'page' ORDER BY menu_order";
		
		$qry_parents = apply_filters('objects_request_rs', $qry_parents, 'post', 'page', $args);
		
		$filtered_pages_by_id = array();
		if ( $results = scoper_get_results($qry_parents) )
			foreach ( $results as $row )
				$filtered_pages_by_id [$row->ID] = $row;

		$hidden_pages_by_id = array_diff_key( $all_pages_by_id, $filtered_pages_by_id );

		// temporarily add in the hidden parents so we can order the visible pages by hierarchy
		$pages = ScoperAdminUI::add_missing_parents($filtered_pages_by_id, $hidden_pages_by_id, 'post_parent');

		// convert keys from post ID to title+ID so we can alpha sort them
		$args['pages'] = array();
		foreach ( array_keys($pages) as $id )
			$args['pages'][ $pages[$id]->post_title . chr(11) . $id ] = $pages[$id];

		// natural case alpha sort
		uksort($args['pages'], "strnatcasecmp");

		$args['pages'] = ScoperAdminUI::order_by_hierarchy($args['pages'], 'ID', 'post_parent');
		
		// take the hidden parents back out
		foreach ( $args['pages'] as $key => $page )
			if ( isset( $hidden_pages_by_id[$page->ID] ) )
				unset( $args['pages'][$key] );
		
		$scoper->page_ids = array();
		$output = '';
		
		$args['object_id'] = $object_id;
		ScoperAdminUI::walk_parent_dropdown($output, $args, true, $stored_parent_id);
		
		// next we'll add disjointed branches, but don't allow this page's descendants to be offered as a parent
		$arr_parent = array();
		$arr_children = array();
		
		if ( $results = scoper_get_results("SELECT ID, post_parent FROM $wpdb->posts WHERE post_type = 'page'") ) {
			foreach ( $results as $row ) {
				$arr_parent[$row->ID] = $row->post_parent;
				
				if ( ! isset($arr_children[$row->post_parent]) )
					$arr_children[$row->post_parent] = array();
					
				$arr_children[$row->post_parent] []= $row->ID;
			}
			
			$descendants = array();
			if ( ! empty( $arr_children[$object_id] ) ) {
				foreach ( $arr_parent as $page_id => $parent_id ) {
					if ( ! $parent_id || ($page_id == $object_id) )
						continue;
						
					do {
						if ( $object_id == $parent_id ) {
							$descendants[$page_id] = true;
							break;
						}
						
						$parent_id = $arr_parent[$parent_id];
					} while ( $parent_id );
				}
			}
			$args['descendants'] = $descendants;
		}
		
		ScoperAdminUI::walk_parent_dropdown($output, $args, false, $stored_parent_id);

		return $output;
	}
				
	// slightly modified transplant of WP 2.6 core parent_dropdown
	function walk_parent_dropdown( &$output, $args, $use_parent_clause = true, $default = 0, $parent = 0, $level = 0 ) {
		global $scoper;

		// todo: defaults, merge
		extract($args);
		
		$scoper->page_ids[$parent] = true;
		
		$pages = ( ! empty($args['pages']) ) ? $args['pages'] : array();
		$descendants = ( ! empty($args['descendants']) ) ? $args['descendants'] : array();
		$already_listed = ( is_array($scoper->page_ids) ) ? $scoper->page_ids : array();
		
		$items = array();
		foreach ( array_keys($pages) as $key ) {
			// we call this without parent criteria to include pages whose parent is unassociable
			if ( $use_parent_clause && $pages[$key]->post_parent != $parent )
				continue;
				
			if ( $descendants && in_array($pages[$key]->ID, array_keys($descendants) ) )
				continue;
				
			if ( $already_listed && in_array($pages[$key]->ID, array_keys($already_listed) ) )
				continue;
				
			$items []= $pages[$key];
		} 
		
		if ( $items ) {
			foreach ( $items as $item ) {
				if ( isset($scoper->page_ids[$item->ID]) )
					continue;
			
				$scoper->page_ids[$item->ID] = true;
			
				// A page cannot be its own parent.
				if ( $object_id && ( $item->ID == $object_id ) )
					continue;

				$pad = str_repeat( '&nbsp;', $level * 3 );
				$current = ( $item->ID == $default) ? ' selected="selected"' : '';
					
				$output .= "\n\t<option value='$item->ID'$current>$pad " . wp_specialchars($item->post_title) . "</option>";
				ScoperAdminUI::walk_parent_dropdown( $output, $args, true, $default, $item->ID, $level +1 );
			}
		} else
			return false;
	}
	
	// object_array = db results 2D array
	function order_by_hierarchy($object_array, $col_id, $col_parent, $id_key = false) {
		$ordered_results = array();
		$find_parent_id = 0;
		$last_parent_id = array();
		
		do {
			$found_match = false;
			$lastcount = count($ordered_results);
			foreach ( $object_array as $key => $item )
				if ( $item->$col_parent == $find_parent_id ) {
					if ( $id_key )
						$ordered_results[$item->$col_id]= $object_array[$key];
					else
						$ordered_results[]= $object_array[$key];
					
					unset($object_array[$key]);
					$last_parent_id[] = $find_parent_id;
					$find_parent_id = $item->$col_id;
					
					$found_match = true;
					break;	
				}
			
			if ( ! $found_match ) {
				if ( ! count($last_parent_id) )
					break;
				else
					$find_parent_id = array_pop($last_parent_id);
			}
		} while ( true );
		
		return $ordered_results;
	}
	
	// listed_objects[object_id] = object, including at least the parent property
	// unlisted_objects[object_id] = object, including at least the parent property
	function add_missing_parents($listed_objects, $unlisted_objects, $col_parent) {
		$need_obj_ids = array();
		foreach ( $listed_objects as $obj )
			if ( $obj->$col_parent && ! isset($listed_objects[ $obj->$col_parent ]) )
				$need_obj_ids[$obj->$col_parent] = true;

		while ( $need_obj_ids ) { // potentially query for several generations of object hierarchy (but only for parents of objects that have roles assigned)
			if ( $need_obj_ids == $last_need )
				break; //precaution

			$last_need = $need_obj_ids;

			if ( $add_objects = array_intersect_key( $unlisted_objects, $need_obj_ids) ) {
				$listed_objects = $listed_objects + $add_objects; // array_merge will not maintain numeric keys
				$unlisted_objects = array_diff_key($unlisted_objects, $add_objects);
			}
			
			$new_need = array();
			foreach ( array_keys($need_obj_ids) as $id ) {
				if ( ! empty($listed_objects[$id]->$col_parent) )  // does this object itself have a nonzero parent?
					$new_need[$listed_objects[$id]->$col_parent] = true;
			}

			$need_obj_ids = $new_need;
		}
		
		return $listed_objects;
	}
	
} // end class ScoperAdminUI
?>