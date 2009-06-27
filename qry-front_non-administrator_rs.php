<?php
if( basename(__FILE__) == basename($_SERVER['SCRIPT_FILENAME']) )
	die();

add_filter('comments_array', array('QueryInterceptorFront_NonAdmin_RS', 'flt_comments_results'), 99);

add_filter('getarchives_where', array('QueryInterceptorFront_NonAdmin_RS', 'flt_getarchives_where') );
add_filter('getarchives_join', array('QueryInterceptorFront_NonAdmin_RS', 'flt_getarchives_join') );

add_filter('getarchives_distinct', array('QueryInterceptorFront_NonAdmin_RS', 'flt_getarchives_distinct'), 50, 2 );
add_filter('getarchives_fields', array('QueryInterceptorFront_NonAdmin_RS', 'flt_getarchives_fields'), 2, 2 );

class QueryInterceptorFront_NonAdmin_RS {
	// Strips comments from teased posts/pages
	function flt_comments_results($results) {
		global $scoper;
	
		if ( $results && ! empty($scoper->teaser_ids) ) {
			foreach ( $results as $key => $row )
				if ( isset($row->comment_post_ID) && isset($scoper->teaser_ids['post'][$row->comment_post_ID]) )
					unset( $results[$key] );
		}
		
		return $results;
	}
	
	function flt_getarchives_distinct ( $distinct, $r ) {
		$nofilter_types = array('monthly', 'weekly', 'daily', 'yearly');
		if ( isset($r['type']) && ! in_array($r['type'], $nofilter_types) )
			$distinct = 'DISTINCT';

		return $distinct;
	}
	
	function flt_getarchives_fields ( $fields, $r ) {
		$nofilter_types = array('monthly', 'weekly', 'daily', 'yearly');
		if ( isset($r['type']) && ! in_array($r['type'], $nofilter_types) ) {
			global $wpdb;
			$fields = "$wpdb->posts.*";
		}

		return $fields;
	}
	
	// custom wrapper to clean up after get_archives() nonstandard arg syntax (passes "WHERE post_type=...)
	function flt_getarchives_where ( $where ) {
		global $current_user, $wpdb;
		
		$where = str_replace( "WHERE ", "WHERE $wpdb->posts.post_date > 0 AND ", $where );
		
		if ( ! empty($current_user->ID) )
			$where = str_replace( "AND post_status = 'publish'", "AND post_status IN ('publish', 'private')", $where );

		$where = str_replace( "WHERE ", "AND ", $where );
	
		// pass force arg to ignore teaser setting
		$where = apply_filters('objects_where_rs', $where, 'post', '', array('skip_teaser' => true) );

		if ( $where && ( false === strpos($where, 'WHERE ') ) )
			$where = 'WHERE 1=1 ' . $where;
			
		return $where;
	}
	
	// custom wrapper to clean up after get_archives() nonstandard arg syntax (passes "WHERE post_type=...) and must pass force arg
	function flt_getarchives_join ( $join ) {
		// pass force arg to ignore teaser setting
		$join = apply_filters('objects_join_rs', $join, 'post', '', array( 'skip_teaser' => true ) );
		return $join;
	}
}
?>