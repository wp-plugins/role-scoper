<?php
// In effect, override corresponding WP functions with a scoped equivalent, 
// including per-group wp_cache.  Any previous result set modifications by other plugins
// would be discarded.  These filters are set to execute as early as possible to avoid such conflict.
//
// (note: if wp_cache is not enabled, WP core queries will execute pointlessly before these filters have a chance)

if( basename(__FILE__) == basename($_SERVER['SCRIPT_FILENAME']) )
	die();

global $scoper;

if ( $scoper->is_front() ) {
	require_once('hardway-front_rs.php');
}

if ( $scoper->data_sources->member_property('post', 'object_types', 'page') )
	add_filter('get_pages', array('ScoperHardway', 'flt_get_pages'), 1, 2);

add_filter('get_terms', array('ScoperHardway', 'flt_get_terms'), 1, 3);

// Since the NOT IN subquery is a painful aberration for filtering, replace it with the separate term query used by WP prior to 2.7
add_filter('posts_where', array('ScoperHardway', 'flt_cat_not_in_subquery'), 1);

/**
 * ScoperHardway PHP class for the WordPress plugin Role Scoper
 * hardway_rs.php
 * 
 * @author 		Kevin Behrens
 * @copyright 	Copyright 2009
 * 
 * Used by Role Scoper Plugin as a container for statically-called functions
 *
 */	
