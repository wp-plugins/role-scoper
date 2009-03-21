<?php

if( basename(__FILE__) == basename($_SERVER['SCRIPT_FILENAME']) )
	die();

if ( ! awp_ver('2.7-dev') )
	require_once('hardway-admin-legacy_rs.php');
	
global $scoper;

// WP 2.5 - 2.7 autosave wipes out page parent. WP 2.5 autosave sets author to current user
if ( isset($_POST['action']) && ($_POST['action'] == 'autosave') && isset($_POST['post_type']) )
	add_filter('query', array('ScoperAdminHardway', 'flt_autosave_bugstomper') );

if ( is_admin() ) {
	$nomess_uris = apply_filters( 'scoper_skip_lastresort_filter_uris', array( 'p-admin/categories.php', 'p-admin/themes.php', 'p-admin/plugins.php', 'p-admin/profile.php' ) );

	// filter users list for edit-capable users as a convenience to administrator
	// URIs ending in specified filename will not be subjected to low-level query filtering
	if ( ! agp_string_ends_in($_SERVER['REQUEST_URI'], $nomess_uris ) )
		add_filter('query', array('ScoperAdminHardway', 'flt_editable_user_ids') );
	
	// wrapper to users_where filter for "post author" / "page author" dropdown (limit to users who have appropriate caps)
  	add_filter('get_editable_authors', array('ScoperAdminHardway', 'flt_get_editable_authors'), 50, 1);
 
  	add_filter('wp_dropdown_users', array('ScoperAdminHardway', 'flt_wp_dropdown_users'), 50, 1);	
  	
	// TODO: only for edit / write post/page URIs and dashboard ?
	if ( awp_ver('2.6') )
		add_filter('query', array('ScoperAdminHardway', 'flt_include_pending_revisions') );
}

if ( ! $is_administrator = is_administrator_rs() ) {
	require_once( 'hardway-admin_non-administrator_rs.php' );
}

if ( (is_admin() || defined('XMLRPC_REQUEST') ) && ! $is_administrator ) {
	// URIs ending in specified filename will not be subjected to low-level query filtering
	$nomess_uris = array_merge($nomess_uris, array('p-admin/admin-ajax.php'));

	if ( ! agp_string_ends_in($_SERVER['REQUEST_URI'], $nomess_uris, true ) ) {  //arg: actually return true for any strpos value
		add_filter('query', array('ScoperAdminHardway_Ltd', 'flt_last_resort_query') );
	}
}

if ( is_admin() && ! $is_administrator ) {
	// limit these links on post/page edit listing to drafts which current user can edit
	add_filter('get_others_drafts', array('ScoperAdminHardway_Ltd', 'flt_get_others_drafts'), 50, 1);
} // endif filtering disabled for admin access type


/**
 * ScoperAdminHardway PHP class for the WordPress plugin Role Scoper
 * hardway-admin_rs.php
 * 
 * @author 		Kevin Behrens
 * @copyright 	Copyright 2009
 * 
 * Used by Role Role Scoper Plugin as a container for statically-called functions
 *
 */
class ScoperAdminHardway {
	// WP autosave wipes out page parent and sets author to current user
	function flt_autosave_bugstomper($query) {
		global $wpdb;

		if ( ( strpos($query, "PDATE $wpdb->posts ") && strpos($query, "post_parent") ) ) {
			// as of WP 2.6, only the post_parent is being wiped.
			if ( ! awp_ver('2.6') ) {
				global $current_user;
				$query = preg_replace( "/,\s*`post_author`\s*=\s*'{$current_user->ID}'/", "", $query);
				$query = preg_replace( "/`post_author`\s*=\s*'{$current_user->ID}',/", "", $query);
			}
			
			$query = preg_replace( "/,\s*`post_parent`\s*=\s*'0'/", "", $query);
		}

		return $query;
	}
	
