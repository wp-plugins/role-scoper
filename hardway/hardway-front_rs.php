<?php
if( basename(__FILE__) == basename($_SERVER['SCRIPT_FILENAME']) )
	die();

add_filter('query', array('ScoperHardwayFront', 'flt_recent_comments') );
add_filter('getarchives_where', array('ScoperHardwayFront', 'flt_log_getarchives') ); // work around WP bug in wp_get_archives
add_filter('get_terms', array('ScoperHardwayFront', 'flt_get_tags'), 50, 3);

add_filter('get_the_category_for_list', array('ScoperHardwayFront', 'flt_get_the_category') );		// TODO: eliminate this - published in RS forum as a WP hack for use with a dev version
add_filter('get_the_category', array('ScoperHardwayFront', 'flt_get_the_category'), 10, 2);

// Todo: need get_the_category hook added to WP source

class ScoperHardwayFront
{	
	function flt_get_the_category( $cats, $context = '' ) {
		if ( $context && ( 'display' != $context ) )
			return;
	
		$readable_cats = apply_filters( 'get_terms', array(), 'category', array('fields' => 'ids', 'skip_teaser' => true) );
	
		foreach ( $cats as $key => $cat )
			if ( ! in_array($cat->cat_ID, $readable_cats) )
				unset( $cats[$key] );
	
		return $cats;
	}

	function flt_recent_comments($query) {
		// Due to missing get_comments hook, this filter operates on every front-end query.
		// If query doesn't pertain to comments, skip out with as little overhead as possible.
		if ( strpos($query, 'comment') 
		//&& ( preg_match("/WHERE\s*comment_approved\s*=\s*'1'/", $query) || preg_match("/AND\s*comment_approved\s*=\s*'1'/", $query) )
		&& strpos($query, "ELECT") && ! strpos($query, 'JOIN') && ! strpos($query, "COUNT") && strpos($query, "comment_approved") )
		{
			if ( ! is_attachment() && ! is_administrator_rs() ) {
				global $wpdb;

				if ( strpos($query, $wpdb->comments) ) {
					$query = str_replace( "user_id ", "$wpdb->comments.user_id ", $query);
			
					$query = str_replace( "SELECT * FROM $wpdb->comments", "SELECT DISTINCT $wpdb->comments.* FROM $wpdb->comments", $query);
			
					if ( ! strpos( $query, ' DISTINCT ' ) )
						$query = str_replace( "SELECT ", "SELECT DISTINCT ", $query);

					// theoretically, a slight performance enhancement if we can simplify the query to skip filtering of attachment comments
					if ( defined('SCOPER_NO_ATTACHMENT_COMMENTS') || ( false !== strpos( $query, 'comment_post_ID =') ) ) {
						$query = preg_replace( "/FROM\s*{$wpdb->comments}\s*WHERE /", "FROM $wpdb->comments INNER JOIN $wpdb->posts ON {$wpdb->posts}.ID = {$wpdb->comments}.comment_post_ID WHERE ", $query);
						$query = apply_filters('objects_request_rs', $query, 'post', '', array('skip_teaser' => true) );
					} else {
						$join = "LEFT JOIN $wpdb->posts as parent ON parent.ID = {$wpdb->posts}.post_parent AND parent.post_type IN ('post', 'page') AND $wpdb->posts.post_type = 'attachment'";
						$join = apply_filters('objects_join_rs', $join, 'post', '', array('skip_teaser' => true) );

						$where_post = apply_filters('objects_where_rs', '', 'post', 'post', array('skip_teaser' => true) );
						$where_page = apply_filters('objects_where_rs', '', 'post', 'page', array('skip_teaser' => true) );

						$where_post_att = str_replace( "$wpdb->posts.", "parent.", $where_post );
						$where_page_att = str_replace( "$wpdb->posts.", "parent.", $where_page );

						$where = " ( ( $wpdb->posts.post_type = 'post' $where_post )"
								. " OR ( $wpdb->posts.post_type = 'page' $where_page )"
								. " OR ( $wpdb->posts.post_type = 'attachment' AND parent.post_type = 'post' $where_post_att )"
								. " OR ( $wpdb->posts.post_type = 'attachment' AND parent.post_type = 'page' $where_page_att ) )";

						$query = preg_replace( "/FROM\s*{$wpdb->comments}\s*WHERE /", "FROM $wpdb->comments INNER JOIN $wpdb->posts ON {$wpdb->posts}.ID = {$wpdb->comments}.comment_post_ID $join WHERE $where AND ", $query);
					}
				}
			}
		}
		
		return $query;
	}
	
	// wp_get_archives uses unfilterable SELECT * for postbypost archive type
	// TODO: make these WP version-dependant if WP fixes the bug
	function flt_log_getarchives( $query ) {
		add_filter( 'query', array('ScoperHardwayFront', 'flt_archives_bugstomper') );

		return $query;
	}

