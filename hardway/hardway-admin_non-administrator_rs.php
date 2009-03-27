<?php

if( basename(__FILE__) == basename($_SERVER['SCRIPT_FILENAME']) )
	die();
	
// link category roles, restrictions are only for bookmark management
global $scoper;
if ( $scoper->data_sources->is_member('link') )
	add_filter('get_bookmarks', array('ScoperAdminHardway_Ltd', 'flt_get_bookmarks'), 1, 2);	
	
class ScoperAdminHardway_Ltd {

	// low-level filtering of otherwise unhookable queries
	//
	// Todo: review all queries for version-specificity; apply regular expressions to make it less brittle
	function flt_last_resort_query($query) {
		global $wpdb, $scoper;

		$posts = $wpdb->posts;
		$comments = $wpdb->comments;
		$links = $wpdb->links;
		$term_taxonomy = $wpdb->term_taxonomy;
		
		// no recursion
		if ( scoper_querying_db() )
			return $query;

		////rs_errlog ("<br />checking $query <br />");
		
		// todo: confirm this is still necessary for elevated users
		// kill extra capability checks for revisions (user already passed our scoped test)		TODO: confirm did_action check is not needed, eliminate it
		if ( strpos($query, 'ELECT ') && strpos($query, $posts) ) {
			if ( preg_match("/SELECT\s*DISTINCT\s*$posts.ID\s*FROM\s*$posts\s*WHERE\s*1=1\s*AND\s*\(\s*post_author\s*=/", $query) && preg_match("/AND\s*$posts.ID\s*IN'/", $query) && did_action('posts_selection') && awp_ver('2.6') ) {
				global $current_user;	
				$query = preg_replace( "/AND \( post_author = '{$current_user->ID}' \)/", '', $query);
				return $query;
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
		
			$query = str_replace( "SELECT ID", "SELECT DISTINCT ID", $query);
			$query = apply_filters('objects_request_rs', $query, 'post', 'post', '');
			//rs_errlog ("<br /><br />replaced with $query<br /><br />");
		}
		
		// Dashboard
		// num posts: "SELECT COUNT(*) FROM $posts WHERE post_type = 'post' AND post_status = 'publish'"
		if ( strpos ($query, "ELECT COUNT(*) FROM $posts WHERE") ) {
			//rs_errlog ("<br />caught $query <br />");	
		
			$query = str_replace( "COUNT(*)", " COUNT(DISTINCT $posts.ID)", $query);
			$query = str_replace( "SELECT post_status", "SELECT DISTINCT $posts.post_status", $query);
			$query = apply_filters('objects_request_rs', $query, 'post', 'post', '');
			
			//rs_errlog ("<br /><br />replaced with $query<br /><br />");
			return $query;
		}
		
		// totals on edit.php
		// WP 2.5: SELECT post_status, COUNT( * ) AS num_posts FROM wp_posts WHERE post_type = 'post' GROUP BY post_status
		if ( strpos($query, "ELECT post_status, COUNT( * ) AS num_posts ") && strpos($query, " FROM $posts WHERE post_type = 'post'") ) {
			//rs_errlog ("<br />caught $query <br />");	
			
			global $current_user;
			$query = str_replace( "AND (post_status != 'private' OR ( post_author = '{$current_user->ID}' AND post_status = 'private' ))", '', $query);
			
			$query = str_replace( "COUNT( * )", " COUNT(DISTINCT ID)", $query);
			$query = str_replace( "post_status", "$posts.post_status", $query);
			$query = str_replace( "SELECT", "SELECT DISTINCT", $query);
			
			$query = apply_filters('objects_request_rs', $query, 'post', 'post', '');
			
			//rs_errlog ("<br /><br /> returned $query ");
			return $query;
		}
		
		// totals on edit-pages.php
		// WP 2.5: SELECT post_status, COUNT( * ) AS num_posts FROM wp_posts WHERE post_type = 'post' GROUP BY post_status
		if ( strpos($query, "ELECT post_status, COUNT( * )") && strpos($query, " FROM $posts WHERE post_type = 'page'") ) {
			global $current_user;
			
			//rs_errlog ("<br />caught $query <br />");	
			
			$query = str_replace( "AND (post_status != 'private' OR ( post_author = '{$current_user->ID}' AND post_status = 'private' ))", '', $query);
			
			$query = str_replace( "COUNT( * )", " COUNT(DISTINCT ID)", $query);
			$query = str_replace( "post_status", "$posts.post_status", $query);
			$query = str_replace( "SELECT", "SELECT DISTINCT", $query);
			
			$query = apply_filters('objects_request_rs', $query, 'post', 'page', '');
			
			//rs_errlog ("<br /><br /> returned $query ");
			return $query;
		}
		
		////rs_errlog ("<br /><br />checking $query");
		
		// num cats: "SELECT COUNT(*) FROM $categories"
		// SELECT DISTINCT COUNT(tt.term_id) FROM wp_term_taxonomy AS tt WHERE 1=1 AND tt.taxonomy = 'category' 
		// SELECT DISTINCT tt.term_id FROM wp_term_taxonomy AS tt WHERE
		$script_name = $_SERVER['REQUEST_URI'];
		if ( ! strpos($script_name, 'p-admin/post.php') && ! strpos($script_name, 'p-admin/post-new.php') && ! strpos($script_name, 'p-admin/page.php') && ! strpos($script_name, 'p-admin/page-new.php') && ! defined('XMLRPC_REQUEST') ) {
			if ( ( strpos ($query, "ELECT COUNT(*) FROM $term_taxonomy WHERE") ) 
			|| ( strpos ($query, "ELECT DISTINCT COUNT(*) FROM $term_taxonomy WHERE") ) 
			|| ( strpos ($query, " tt.term_id FROM $term_taxonomy AS tt WHERE") )  // todo: might we double-filter here?
			|| ( strpos ($query, " t.*, tt.* FROM $wpdb->terms ") )  // todo: might we double-filter here?
			) {
				//rs_errlog ("<br />caught $query <br />");
				
				// don't mess with parent category selection/availability for single category edit
				if ( $tx = $scoper->taxonomies->get('category') )
					if ( $term_id = $scoper->data_sources->detect('id', $tx->source) ) {
						return $query;
					}
				
				$search = "taxonomy IN ('";
				if ( $pos = strpos($query, $search) )
					if ( $pos_end = strpos($query, "'", $pos + strlen($search) ) )
						$taxonomy = substr($query, $pos + strlen($search), $pos_end - ( $pos + strlen($search) ) );
				
				if ( empty($taxonomy ) ) {
					$search = "taxonomy = '";
					if ( $pos = strpos($query, $search) )
						if ( $pos_end = strpos($query, "'", $pos + strlen($search) ) )
							$taxonomy = substr($query, $pos + strlen($search), $pos_end - ( $pos + strlen($search) ) );
				}
				
				if ( $taxonomy && $scoper->taxonomies->is_member($taxonomy) ) {
					if ( $src = $scoper->taxonomies->member_property($taxonomy, 'object_source') ) {
						$object_types = array();
						foreach ( array_keys($src->object_types) as $object_type )
							if ( scoper_get_otype_option('use_term_roles', $src->name, $object_type) )
								$object_types []= $object_type;
						
						if ( $object_types ) {
							$query = str_replace( "ELECT COUNT(*) FROM $term_taxonomy WHERE", "ELECT COUNT(DISTINCT tt.term_taxonomy_id) FROM $term_taxonomy AS tt WHERE", $query);
							$args = array('terms_query' => true);
							$query = apply_filters('objects_request_rs', $query, $src->name, $object_types, $args);
						}
					}
				}
				
				//rs_errlog ("<br /><br /> returning $query <br />");
				return $query;
			}
			
		} 
		
		if ( strpos ($query, "ELECT COUNT(*) FROM $term_taxonomy") ) {
			//rs_errlog ("<br />caught $query <br />");	
		
			$query = str_replace( "COUNT(*)", " COUNT(DISTINCT ID)", $query);
			$query = str_replace( "FROM $term_taxonomy", "FROM $term_taxonomy AS tt", $query);
			$args = array('terms_query' => true);
			$query = apply_filters('objects_request_rs', $query, 'post', 'post', $args);
	
			//rs_errlog ("<br /><br /> returning $query <br />");
			return $query;
		}
		
		// TODO: use wp_count_comments filter with WP >= 2.7 (though it is not used for pending comments count)
		
		//	WP 2.5: SELECT comment_approved, COUNT( * ) AS num_comments FROM wp_comments GROUP BY comment_approved
		// 			SELECT comment_post_ID, COUNT(comment_ID) as num_comments
		//			SELECT SQL_CALC_FOUND_ROWS * FROM wp_comments USE INDEX (comment_date_gmt) WHERE ( comment_approved = '0' OR comment_approved = '1' )
		//
		// WP 2.6:  SELECT comment_approved, COUNT( * ) AS num_comments FROM wp_comments GROUP BY comment_approved
		//
		// comment count: SELECT COUNT(*) FROM wp_comments WHERE comment_approved = '0' 
		// comments: SELECT SQL_CALC_FOUND_ROWS * FROM wp_comments WHERE comment_approved = '0' OR comment_approved = '1' ORDER BY comment_date DESC LIMIT 0, 25 
		// comment moderation : SELECT * FROM wp_comments WHERE comment_approved = '0' 
		if ( strpos($query, "ELECT ") && preg_match ("/FROM\s*{$comments}\s*(WHERE|GROUP BY|USE INDEX|ORDER BY)/", $query)
		&& ( ! strpos($query, "ELECT COUNT") || empty( $_POST ) ) )  // don't filter the comment count query prior to DB storage of comment_count to post record
		{
			//rs_errlog ("<br /> <b>caught</b> $query<br /> ");	
			
			$query = str_replace( "SELECT *", "SELECT DISTINCT $comments.*", $query);
			$query = str_replace( "SELECT SQL_CALC_FOUND_ROWS *", "SELECT SQL_CALC_FOUND_ROWS DISTINCT $comments.*", $query);
			
			if ( ! strpos( $query, ' DISTINCT ' ) )
				$query = str_replace( "SELECT ", "SELECT DISTINCT ", $query);

			$query = str_replace( "COUNT(*)", " COUNT(DISTINCT $comments.comment_ID)", $query);
			$query = str_replace( "COUNT(comment_ID)", " COUNT(DISTINCT $comments.comment_ID)", $query);
			$query = preg_replace( "/COUNT(\s*\*\s*)/", " COUNT(DISTINCT $comments.comment_ID)", $query);
			$query = preg_replace( "/COUNT(\s*comment_ID\s*)/", " COUNT(DISTINCT $comments.comment_ID)", $query);

			$query = str_replace( "user_id ", "$comments.user_id ", $query);
			
			$query = preg_replace( "/FROM\s*{$comments}\s*WHERE /", "FROM $comments INNER JOIN $posts ON $posts.ID = $comments.comment_post_ID WHERE ", $query);
			
			// wp 2.6: also some formatting changes with leading tabs instead of spaces
			//$query = preg_replace( "/FROM\s*{$comments}\s*USE INDEX\s*(comment_date_gmt)\s*WHERE/", "FROM $comments USE INDEX (comment_date_gmt) INNER JOIN $posts ON $posts.ID = $comments.comment_post_ID WHERE", $query);
			$query = str_replace("FROM $comments USE INDEX (comment_date_gmt) WHERE", "FROM $comments USE INDEX (comment_date_gmt) INNER JOIN $posts ON $posts.ID = $comments.comment_post_ID WHERE", $query);

			$query = preg_replace( "/FROM\s*$comments\s*GROUP BY /", "FROM $comments INNER JOIN $posts ON $posts.ID = $comments.comment_post_ID WHERE 1=1 GROUP BY ", $query);
			$query = str_replace( "SELECT comment_approved", "SELECT DISTINCT comment_approved", $query);
			$query = str_replace( "SELECT comment_post_ID, COUNT(comment_ID) as num_comments", "SELECT DISTINCT comment_post_ID, COUNT(DISTINCT comment_ID)", $query);
			
			$reqd_caps = array();
			if ( $statuses = $scoper->data_sources->member_property('post', 'statuses') )
				foreach ( array_keys($statuses) as $status_name ) {
					$reqd_caps['post'][$status_name] = array('edit_others_posts', 'moderate_comments');
					$reqd_caps['page'][$status_name] = array('edit_others_pages', 'moderate_comments');
				}

			$reqd_caps['post']['private'] = array('edit_others_posts', 'edit_private_posts', 'moderate_comments');
			$reqd_caps['page']['private'] = array('edit_others_pages', 'edit_private_pages', 'moderate_comments');

			$args = array( 'force_reqd_caps' => $reqd_caps );
			$query = apply_filters('objects_request_rs', $query, 'post', array('post', 'page'), $args);
			
			if ( ! strpos($query, "JOIN $posts") )
				$query = str_replace( " FROM $comments ", " FROM $comments INNER JOIN $posts ON $posts.ID = $comments.comment_post_ID ", $query);
			
			// pre-execute the comments listing query and buffer the listed IDs for more efficient user_has_cap calls
			if ( strpos( $query, "* FROM $comments") && empty($scoper->listed_ids['post']) ) {
				if ( $results = scoper_get_results($query) ) {
					$scoper->listed_ids['post'] = array();
					
					foreach ( $results as $row ) {
						if ( ! empty($row->comment_post_ID) )
							$scoper->listed_ids['post'][$row->comment_post_ID] = true;
					}
				}
			}

			//rs_errlog ("<br /><br />replaced with $query<br /><br />");
			return $query;
		}
		
		// TODO: confirm this was redundant
		//if ( strpos( $query, "$comments.* FROM $comments LEFT JOIN $posts" ) ) {
		//	$query = apply_filters('objects_request_rs', $query, 'post', 'post', '');
		//}
		
		// Page parent dropdown: Only display pages for which user has edit_pages or create_child_pages.
		if ( ! awp_ver('2.7-dev') || strpos($_SERVER['SCRIPT_NAME'], 'p-admin/admin.php') ) {
			if ( strpos ($query, "ELECT ID, post_parent, post_title") && strpos($query, "FROM $posts WHERE post_parent =") && function_exists('parent_dropdown') ) {
				require_once( WP_CONTENT_DIR . '/plugins/' . SCOPER_FOLDER . '/admin/admin_ui_lib_rs.php');
				
				$page_temp = '';
				$object_id = $scoper->data_sources->detect( 'id', 'post' );
				if ( $object_id )
					$page_temp = get_post( $object_id );

				if ( empty($page_temp) || ! isset($page_temp->post_parent) || $page_temp->post_parent ) {
					$output = ScoperAdminUI::dropdown_pages();
					echo $output;
				}
				$query = "SELECT ID, post_parent FROM $posts WHERE 1=2";
				
				return $query;
			}
		}
		
		// attachment count
		//SELECT post_mime_type, COUNT( * ) AS num_posts FROM wp_trunk_posts WHERE post_type = 'attachment' GROUP BY post_mime_type
		//if ( preg_match( "/ELECT\s*post_mime_type", $query ) ) {
		if ( strpos($query, 'ELECT post_mime_type') ) {
			if ( $where_pos = strpos($query, 'WHERE ') ) {
				$parent_query = "SELECT $wpdb->posts.ID FROM $wpdb->posts WHERE 1=1";
				$parent_query = apply_filters('objects_request_rs', $parent_query, 'post', array('post', 'page') );

				global $current_user;
				$unattached_clause = ( ! empty( $current_user->allcaps['upload_files'] ) ) ? "$wpdb->posts.post_parent = '0' OR " : '';

				$where_insert = "( $unattached_clause ( $wpdb->posts.post_parent IN ($parent_query) ) ) AND ";
				
				$query = substr( $query, 0, $where_pos + strlen('WHERE ') ) . $where_insert . substr($query, $where_pos + strlen('WHERE ') );
				
				return $query;
			}
		}
		
		// Media Library
		//SELECT post_mime_type, COUNT( * ) AS num_posts FROM wp_trunk_posts WHERE post_type = 'attachment' GROUP BY post_mime_type
		//if ( preg_match( "/ELECT\s*post_mime_type", $query ) ) {
		if ( strpos($query, ".post_type = 'attachment'") && strpos($query, '* FROM') ) {
			if ( $where_pos = strpos($query, 'WHERE ') ) {
				global $current_user;

				if ( ! empty( $current_user->allcaps['upload_files'] ) ) {
					$unattached_clause = "$wpdb->posts.post_type = 'attachment' AND $wpdb->posts.post_parent = '0' OR ";
					$query = str_replace( 'AND ( ( wp_trunk_posts.post_parent IN', "AND $unattached_clause ( ( wp_trunk_posts.post_parent IN", $query );

					return $query;
				}
				
			}
		}
		
		// links
		//SELECT * , IF (DATE_ADD(link_updated, INTERVAL 120 MINUTE) >= NOW(), 1,0) as recently_updated FROM wp_links WHERE 1=1 ORDER BY link_name ASC
		if ( ( strpos($query, "FROM $links WHERE") || strpos($query, "FROM $links  WHERE") ) && strpos($query, "ELECT ") ) {
			if ( ! strpos($query, ' DISTINCT ') )
				$query = str_replace( "SELECT ", "SELECT DISTINCT ", $query);
			
			//rs_errlog ("<br /> caught $query <br />");	
				
			$args = array();
			$query = apply_filters('objects_request_rs', $query, 'link', 'link', $args);
			
			//rs_errlog ("<br /><br />replaced with $query<br /><br />");
			
			return $query;
		}
		
		return $query;
	}
	
	function flt_get_others_drafts($results) {
		global $wpdb, $current_user, $scoper;
		
		// WP 2.3 added pending status, but no new hook or hook argument
		$draft_query = strpos($wpdb->last_query, 'draft');
		$pending_query = strpos($wpdb->last_query, 'pending');
		
		if ( $draft_query && $pending_query )
			$status_clause = "AND ( post_status = 'draft' OR post_status = 'pending' )";
		elseif ( $draft_query )
			$status_clause = "AND post_status = 'draft'";
		else
			$status_clause = "AND post_status = 'pending'";
		
		$object_type = $scoper->data_sources->detect('type', 'post');
		if ( ! $object_type )
			$object_type = 'post';
			
		if ( ! $otype_val = $scoper->data_sources->member_property('post', 'object_types', $object_type, 'val') )
			$otype_val = $object_type;
			
		$qry = "SELECT ID, post_title FROM $wpdb->posts WHERE post_type = '$otype_val' AND post_author != '$current_user->ID' $status_clause";
		$qry = apply_filters('objects_request_rs', $qry, 'post', '', '');
		
		$items = scoper_get_results($qry);
		
		return $items;
	}

	// scoped equivalent to WP core get_bookmarks
	//	 As of WP 2.7, scoped roles cannot be enforced without replicating the whole function 
	//	 following get_pages execution or wp_cache retrieval.  Modifications from WP 2.7.1-beta1 get_bookmarks are noted.
	//
	// Enforces cap requirements as specified in WP_Scoped_Data_Source::reqd_caps
	function flt_get_bookmarks($results, $args) {
		global $wpdb;

		$defaults = array(
			'orderby' => 'name', 'order' => 'ASC',
			'limit' => -1, 'category' => '',
			'category_name' => '', 'hide_invisible' => 1,
			'show_updated' => 0, 'include' => '',
			'exclude' => '', 'search' => ''
		);
	
		$r = wp_parse_args( $args, $defaults );
		extract( $r, EXTR_SKIP );
		
		if ( is_administrator_rs() )
			return $results;
		
		//-- BEGIN RoleScoper Modification: wp-cache key and flag specific to access type and user/groups --//
		global $current_user;
		$ckey = md5 ( serialize( $r ) . CURRENT_ACCESS_NAME_RS );
		
		$cache_flag = SCOPER_ROLE_TYPE . '_get_bookmarks';
		
		if ( $cache = $current_user->cache_get( $cache_flag ) ) {
		//-- END RoleScoper Modification --//
			if ( isset( $cache[ $ckey ] ) )
				//-- RoleScoper Modification: alternate filter name --//
				return apply_filters('get_bookmarks_rs', $cache[ $ckey ], $r);
		}
		
		$inclusions = '';
		if ( !empty($include) ) {
			$exclude = '';  //ignore exclude, category, and category_name params if using include
			$category = '';
			$category_name = '';
			$inclinks = preg_split('/[\s,]+/',$include);
			if ( count($inclinks) ) {
				foreach ( $inclinks as $inclink ) {
					if (empty($inclusions))
						$inclusions = ' AND ( link_id = ' . intval($inclink) . ' ';
					else
						$inclusions .= ' OR link_id = ' . intval($inclink) . ' ';
				}
			}
		}
		if (!empty($inclusions))
			$inclusions .= ')';
	
		$exclusions = '';
		if ( !empty($exclude) ) {
			$exlinks = preg_split('/[\s,]+/',$exclude);
			if ( count($exlinks) ) {
				foreach ( $exlinks as $exlink ) {
					if (empty($exclusions))
						$exclusions = ' AND ( link_id <> ' . intval($exlink) . ' ';
					else
						$exclusions .= ' AND link_id <> ' . intval($exlink) . ' ';
				}
			}
		}
		if (!empty($exclusions))
			$exclusions .= ')';
	
		if ( ! empty($category_name) ) {
			if ( $category = get_term_by('name', $category_name, 'link_category') )
				$category = $category->term_id;
		}
	
		if ( ! empty($search) ) {
			$search = like_escape($search);
			$search = " AND ( (link_url LIKE '%$search%') OR (link_name LIKE '%$search%') OR (link_description LIKE '%$search%') ) ";
		}
		
		$category_query = '';
		$join = '';
		if ( !empty($category) ) {
			$incategories = preg_split('/[\s,]+/',$category);
			if ( count($incategories) ) {
				foreach ( $incategories as $incat ) {
					if (empty($category_query))
						$category_query = ' AND ( tt.term_id = ' . intval($incat) . ' ';
					else
						$category_query .= ' OR tt.term_id = ' . intval($incat) . ' ';
				}
			}
		}
		if (!empty($category_query)) {
			$category_query .= ") AND taxonomy = 'link_category'";
			$join = " INNER JOIN $wpdb->term_relationships AS tr ON ($wpdb->links.link_id = tr.object_id) INNER JOIN $wpdb->term_taxonomy as tt ON tt.term_taxonomy_id = tr.term_taxonomy_id";
		}
		
		if (get_option('links_recently_updated_time')) {
			$recently_updated_test = ", IF (DATE_ADD(link_updated, INTERVAL " . get_option('links_recently_updated_time') . " MINUTE) >= NOW(), 1,0) as recently_updated ";
		} else {
			$recently_updated_test = '';
		}
	
		if ($show_updated) {
			$get_updated = ", UNIX_TIMESTAMP(link_updated) AS link_updated_f ";
		} else
			$get_updated = '';
	
		$orderby = strtolower($orderby);
		$length = '';
		switch ($orderby) {
			case 'length':
				$length = ", CHAR_LENGTH(link_name) AS length";
				break;
			case 'rand':
				$orderby = 'rand()';
				break;
			default:
				$orderby = "link_" . $orderby;
		}
	
		if ( 'link_id' == $orderby )
			$orderby = "$wpdb->links.link_id";
	
		$visible = '';
		if ( $hide_invisible )
			$visible = "AND link_visible = 'Y'";
		
		$query = "SELECT DISTINCT $wpdb->links.* $length $recently_updated_test $get_updated FROM $wpdb->links $join WHERE 1=1 $visible $category_query";
		$query .= " $exclusions $inclusions $search";
		$query .= " ORDER BY $orderby $order";
		if ($limit != -1)
			$query .= " LIMIT $limit";
			
		// RoleScoper Modification: run query through scoping filter 
		$query = apply_filters('objects_request_rs', $query, 'link', '', '');
		
		//d_echo("<br /><br />get_bookmarks filtered: $query<br /><br />");
		$results = scoper_get_results($query);

		// RoleScoper Modification: wp-cache key and flag specific to access type and user/groups
		$cache[ $ckey ] = $results;
		$current_user->cache_set( $cache, $cache_flag );
		
		// RoleScoper Modification: alternate hook name
		$links = apply_filters('get_bookmarks_rs', $results, $r);
		
		// RoleScoper Modification: memory cache akin to page_cache to assist bulk operations
		global $scoper;
		$ilim = count($links);
		for ($i = 0; $i < $ilim; $i++)
			$scoper->listed_ids['link'][$links[$i]->link_id] = true;
		
		return $links;
	}
	
} // end class
?>