	function flt_include_pending_revisions($query) {
		// no recursion
		if ( scoper_querying_db() )
			return $query;
		
		// Require current user to be a blog-wide editor due to complexity of applying scoped roles to revisions
		if ( strpos($query, ".post_status = 'pending'") || strpos($query, ".post_status = 'inherit'") || strpos($query, 'GROUP BY post_status') ) {
		
			// Not yet able to support pending revision restoration by non-administrator
			/*
			global $scoper, $current_user;
			
			$page_roles = $scoper->role_defs->qualify_roles('edit_others_pages');
			$post_roles = $scoper->role_defs->qualify_roles('edit_others_posts');
			
			$is_blogwide_editor = array();
			$is_blogwide_editor['post'] = array_intersect_key($post_roles, $current_user->blog_roles);
			$is_blogwide_editor['page'] = array_intersect_key($page_roles, $current_user->blog_roles);
			
			if ( $is_blogwide_editor['post'] || $is_blogwide_editor['page'] ) {
			*/
			if ( is_administrator_rs() ) {
				global $wpdb;
				
				//$object_type = ( strpos($query, 'page') ) ? 'page' : 'post';
				
				//if ( $is_blogwide_editor[$object_type] ) {
					if ( strpos($query, ".post_status = 'pending'") && strpos($query, 'ELECT') && ! strpos($query, ".post_status = 'draft'") ) {
						if ( awp_ver('2.7-dev') ) {
							$query = str_replace("$wpdb->posts.post_type = 'page'", "$wpdb->posts.post_type IN ('page', 'revision') AND ($wpdb->posts.post_status = 'pending')", $query);
							$query = str_replace("$wpdb->posts.post_type = 'post'", "$wpdb->posts.post_type IN ('post', 'revision') AND ($wpdb->posts.post_status = 'pending')", $query);
						} else {
							$query = str_replace("$wpdb->posts.post_type = 'page' AND ($wpdb->posts.post_status = 'pending')", "$wpdb->posts.post_type IN ('page', 'revision') AND ($wpdb->posts.post_status = 'pending')", $query);
							$query = str_replace("$wpdb->posts.post_type = 'post' AND ($wpdb->posts.post_status = 'pending')", "$wpdb->posts.post_type IN ('post', 'revision') AND ($wpdb->posts.post_status = 'pending')", $query);
						}
					}
				
					if ( strpos($query, "GROUP BY post_status") )
						$query = str_replace("SELECT post_status, COUNT( * ) AS num_posts FROM $wpdb->posts WHERE post_type = 'page'", "SELECT post_status, COUNT( * ) AS num_posts FROM $wpdb->posts WHERE ( post_type = 'page' OR ( post_type = 'revision' AND post_status = 'pending' ) )", $query);
				//}
				
				if ( strpos($query, "inherit") && strpos($query, "ELECT") )
					$query = str_replace("post_type = 'revision' AND ($wpdb->posts.post_status = 'inherit')", "post_type = 'revision' AND ($wpdb->posts.post_status IN ('inherit', 'pending') )", $query);

			} // endif current user is allowed to "restore" pending revisions
		} // endif query pertains in any way to pending status and/or revisions
		
		return $query;
	}

	// Filter the otherwise unfilterable get_editable_user_ids() result set, which affects the admin UI
	function flt_editable_user_ids($query) {
		// Only display users who can read / edit the object in question
		if ( strpos ($query, "user_id FROM") && strpos ($query, "meta_key =") ) {
			global $wpdb, $scoper;
			
			if ( strpos ($query, "user_id FROM $wpdb->usermeta WHERE meta_key = '{$wpdb->prefix}user_level'") ) {
				if ( isset( $_POST['post_type']) ) {
					if ( 'page' == $_POST['post_type'] )
						$reqd_caps = array('edit_pages');
					else
						$reqd_caps = array('edit_posts');
				
					if ( isset($_POST['post_ID']) )
						$object_id = $_POST['post_ID'];
						
				} elseif ( $context = $scoper->admin->get_context('') ) {//arg: return reqd_caps only {
					if ( $context->source ) {
						$src_name = $context->source->name;
						$object_id = $scoper->data_sources->detect('id', $src_name);
					} else {
						$src_name = '';
						$object_id = 0;
					}
					
					$reqd_caps = $context->reqd_caps;
				}
				
				if ( ! empty($reqd_caps) ) {
					global $scoper, $current_user;

					$users = $scoper->users_who_can($reqd_caps, COL_ID_RS, $src_name, $object_id);

					if ( ! in_array($current_user->ID, $users) )
						$users []= $current_user->ID;
					
					$query = "SELECT $wpdb->users.ID FROM $wpdb->users WHERE ID IN ('" . implode("','", $users) . "')";
					
					/*
					$query = str_replace(' user_id', " DISTINCT $wpdb->usermeta.user_id", $query);
					$args = array('usermeta_table' => true); 

					if ( ! $object_id ) {
						// always include current user as an available author for new posts/pages
						global $current_user;
						$args['preserve_or_clause'] = " $wpdb->usermeta.user_id = '$current_user->ID' ";
					}
					
					// no need to exclude users by level; users_request will take care of it 
					// and allow Subscribers with an editing role for this object to be included
					$query = str_replace("AND meta_value != '0'", '', $query);
					
							// note: would need to require users-interceptor.php to do this
					$query = apply_filters('users_request_rs', $query, $reqd_caps, $src_name, $object_id, $args);
					*/
				}
			}
		}
		
		return $query;
	}
	
