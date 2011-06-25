<?php

if( basename(__FILE__) == basename($_SERVER['SCRIPT_FILENAME']) )
	die();

if ( 'nav-menus.php' == $GLOBALS['pagenow'] ) {	// nav-menus.php only needs admin_referer check.  TODO: split this file
	add_action( 'check_admin_referer', array('ScoperAdminHardway_Ltd', 'act_check_admin_referer') );
} else {
	//if ( false === strpos( $_SERVER['REQUEST_URI'], 'upload.php' ) )	// TODO: internal criteria to prevent application of flt_last_resort when scoped user object is not fully loaded
		ScoperAdminHardway_Ltd::add_filters();
}

class ScoperAdminHardway_Ltd {
	function add_filters() {
		add_action( 'check_admin_referer', array('ScoperAdminHardway_Ltd', 'act_check_admin_referer') );
	
		add_action( 'check_ajax_referer', array('ScoperAdminHardway_Ltd', 'act_check_ajax_referer') );
		
		// limit these links on post/page edit listing to drafts which current user can edit
		add_filter('get_others_drafts', array('ScoperAdminHardway_Ltd', 'flt_get_others_drafts'), 50, 1);
		
		// TODO: better handling of low-level AJAX filtering
		// URIs ending in specified filename will not be subjected to low-level query filtering
		$nomess_uris = apply_filters( 'scoper_skip_lastresort_filter_uris', array( 'categories.php', 'themes.php', 'plugins.php', 'profile.php', 'link.php' ) );
		
		if ( empty( $_POST['ps'] ) )	// need to filter Find Posts query in Media Library
			$nomess_uris = array_merge($nomess_uris, array('admin-ajax.php'));
		
		if ( ! in_array( $GLOBALS['pagenow'], $nomess_uris ) && ! in_array( $GLOBALS['plugin_page_cr'], $nomess_uris ) )
			add_filter('query', array('ScoperAdminHardway_Ltd', 'flt_last_resort_query') );	
	}

