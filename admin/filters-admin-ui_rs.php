<?php

if( basename(__FILE__) == basename($_SERVER['SCRIPT_FILENAME']) )
	die();

require_once( 'admin_lib_rs.php' );

/**
 * ScoperAdminFiltersUI PHP class for the WordPress plugin Role Scoper
 * filters-admin-ui_rs.php
 * 
 * @author 		Kevin Behrens
 * @copyright 	Copyright 2009
 * 
 */
class ScoperAdminFiltersUI
{
	var $scoper;
	var $impose_pending_rev;
	var $item_filters;
	
	function ScoperAdminFiltersUI() {
		global $scoper;
		$this->scoper =& $scoper;
		
		$current_script = $_SERVER['SCRIPT_NAME'];							// possible todo: separate file for term edit
		$item_edit_scripts = apply_filters( 'item_edit_scripts_rs', array('p-admin/post-new.php', 'p-admin/post.php', 'p-admin/page.php', 'p-admin/page-new.php', 'p-admin/categories.php') );
		$item_edit_scripts []= 'p-admin/admin-ajax.php';

		foreach( $item_edit_scripts as $edit_script ) {
			if ( strpos( $current_script, $edit_script ) ) {
				require_once( 'filters-admin-ui-item_rs.php' );
				$scoper->filters_admin_item_ui = new ScoperAdminFiltersItemUI();
				break;
			}
		}

		if ( awp_ver('2.7-dev') )
			add_action( '_admin_menu', array(&$this, 'act_admin_menu') );
		else {
			require_once( 'filters-admin-legacy_rs.php' );
			add_action( '_admin_menu', array('ScoperAdminFilters_Legacy', 'act_admin_menu_26') );
		}
		
		add_action('admin_head', array(&$this, 'ui_hide_admin_divs') );

		if ( is_administrator_rs() || strpos($_SERVER['SCRIPT_NAME'], SCOPER_FOLDER) )
			add_action('in_admin_footer', array(&$this, 'ui_admin_footer') );
		
		// WP 2.5 throws a notice if plugins add their own hooks without prepping the global array
		// Source or taxonomy-specific hooks are mapped to these based on config member properties
		// in WP_Scoped_Data_Sources::process() and WP_Scoped_Taxonomies:process()
		$setargs = array( 'is_global' => true );
		$setvars = array ('edit_group_profile_rs');
		awp_force_set('wp_filter', array(), $setargs, $setvars, 10);

		if ( GROUP_ROLES_RS ) {
			add_action('show_user_profile', array(&$this, 'ui_user_groups'), 2);
			add_action('edit_user_profile', array(&$this, 'ui_user_groups'), 2);
			add_action('edit_group_profile_rs', array(&$this, 'ui_group_roles'), 10);
		}
		
		add_action('show_user_profile', array(&$this, 'ui_user_roles'), 2);
		add_action('edit_user_profile', array(&$this, 'ui_user_roles'), 2);
		
		if ( awp_ver('2.6') && scoper_get_option('pending_revisions') ) {
			// special filtering to support Contrib editing of published posts/pages to revision
			add_filter('pre_post_status', array(&$this, 'flt_pendingrev_post_status') );
			add_action('pre_post_update', array(&$this, 'act_impose_pending_rev'), 2 );
			add_action('wp_restore_post_revision', array(&$this, 'act_restore_post_revision') );
		}
		
		$script_name = $_SERVER['SCRIPT_NAME'];
		
		if ( strpos($_SERVER['SCRIPT_NAME'], 'role-management.php') && empty($_POST) )
			add_filter( 'capabilities_list', array(&$this, 'flt_capabilities_list') );	// capabilities_list is a Role Manager hook
		
		//temporary solution to alert user of filtering performed on saved page - see corresponding code in hardway-admin
		if ( strpos($script_name, 'p-admin/page.php') || strpos($script_name, 'p-admin/page-new.php') || strpos($script_name, 'p-admin/edit-pages.php') ) {
			global $current_user;
			if ( $notice = get_option("scoper_notice_{$current_user->ID}") ) {
				delete_option( "scoper_notice_{$current_user->ID}" );
				rs_notice($notice);
			}
		}
	} // end class constructor
	
