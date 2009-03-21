<?php
if( basename(__FILE__) == basename($_SERVER['SCRIPT_FILENAME']) )
	die( 'This page cannot be called directly.' );

	function scoper_sync_wproles($user_ids = '') {
		global $wpdb, $wp_roles;
		
		if ( $user_ids && ( ! is_array($user_ids) ) )
			$user_ids = array($user_ids);
		
		if ( empty($wp_roles->role_objects) )
			return;
		
		$wp_rolenames = array_keys($wp_roles->role_objects);
		
		$uro_table = $wpdb->user2role2object_rs;
		$groups_table = $wpdb->groups_rs;
		$user2group_table = $wpdb->user2group_rs;
		
		// Delete any role entries for WP roles which were deleted or renamed while Role Scoper was deactivated
		// (users will be re-synched to new role name)
		$name_in = "'" . implode("', '", $wp_rolenames) . "'";
		$qry = "DELETE FROM $uro_table WHERE role_type = 'wp' AND scope = 'blog' AND role_name NOT IN ($name_in)";
		scoper_query($qry);
		
		// also sync WP Role metagroups
		if ( ! empty($user_ids) )
			foreach ( $user_ids as $user_id )
				wpp_cache_delete( $user_id, 'group_membership_for_user' );
		
		$metagroup_ids = array();
		$metagroup_names = array();
		$metagroup_descripts = array();
		foreach ( $wp_rolenames as $role_name ) {
			$metagroup_ids []= "wp_role_{$role_name}";
			$metagroup_names [ "wp_role_{$role_name}" ] = "[WP {$role_name}]";
			$metagroup_descripts[ "wp_role_{$role_name}" ] = sprintf( 'All users with the Wordpress %s blog role', $role_name );
		}
		
		$stored_metagroup_ids = array();
		$qry = "SELECT $wpdb->groups_meta_id_col, $wpdb->groups_id_col, $wpdb->groups_name_col FROM $groups_table WHERE $wpdb->groups_meta_id_col LIKE 'wp_role_%'";
		if ( $results = scoper_get_results($qry) ) {
			//rs_errlog("metagroup results: " . serialize($stored_metagroup_ids)');
		
			$delete_metagroup_ids = array();
			$update_metagroup_ids = array();
					
			foreach ( $results as $row ) {
				if ( ! in_array( $row->{$wpdb->groups_meta_id_col}, $metagroup_ids ) )
					$delete_metagroup_ids []= $row->{$wpdb->groups_id_col};
				else {
					$stored_metagroup_ids []= $row->{$wpdb->groups_meta_id_col};
					
					if ( $row->groups_name_col != $metagroup_names[$row->{$wpdb->groups_meta_id_col}] )
						$update_metagroup_ids[] = $row->{$wpdb->groups_meta_id_col};
				}
			}
			
			if ( $delete_metagroup_ids ) {
				$id_in = "'" . implode("', '", $delete_metagroup_ids) . "'";
				scoper_query( "DELETE FROM $groups_table WHERE $wpdb->groups_id_col IN ($id_in)" );
			}
			
			if ( $update_metagroup_ids ) {
				foreach ( $update_metagroup_ids as $metagroup_id ) {
					if ( $metagroup_id )
						scoper_query( "UPDATE $groups_table SET $wpdb->groups_name_col = '$metagroup_names[$metagroup_id]', $wpdb->groups_descript_col = '$metagroup_descripts[$metagroup_id]' WHERE $wpdb->groups_meta_id_col = '$metagroup_id'" );
				}
			}
		}
		
		if ( $insert_metagroup_ids = array_diff( $metagroup_ids, $stored_metagroup_ids ) ) {
			//rs_errlog("inserting metagroup ids: " . serialize($insert_metagroup_ids)');
		
			foreach ( $insert_metagroup_ids as $metagroup_id ) {
				scoper_query( "INSERT INTO $groups_table ( $wpdb->groups_meta_id_col, $wpdb->groups_name_col, $wpdb->groups_descript_col ) VALUES ( '$metagroup_id', '$metagroup_names[$metagroup_id]', '$metagroup_descripts[$metagroup_id]' )" );
				//rs_errlog( "INSERT INTO $groups_table ( $wpdb->groups_meta_id_col, $wpdb->groups_name_col, $wpdb->groups_descript_col ) VALUES ( '$metagroup_id', '$metagroup_names[$metagroup_id]', '$metagroup_descripts[$metagroup_id]' )" );
			}
		}
		
		if ( ! empty($delete_metagroup_ids) || ! empty($insert_group_ids) || ! empty($update_metagroup_ids) ) {
			wpp_cache_flush_group( 'all_usergroups' );
			wpp_cache_flush_group( 'usergroups_for_groups' );
			wpp_cache_flush_group( 'usergroups_for_user' );
			wpp_cache_flush_group( 'usergroups_for_ug' );
		}

		// Now step through every WP usermeta record, 
		// synchronizing the user's user2role2object_rs blog role entries with their WP role and custom caps

		// get each user's WP roles and caps
		$user_clause = ( $user_ids ) ? 'AND user_id IN (' . implode(', ', $user_ids) . ')' : ''; 
		
		$qry = "SELECT user_id, meta_value FROM $wpdb->usermeta WHERE meta_key = '{$wpdb->prefix}capabilities' $user_clause";
		if ( ! $usermeta = scoper_get_results($qry) )
			return;
	
		//rs_errlog("got " . count($usermeta) . " usermeta records");
			
		$wp_rolecaps = array();
		foreach ( $wp_roles->role_objects as $role_name => $role )
			$wp_rolecaps[$role_name] = $role->capabilities;

		//rs_errlog(serialize($wp_rolecaps));
		
		$strip_vals = array('', 0, false);

		foreach ( array_keys($usermeta) as $key ) {
			$user_id = $usermeta[$key]->user_id;
			$user_caps = maybe_unserialize($usermeta[$key]->meta_value);
			if ( empty($user_caps) || ! is_array($user_caps) )
				continue;
			
			//rs_errlog("user caps: " . serialize($user_caps));
				
			$user_roles = array();
				
			// just in case, strip out any entries with false value
			$user_caps = array_diff($user_caps, $strip_vals);
			
			$user_roles = array( 'wp' => array(), 'wp_cap' => array() );
			
			//Filter out caps that are not role names
			$user_roles['wp'] = array_intersect(array_keys($user_caps), $wp_rolenames);
			
			
			// Store any custom-assigned caps as single-cap roles
			// This will be invisible and only used to support the users query filter
			// With current implementation, the custom cap will only be honored when
			// users_who_can is called with a single capreq 
			$user_roles['wp_cap'] = array_diff( array_keys($user_caps), $user_roles['wp'] );
			

			// which roles are already stored in user2role2object_rs table?
			$stored_roles = array();
			$delete_roles = array();
			foreach ( array_keys($user_roles) as $role_type ) {
				$results = scoper_get_results("SELECT role_name, assignment_id FROM $uro_table WHERE scope = 'blog' AND role_type = '$role_type' AND user_id = '$user_id'");
				if ( $results ) {
					//rs_errlog("results: " . serialize($results));
					foreach ( $results as $row ) {
						// log stored roles, and delete any roles which user no longer has 
						// (maybe WP role changes were made while Role Scoper was deactivated)	
						if ( in_array( $row->role_name, $user_roles[$role_type]) )
							$stored_roles[$role_type] []= $row->role_name;
						else
							$delete_roles []= $row->assignment_id;
					}
				} else
					$stored_roles[$role_type] = array();
			}
			
			if ( $delete_roles ) {
				$id_in = implode(', ', $delete_roles);
				scoper_query("DELETE FROM $uro_table WHERE assignment_id IN ($id_in)");
			}
			
			//rs_errlog("user roles " . serialize($user_roles) ');
			//rs_errlog("stored roles " . serialize($stored_roles)');
			
			// add any missing roles
			foreach ( array_keys($user_roles) as $role_type ) {
				if ( $stored_roles[$role_type] )
					$user_roles[$role_type] = array_diff($user_roles[$role_type], $stored_roles[$role_type]);
				
				if ( $user_roles[$role_type] )
					foreach ( $user_roles[$role_type] as $role_name ) {
						//rs_errlog("INSERT INTO $uro_table (user_id, role_name, role_type, scope) VALUES ('$user_id', '$role_name', '$role_type', 'blog')");
						scoper_query("INSERT INTO $uro_table (user_id, role_name, role_type, scope) VALUES ('$user_id', '$role_name', '$role_type', 'blog')");	
					}
			}
			
		} // end foreach WP usermeta
		

		// TODO: reinstate this after further testing
		/*
		// Delete any role entries for WP metagroups (or other groups) which no longer exists
		if ( ! empty($wpdb->groups_id_col) && ! empty($wpdb->groups_rs) ) {
			if ( $groups_table_valid = scoper_get_var( "SELECT $wpdb->groups_id_col FROM $wpdb->groups_rs LIMIT 1" ) ) {
				$qry = "DELETE FROM $uro_table WHERE group_id >= '1' AND group_id NOT IN ( SELECT $wpdb->groups_id_col FROM $wpdb->groups_rs )";
				//rs_errlog( $qry );
				scoper_query($qry);
			}
		}
		*/
		
		//rs_errlog("finished syncroles "');
		
	} // end scoper_sync_wproles function


	function scoper_fix_page_parent_recursion() {
		global $wpdb;
		$arr_parent = array();
		$arr_children = array();
		
		if ( $results = scoper_get_results("SELECT ID, post_parent FROM $wpdb->posts WHERE post_type = 'page'") ) {
			foreach ( $results as $row ) {
				$arr_parent[$row->ID] = $row->post_parent;
				
				if ( ! isset($arr_children[$row->post_parent]) )
					$arr_children[$row->post_parent] = array();
					
				$arr_children[$row->post_parent] []= $row->ID;
			}
			
			// if a page's parent is also one of its children, set parent to Main
			foreach ( $arr_parent as $page_id => $parent_id )
				if ( isset($arr_children[$page_id]) && in_array($parent_id, $arr_children[$page_id]) )
					scoper_query("UPDATE $wpdb->posts SET post_parent = '0' WHERE ID = '$page_id'");
		}
	}
?>