	// next-best way to handle any permission checks for non-Ajax operations which can't be done via has_cap filter
	function act_check_admin_referer( $referer_name ) {
		
		if ( ! empty($_POST['tag_ID']) && ( 'update-tag_' . $_POST['tag_ID'] == $referer_name ) ) {
			// filter category parent selection for Category editing
			if ( ! isset( $_POST['tag_ID'] ) )
				return;
			
			$taxonomy = $_POST['taxonomy'];
			
			if ( ! $tx = get_taxonomy($taxonomy) )
				return;
				
			if ( ! $tx->hierarchical )
				return;
			
			$stored_term = get_term_by( 'id', $_POST['tag_ID'], $taxonomy );

			$selected_parent = $_POST['parent'];
			
			if ( -1 == $selected_parent )
				$selected_parent = 0;
			
			if ( $stored_term->parent != $selected_parent ) {
				global $scoper;
				
				if ( $tx_obj = get_taxonomy( $taxonomy ) ) {
					if ( $selected_parent ) {
						$user_terms = $scoper->qualify_terms( $tx_obj->cap->manage_terms, $taxonomy );
						$permit = in_array( $selected_parent, $user_terms );
					} else {
						$permit = cr_user_can( $tx_obj->cap->manage_terms, 0, 0, array( 'skip_id_generation' => true, 'skip_any_term_check' => true ) );
					}
				}
				
				if ( ! $permit )
					wp_die( __('You do not have permission to select that Category Parent', 'scoper') );
			}

		} elseif ( 'update-nav_menu' == $referer_name ) {
			$tx = get_taxonomy( 'nav_menu' );
			
			if ( ! cr_user_can( $tx->cap->manage_terms, $_REQUEST['menu'], 0, array( 'skip_id_generation' => true, 'skip_any_term_check' => true ) ) ) {
				if ( $_REQUEST['menu'] )
					wp_die( __('You do not have permission to update that Navigation Menu', 'scoper') );
				else
					wp_die( __('You do not have permission to create new Navigation Menus', 'scoper') );
			}
		
		} elseif ( false !== strpos( $referer_name, 'delete-menu_item_' ) ) {
			if ( scoper_get_option( 'admin_nav_menu_filter_items' ) ) {
				$menu_item_id = substr( $referer_name, strlen( 'delete-menu_item_' ) );
				require_once( SCOPER_ABSPATH . '/admin/filters-admin-nav_menus_rs.php' );
				_rs_mnt_modify_nav_menu_item( $menu_item_id, 'delete' );
			}
		} elseif ( $referer_name == 'move-menu_item' ) {
			if ( scoper_get_option( 'admin_nav_menu_filter_items' ) ) {
				require_once( SCOPER_ABSPATH . '/admin/filters-admin-nav_menus_rs.php' );
				_rs_mnt_modify_nav_menu_item( $_REQUEST['menu-item'], 'move' );
			}
		} elseif ( 'add-bookmark' == $referer_name ) {
			require_once( dirname(__FILE__).'/hardway-admin-links_rs.php' );
			$link_category = ! empty( $_POST['link_category'] ) ? $_POST['link_category'] : array();
			$_POST['link_category'] = scoper_flt_newlink_category( $link_category );

		} elseif ( 0 === strpos( $referer_name, 'update-bookmark_' ) ) {
			require_once( dirname(__FILE__).'/hardway-admin-links_rs.php' );
			$link_category = ! empty( $_POST['link_category'] ) ? $_POST['link_category'] : array();
			$_POST['link_category'] = scoper_flt_link_category( $link_category );
		}
	}
	
	
	// next-best way to handle permission checks for Ajax operations which can't be done via has_cap filter
	function act_check_ajax_referer( $referer_name ) {
		if ( 'add-tag' == $referer_name ) {			
			if ( $tx_obj = get_taxonomy( $_POST['taxonomy'] ) )
				$cap_name = $tx_obj->cap->manage_terms;

			if ( empty($cap_name) )
				$cap_name = 'manage_categories';

			// Concern here is for addition of top level terms.  Subcat addition attempts will already be filtered by has_cap filter.
			if ( ( empty( $_POST['parent'] ) || $_POST['parent'] < 0 ) && ! cr_user_can( $cap_name, BLOG_SCOPE_RS ) )
				die('-1');	
		
		} elseif ( 'add-link-category' == $referer_name ) {		
			if ( ! cr_user_can( 'manage_categories', BLOG_SCOPE_RS ) )
				die('-1');	
		
		}
	}
	
	function flt_last_resort_query($query) {
		static $in_process = false;
		
		if ( $in_process )
			return $query;
			
		$in_process = true;
		$query = ScoperAdminHardway_Ltd::_flt_last_resort_query($query);
		$in_process = false;
		return $query;
	}
	