	// remove "Write Post" / "Write Page" menu items if user only has role for certain existing objects
	function act_admin_menu() {
		global $submenu;

		if ( isset($submenu['edit.php'][10]) ) {
			$this->scoper->ignore_object_roles = true;
			if ( ! current_user_can( 'edit_posts' ) )
				unset( $submenu['edit.php'][10] );

			$this->scoper->ignore_object_roles = false;
		}
		
		if ( isset($submenu['edit-pages.php'][10]) ) {
			$this->scoper->ignore_object_roles = true;
			if ( ! current_user_can( 'edit_pages' ) )
				unset( $submenu['edit-pages.php'][10] );

			$this->scoper->ignore_object_roles = false;
		}
		
		if ( isset($submenu['link-manager.php'][10]) ) {
			$this->scoper->ignore_object_roles = true;
			if ( ! current_user_can( 'manage_links' ) )
				unset( $submenu['link-manager.php'][10] );

			$this->scoper->ignore_object_roles = false;
		}
	}
	
	function ui_hide_admin_divs() {
		if ( is_administrator_rs() )
			return;
	
		// Determine data source based on URI
		// However, this will only consider URIs included in Scoped_Data_Source->users_where_reqd_caps
		if ( ! ( $context = $this->scoper->admin->get_context() ) || empty($context->source ) || empty($context->object_type_def ) )
			return;

		$src_name = $context->source->name;
		$object_type = $context->object_type_def->name;

		// confirm that user isn't editing own object
		if ( ! $col_owner = $this->scoper->data_sources->member_property($src_name, 'cols', 'owner') )
			return;
			
		// For this data source, is there any html content to hide from non-administrators?
		$css_ids = scoper_get_otype_option('admin_css_ids', $src_name, $object_type);
		$css_ids = str_replace(' ', '', $css_ids);
		$css_ids = str_replace(',', ';', $css_ids);
		$css_ids = explode(';', $css_ids);	// option storage is as semicolon-delimited string
	
		$object_id = $this->scoper->data_sources->detect('id', $src_name, '', $object_type);

		$blogwide_edit = $this->scoper->admin->user_can_edit_blogwide($src_name, $object_type, OP_EDIT_RS);

		if ( $blogwide_edit ) {
			// don't hide anything if user is creating a new object
			if ( ! $object_id )
				return;
				
			if ( ! $object = $this->scoper->data_sources->get_object($src_name, $object_id) )
				return;
			
			// don't hide anything if a user is editing their own object
			global $current_user;
			if ( empty($object->$col_owner) || ( $object->$col_owner == $current_user->ID) )
				return;
		}

		if ( ! $blogwide_edit && ('post' == $src_name) ) {
			$object = $this->scoper->data_sources->get_object($src_name, $object_id);

			if ( ! empty($object->post_date) ) // don't prevent the full editing of new posts/pages
				if ( scoper_get_option('hide_non_editor_admin_divs') )
					$force_hide = true;
		}

		if ( ! empty($force_hide) || ! $this->scoper->admin->user_can_admin_object($src_name, $object_type, $object_id) ) {
			if ( ! awp_ver('2.7-dev') )	// side-info with related posts, links, etc.
				echo "\n<!-- " . __('Role Scoper Plugin CSS:') . " -->\n<style type='text/css'>\n<!--\n.side-info { display: none !important; }\n-->\n</style>\n";	
		
			foreach ( $css_ids as $id )
				echo "\n<!-- " . __('Role Scoper Plugin CSS:') . " -->\n<style type='text/css'>\n<!--\n#$id { display: none !important; }\n-->\n</style>\n";  // this line adapted from Clutter Free plugin by Mark Jaquith
		}
	}

