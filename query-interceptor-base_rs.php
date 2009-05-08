<?php
if( basename(__FILE__) == basename($_SERVER['SCRIPT_FILENAME']) )
	die();

class QueryInterceptorBase_RS {

	function QueryInterceptorBase_RS() {
		global $scoper;
	
		awp_force_set('wp_filter', array(), array( 'is_global' => true ), 'objects_listing_rs', 50);
		add_filter('objects_listing_rs', array('QueryInterceptorBase_RS', 'flt_objects_listing'), 50, 4);
		
		$arg_str = agp_get_lambda_argstring(1);
		foreach ( $scoper->data_sources->get_all() as $src_name => $src ) {
			if ( isset($src->query_hooks->listing) ) {
				// Call our abstract handlers with a lambda function that passes in original hook name
				// In effect, make WP pass the hook name so multiple hooks can be registered to a single handler 
				$rs_args = "'$src_name', '', '' ";
				$func = "return apply_filters( 'objects_listing_rs', $arg_str , $rs_args );";
				add_filter( $src->query_hooks->listing, create_function( $arg_str, $func ), 50, 1 );	
				//d_echo ("adding filter: $original_hook -> $func <br />");
			}
		} //foreach data_sources
	}

	// can't do this from posts_results or it will throw off found_rows used for admin paging
	function flt_objects_listing($results, $src_name, $object_types, $args = '') {
		global $wpdb;
		global $scoper;

		// it's not currently necessary or possible to log listed revisions from here
		if ( isset($wpdb->last_query) && strpos( $wpdb->last_query, "post_type = 'revision'") )
			return $results;

		// if currently listed IDs are not already in post_cache, make our own equivalent memcache
		// ( create this cache for any data source, front end or admin )
		if ( 'post' == $src_name )
			global $wp_object_cache;
		
		$listed_ids = array();
		
		if ( ('post' != $src_name) || empty($wp_object_cache->cache['posts']) ) {
			if ( empty($scoper->listed_ids[$src_name]) ) {
				
				if ( $col_id = $scoper->data_sources->member_property( $src_name, 'cols', 'id' ) ) {
					$listed_ids = array();
					foreach ( $results as $row ) {
						if ( isset($row->$col_id) )
							$listed_ids [$row->$col_id] = true;
					}
					if ( empty($scoper->listed_ids) )
						$scoper->listed_ids = array();
					
					$scoper->listed_ids[$src_name] = $listed_ids;
				}
			} else
				return $results;
		}
		
		// now determine what restrictions were in place on these results 
		// (currently only for RS role type, post data source, front end or manage posts/pages)
		//
		// possible todo: support other data sources, WP role type
		if ( is_admin() && ( strpos($_SERVER['SCRIPT_NAME'], 'p-admin/edit.php') || strpos($_SERVER['SCRIPT_NAME'], 'p-admin/edit-pages.php') ) ) {
			require_once( 'role_usage_rs.php' );
			determine_role_usage_rs( 'post', $listed_ids );
		}
		
		return $results;
	}

} // end class
?>