	// low-level filtering of otherwise unhookable queries
	//
	// Todo: review all queries for version-specificity; apply regular expressions to make it less brittle
	function _flt_last_resort_query($query) {
		// no recursion
		if ( scoper_querying_db() || $GLOBALS['cap_interceptor']->in_process )
			return $query;

		global $wpdb, $pagenow, $scoper;

		$posts = $wpdb->posts;
		$comments = $wpdb->comments;
		$links = $wpdb->links;
		$term_taxonomy = $wpdb->term_taxonomy;

		
		// Media Library - unattached (as of WP 2.8, not filterable via posts_request)
		//
		//SELECT post_mime_type, COUNT( * ) AS num_posts FROM wp_trunk_posts WHERE post_type = 'attachment' GROUP BY post_mime_type
		//if ( preg_match( "/ELECT\s*post_mime_type", $query ) ) {
		if ( strpos($query, "post_type = 'attachment'") && strpos($query, "post_parent < 1") && strpos($query, '* FROM') ) {

			if ( $where_pos = strpos($query, 'WHERE ') ) {
				// optionally hide other users' unattached uploads, but not from blog-wide Editors
				if ( ( ! scoper_get_option( 'admin_others_unattached_files' ) ) && ! $scoper->user_can_edit_blogwide( 'post', '', array( 'require_others_cap' => true, 'status' => 'publish' ) ) ) {
					global $current_user;
					
					$author_clause = "AND $wpdb->posts.post_author = '{$current_user->ID}'";

					$query = str_replace( "post_type = 'attachment'", "post_type = 'attachment' $author_clause", $query);

					return $query;
				}
			}
		}
		
		// Search on query portions to make this as forward-compatible as possible.
		// Important to include " FROM table WHERE " as a strpos requirement because scoped queries (which should not be further altered here) will insert a JOIN clause
		// strpos search for "ELECT " rather than "SELECT" so we don't have to distinguish 0 from false
		
		// Recent posts: SELECT ID, post_title FROM wp_posts WHERE post_type = 'post' AND (post_status = 'publish' OR post_status = 'private') AND post_date_gmt < '2008-04-30 05:04:04' ORDER BY post_date DESC LIMIT 5 
		// Scheduled entries: SELECT ID, post_title, post_date_gmt FROM wp_posts WHERE post_type = 'post' AND post_status = 'future' ORDER BY post_date ASC"
		if ( 
		   ( strpos($query, "post_date_gmt <") && strpos ($query, "ELECT ID, post_title") && strpos($query, " FROM $posts WHERE ") )
		|| ( strpos ($query, "ELECT ID, post_title, post_date_gmt") && strpos($query, " FROM $posts WHERE ") ) 
		) {
			//rs_errlog ("<br />caught $query <br />");	
			if ( $_post_type = cr_find_post_type() )
				$query = apply_filters('objects_request_rs', $query, 'post', $_post_type, '');
			//rs_errlog ("<br /><br />replaced with $query<br /><br />");
		}
		
		// totals on edit.php
		// SELECT post_status, COUNT( * ) AS num_posts FROM wp_posts WHERE post_type = 'post' GROUP BY post_status
		$matches = array();
		if ( strpos($query, "ELECT post_status, COUNT( * ) AS num_posts ") && preg_match("/FROM\s*{$posts}\s*WHERE post_type\s*=\s*'([^ ]+)'/", $query, $matches) ) {
			$_post_type = ( ! empty( $matches[1] ) ) ? $matches[1]	: cr_find_post_type();

			if ( $_post_type ) {
				global $current_user;

				foreach( get_post_stati( array( 'private' => true ) ) as $_status )
					$query = str_replace( "AND (post_status != '$_status' OR ( post_author = '{$current_user->ID}' AND post_status = '$_status' ))", '', $query);
				
				$query = str_replace( "post_status", "$posts.post_status", $query);
				
				$query = apply_filters( 'objects_request_rs', $query, 'post', $_post_type, array( 'objrole_revisions_clause' => true ) );
				
				// as of WP 3.0.1, additional queries triggered by objects_request filter breaks all subsequent filters which would have operated on this query
				if ( defined( 'RVY_VERSION' ) ) {
					if ( class_exists( 'RevisionaryAdminHardway_Ltd' ) )
						$query = RevisionaryAdminHardway_Ltd::flt_last_resort_query( $query );
						
					$query = RevisionaryAdminHardway::flt_include_pending_revisions( $query );
				}
			}
				
			//rs_errlog ("<br /><br /> returned $query ");
			return $query;
		}
		
		////rs_errlog ("<br /><br />checking $query");
		
		// num cats: "SELECT COUNT(*) FROM wp_term_taxonomy"
		// SELECT DISTINCT COUNT(tt.term_id) FROM wp_term_taxonomy AS tt WHERE 1=1 AND tt.taxonomy = 'category' 
		// SELECT DISTINCT tt.term_id FROM wp_term_taxonomy AS tt WHERE
		if ( ! in_array( $pagenow, array( 'post.php', 'post-new.php') ) && ! defined('XMLRPC_REQUEST') ) {
			if ( strpos($query, " FROM $term_taxonomy") || strpos($query, " FROM $wpdb->terms") ) 
			{
				//rs_errlog ("<br />caught $query <br />");

				// don't mess with parent category selection/availability for single term edit
				$is_term_admin = ( in_array( $pagenow, array( 'edit-tags.php', 'edit-link-categories.php' ) ) );
				if ( $is_term_admin ) {
					if ( ! empty( $_REQUEST['tag_ID'] ) )
						return $query;
				}

				$matches = array();
				if ( $return = preg_match( "/taxonomy IN \('(.*)'/", $query, $matches ) )
					$taxonomy = explode( "','", str_replace( ' ', '', $matches[1] ) );
				elseif ( $return = preg_match( "/taxonomy\s*=\s*'(.*)'/", $query, $matches ) )
					$taxonomy = $matches[1];
				
				if ( ! empty($taxonomy) ) {
					if ( 'profile.php' == $pagenow )
						return $query;	
					else
						$query = apply_filters( 'terms_request_rs', $query, $taxonomy, array( 'is_term_admin' => $is_term_admin ) );
				}

				//rs_errlog ("<br /><br /> returning $query <br />");
				return $query;
			}
		} 


		// WP 3.0:  SELECT * FROM wp_comments c LEFT JOIN wp_posts p ON c.comment_post_ID = p.ID WHERE p.post_status != 'trash' AND ( c.comment_approved = '0' OR c.comment_approved = '1' ) ORDER BY c.comment_date_gmt
		// 
		if ( strpos($query, "ELECT ") && preg_match ("/FROM\s*{$comments}/", $query)
		&& ( ! strpos($query, "ELECT COUNT") || empty( $_POST ) )
		&& ( ! strpos($_SERVER['SCRIPT_FILENAME'], 'p-admin/upload.php') )
		 )  // don't filter the comment count query prior to DB storage of comment_count to post record
		{
			//define( 'SCOPER_NO_COMMENT_FILTERING', true );
			if ( defined( 'SCOPER_NO_COMMENT_FILTERING' ) && empty( $GLOBALS['current_user']->allcaps['moderate_comments'] ) ) {
				return $query;			
			}
			
			//rs_errlog ("<br /> <strong>caught</strong> $query<br /> ");	
			//d_echo( "<b>caught: <br />$query<br /></b>" );
			
			// cache the filtered results for pending comment count query, which (as of WP 3.0.1) is executed once per-post in the edit listing
			$post_id = 0;
			if ( $doing_pending_comment_count = strpos( $query, 'COUNT(comment_ID)' ) && strpos( $query, 'comment_post_ID' ) && strpos( $query, "comment_approved = '0'" ) ) {
				if ( 'index.php' != $pagenow ) {	// there's too much happening on the dashboard (and too much low-level query filtering) to buffer listed IDs reliably.
					if ( preg_match( "/comment_post_ID IN \( '([0-9]+)' \)/", $query, $matches ) ) {
						if ( $matches[1] )
							$post_id = $matches[1];
					}			
				}
				
				if ( $post_id ) {
					static $cache_pending_comment_count;
						
					if ( ! isset($cache_pending_comment_count) ) {
						$cache_pending_comment_count = array();
					
					} elseif ( isset( $cache_pending_comment_count[$post_id] ) ) {
						return "SELECT $post_id AS comment_post_ID, {$cache_pending_comment_count[$post_id]} AS num_comments";
					}
				}
			}
			
			$comment_alias = ( strpos( $query, "$comments c" ) || strpos( $query, "$comments AS c" ) ) ? 'c' : $comments;
			
			// apply DISTINCT clause so JOINs don't cause redundant comment count
			$query = str_replace( "SELECT *", "SELECT DISTINCT $comment_alias.*", $query);
			$query = str_replace( "SELECT SQL_CALC_FOUND_ROWS *", "SELECT SQL_CALC_FOUND_ROWS DISTINCT $comment_alias.*", $query);
		
			if ( ! strpos( $query, ' DISTINCT ' ) )
				$query = str_replace( "SELECT ", "SELECT DISTINCT ", $query);

			//$query = str_replace( "COUNT(*)", " COUNT(DISTINCT $comments.comment_ID)", $query);				// TODO: confirm preg_replace works and str_replace is not needed
			//$query = str_replace( "COUNT(comment_ID)", " COUNT(DISTINCT $comments.comment_ID)", $query);
			$query = preg_replace( "/COUNT(\s*\*\s*)/", " COUNT(DISTINCT $comments.comment_ID)", $query);
			$query = preg_replace( "/COUNT(\s*comment_ID\s*)/", " COUNT(DISTINCT $comments.comment_ID)", $query);

			$query = str_replace( " user_id ", " $comment_alias.user_id ", $query);
			
			if ( ! strpos( $query, "JOIN $posts" ) ) {
				if ( strpos( $query, "$comments c" ) )
					$query = preg_replace( "/FROM\s*{$comments} c\s*WHERE /", "FROM $comments c INNER JOIN $posts ON $posts.ID = $comment_alias.comment_post_ID WHERE ", $query);
				else
					$query = preg_replace( "/FROM\s*{$comments}\s*WHERE /", "FROM $comments INNER JOIN $posts ON $posts.ID = $comment_alias.comment_post_ID WHERE ", $query);
				
				if ( strpos( $query, "GROUP BY" ) )
					$query = preg_replace( "/FROM\s*{$comments}\s*GROUP BY /", "FROM $comments INNER JOIN $posts ON $posts.ID = $comment_alias.comment_post_ID GROUP BY ", $query);
			}

			$generic_uri = in_array( $pagenow, array( 'index.php', 'comments.php' ) );

			if ( ! $generic_uri && ( $_post_type = cr_find_post_type( '', false ) ) )  // arg: don't return 'post' as default if detection fails
				$post_types = array( $_post_type => get_post_type_object( $_post_type ) );
			else
				$post_types = array_diff_key( get_post_types( array( 'public' => true ), 'object' ), array( 'attachment' => true ) );

			$post_statuses = get_post_stati( array( 'internal' => null ), 'object' );
				
			$reqd_caps = array();
			
			$use_post_types = scoper_get_option( 'use_post_types' );
			
			foreach( $post_types as $_post_type => $type_obj ) {
				if ( empty( $use_post_types[$_post_type] ) )
					continue;
					
				foreach ( $post_statuses as $status => $status_obj ) {
					$reqd_caps[$_post_type][$status] = array( $type_obj->cap->edit_others_posts, 'moderate_comments' );
					
					if ( $status_obj->private )
						$reqd_caps[$_post_type][$status] []= $type_obj->cap->edit_private_posts;
						
					$status_name = ( ( 'publish' == $status ) || ( 'future' == $status ) ) ? 'published' : $status;

					$property = "edit_{$status_name}_posts";
					if ( ! empty( $type_obj->cap->$property ) && ! in_array( $type_obj->cap->$property, $reqd_caps[$_post_type][$status] ) )
						$reqd_caps[$_post_type][$status] []= $type_obj->cap->$property;
				}
			}

			$args = array( 'force_reqd_caps' => $reqd_caps );
			
			if ( strpos( $query, "$posts p" ) || strpos( $query, "$posts AS p" ) )
				$args['source_alias'] = 'p';
	
			$object_type = ( 'edit.php' == $pagenow ) ? cr_find_post_type() : '';
			$query = apply_filters( 'objects_request_rs', $query, 'post', $object_type, $args );
			
			// pre-execute the comments listing query and buffer the listed IDs for more efficient user_has_cap calls
			if ( strpos( $query, "* FROM $comments") && empty($scoper->listed_ids['post']) ) {
				if ( $results = scoper_get_results($query) ) {
					$scoper->listed_ids['post'] = array();
					
					foreach ( $results as $row ) {
						if ( ! empty($row->comment_post_ID) )
							$scoper->listed_ids['post'][$row->comment_post_ID] = true;
					}
				}
			} elseif ( $doing_pending_comment_count && $post_id ) {	
				if ( isset($scoper->listed_ids['post']) )
					$listed_ids = array_keys($scoper->listed_ids['post']);
				elseif ( ! empty($GLOBALS['wp_object_cache']->cache['posts']) && is_array($GLOBALS['wp_object_cache']->cache['posts']) )
					$listed_ids = array_keys($GLOBALS['wp_object_cache']->cache['posts']);
				else
					$listed_ids = array();
					
				// make sure our current post_id is in the list
				$listed_ids[] = $post_id;

				if ( count( $listed_ids ) > 1 ) {
					// cache the pending comment count for all listed posts
					$query = str_replace( "comment_post_ID IN ( '$post_id' )", "comment_post_ID IN ( '" . implode( "','", $listed_ids ) . "' )", $query );
					$results = scoper_get_results( $query );

					$cache_pending_comment_count = array_fill_keys( $listed_ids, 0 );

					foreach( $results as $row )
						$cache_pending_comment_count[ $row->comment_post_ID ] = $row->comment_count;
				}
			}

			//d_echo( "<br />replaced: $query<br />" );
			
			//rs_errlog ("<br /><br />replaced with $query<br /><br />");
			
			return $query;
		}
		
		// filter parent_dropdown() function.  As of WP 3.0.1, it still executes an otherwise unfilterable direct db query
		if ( 'admin.php' == $pagenow ) {
			if ( strpos ($query, "ELECT ID, post_parent, post_title") && strpos($query, "FROM $posts WHERE post_parent =") && function_exists('parent_dropdown') ) {
				$page_temp = '';
				$object_id = $scoper->data_sources->detect( 'id', 'post' );
				if ( $object_id )
					$page_temp = get_post( $object_id );

				if ( empty($page_temp) || ! isset($page_temp->post_parent) || $page_temp->post_parent ) {
					require_once( SCOPER_ABSPATH . '/hardway/hardway-parent-legacy_rs.php');
					$output = ScoperHardwayParentLegacy::dropdown_pages();
					echo $output;
				}
				$query = "SELECT ID, post_parent FROM $posts WHERE 1=2";
				
				return $query;
			}
		}
		
		// attachment count
		//SELECT post_mime_type, COUNT( * ) AS num_posts FROM wp_trunk_posts WHERE post_type = 'attachment' GROUP BY post_mime_type
		//if ( strpos($query, 'ELECT post_mime_type') ) {
		if ( strpos($query, "post_type = 'attachment'") && ( 0 === strpos($query, "SELECT " ) ) ) {
			if ( $where_pos = strpos($query, 'WHERE ') ) {

				if ( ! defined( 'SCOPER_ALL_UPLOADS_EDITABLE' ) ) {  // note: this constant actually just prevents Media Library filtering, falling back to WP Roles for attachment editability and leaving uneditable uploads viewable in Library
					static $att_sanity_count = 0;
					
					if ( $att_sanity_count > 5 )  // TODO: why does this apply filtering to 300+ queries on at least one MS installation?
						return $query;
					
					$att_sanity_count++;
					
					$admin_others_attached = scoper_get_option( 'admin_others_attached_files' );
					$admin_others_unattached = scoper_get_option( 'admin_others_unattached_files' );
					
					if ( ( ! $admin_others_attached ) || ! $admin_others_unattached )
						$can_edit_others_blogwide = $scoper->user_can_edit_blogwide( 'post', '', array( 'require_others_cap' => true, 'status' => 'publish' ) );
	
					global $wpdb, $current_user;
					
					// optionally hide other users' unattached uploads, but not from blog-wide Editors
					if ( $admin_others_unattached || $can_edit_others_blogwide )
						$author_clause = '';
					else
						$author_clause = "AND $wpdb->posts.post_author = '{$current_user->ID}'";
					
					if ( ! defined('SCOPER_BLOCK_UNATTACHED_UPLOADS') || ! SCOPER_BLOCK_UNATTACHED_UPLOADS )
						$unattached_clause = "( $wpdb->posts.post_parent = 0 $author_clause ) OR";
					else
						$unattached_clause = '';
	
					$attached_clause = ( $admin_others_attached || $can_edit_others_blogwide ) ? '' : "AND $wpdb->posts.post_author = '{$current_user->ID}'";
	
					$parent_query = "SELECT $wpdb->posts.ID FROM $wpdb->posts WHERE 1=1";

					$parent_query = apply_filters('objects_request_rs', $parent_query, 'post', array('post', 'page') );

					$where_insert = "( $unattached_clause ( $wpdb->posts.post_parent IN ($parent_query) $attached_clause ) ) AND ";
					
					$query = substr( $query, 0, $where_pos + strlen('WHERE ') ) . $where_insert . substr($query, $where_pos + strlen('WHERE ') );
				}
				
				return $query;
			}
		}
		
		// Find Posts in Media Library
		if ( strpos( $query, "ELECT ID, post_title, post_status, post_date FROM" ) ) {
			if ( ! empty( $_POST['post_type'] ) )
				$query = apply_filters('objects_request_rs', $query, 'post', $_POST['post_type'] );	
		}
		
		// links
		//SELECT * , IF (DATE_ADD(link_updated, INTERVAL 120 MINUTE) >= NOW(), 1,0) as recently_updated FROM wp_links WHERE 1=1 ORDER BY link_name ASC
		if ( ( strpos($query, "FROM $links WHERE") || strpos($query, "FROM $links  WHERE") ) && strpos($query, "ELECT ") ) {
			$query = apply_filters('objects_request_rs', $query, 'link', 'link');
			return $query;
		}
		
		return $query;
	} // end function flt_last_resort_query
	