	function ui_admin_footer() {
		echo '<span style="float:right; margin-left: 2em"><a href="http://agapetry.net/">' . __('Role Scoper', 'scoper') . '</a> ' . SCOPER_VERSION . ' | ' . '<a href="http://agapetry.net/forum/">' . __('Support Forum', 'scoper') . '</a>&nbsp;</span>';
	}
	
	function ui_user_groups() {
		// todo: make this an option
		//if ( strpos( $_SERVER['SCRIPT_NAME'], 'profile.php') && ! is_administrator_rs() )
			//return;
	
		if ( ! is_administrator_rs() && ! scoper_get_option( 'display_user_profile_groups' ) )
			return;
			
		if ( ! $all_groups = ScoperAdminLib::get_all_groups(UNFILTERED_RS) )
			return;

		global $current_user, $profileuser;
		$user_id = $profileuser->id;
		
		$editable_ids = ScoperAdminLib::get_all_groups(FILTERED_RS, COL_ID_RS);
		
		// can't manually edit membership of WP Roles groups, other metagroups
		foreach ( $all_groups as $key => $group )
			if ( ! empty($group->meta_id) && in_array( $group->ID, $editable_ids ) )
				$editable_ids = array_diff( $editable_ids, array($group->ID) );

		if ( $user_id == $current_user->ID )
			$stored_groups = array_keys($current_user->groups);
		else {
			$user = new WP_Scoped_User($user_id, '', array( 'skip_role_merge' => 1 ) );
			$stored_groups = array_keys($user->groups);
		}
		
		if ( ! $editable_ids && ! $stored_groups )
			return;
		
		echo "<div id='userprofile_groupsdiv_rs' class='rs-group_members'>";
		echo "<h3>" . __('User Groups', 'scoper') . "</h3>";
		
		$css_id = 'group';
		
		$locked_ids = array_diff($stored_groups, $editable_ids );
		$args = array( 'suppress_extra_prefix' => true, 'eligible_ids' => $editable_ids, 'locked_ids' => $locked_ids );
		
		require_once('agents_checklist_rs.php');
 		ScoperAgentsChecklist::agents_checklist( ROLE_BASIS_GROUPS, $all_groups, $css_id, array_flip($stored_groups), $args);
		
		echo '</fieldset>';
		echo '</div>';
	} // end function act_edit_user_groups
	

	function ui_group_roles($group_id) {
		if ( ! $users = ScoperAdminLib::get_group_members($group_id, COL_ID_RS) )
			return;
			
		$args = array('disable_user_roles' => true, 'filter_usergroups' => array($group_id => true), 'disable_wp_roles' => true );
		$user = new WP_Scoped_User($users[0], '', $args);
		
		include_once('profile_ui_rs.php');
		ScoperProfileUI::display_ui_user_roles($user, true);  //arg: groups only
	}
	
	function ui_user_roles() {
		global $profileuser, $current_user;
		
		// todo: make this an option
		//if ( strpos( $_SERVER['SCRIPT_NAME'], 'profile.php') && ! is_administrator_rs() )
		//	return;
		
		$profile_user_rs = ( $profileuser->ID == $current_user->ID ) ? $current_user : new WP_Scoped_User($profileuser->ID);

		include_once('profile_ui_rs.php');
		ScoperProfileUI::display_ui_user_roles($profile_user_rs);
	}
	
	
	function flt_pendingrev_post_status($status) {
		if ( is_administrator_rs() )
			return $status;
		
		if ( isset($_POST['post_ID']) && isset($_POST['post_type']) ) {
			$post_id = $_POST['post_ID'];

			if ( $object = $this->scoper->data_sources->get_object('post', $post_id) ) {

				if ( isset( $object->post_status ) && in_array( $object->post_status, array('publish', 'private') ) ) {
					$status_name = ( 'private' == $object->post_status ) ? 'private' : 'published';
					$cap_name = "edit_{$status_name}_{$_POST['post_type']}s";
					
					global $check_for_pending_rev;
					$check_for_pending_rev = true;
					
					//$this->scoper->hascap_object_ids = array();
					
					if ( ! current_user_can($cap_name, $post_id) ) {
						$this->impose_pending_rev = $post_id;
					}
				}
			}
		}
		
		return $status;
	}
	
