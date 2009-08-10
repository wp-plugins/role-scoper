<?php
if( basename(__FILE__) == basename($_SERVER['SCRIPT_FILENAME']) )
	die( 'This page cannot be called directly.' );
	
function scoper_object_roles_list( $viewing_user ) {

if ( ! USER_ROLES_RS && ! GROUP_ROLES_RS )
	wp_die(__('Cheatin&#8217; uh?'));

global $scoper, $wpdb, $current_user;

if ( $viewing_user ) {
	if ( ! is_object($viewing_user) ) {
		global $current_user;
		if ( $viewing_user == $current_user->ID )
			$viewing_user = $current_user;
		else
			$viewing_user = new WP_Scoped_User($viewing_user);
	}
}

$all_roles = array();
$role_display = array();
foreach ( $scoper->role_defs->get_all() as $role_handle => $role_def ) {
	if ( $viewing_user )
		$role_display[$role_handle] = ( empty($role_def->display_name_for_object_ui) ) ? $role_def->display_name : $role_def->display_name_for_object_ui;
	else
		$role_display[$role_handle] = ( empty($role_def->abbrev_for_object_ui) ) ? $role_def->abbrev : $role_def->abbrev_for_object_ui;
}

$require_blogwide_editor = scoper_get_option('role_admin_blogwide_editor_only');

foreach ( $scoper->data_sources->get_all() as $src_name => $src) {
	$otype_count = 0;	
	
	if ( ! empty($src->taxonomy_only) || ( ($src_name == 'group') && ! $viewing_user ) )
		continue;

	$strict_objects = $scoper->get_restrictions(OBJECT_SCOPE_RS, $src_name);
	
	foreach ( $src->object_types as $object_type => $otype ) {
		$otype_count++;
	
		$disable_role_admin = false;
		if ( $require_blogwide_editor ) {
			$required_cap = ( 'page' == $object_type ) ? 'edit_others_pages' : 'edit_others_posts';

			global $current_user;
			if ( empty( $current_user->allcaps[$required_cap] ) )
				$disable_role_admin = true;
		}
		
		if ( ! empty($src->cols->type) && ! empty($otype->val) ) {
			$col_type = $src->cols->type;
			$otype_clause = "AND $src->table.$col_type = '$otype->val'";
		} elseif ( $otype_count < 2 )
			$otype_clause = '';
		else
			continue;
		
		$col_id = $src->cols->id;
		$col_name = $src->cols->name;
		$SCOPER_ROLE_TYPE = SCOPER_ROLE_TYPE;
		
		$ug_clause_for_user_being_viewed = ( $viewing_user ) ? $viewing_user->get_user_clause('uro') : '';
		
		$qry = "SELECT DISTINCT $src->table.$col_name, $src->table.$col_id, uro.role_name"
			. " FROM $src->table ";
		
		$join = " INNER JOIN $wpdb->user2role2object_rs AS uro"
			. " ON uro.obj_or_term_id = $src->table.$col_id"
			. " AND uro.src_or_tx_name = '$src_name'"
			. " AND uro.scope = 'object' AND uro.role_type = '$SCOPER_ROLE_TYPE'";
		
		$where = " WHERE 1=1 $otype_clause $ug_clause_for_user_being_viewed";
		$orderby = " ORDER BY $src->table.$col_name ASC, uro.role_name ASC";
		
		$qry .= $join . $where . $orderby;
		
		$results = scoper_get_results( $qry );
		
		if ( ! is_administrator_rs() ) {  // no need to filter admins - just query the assignments	
		
			// only list role assignments which the logged-in user can administer
			if ( isset($src->reqd_caps[OP_ADMIN_RS]) ) {
				$args['required_operation'] = OP_ADMIN_RS;
			} else {
				$reqd_caps = array();
				foreach (array_keys($src->statuses) as $status_name) {
					$admin_caps = $scoper->cap_defs->get_matching($src_name, $object_type, OP_ADMIN_RS, $status_name);
					$delete_caps = $scoper->cap_defs->get_matching($src_name, $object_type, OP_DELETE_RS, $status_name);
					$reqd_caps[$object_type][$status_name] = array_merge(array_keys($admin_caps), array_keys($delete_caps));
				}
				$args['force_reqd_caps'] = $reqd_caps;
			}
			
			$qry = "SELECT DISTINCT $src->table.$col_id FROM $src->table WHERE 1=1";
			
			$args['require_full_object_role'] = true;
			$qry_flt = apply_filters('objects_request_rs', $qry, $src_name, $object_type, $args);
			$cu_admin_results = scoper_get_col( $qry_flt );
			
			if ( empty($viewing_user) || ( $current_user->ID != $viewing_user->ID ) ) {
				foreach ( $results as $key => $row )
					if ( ! in_array( $row->$col_id, $cu_admin_results) )
						unset($results[$key]);
			} else {
				// for current user's view of their own user profile, just de-link unadminable objects
				$link_roles = array();
				$link_objects = array();
				
				if ( ! $disable_role_admin ) {
					foreach ( $results as $key => $row )
						if ( in_array( $row->$col_id, $cu_admin_results) )
							$link_roles[$row->$col_id] = true;
							
					$args['required_operation'] = OP_EDIT_RS;
					$args['require_full_object_role'] = false;
					if ( isset($args['force_reqd_caps']) ) unset($args['force_reqd_caps']);
					$qry_flt = apply_filters('objects_request_rs', $qry, $src_name, $object_type, $args);
					$cu_edit_results = scoper_get_col( $qry_flt );
					
					foreach ( $results as $key => $row )
						if ( in_array( $row->$col_id, $cu_edit_results) )
							$link_objects[$row->$col_id] = true;
				}
			}
		}
		
		$object_roles = array();
		$objnames = array();
		
		if ( $results ) {
			if ( ! $disable_role_admin && ( is_administrator_rs() || $cu_admin_results ) ) {
				$url = SCOPER_ADMIN_URL . "/roles/$src_name/$object_type";
				echo "<h4><a name='$object_type' href='$url'><b>" . sprintf( _c('%s Roles:|Post/Page Roles', 'scoper'), $otype->display_name) . "</b></a></h4>";
			} else
				echo "<h4><b>" . sprintf( _c('%s Roles:|Post/Page Roles', 'scoper'), $otype->display_name) . "</b></h4>";

			$got_object_roles = true;
		
			foreach ( $results as $result ) {
				if ( ! isset($objnames[ $result->$col_id ]) ) {
					if ( 'post' == $src->name )
						$objnames[ $result->$col_id ] = apply_filters( 'the_title', $result->$col_name, $result->$col_id);
					else
						$objnames[ $result->$col_id ] = $result->$col_name;
				}
				
				$role_handle = SCOPER_ROLE_TYPE . '_' . $result->role_name;
				$object_roles[ $result->$col_id ] [ $role_handle ] = true;
			}
		} else
			continue;
		
		?>
<ul class="rs-termlist"><li>
<table class='widefat'>
<thead>
<tr class="thead">
	<th class="rs-tightcol"><?php _e('ID');?></th>
	<th><?php _e('Name');?></th>
	<th><?php _e('Role Assignments', 'scoper');?></th>
</tr>
</thead>
<tbody id="roles-<?php echo $role_codes[$role_handle]; ?>"><?php
		$style = ' class="rs-backwhite"';

		$title_roles = __('edit roles', 'scoper');
		$title_item = sprintf(_c('edit %s|post/page/category/etc.', 'scoper'), strtolower($otype->display_name) );
		
		foreach ( $object_roles as $obj_id => $roles ) {
			$object_name = attribute_escape($objnames[$obj_id]);
	
			echo "\n\t<tr$style>";
			
			$link_this_object = ( ! isset($link_objects) || isset($link_objects[$obj_id]) );
			
			// link from object ID to the object type's default editor, if defined
			if ( $link_this_object && ! empty($src->edit_url) ) {
				$src_edit_url = sprintf($src->edit_url, $obj_id);
				echo "<td><a href='$src_edit_url' class='edit' title='$title_item'>$obj_id</a></td>";
			} else
				echo "<td>$obj_id</td>";
				
			// link from object name to our "Edit Object Role Assignment" interface
			$link_this_role = ( ! isset($link_roles) || isset($link_roles[$obj_id]) );
			
			if ( $link_this_role ) {
				$rs_edit_url = SCOPER_ADMIN_URL . "/object_role_edit.php&amp;src_name=$src_name&amp;object_type=$object_type&amp;object_id=$obj_id&amp;object_name=$object_name";
				echo "\n\t<td><a href='$rs_edit_url' title='$title_roles'>{$objnames[$obj_id]}</a></td>";
			} else
				echo "\n\t<td>{$objnames[$obj_id]}</td>";
			
			echo "<td>";
			
			$role_list = array();
			foreach ( array_keys($roles) as $role_handle ) {
				// roles which require object assignment are asterisked (bolding would contradict the notation of term roles list, where propogating roles are bolded)
				if ( isset($strict_objects['restrictions'][$role_handle][$obj_id]) 
				|| ( isset($strict_objects['unrestrictions'][$role_handle]) && is_array($strict_objects['unrestrictions'][$role_handle]) && ! isset($strict_objects['unrestrictions'][$role_handle][$obj_id]) ) )
					$role_list[] = "<span class='rs-backylw'>" . $role_display[$role_handle] . '</span>';
				else
					$role_list[] = $role_display[$role_handle];
			}
			
			echo( implode(', ', $role_list) );
			echo '</td></tr>';

			$style = ( ' class="alternate"' == $style ) ? ' class="rs-backwhite"' : ' class="alternate"';
		} // end foreach object_roles
		
		echo '</tbody></table>';
		echo '</li></ul><br />';
	} // end foreach object_types
	
} // end foreach data source

 return ! empty($got_object_roles);

} // end wrapper function

?>