	// Note: this filter is never invoked by WP core as of WP 2.7
	function flt_get_others_drafts($results) {
		global $wpdb, $current_user, $scoper;
		
		// buffer titles in case they were filtered previously
		$titles = scoper_get_property_array( $results, 'ID', 'post_title' );
		
		// WP 2.3 added pending status, but no new hook or hook argument
		$draft_query = strpos($wpdb->last_query, 'draft');
		$pending_query = strpos($wpdb->last_query, 'pending');
		
		if ( $draft_query && $pending_query )
			$status_clause = "AND ( post_status = 'draft' OR post_status = 'pending' )";
		elseif ( $draft_query )
			$status_clause = "AND post_status = 'draft'";
		else
			$status_clause = "AND post_status = 'pending'";
		
		$object_type = cr_find_post_type();
		if ( ! $object_type )
			$object_type = 'post';
			
		if ( ! $otype_val = $scoper->data_sources->member_property('post', 'object_types', $object_type, 'val') )
			$otype_val = $object_type;
			
		$qry = "SELECT ID, post_title, post_author FROM $wpdb->posts WHERE post_type = '$otype_val' AND post_author != '$current_user->ID' $status_clause";
		$qry = apply_filters('objects_request_rs', $qry, 'post', '', '');
		
		$items = scoper_get_results($qry);
		
		// restore buffered titles in case they were filtered previously
		scoper_restore_property_array( $items, $titles, 'ID', 'post_title' );

		return $items;
	}
	
} // end class
?>