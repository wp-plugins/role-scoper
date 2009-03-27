<?php
if( basename(__FILE__) == basename($_SERVER['SCRIPT_FILENAME']) ) 
	die('This page cannot be called directly.');

/* this file adapted from:
 Group Restriction plugin
 http://code.google.com/p/wp-group-restriction/
 Tiago Pocinho, Siemens Networks, S.A.
 
 some group-related functions also moved to ScoperAdminLib with slight adaptation
 */

class UserGroups_tp {
	function getUsersWithGroup($group_id) {
		return ScoperAdminLib::get_group_members($group_id);
	}
	
	function addGroupMembers ($group_id, $user_ids){
		ScoperAdminLib::add_group_user($group_id, $user_ids);
	}
	
	function deleteGroupMembers ($group_id, $user_ids) {
		ScoperAdminLib::remove_group_user($group_id, $user_ids);
	}
		
	/**
	 * Gets a group with a given identifier
	 *
	 * @param int $id - Group Identifier
	 * @return Object An object with the group details
	 **/
	function getGroup($group_id) {
		global $wpdb;

		$query = "SELECT $wpdb->groups_id_col AS ID, $wpdb->groups_name_col AS display_name, $wpdb->groups_descript_col as descript, $wpdb->groups_meta_id_col as meta_id"
				. " FROM $wpdb->groups_rs WHERE $wpdb->groups_id_col='$group_id'";

		$results = scoper_get_results( $query );
		if(isset($results) && isset($results[0]))
		return $results[0];
	}

	/**
	 * Gets a group with a given name
	 *
	 * @param string $name - Group Name
	 * @return Object An object with the group details
	 **/
	function getGroupByName($name) {
		global $wpdb;

		$query = "SELECT $wpdb->groups_id_col AS ID, $wpdb->groups_name_col AS display_name, $wpdb->groups_descript_col as descript "
				. " FROM $wpdb->groups_rs WHERE $wpdb->groups_name_col='$name'";

		$result = scoper_get_row( $query );
		return $result;
	}

	/**
	 * Removes a given group
	 *
	 * @param int $id - Identifier of the group to delete
	 * @param boolean True if the deletion is successful
	 **/
	function deleteGroup ($group_id){
		global $wpdb;

		$role_type = SCOPER_ROLE_TYPE;
		
		if( ! $group_id || ! UserGroups_tp::getGroup($group_id) )
			return false;

		do_action('delete_group_rs', $group_id);
		
		wpp_cache_flush_group( 'all_usergroups' );
		wpp_cache_flush_group( 'group_members' );
		wpp_cache_flush_group( 'usergroups_for_user' );
		wpp_cache_flush_group( 'usergroups_for_groups' );
		wpp_cache_flush_group( 'usergroups_for_ug' );
		
		// first delete all cache entries related to this group
		if ( $group_members = ScoperAdminLib::get_group_members( $group_id, COL_ID_RS ) ) {
			$id_in = "'" . implode("', '", $group_members) . "'";
			$any_user_roles = scoper_get_var("SELECT assignment_id FROM $wpdb->user2role2object_rs WHERE role_type = '$role_type' AND user_id IN ($id_in) LIMIT 1");
			
			foreach ($group_members as $user_id )
				wpp_cache_delete( $user_id, 'group_membership_for_user' );
			
			if ( $got_blogrole = scoper_get_var("SELECT assignment_id FROM $wpdb->user2role2object_rs WHERE scope = 'blog' AND role_type = '$role_type' AND group_id = '$group_id' LIMIT 1") ) {
				scoper_query("DELETE FROM $wpdb->user2role2object_rs WHERE scope = 'blog' AND role_type = '$role_type' AND group_id = '$group_id'");
			
				scoper_flush_roles_cache( BLOG_SCOPE_RS, ROLE_BASIS_GROUPS );
				
				if ( $any_user_roles )
					scoper_flush_roles_cache( BLOG_SCOPE_RS, ROLE_BASIS_USER_AND_GROUPS, $group_members );
			}
			
			if ( $got_taxonomyrole = scoper_get_var("SELECT assignment_id FROM $wpdb->user2role2object_rs WHERE scope = 'term' AND role_type = '$role_type' AND group_id = '$group_id' LIMIT 1") ) {
				scoper_query("DELETE FROM $wpdb->user2role2object_rs WHERE scope = 'term' AND role_type = '$role_type' AND group_id = '$group_id'");
			
				scoper_flush_roles_cache( TERM_SCOPE_RS, ROLE_BASIS_GROUPS );
				
				if ( $any_user_roles )
					scoper_flush_roles_cache( TERM_SCOPE_RS, ROLE_BASIS_USER_AND_GROUPS, $group_members );
			}
			
			if ( $got_objectrole = scoper_get_var("SELECT assignment_id FROM $wpdb->user2role2object_rs WHERE scope = 'object' AND role_type = '$role_type' AND group_id = '$group_id' LIMIT 1") ) {
				scoper_query("DELETE FROM $wpdb->user2role2object_rs WHERE scope = 'object' AND role_type = '$role_type' AND group_id = '$group_id'");

				scoper_flush_roles_cache( OBJECT_SCOPE_RS, ROLE_BASIS_GROUPS );
				
				if ( $any_user_roles )
					scoper_flush_roles_cache( OBJECT_SCOPE_RS, ROLE_BASIS_USER_AND_GROUPS, $group_members );
			}
			
			if ( $got_blogrole || $got_taxonomyrole || $got_objectrole ) {
				scoper_flush_results_cache( ROLE_BASIS_GROUPS );
				
				if ( $any_user_roles )
					scoper_flush_results_cache( ROLE_BASIS_USER_AND_GROUPS, $group_members );
			}
		}
		
		$delete = "DELETE FROM $wpdb->groups_rs WHERE $wpdb->groups_id_col='$group_id'";
		scoper_query( $delete );

		$delete = "DELETE FROM $wpdb->user2group_rs WHERE $wpdb->user2group_gid_col='$group_id'";
		scoper_query( $delete );
		
		return true;
	}