	function act_impose_pending_rev() {
		if ( ! empty($this->impose_pending_rev) ) {
			$object_id = $this->impose_pending_rev;
			$post_arr = $_POST;
			
			$object_type = isset($post_arr['post_type']) ? $post_arr['post_type'] : '';
		
			$post_arr['post_type'] = 'revision';
			$post_arr['post_status'] = 'pending';
			$post_arr['post_parent'] = $this->impose_pending_rev;  // side effect: don't need to filter page parent selection because parent is set to published revision
			$post_arr['post_ID'] = 0;
			$post_arr['ID'] = 0;
			$post_arr['guid'] = '';
			
			if ( isset($post_arr['post_category']) )	// todo: also filter other post taxonomies
				$post_arr['post_category'] = $this->scoper->filters_admin->flt_pre_object_terms($post_arr['post_category'], 'category');

			global $current_user;
			$post_arr['post_author'] = $current_user->ID;		// store current user as revision author (but will retain current post_author on restoration)
			$post_arr['post_date'] = current_time( 'mysql' );
			$post_arr['post_date_gmt'] = current_time( 'mysql', 1 );
			$post_arr['post_modified'] = current_time( 'mysql' );
			$post_arr['post_modified_gmt'] = current_time( 'mysql', 1 );

			unset($this->impose_pending_rev);
			wp_insert_post($post_arr);
			
			global $wpdb;
			$qry = "SELECT ID FROM $wpdb->posts WHERE post_title = '" . $post_arr['post_title'] . "' ORDER BY ID DESC LIMIT 1";
			$rev_id = $wpdb->get_var( $qry );
			
			scoper_query("UPDATE $wpdb->posts SET post_status = 'pending', post_parent = '$this->impose_pending_rev' WHERE ID = '$rev_id'");

			$this->impose_pending_rev = $object_id;
			
			$site_url = get_option('siteurl');
			$manage_uri = ( 'page' == $object_type ) ? 'edit-pages.php' : 'edit.php';
			$msg = __('Your modification has been saved for editorial review.', 'scoper') . ' ';
			if ( 'page' == $object_type )
				$msg .= sprintf( __('You may hit the back button to continue editing, or <a href="%s">Return to Manage Pages</a>.', 'scoper'), "$site_url/wp-admin/$manage_uri");
			else
				$msg .= sprintf( __('You may hit the back button to continue editing, or <a href="%s">Return to Manage Posts</a>.', 'scoper'), "$site_url/wp-admin/$manage_uri");
			
			$msg .= '<br /><br />' . __('Note that due to technical limitations, you <strong>cannot</strong> come back later and further edit an unpublished revision.  Be sure to save your changes offline if you need to revise again before editorial approval.', 'scoper');
				
			wp_die( $msg );
		}
	}
	
	// when one "pending" revision is "restored", clear pending status of all others for this post 
	function act_restore_post_revision($post_id) {
		global $wpdb;
		scoper_query("UPDATE $wpdb->posts SET post_status = 'inherit' WHERE post_type = 'revision' AND post_status = 'pending' AND post_parent = '$post_id'");
	}
	
	function flt_capabilities_list($unused) {
		echo '<p>';
		_e("<b>Note:</b> Role Manager settings determine each user's default blog-wide capabilities. Since the Role Scoper plugin is also enabled, <b>Term-specific or Object-specific Role Assignments</b> may increase or decrease a user's actual capabilities.", 'scoper');
		echo '</p>';

		return $unused;
	}
}

?>