class ScoperHardway
{	
	//  Scoped equivalent to WP 2.7.1 core get_terms
	//	Currently, scoped roles cannot be enforced without replicating the whole function 
	// 
	// Cap requirements depend on access type, and are specified in the WP_Scoped_Data_Source->get_terms_reqd_caps corresponding to taxonomy in question
	function flt_get_terms($results, $taxonomies, $args) {
		global $wpdb;
		global $scoper;
		
		//d_echo ("<br /><br />----- HARDWAY GET_TERMS ------<br />");
		
		// --- Role Scoper mod - if array has one element it's still a single taxonomy
		if ( ! is_array($taxonomies) )
			$taxonomies = array($taxonomies);

		$single_taxonomy = ( count($taxonomies) < 2 ); 
		//---
		
		// link category roles / restrictions are only scoped for management (TODO: abstract this)
		if ( $single_taxonomy && ( 'link_category' == $taxonomies[0] ) && $scoper->is_front() )
			return $results;
		
		foreach ( $taxonomies as $taxonomy ) {
			if ( ! is_taxonomy($taxonomy) )
				return array();
				//return new WP_Error('invalid_taxonomy', __('Invalid Taxonomy'));  // this caused Fatal error on activation under some conditions
		}
		
		$parent_or = '';
		if ( ( is_admin() || defined('XMLRPC_REQUEST') ) ) {
			if ( is_administrator_rs() ) {
				return $results;
			} elseif ( $tx = $scoper->taxonomies->get($taxonomies[0]) ) {
				if ( $term_id = $scoper->data_sources->detect('id', $tx->source) )
					// for category edit, don't filter parent term out of selection UI even if user can't manage it
					// Todo: more parent filtering on update
					$parent_or = " OR t.term_id = (SELECT parent FROM $wpdb->term_taxonomy WHERE term_id = '$term_id') ";
			}
		}
		
		$in_taxonomies = "'" . implode("', '", $taxonomies) . "'";
		
		$defaults = array('orderby' => 'name', 'order' => 'ASC',
			'hide_empty' => true, 'exclude' => '', 'exclude_tree' => '', 'include' => '',
			'number' => '', 'fields' => 'all', 'slug' => '', 'parent' => '',
			'hierarchical' => true, 'child_of' => 0, 'get' => '', 'name__like' => '',
			'pad_counts' => false, 'offset' => '', 'search' => '', 'skip_teaser' => false );
		$args = wp_parse_args( $args, $defaults );
		$args['number'] = (int) $args['number'];
		$args['offset'] = absint( $args['offset'] );
		if ( !$single_taxonomy || !is_taxonomy_hierarchical($taxonomies[0]) ||
			'' != $args['parent'] ) {
			$args['child_of'] = 0;
			$args['hierarchical'] = false;
			$args['pad_counts'] = false;
		}
	
		if ( 'all' == $args['get'] ) {
			$args['child_of'] = 0;
			$args['hide_empty'] = 0;
			$args['hierarchical'] = false;
			$args['pad_counts'] = false;
		}
		
		extract($args, EXTR_SKIP);
		
		// don't offer to set a category as its own parent
		if ( is_admin() && ( 'category' == $taxonomy ) ) {
			if ( strpos($_SERVER['REQUEST_URI'], 'categories.php') ) {
				if ( $editing_cat_id = $scoper->data_sources->get_from_uri('id', 'term') ) {
					if ( $exclude )
						$exclude .= ',';
	
					$exclude .= $editing_cat_id;
				}
			}
		}
		
		//-- BEGIN RoleScoper Modification - currently only support single taxonomy call through this filter
					 // (terms_where filter does support multiple taxonomies and this function could be made to do so)
		
		$scoped_taxonomies = scoper_get_option('enable_wp_taxonomies');
		if ( ! $single_taxonomy || ! isset($scoped_taxonomies[ $taxonomies[0] ]) )
				return $results;
		// END RoleScoper Modification --//
		
		//-- RoleScoper Modification: alternate function call (was _get_term_hierarchy) --//
		$children = rs_get_terms_children($taxonomies[0]);
		
		if ( $child_of )
			if ( ! isset($children[$child_of]) )
				return array();
	
		if ( $parent )
			if ( ! isset($children[$parent]) )
				return array();
	
		$filter_key = ( has_filter('list_terms_exclusions') ) ? serialize($GLOBALS['wp_filter']['list_terms_exclusions']) : '';
		$key = md5( serialize( compact(array_keys($defaults)) ) . serialize( $taxonomies ) . $filter_key );
		
		//-- BEGIN RoleScoper Modification: wp-cache key specific to access type and user/groups --//
		$object_src_name = $scoper->taxonomies->member_property($taxonomies[0], 'object_source', 'name');
		$ckey = md5( $key . serialize($scoper->get_terms_reqd_caps($object_src_name)) );
		
		global $current_user;
		$cache_flag = SCOPER_ROLE_TYPE . '_get_terms';
		
		if ( $cache = $current_user->cache_get( $cache_flag ) ) {
		//-- END RoleScoper Modification --//
		
			if ( isset( $cache[ $ckey ] ) )
				//-- RoleScoper Modification: alternate filter name --//
				return apply_filters('get_terms_rs', $cache[ $ckey ], $taxonomies, $args);
		}
		
		// buffer term names in case they were filtered previously
		$term_names = scoper_buffer_property( $results, 'term_id', 'name' );
		
		if ( 'count' == $orderby )
			$orderby = 'tt.count';
		else if ( 'name' == $orderby )
			$orderby = 't.name';
		else if ( 'slug' == $orderby )
			$orderby = 't.slug';
		else if ( 'term_group' == $orderby )
			$orderby = 't.term_group';
		else
			$orderby = 't.term_id';
	
		$where = '';
		$inclusions = '';
		if ( !empty($include) ) {
			$exclude = '';
			$interms = preg_split('/[\s,]+/',$include);
			if ( count($interms) ) {
				foreach ( $interms as $interm ) {
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
		
		if ( ! empty( $exclude_tree ) ) {
			$all_terms = $scoper->get_terms($taxonomies[0], UNFILTERED_RS, COL_ID_RS);

			$excluded_trunks = (array) preg_split('/[\s,]+/',$exclude_tree);
			
			foreach( $excluded_trunks as $extrunk ) {
				$excluded_children = rs_get_term_descendants( $extrunk, $all_terms, $taxonomies[0] );	// replace core call to get_terms to avoid needless filtering
				
				$excluded_children[] = $extrunk;
				foreach( (array) $excluded_children as $exterm ) {
					if ( empty($exclusions) )
						$exclusions = ' AND ( t.term_id <> "' . intval($exterm) . '" ';
					else
						$exclusions .= ' AND t.term_id <> "' . intval($exterm) . '" ';
	
				}
			}
		}

		if ( !empty($exclude) ) {
			$exterms = preg_split('/[\s,]+/',$exclude);
			if ( count($exterms) ) {
				foreach ( $exterms as $exterm ) {
					if (empty($exclusions))
						$exclusions = ' AND ( t.term_id <> "' . intval($exterm) . '" ';
					else
						$exclusions .= ' AND t.term_id <> "' . intval($exterm) . '" ';
				}
			}
		}
	
		if ( !empty($exclusions) )
			$exclusions .= ')';
		
		$exclusions = apply_filters('list_terms_exclusions', $exclusions, $args);
		$where .= $exclusions;
	
		if ( !empty($slug) ) {
			$slug = sanitize_title($slug);
			$where .= " AND t.slug = '$slug'";
		}
	
		if ( !empty($name__like) )
			$where .= " AND t.name LIKE '{$name__like}%'";
			
		// Instead, manually remove truly empty cats at the bottom of this function, so we don't exclude cats with private but readable posts
		//if ( $hide_empty && !$hierarchical )
		//	$where .= ' AND tt.count > 0';
	
		$where_base = $where;
		
		if ( ( $parent ) && ('ids' == $fields) && isset($scoper->all_user_term_ids[$taxonomies[0]][$where_base]) ) {
			// use the previous results (all user-accessible terms) for this parent requirement
			if ( isset($children[$parent]) )
				$terms = array_intersect($scoper->all_user_term_ids[$taxonomies[0]][$where_base], $children[$parent]);

		} else { // do the query
			if ( '' != $parent ) {
				$parent = (int) $parent;
				
				// Role scoper mod: otherwise termroles only work if parent terms also have role
				if ( ( $parent ) || ('ids' != $fields) )
					$where .= " AND tt.parent = '$parent'";
			}
				
			// don't limit the query results when we have to descend the family tree 
			if ( ! empty($number) && ! $hierarchical && empty( $child_of ) && '' == $parent ) {
				if( $offset )
					$limit = 'LIMIT ' . $offset . ',' . $number;
				else
					$limit = 'LIMIT ' . $number;
		
			} else
				$limit = '';
		
			if ( ! empty($search) ) {
				$search = like_escape($search);
				$where .= " AND (t.name LIKE '%$search%')";
			}
				
			$select_this = '';
			if ( 'all' == $fields )
				$select_this = 't.*, tt.*';
			else if ( 'ids' == $fields )
				$select_this = 't.term_id, tt.parent, tt.count';
			else if ( 'names' == $fields )
				$select_this = 't.term_id, tt.parent, tt.count, t.name';

			$query_base = "SELECT DISTINCT $select_this FROM $wpdb->terms AS t INNER JOIN $wpdb->term_taxonomy AS tt ON t.term_id = tt.term_id WHERE 1=1 AND tt.taxonomy IN ($in_taxonomies) $where $parent_or ORDER BY $orderby $order $limit";

			// --- Role Scoper mod: ---
			// only force application of scoped query filter if we're NOT doing a teaser  
			$do_teaser = ( $scoper->is_front() && empty($skip_teaser) && scoper_get_otype_option('do_teaser', 'post') );

			$query = apply_filters('terms_request_rs', $query_base, $taxonomies[0], '', array('skip_teaser' => ! $do_teaser));

			// if no filering was applied because the teaser is enabled, save a redundant query
			if ( ! empty($exclude_tree) || ($query_base != $query) || $parent ) {
				$terms = scoper_get_results($query);
			} else
				$terms = $results;
			
			if ( 'all' == $fields )
				update_term_cache($terms);
		}
		
		if ( empty($terms) )
			return array();


		if ( 'all' == $fields ) {
			//====== Role Scoper Mod: Support a disjointed pages tree with some parents hidden ========
			$filtered_terms_by_id = array();
			foreach ( $terms as $term )
				$filtered_terms_by_id[$term->term_id] = true;
				
			foreach ( $terms as $key => $term ) {
				if ( $term->parent && ! isset($filtered_terms_by_id[$term->parent]) && ( $child_of != $term->parent ) ) {
					// remap to a visible ancestor, if any
					$ancestor_id = $term->parent;
					$visible_ancestor_id = 0;
					do {
						foreach ( $children as $maybe_papa_id => $child_ids ) {
							if ( in_array( $ancestor_id, $child_ids ) ) {
								$ancestor_id = $maybe_papa_id;
								
								if ( isset($filtered_terms_by_id[$ancestor_id]) || ($ancestor_id == $child_of) ) {
									$visible_ancestor_id = $ancestor_id;
									break 2;
								}
								else
									continue 2;
							}	
						}
					} while ( false ); // fall out of the do loop if the whole children array is traversed without finding a visible ancestor
					
					if ( ! $visible_ancestor_id )
						$terms[$key]->parent = 0;
					else
						$terms[$key]->parent = $visible_ancestor_id;
				}
			}
			//=============================================================================
		}
		
		//-- RoleScoper Modification - call alternate function (was _get_term_children) --//
		if ( ( $child_of || $hierarchical ) && ! empty($children) )
			$terms = rs_get_term_descendants($child_of, $terms, $taxonomies[0]);
		
		if ( ! $terms )
			return array();
		
		// Replace DB-stored term counts with actual number of posts this user can read.
		// In addition, without the rs_tally_term_counts call, WP will hide categories that have no public posts (even if this user can read some of the pvt posts).
		// Post counts will be incremented to include child categories only if $pad_counts is true
		if ( ! defined('XMLRPC_REQUEST') && ( 'all' == $fields ) )
			//-- RoleScoper Modification - alternate function call (was _pad_term_counts) --//
			rs_tally_term_counts($terms, $taxonomies[0], '', array('pad_counts' => $pad_counts, 'skip_teaser' => ! $do_teaser ) );
		
		// Make sure we show empty categories that have children.
		if ( $hierarchical && $hide_empty ) {
			foreach ( $terms as $k => $term ) {
				if ( ! $term->count ) {
					//-- RoleScoper Modification - call alternate function (was _get_term_children) --//
					if ( $children = rs_get_term_descendants($term->term_id, $terms, $taxonomies[0]) )
						foreach ( $children as $child )
							if ( $child->count )
								continue 2;
		
					// It really is empty
					unset($terms[$k]);
				}
			}
		}
		reset ( $terms );
		
		// do this instead of 'count > 0' clause in initial query, so we don't exclude cats with private but readable posts
		if ( $terms && empty( $hierarchical ) && ! empty( $hide_empty ) ) {
			foreach ( $terms as $key => $term )
				if ( ! $term->count )
					unset( $terms[$key] );
		}
		
		$_terms = array();
		if ( 'ids' == $fields ) {
			while ( $term = array_shift($terms) )
				$_terms[] = $term->term_id;
			$terms = $_terms;
		} elseif ( 'names' == $fields ) {
			while ( $term = array_shift($terms) )
				$_terms[] = $term->name;
			$terms = $_terms;
		}
	
		if ( 0 < $number && intval(@count($terms)) > $number ) {
			$terms = array_slice($terms, $offset, $number);
		}
		
		//-- RoleScoper Modification: store to user/group - specific wp-cache key --//
		$cache[ $ckey ] = $terms;
		$current_user->cache_set( $cache, $cache_flag );
		
		
		//-- RoleScoper Modification: alternate filter name --//
		$terms = apply_filters('get_terms_rs', $terms, $taxonomies, $args);

		// restore buffered term names in case they were filtered previously
		scoper_restore_property( $terms, $term_names, 'term_id', 'name' );
		
		return $terms;
	}

	
	// Scoped equivalent to WP core get_pages
	//	 As of WP 2.7, scoped roles cannot be enforced without replicating the whole function 
	//	 following get_pages execution or wp_cache retrieval.  Modifications from WP 2.7.1-beta1 get_pages are noted.
	//
	//	 Enforces cap requirements as specified in WP_Scoped_Data_Source::reqd_caps
	function flt_get_pages($results, $args = '') {
		global $wpdb;
		
		// buffer titles in case they were filtered previously
		$titles = scoper_buffer_property( $results, 'ID', 'post_title' );

		if ( ! scoper_get_otype_option( 'use_object_roles', 'post', 'page' ) )
			return $results;

		$defaults = array(
			'child_of' => 0, 'sort_order' => 'ASC',
			'sort_column' => 'post_title', 'hierarchical' => 1,
			'exclude' => '', 'include' => '',
			'meta_key' => '', 'meta_value' => '',
			'authors' => '', 'parent' => -1, 'exclude_tree' => ''
		);
		
		// RoleScoper modification to support xmlrpc getpagelist method
		$defaults['fields'] = "$wpdb->posts.*";
	
		$r = wp_parse_args( $args, $defaults );
		extract( $r, EXTR_SKIP );
		
		$key = md5( serialize( $r ) );
		
		//-- BEGIN RoleScoper Modification: wp-cache key and flag specific to access type and user/groups --//
		global $scoper, $current_user;
		$ckey = md5 ( $key . CURRENT_ACCESS_NAME_RS );
		
		global $current_user;
		$cache_flag = SCOPER_ROLE_TYPE . '_get_pages';

		if ( $cache = $current_user->cache_get($cache_flag) ) {
		//-- END RoleScoper Modification --//
			if ( isset( $cache[ $ckey ] ) )
				//-- RoleScoper Modification: alternate filter name --//
				return apply_filters('get_pages_rs', $cache[ $ckey ], $r);
		}

		$inclusions = '';
		if ( !empty($include) ) {
			$child_of = 0; //ignore child_of, parent, exclude, meta_key, and meta_value params if using include
			$parent = -1;
			$exclude = '';
			$meta_key = '';
			$meta_value = '';
			$hierarchical = false;
			$incpages = preg_split('/[\s,]+/',$include);
			if ( count($incpages) ) {
				foreach ( $incpages as $incpage ) {
					if (empty($inclusions))
						$inclusions = ' AND ( ID = ' . intval($incpage) . ' ';
					else
						$inclusions .= ' OR ID = ' . intval($incpage) . ' ';
				}
			}
		}
		if (!empty($inclusions))
			$inclusions .= ')';
	
		$exclusions = '';
		if ( !empty($exclude) ) {
			$expages = preg_split('/[\s,]+/',$exclude);
			if ( count($expages) ) {
				foreach ( $expages as $expage ) {
					if (empty($exclusions))
						$exclusions = ' AND ( ID <> ' . intval($expage) . ' ';
					else
						$exclusions .= ' AND ID <> ' . intval($expage) . ' ';
				}
			}
		}
		if (!empty($exclusions))
			$exclusions .= ')';
	
		$author_query = '';
		if (!empty($authors)) {
			$post_authors = preg_split('/[\s,]+/',$authors);
	
			if ( count($post_authors) ) {
				foreach ( $post_authors as $post_author ) {
					//Do we have an author id or an author login?
					if ( 0 == intval($post_author) ) {
						$post_author = get_userdatabylogin($post_author);
						if ( empty($post_author) )
							continue;
						if ( empty($post_author->ID) )
							continue;
						$post_author = $post_author->ID;
					}
	
					if ( '' == $author_query )
						$author_query = ' post_author = ' . intval($post_author) . ' ';
					else
						$author_query .= ' OR post_author = ' . intval($post_author) . ' ';
				}
				if ( '' != $author_query )
					$author_query = " AND ($author_query)";
			}
		}
	
		// BEGIN RoleScoper Modifications: split query into join, where clause for filtering
		$where_base = " AND post_type = 'page' AND post_status='publish' $exclusions $inclusions $author_query ";
		
		if ( $parent >= 0 )
			$where_base .= $wpdb->prepare(' AND post_parent = %d ', $parent);

		if ( ! empty( $meta_key ) && ! empty($meta_value) ) {
			// meta_key and meta_value might be slashed
			$meta_key = stripslashes($meta_key);
			$meta_value = stripslashes($meta_value);
			$join_base = " INNER JOIN $wpdb->postmeta ON $wpdb->posts.ID = $wpdb->postmeta.post_id";
			$where_base .= " AND $wpdb->postmeta.meta_key = '$meta_key' AND $wpdb->postmeta.meta_value = '$meta_value'";
		} else
			$join_base = '';

		$request = "SELECT DISTINCT $fields FROM $wpdb->posts $join_base WHERE 1=1 $where_base ORDER BY $sort_column $sort_order ";
		
		// option to omit private pages from page listing even if user can read them
		if ( ! $list_private_pages = scoper_get_otype_option('private_items_listable', 'post', 'page') ) {
			// As an extra precaution to make sure we can PREVENT private page listing even if private status is included in query,
			// temporarily set the required cap for reading private pages to a nonstandard cap name (which is probably not owned by any user)
			$def_caps = $scoper->data_sources->members['post']->reqd_caps['read']['page']['private'];
			$scoper->data_sources->members['post']->reqd_caps['read']['page']['private'] = array('list_private_pages');
		} else {
			// WP core does not include private pages in query.  Include private status clause in anticipation of user-specific filtering
			$request = str_replace("AND post_status='publish'", "AND ( post_status IN ('publish','private') )", $request);
		}
		
		if ( $scoper->is_front() && scoper_get_otype_option('do_teaser', 'post') && scoper_get_otype_option('use_teaser', 'post', 'page') ) {
			// We are in the front end and the teaser is enabled for pages	

			$pages = scoper_get_results($request);			// execute unfiltered query
			
			// Pass results of unfiltered query through the teaser filter.
			// If listing private pages is disabled, they will be omitted completely, but restricted published pages
			// will still be teased.  This is a slight design compromise to satisfy potentially conflicting user goals without yet another option
			$pages = apply_filters('objects_teaser_rs', $pages, 'post', 'page', array('request' => $request, 'force_teaser' => true) );
			
			if ( $list_private_pages ) {
				if ( ! scoper_get_otype_option('teaser_hide_private', 'post', 'page') )
					$tease_all = true;
			} else
				// now that the teaser filter has been applied, restore reqd_caps value to normal
				$scoper->data_sources->members['post']->reqd_caps['read']['page']['private'] = $def_caps;
	
		} else {
			// Pass query through the request filter
			$request = apply_filters('objects_request_rs', $request, 'post', 'page', array('skip_teaser' => true));
			
			// now that the request filter has been applied, restore reqd_caps value to normal
			if ( ! $list_private_pages )
				$scoper->data_sources->members['post']->reqd_caps['read']['page']['private'] = $def_caps;
			
			// Execute the filtered query
			$pages = scoper_get_results($request);
		}

		if ( empty($pages) )
			// RoleScoper Modification: alternate hook name
			return apply_filters('get_pages_rs', array(), $r);
		
		// restore buffered titles in case they were filtered previously
		scoper_restore_property( $pages, $titles, 'ID', 'post_title' );

		// Role Scoper note: WP core get_pages has already updated wp_cache and pagecache with unfiltered results.
		// TODO: Suggest moving WP core apply_filters call back before cache update
		update_page_cache($pages);
		
		
		//====== Role Scoper Mod: Support a disjointed pages tree with some parents hidden ========
		if ( empty($tease_all) || $child_of ) {  // if we're including all pages with teaser, no need to continue thru redraw_page_hierarchy processing
			$pages = ScoperHardway::redraw_page_hierarchy($pages, $child_of);
		}
		//===================================================
		
		if ( ! empty($exclude_tree) ) {
			$exclude = array();
	
			$exclude = (int) $exclude_tree;
			$children = get_page_children($exclude, $pages);	// RS note: okay to use unfiltered function here since it's only used for excluding
			$excludes = array();
			foreach ( $children as $child )
				$excludes[] = $child->ID;
			$excludes[] = $exclude;
			$total = count($pages);
			for ( $i = 0; $i < $total; $i++ ) {
				if ( in_array($pages[$i]->ID, $excludes) )
					unset($pages[$i]);
			}
		}
		
		// RoleScoper Modification: wp-cache key and flag specific to access type and user/groups
		$cache[ $ckey ] = $pages;
		$current_user->cache_set($cache, $cache_flag);
		
		// RoleScoper Modification: alternate hook name
		$pages = apply_filters('get_pages_rs', $pages, $r);
		
		return $pages;
	}
	
	function redraw_page_hierarchy($pages, $child_of) {
		global $scoper;

		if ( $child_of )
			$ancestors = rs_get_page_ancestors();
			
		$filtered_pages_by_id = array();
		foreach ( $pages as $page )
			$filtered_pages_by_id[$page->ID] = true;
		
		$skip_root_remap = false;
		
		if ( $scoper->is_front() && awp_is_plugin_active('fold_page_list.php') ) {
			// Fold Page List plugin can't deal with remapping of subpages to root
			
			if ( defined('FOLD_PAGE_LIST_ONLY') ) // define this if the debug_backtrace call is a performance concern
				$skip_root_remap = true;
				
			elseif ( $call_stack = debug_backtrace() ) {
				// don't disable remapping of pages to root unless this is actually a fold_page_list call
				foreach ( array_keys($call_stack) as $key )
					if ( 'wswwpx_fold_page_list' == $call_stack[$key]['function'] ) {
						$skip_root_remap = true;
						break;
					}
			}
		}

		foreach ( $pages as $key => $page ) {
			if ( ! empty($child_of) ) {
				if ( ! isset($ancestors[$page->ID]) || ! in_array($child_of, $ancestors[$page->ID]) ) {
					unset($pages[$key]);
					continue;
				}
			}

			//if ( $skip_root_remap )
			//	continue;
			
			if ( $page->post_parent && ( ! isset($filtered_pages_by_id[$page->post_parent]) || ( $child_of && ( $child_of == $page->post_parent ) ) ) ) {
				if ( $child_of && ( $child_of == $page->post_parent ) )
					// for child_of requests, remap first-gen children to root
					$visible_ancestor_id = 0;
				else {
					if ( ! isset($children) )
						$children = rs_get_page_children();
				
					// remap to a visible ancestor, if any
					$ancestor_id = $page->post_parent;
					$visible_ancestor_id = 0;
					do {
						foreach ( $children as $maybe_papa_id => $child_ids ) {
							if ( in_array( $ancestor_id, $child_ids ) ) {
								$ancestor_id = $maybe_papa_id;
								
								if ( isset($filtered_pages_by_id[$ancestor_id]) || ($ancestor_id == $child_of) ) {
									$visible_ancestor_id = $ancestor_id;
									break 2;
								}
								else
									continue 2;
							}	
						}
					} while ( false ); // fall out of the do loop if the whole children array is traversed without finding a visible ancestor
				}
				
				if ( $visible_ancestor_id )
					$pages[$key]->post_parent = $visible_ancestor_id;
				elseif ( ! $skip_root_remap )
					$pages[$key]->post_parent = 0;
			}
		}
		
		return $pages;
	}

	function flt_cat_not_in_subquery( $where ) {
		global $wpdb;
	
		if ( false !== strpos( $where, " AND $wpdb->posts.ID NOT IN ( SELECT tr.object_id FROM " ) ) {
			global $wp_query;

			// Since the NOT IN subquery is a painful aberration for filtering, 
			// replace it with the separatare term query used by WP prior to 2.7
			if ( strpos( $where, "AND {$wpdb->posts}.ID NOT IN ( SELECT tr.object_id" ) ) { // global wp_query is not set on manual WP_Query calls by template code
			//if ( ! empty($wp_query->query_vars['category__not_in']) ) {
				$ids = get_objects_in_term($wp_query->query_vars['category__not_in'], 'category');
				if ( is_wp_error( $ids ) )
					$ids = array();
				if ( is_array($ids) && count($ids > 0) ) {
					$out_posts = "'" . implode("', '", $ids) . "'";
					$whichcat .= " AND $wpdb->posts.ID NOT IN ($out_posts)";
				}
				$where = preg_replace( "/ AND {$wpdb->posts}\.ID NOT IN \( SELECT tr\.object_id [^)]*\) \)/", $whichcat, $where );
			}
		}
		
		return $where;
	}
	
} // end class ScoperHardway

//
// Private
//

function rs_get_term($term, $taxonomy) {
	global $scoper;
	
	if ( ! isset($scoper->taxonomies[$taxonomy]) )	// todo: copy this err handler elsewhere
		return new WP_Error('invalid_taxonomy', __('Invalid Taxonomy') . ': ' . __($taxonomy));
	
	$tx = $scoper->taxonomies[$taxonomy];
	
	if ( $tx->uses_standard_schema )
		return get_term($term, $taxonomy);  // this is a WP core taxonomy, let core get_term() handle it
		
	$term = (int) $term;
	if ( ! $_term = wpp_cache_get($term, "rs_$taxonomy") ) {   //TODO: is this caching useful?
		$_term = scoper_get_row("SELECT * FROM $tx->source WHERE $tx->source->cols->id = '$term' LIMIT 1");
		wpp_cache_set($term, $_term, "rs_$taxonomy");
	}
}
	
// renamed for clarity (was _get_term_hierarchy)
//function rs_get_terms_children($taxonomy) {}
	// moved to agapetry_wp_lib


// renamed for clarity (was get_term_children)
// Also adds support for taxonomies that don't use wp_term_taxonomy schema
function &rs_get_term_descendants($requested_parent_id, $qualified_terms, $taxonomy) {
	//d_echo ("qualified terms:");	
	//dump($qualified_terms);
	
	if ( empty($qualified_terms) )
		return array();

	$term_list = array();
	$has_children = rs_get_terms_children($taxonomy);
	
	if  ( $requested_parent_id && ! isset($has_children[$requested_parent_id]) ) {
		$arr = array();
		return $arr;
	}
	
	global $scoper;
	$tx = $scoper->taxonomies->get($taxonomy);	
	$col_id = $tx->source->cols->id;	
	$col_parent = $tx->source->cols->parent;
	
	foreach ( $qualified_terms as $term ) {
		$use_id = false;
		if ( !is_object($term) ) {					// rs_get_term calls get_term for WP core taxonomies, otherwise...
			$term = get_term($term, $taxonomy);		// todo: abstract equivalent (must wp-cache, return at least col_id, col_parent) 
			if ( is_wp_error( $term ) )
				return $term;
			$use_id = true;
		}

		if ( $term->$col_id == $requested_parent_id )
			continue;
		
		// if this qualified term has the requested parent, log it and all its descendants
		if ( $term->$col_parent == $requested_parent_id ) {
			if ( $use_id )
				$descendant_list[] = $term->$col_id;
			else
				$descendant_list[] = $term;

			if ( !isset($has_children[$term->$col_id]) )
				continue;
	
			if ( $descendants = rs_get_term_descendants($term->$col_id, $qualified_terms, $taxonomy) )
				$descendant_list = array_merge($descendant_list, $descendants);
		}
	}
	
	return $descendant_list;
}


// Rewritten from WP core pad_term_counts to make object count reflect any user-specific roles
// Recalculates term counts by including items from child terms (or if pad_counts is false, simply credits each term for readable private posts)
// Assumes all relevant children are already in the $terms argument
function rs_tally_term_counts(&$terms, $taxonomy, $object_type = '', $args = '') {
	global $wpdb, $scoper;
	
	$defaults = array ( 'pad_counts' => true, 'skip_teaser' => false );
	$args = array_merge( $defaults, $args );
	extract($args);
	
	if ( ! $terms )
		return;
	
	if ( ! $tx = $scoper->taxonomies->get($taxonomy) )
		return;
	
	if ( empty($tx->cols->count) || empty($tx->object_source) )
		return;
		
	$term_items = array();
	$terms_by_id = array();
	foreach ( $terms as $key => $term ) {
		$terms_by_id[$term->{$tx->source->cols->id}] = & $terms[$key];
		$term_ids[$term->{$tx->cols->term2obj_tid}] = $term->{$tx->source->cols->id};  // key and value will match for WP < 2.3 and other non-taxonomy category types
	}

	$src = $scoper->data_sources->get('post');
	$categorized_types = array('post');
	foreach ( array_keys($src->object_types) as $this_object_type )
		if ( 'post' != $this_object_type )
			if ( scoper_get_otype_option( 'use_term_roles', 'post', $this_object_type ) )
				$categorized_types []= $this_object_type;

	// Get the object and term ids and stick them in a lookup table
	$request = "SELECT DISTINCT $wpdb->posts.ID, tt.term_taxonomy_id, tt.term_id, tr.object_id"
			 . " FROM $wpdb->posts"
			 . " INNER JOIN $wpdb->term_relationships AS tr ON $wpdb->posts.ID = tr.object_id "
			 . " INNER JOIN $wpdb->term_taxonomy AS tt ON tr.term_taxonomy_id = tt.term_taxonomy_id "
			 . " WHERE tt.term_id IN ('" . implode("','", $term_ids) . "') "
			 . " AND $wpdb->posts.post_type IN ('" . implode("','", $categorized_types) . "')";
	
	// no need to pass any parameters which do not pertain to the objects_request filter
	$args = array_intersect_key( $args, array_flip( array('skip_teaser') ) );

	if ( 1 == count($categorized_types) )
		$object_type = reset($categorized_types);

	$request = apply_filters('objects_request_rs', $request, $tx->object_source->name, $object_type, $args);

	$results = scoper_get_results($request);
	
	foreach ( $results as $row ) {
		$id = $term_ids[$row->term_taxonomy_id];
		if ( isset($term_items[$id][$row->object_id]) )
			++$term_items[$id][$row->object_id];
		else
			$term_items[$id][$row->object_id] = 1;
	}
	
	// credit each term for every object contained in any of its descendant terms
	if ( $pad_counts && rs_get_terms_children($taxonomy) ) {
		foreach ( $term_ids as $term_id ) {
			$child_term_id = $term_id;
			
			while ( isset($terms_by_id[$child_term_id]->{$tx->source->cols->parent}) ) {
				if ( ! $parent_term_id = $terms_by_id[$child_term_id]->{$tx->source->cols->parent} )
					break;
				
				if ( ! empty($term_items[$term_id]) )
					foreach ( array_keys($term_items[$term_id]) as $item_id )
						$term_items[$parent_term_id][$item_id] = 1;
						
				$child_term_id = $parent_term_id;
			}
		}
	}
	
	// Tally and apply the item credits
	foreach ( $term_items as $term_id => $items )
		if ( isset($terms_by_id[$term_id]) )
			$terms_by_id[$term_id]->count = count($items);
			
	// update count property for zero-item terms too 
	foreach ( array_keys($terms_by_id) as $term_id )
		if ( ! isset($term_items[$term_id]) )
			if ( is_object($terms_by_id[$term_id]) )
				$terms_by_id[$term_id]->count = 0;
}

?>