	/**
	 * Checks if a group with a given name exists
	 *
	 * @param string $name - Name of the group to test
	 * @return boolean True if the group exists, false otherwise.
	 **/
	function groupExists($name) {
		global $wpdb;

		$query = "SELECT COUNT(*) FROM $wpdb->groups_rs WHERE $wpdb->groups_name_col = '$name'";
		$results = scoper_get_var( $query );
		
		return $results != 0;
	}
	
	/**
	 * Verifies if a group name is valid (for a new group)
	 *
	 * @param string $string - Name of the group
	 * @return boolean True if the name is valid, false otherwise.
	 **/
	function isValidName($string){
		if($string == "" || UserGroups_tp::groupExists($string)){
			return false;
		}
		return true;
	}
	
	/**
	 * Creates a new Group
	 *
	 * @param string $name - Name of the group
	 * @param string $description - Group description (optional)
	 * @return boolean True on successful creation
	 **/
	function createGroup ($name, $description = ''){
		global $wpdb;

		if(!UserGroups_tp::isValidName($name))
			return false;

		$insert = "INSERT INTO $wpdb->groups_rs ($wpdb->groups_name_col, $wpdb->groups_descript_col) VALUES ('$name','$description')";
		scoper_query( $insert );

		wpp_cache_flush_group('all_usergroups');
		wpp_cache_flush_group( 'group_members' );
		wpp_cache_flush_group('usergroups_for_user');
		wpp_cache_flush_group('usergroups_for_groups');
		wpp_cache_flush_group('usergroups_for_ug');
		
		do_action('created_group_rs', (int) $wpdb->insert_id);
		
		return true;
	}

	/**
	 * Updates an existing Group
	 *
	 * @param int $groupID - Group identifier
	 * @param string $name - Name of the group
	 * @param string $description - Group description (optional)
	 * @return boolean True on successful update
	 **/
	function updateGroup ($group_id, $name, $description = ''){
		global $wpdb;

		$description = strip_tags($description);

		$prevName = scoper_get_var("SELECT $wpdb->groups_name_col FROM $wpdb->groups_rs WHERE $wpdb->groups_id_col='$group_id';");

		if( ($prevName != $name) && ! UserGroups_tp::isValidName($name))
			return false;
		
		do_action('update_group_rs', $group_id);
			
		$query = "UPDATE $wpdb->groups_rs SET $wpdb->groups_name_col = '$name', $wpdb->groups_descript_col='$description' WHERE $wpdb->groups_id_col='$group_id';";
		scoper_query( $query );

		wpp_cache_flush_group('all_usergroups');
		wpp_cache_flush_group( 'group_members' );
		wpp_cache_flush_group('usergroups_for_user');
		wpp_cache_flush_group('usergroups_for_groups');
		wpp_cache_flush_group('usergroups_for_ug');
		
		return true;
	}

	
	// Called once each for members checklist, managers checklist in admin UI.
	// In either case, current (checked) members are at the top of the list.
	function group_members_checklist( $group_id, $user_class = 'member', $all_users = '' ) {
		global $scoper;
		
		if ( ! $all_users )
			$all_users = $scoper->users_who_can('', COLS_ID_DISPLAYNAME_RS);
		
		if ( 'manager' == $user_class ) {
			if ( $group_id ) {
				$group_role_defs = $scoper->role_defs->qualify_roles( 'manage_groups');

				require_once('role_assignment_lib_rs.php');
				$current_roles = ScoperRoleAssignments::organize_assigned_roles(OBJECT_SCOPE_RS, 'group', $group_id, array_keys($group_role_defs), ROLE_BASIS_USER);

				$current_roles = agp_array_flatten($current_roles, false);
				
				$current_ids = ( isset($current_roles['assigned']) ) ? $current_roles['assigned'] : array();
			} else
				$current_ids = array();
			
			$admin_ids = $scoper->users_who_can( 'activate_plugins', COL_ID_RS );
	
			$eligible_ids = scoper_get_option('role_admin_blogwide_editor_only') ? $scoper->users_who_can('edit_posts', COL_ID_RS) : '';

		} else {
			$current_ids = ($group_id) ? array_flip(ScoperAdminLib::get_group_members($group_id, COL_ID_RS)) : array();
			$eligible_ids = $scoper->users_who_can('', COL_ID_RS);
			$admin_ids = array();
		}
		
		$css_id = ( 'manager' == $user_class ) ? 'manager' : 'member';
		$args = array( 'eligible_ids' => $eligible_ids, 'via_other_scope_ids' => $admin_ids, 'suppress_extra_prefix' => true );
 		require_once('agents_checklist_rs.php');
		ScoperAgentsChecklist::agents_checklist( ROLE_BASIS_USER, $all_users, $css_id, $current_ids, $args);
	}
	
	/**
	 * Writes the success/error messages
	 * @param string $string - message to be displayed
	 * @param boolean $success - boolean that defines if is a success(true) or error(false) message
	 **/
	function write($string, $success=true, $id="message"){
		if($success){
			echo '<div id="'.$id.'" class="updated fade"><p>'.$string.'</p></div>';
		}else{
			echo '<div id="'.$id.'" class="error fade"><p>'.$string.'</p></div>';
		}
	}
}

?>