	//horrible reverse engineering of dropdown_users execution because only available filter is on html output
	function flt_wp_dropdown_users($wp_output) {
		// if (even after our blogcap tinkering) the author list is already locked due to insufficient blog-wide caps, don't mess
		if ( ! $pos = strpos ($wp_output, '<option') )
			return $wp_output;
		
		if ( ! strpos ($wp_output, '<option', $pos + 1) )
			return $wp_output;

		global $wpdb, $scoper;
		
		// This uri-checking functions will be called by flt_users_where.
		// If the current uri is not recognized, don't bother with the painful parsing. 
		$context = $scoper->admin->get_context('');
	
		if ( empty ($context->reqd_caps) )
			return $wp_output;
			
		$src_name = $context->source->name;
			
		$last_query = $wpdb->last_query;
		$orderpos = strpos($last_query, 'ORDER BY');
		$orderby = ( $orderpos ) ? substr($last_query, $orderpos) : '';
		$id_in = $id_not_in = $show_option_all = $show_option_none = '';
		
		$pos = strpos($last_query, 'AND ID IN(');
		if ( $pos ) {
			$pos_close = strpos($last_query, ')', $pos);
			if ( $pos_close)
				$id_in = substr($last_query, $pos, $pos_close - $pos + 1); 
		}
		
		$pos = strpos($last_query, 'AND ID NOT IN(');
		if ( $pos ) {
			$pos_close = strpos($last_query, ')', $pos);
			if ( $pos_close)
				$id_not_in = substr($last_query, $pos, $pos_close - $pos + 1); 
		}
		
		$search = "<option value='0'>";
		$pos = strpos($wp_output, $search . __('Any'));
		if ( $pos ) {
			$pos_close = strpos($wp_output, '</option>', $pos);
			if ( $pos_close)
				$show_option_all = substr($wp_output, $pos + strlen($search), $pos_close - $pos - strlen($search)); 
		}
		
		$search = "<option value='-1'>";
		$pos = strpos($wp_output, $search . __('None'));
		if ( $pos ) {
			$pos_close = strpos($wp_output, '</option>', $pos);
			if ( $pos_close)
				$show_option_none = substr($wp_output, $pos + strlen($search), $pos_close - $pos - strlen($search)); 
		}
		
		$search = "<select name='";
		$pos = strpos($wp_output, $search);
		if ( false !== $pos ) {
			$pos_close = strpos($wp_output, "'", $pos + strlen($search));
			if ( $pos_close)
				$name = substr($wp_output, $pos + strlen($search), $pos_close - $pos - strlen($search)); 
		}
		
		$search = " id='";
		$multi = ! strpos($wp_output, $search);  // beginning with WP 2.7, some users dropdowns lack id attribute
		
		$search = " class='";
		$pos = strpos($wp_output, $search);
		if ( $pos ) {
			$pos_close = strpos($wp_output, "'", $pos + strlen($search));
			if ( $pos_close)
				$class = substr($wp_output, $pos + strlen($search), $pos_close - $pos - strlen($search)); 
		}
		
		$search = " selected='selected'";
		$pos = strpos($wp_output, $search);
		if ( $pos ) {
			$search = "<option value='";
	
			$str_left = substr($wp_output, 0, $pos);
			$pos = strrpos($str_left, $search); //back up to previous option tag

			$pos_close = strpos($wp_output, "'", $pos + strlen($search));
			if ( $pos_close)
				$selected = substr($wp_output, $pos + strlen($search), $pos_close - ($pos + strlen($search)) ); 
		}
		
		// Role Scoper filter application
		$where = "$id_in $id_not_in";
		
		$object_id = 0;
		$reqd_caps = array();
		if ( $context = $scoper->admin->get_context() ) {
			if ( ! empty($context->source) )
				$object_id = $scoper->data_sources->detect('id', $context->source);
			
			if ( isset($context->reqd_caps) )
				$reqd_caps = $context->reqd_caps;
		}
		
		$args = array();
		$args['where'] = $where;
		$args['orderby'] = $orderby;
		
		if ( $object_id ) {
			if ( $current_author = $scoper->data_sources->get_from_db('owner', $src_name, $object_id) )
				$args['preserve_or_clause'] = " uro.user_id = '$current_author'";
		} else {
			global $current_user;
			$args['preserve_or_clause'] = " uro.user_id = '$current_user->ID'";
		}
		
		$users = $scoper->users_who_can($reqd_caps, COLS_ID_DISPLAYNAME_RS, $src_name, $object_id, $args);

		$show = 'display_name'; // no way to back this out

		// ----------- begin wp_dropdown_users code copy (from WP 2.7) -------------
		$id = $multi ? "" : "id='$name'";

		$output = "<select name='$name' $id class='$class'>\n";

		if ( $show_option_all )
			$output .= "\t<option value='0'>$show_option_all</option>\n";

		if ( $show_option_none )
			$output .= "\t<option value='-1'>$show_option_none</option>\n";
			
		foreach ( (array) $users as $user ) {
			$user->ID = (int) $user->ID;
			$_selected = $user->ID == $selected ? " selected='selected'" : '';
			$display = !empty($user->$show) ? $user->$show : '('. $user->user_login . ')';
			$output .= "\t<option value='$user->ID'$_selected>" . wp_specialchars($display) . "</option>\n";
		}

		$output .= "</select>";
		// ----------- end wp_dropdown_users code copy (from WP 2.7) -------------
		
		return $output;
	}
	
	function flt_get_editable_authors($unfiltered_results) {
		global $wpdb, $scoper;
		
		$context = $scoper->admin->get_context('', true); // arg: return reqd_caps only
		if ( empty ($context->reqd_caps) )
			return $unfiltered_results;
		
		$users = $scoper->users_who_can($context->reqd_caps, COLS_ALL_RS);
		
		return $users;
	}
}
?>