	function flt_archives_bugstomper( $query ) {
		if ( strpos( $query, 'ELECT * FROM' ) ) {
			global $wpdb;
			
			$query = str_replace( "SELECT * FROM $wpdb->posts", "SELECT DISTINCT $wpdb->posts.* FROM $wpdb->posts", $query );
		
			remove_filter( 'query', array('ScoperHardwayFront', 'flt_archives_bugstomper') );
		}

		return $query;
	}

	function flt_get_tags( $results, $taxonomies, $args ) {
		if ( ! is_array($taxonomies) )
			$taxonomies = (array) $taxonomies;
	
		if ( ('post_tag' != $taxonomies[0]) || (count($taxonomies) > 1) )
			return $results;

		global $wpdb;

		$defaults = array(
		'exclude' => '', 'include' => '',
		'number' => '45', 'offset' => '', 'slug' => '', 
		'name__like' => '', 'search' => '');
		$args = wp_parse_args( $args, $defaults );
		extract($args, EXTR_SKIP);
		
		global $scoper, $current_user;

		$filter_key = ( has_filter('list_terms_exclusions') ) ? serialize($GLOBALS['wp_filter']['list_terms_exclusions']) : '';
		$ckey = md5( serialize( compact(array_keys($defaults)) ) . serialize( $taxonomies ) . $filter_key );
		$cache_flag = SCOPER_ROLE_TYPE . '_get_terms';

		if ( $cache = $current_user->cache_get( $cache_flag ) )
			if ( isset( $cache[ $ckey ] ) )
				return apply_filters('get_tags_rs', $cache[ $ckey ], 'post_tag', $args);
		

		//------------ WP argument application code from get_terms(), with hierarchy-related portions removed -----------------
		//
		// NOTE: must change 'tt.count' to 'count' in orderby and hide_empty settings
		//		 Also change default orderby to name
		//
		$where = '';
		$inclusions = '';
		if ( !empty($include) ) {
			$exclude = '';
			$exclude_tree = '';
			$interms = preg_split('/[\s,]+/',$include);
			if ( count($interms) ) {
				foreach ( (array) $interms as $interm ) {
					if (empty($inclusions))
						$inclusions = ' AND ( t.term_id = ' . intval($interm) . ' ';
					else
						$inclusions .= ' OR t.term_id = ' . intval($interm) . ' ';
				}
			}
		}
	
		if ( !empty($inclusions) )
			$inclusions .= ')';
		$where .= $inclusions;
	
		$exclusions = '';
		if ( !empty($exclude) ) {
			$exterms = preg_split('/[\s,]+/',$exclude);
			if ( count($exterms) ) {
				foreach ( (array) $exterms as $exterm ) {
					if ( empty($exclusions) )
						$exclusions = ' AND ( t.term_id <> ' . intval($exterm) . ' ';
					else
						$exclusions .= ' AND t.term_id <> ' . intval($exterm) . ' ';
				}
			}
		}
	
		if ( !empty($exclusions) )
			$exclusions .= ')';
		$exclusions = apply_filters('list_terms_exclusions', $exclusions, $args );
		$where .= $exclusions;
	
		if ( !empty($slug) ) {
			$slug = sanitize_title($slug);
			$where .= " AND t.slug = '$slug'";
		}
	
		if ( !empty($name__like) )
			$where .= " AND t.name LIKE '{$name__like}%'";
	
		// don't limit the query results when we have to descend the family tree 
		if ( ! empty($number) ) {
			if( $offset )
				$limit = 'LIMIT ' . $offset . ',' . $number;
			else
				$limit = 'LIMIT ' . $number;
	
		} else
			$limit = '';
	
		if ( !empty($search) ) {
			$search = like_escape($search);
			$where .= " AND (t.name LIKE '%$search%')";
		}
		// ------------- end get_terms() argument application code --------------
		
		
		// embedded select statement for posts ID IN clause
		$posts_qry = "SELECT $wpdb->posts.ID FROM $wpdb->posts WHERE 1=1";
		$posts_qry = apply_filters('objects_request_rs', $posts_qry, 'post', 'post', array('skip_teaser' => true));

		$qry = "SELECT DISTINCT t.*, tt.*, COUNT(p.ID) AS count FROM $wpdb->terms AS t"
			. " INNER JOIN $wpdb->term_taxonomy AS tt ON tt.term_id = t.term_id AND tt.taxonomy = 'post_tag'"
			. " INNER JOIN $wpdb->term_relationships AS tagr ON tagr.term_taxonomy_id = tt.term_taxonomy_id"
			. " INNER JOIN $wpdb->posts AS p ON p.ID = tagr.object_id WHERE p.ID IN ($posts_qry)"
			. " $where GROUP BY t.term_id ORDER BY count DESC $limit";  // must hardcode orderby clause to always query top tags

		$results = scoper_get_results( $qry );

		$cache[ $ckey ] = $results;
		$current_user->cache_set( $cache, $cache_flag );
		
		$results = apply_filters('get_tags_rs', $results, 'post_tag', $args);
		
		return $results;
	}

} // end class
?>