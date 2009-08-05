<?php
if( basename(__FILE__) == basename($_SERVER['SCRIPT_FILENAME']) )
	die();

/**
 * functions for the WordPress plugin Role Scoper
 * defaults_rs.php
 * 
 * @description 
 * These functions define create an object collection of data sources, object types, taxonomies, capabilites 
 * and roles which dictate what access Role Scoper filters. The object properties are a combination of
 * database schema information and RS functionality switches.
 *
 * These definitions may be modified by filters which are applied in Scoper::Scoper() and Scoper::load_config().
 * For an example of 3rd party usage, see the plugin rs-config-ngg (Role Scoping for NextGenGallery)
 *
 * Note: for performance, default config mirrors Class definitions using stdObject cast from array
 *
 * @author 		Kevin Behrens
 * @copyright 	Copyright 2009
 * 
 */

function scoper_default_options() {
	$def = array(
		'persistent_cache' => 1,
		'define_usergroups' => 1,
		'enable_group_roles' => 1,
		'enable_user_roles' => 1,
		'role_type' => 'rs',
		'rs_blog_roles' => 1,
		'custom_user_blogcaps' => 0,
		'enable_wp_taxonomies' => array( 'category' => 1, 'link_category' => 1),
		'no_frontend_admin' => 0,
		'indicate_blended_roles' => 1,
		'force_autosave_enable' => 0,
		'version_update_notice' => 1,
		'version_check_minutes' => 30,
		'strip_private_caption' => 0,
		'display_hints' => 1,
		'pending_revisions' => 0,
		'hide_non_editor_admin_divs' => 1,
		'role_admin_blogwide_editor_only' => 0,
		'feed_link_http_auth' => 'logged',
		'rss_private_feed_mode' => 'title_only',
		'rss_nonprivate_feed_mode' => 'full_content',
		'feed_teaser' => "View the content of this <a href='%permalink%'>article</a>",
		'rs_page_reader_role_objscope' => 0,
		'rs_page_author_role_objscope' => 0,
		'rs_post_reader_role_objscope' => 0,
		'rs_post_author_role_objscope' => 0,
		'lock_top_pages' => 0,
		'display_user_profile_groups' => 1,
		'display_user_profile_roles' => 1,
		'user_role_assignment_csv' => 0
		//'assume_javascript' => 0  //possible todo: implement this
	);

	return $def;
}


function scoper_default_otype_options() {
	$def = array();

	//------------------------ DEFAULT OBJECT TYPE OPTIONS ---------------------		
	// 	format for second key is {src_name}:{object_type}
	$def['do_teaser'] ['post'] = false;  	// enable/disable teaser for WP posts and pages
	$def['use_teaser'] ['post:post'] = 1;  // use teaser (if enabled) for WP posts.  Note: Use integer because this option is multi-select.  Other valid setting is "excerpt"
	$def['use_teaser'] ['post:page'] = 1;  // use teaser (if enabled) for WP pages
	$def['teaser_hide_private']['post:post'] = 0;
	$def['teaser_hide_private']['post:page'] = 0;
	$def['teaser_logged_only'] ['post:post'] = 0;
	$def['teaser_logged_only'] ['post:page'] = 0;

	$def['teaser_replace_content']		['post:post'] = "Sorry, this content requires additional permissions.  Please contact an administrator for help.";
	$def['teaser_replace_content_anon']	['post:post'] = "Sorry, you don't have access to this content.  Please log in or contact a site administrator for help.";
	$def['teaser_prepend_content']		['post:post'] = '';
	$def['teaser_prepend_content_anon']	['post:post'] = '';
	$def['teaser_append_content']		['post:post'] = '';
	$def['teaser_append_content_anon']	['post:post'] = '';
	$def['teaser_prepend_name']			['post:post'] = '(';
	$def['teaser_prepend_name_anon']	['post:post'] = '(';
	$def['teaser_append_name']			['post:post'] = ')*';
	$def['teaser_append_name_anon']		['post:post'] = ')*';
	$def['teaser_replace_excerpt']		['post:post'] = '';
	$def['teaser_replace_excerpt_anon']	['post:post'] = '';
	$def['teaser_prepend_excerpt']		['post:post'] = '';
	$def['teaser_prepend_excerpt_anon']	['post:post'] = '';
	$def['teaser_append_excerpt']		['post:post'] = "<br /><small>note: This content requires a higher login level.</small>";
	$def['teaser_append_excerpt_anon']	['post:post'] = "<br /><small>note: This content requires site login.</small>";
	
	$def['teaser_replace_content']		['post:page'] = "Sorry, this content requires additional permissions.  Please contact an administrator for help.";
	$def['teaser_replace_content_anon']	['post:page'] = "Sorry, you don't have access to this content.  Please log in or contact a site administrator for help.";
	$def['teaser_prepend_content']		['post:page'] = '';
	$def['teaser_prepend_content_anon']	['post:page'] = '';
	$def['teaser_append_content']		['post:page'] = '';
	$def['teaser_append_content_anon']	['post:page'] = '';
	$def['teaser_prepend_name']			['post:page'] = '(';
	$def['teaser_prepend_name_anon']	['post:page'] = '(';
	$def['teaser_append_name']			['post:page'] = ')*';
	$def['teaser_append_name_anon']		['post:page'] = ')*';
	$def['teaser_replace_excerpt']		['post:page'] = '';
	$def['teaser_replace_excerpt_anon']	['post:page'] = '';
	$def['teaser_prepend_excerpt']		['post:page'] = '';
	$def['teaser_prepend_excerpt_anon']	['post:page'] = '';
	$def['teaser_append_excerpt']		['post:page'] = "<br /><small>note: This content requires a higher login level.</small>";
	$def['teaser_append_excerpt_anon']	['post:page'] = "<br /><small>note: This content requires site login.</small>";

	$def['admin_css_ids'] ['post:post'] = 'password-span; slugdiv; authordiv; commentstatusdiv; trackbacksdiv;';
	$def['admin_css_ids'] ['post:page'] = 'password-span; pageslugdiv; pageauthordiv; pageparentdiv; pagecommentstatusdiv';
	
	$def['use_term_roles']['post:post'] = 1;
	$def['use_term_roles']['post:page'] = 0;  // Wordpress core does not categorize pages by default
	$def['use_term_roles']['link:link'] = 1;
	
	$def['use_object_roles']['post:post'] = 1;
	$def['use_object_roles']['post:page'] = 1;
	
	$def['limit_object_editors']['post:post'] = 0;
	$def['limit_object_editors']['post:page'] = 0;
	
	$def['private_items_listable']['post:page'] = 1;
	
	return $def;
}

function scoper_core_access_types() {
	$arr = array( 'front' => (object) array(), 'admin' => (object) array() );
	
	if ( is_admin() ) {
		$arr['front']->display_name = _c('front-end|front-end access', 'scoper');
		$arr['admin']->display_name = _c('admin|wp-admin access', 'scoper');
	}

	// possible future TODO: reinstate property 'uri_substrings' => array() to support custom-defined access types
	return $arr;	
}

function scoper_core_data_sources() {
	global $wpdb;

	$arr = array();
	
	$is_admin = is_admin();
	
	$name = 'post';		
	$arr[$name] = (object) array(
	'table_basename' => 'posts',		
	'cols' => (object) array( 
		'id' => 'ID', 					'name' => 'post_title', 		'type' => 'post_type', 
		'owner' => 'post_author', 		'content' => 'post_content', 	'parent' => 'post_parent',
		'status' => 'post_status', 		'excerpt' => 'post_excerpt'
		),	
	'http_post_vars' => (object) array( 'id' => 'post_ID', 'category' => 'post_category' ),
	'uri_vars' => (object) array( 'id' => 'post' ),
	'uri_vars_alt' => (object) array( 'id' => array('post_id') ),
	'http_post_vars_alt' => (object) array( 'id' => array('post_id') ),

	'collections' => array ('type' => 'object_types'),
	'value_arrays' => array ('status' => 'statuses'),
	'object_types' => array( 
		'post' => (object) array(
			'val' => 		'post',
			'uri' => array( 'wp-admin/post.php', 'wp-admin/post-new.php', 'wp-admin/edit.php' ),
			'admin_default_hide_empty' => true,
			'admin_max_unroled_objects' => 100,
			'ignore_object_hierarchy' => true
			),
		'page' => (object) array(
			'val' =>		'page',
			'function' => 'is_page',
			'wp_cache_all_group' =>	'get_pages',
			'uri_vars' => (object) array( 'id' => array( 'front' => 'page_id' ) ),
			'uri' => array( 'wp-admin/page.php', 'wp-admin/page-new.php', 'wp-admin/edit-pages.php' )
			)
		),

	'statuses' => array( 
		'published' => 'publish', 'private' => 'private', 'draft' => 'draft', 'future' => 'future', 'pending' => 'pending' 
		),

	'usage' => (object) array(
		'statuses' => (object) array(
			'access_type' => array(
				'front' => array( 'published', 'private' ), 
				'admin' => array( 'published', 'private', 'draft', 'future', 'pending' )
				)
			)
		),

	'uses_taxonomies' => array( 'category' ),

	'query_hooks' => (object) array( 'request' => 'posts_request', 'results' => 'posts_results', 'listing' => 'the_posts', 'distinct' => 'posts_distinct' ),
	
	'query_replacements' => array( "OR post_author = [user_id] AND post_status = 'private'" => "OR post_status = 'private'" ),
		
	// This is somewhat redundant with cap_defs, but does allow for a clear distinction between
	// caps which are defined (cap defs) and caps which are always required for common operations (this array)
	//
	// user_can_admin functions require these caps if defined for object type, otherwise require cap_defs with matching op_type, object_type and status
	'reqd_caps' => array(
		'read' => array( 
			'post' => array(
				'published' => 	array( 'read' ), 	
				'private' => 	array( 'read', 'read_private_posts' )
				),
			'page' => array(
				'published' => 	array( 'read' ), 	
				'private' => 	array( 'read', 'read_private_pages' ) 
				)
			),
		'edit' => array(
			'post' => array(
				'published' =>	array( 'edit_others_posts', 'edit_published_posts' ),
				'private' => 	array( 'edit_others_posts', 'edit_published_posts', 'edit_private_posts' ), 
				'draft' => 		array( 'edit_others_posts' ),
				'pending' => 	array( 'edit_others_posts' ),
				'future' => 	array( 'edit_others_posts' )
				),
			'page' => array(
				'published' => 	array( 'edit_others_pages', 'edit_published_pages' ),
				'private' => 	array( 'edit_others_pages', 'edit_published_pages', 'edit_private_pages' ), 
				'draft' => 		array( 'edit_others_pages' ),
				'pending' => 	array( 'edit_others_pages' ),
				'future' => 	array( 'edit_others_pages' )
				)
			),
		'admin' => array(
			'post' => array(
				'published' =>	array( 'delete_others_posts', 'delete_published_posts' ),
				'private' => 	array( 'delete_others_posts', 'delete_published_posts', 'delete_private_posts' ), 
				'draft' => 		array( 'delete_others_posts' ),
				'pending' => 	array( 'delete_others_posts' ),
				'future' => 	array( 'delete_others_posts' )
				),
			'page' => array(
				'published' => 	array( 'delete_others_pages', 'delete_published_pages' ),
				'private' => 	array( 'delete_others_pages', 'delete_published_pages', 'delete_private_pages' ), 
				'draft' => 		array( 'delete_others_pages' ),
				'pending' => 	array( 'delete_others_pages' ),
				'future' => 	array( 'delete_others_pages' )
				)
			)
		),
			
	'terms_where_reqd_caps' => array(	// note: This criteria is not used to determine the data source, since taxonomy is always supplied to get_terms
		'front' => array(
			'' =>							array( 'post' => array('read') )
			),
		'admin' => array(
			'wp-admin/categories.php' => 	array( 'post' => array('manage_categories') ),
			'wp-admin/page.php' => 			array( 'page' => array('edit_pages') ),
			'wp-admin/page-new.php' => 		array( 'page' => array('edit_pages') ),
			'' => 							array( 'post' => array('edit_posts'), 'page' => array('edit_pages') )
			)
		),
						
	'users_where_reqd_caps' => array(	// note: A positive URI match can be used to determine data source and cap requirements.
		'front' => array(				// 		 If data source is known but URI not matched, default capreq will be used.
			'' => array( 'read' )
			),
		'admin' => array(
			'wp-admin/post-new.php' => 	array( 'edit_posts' ),
			'wp-admin/post.php' => 		array( 'edit_posts' ),
			'wp-admin/page-new.php' => 	array( 'edit_pages' ),
			'wp-admin/page.php' => 		array( 'edit_pages' ),
			'' =>						array( 'edit_posts' )
			)
		),
	); // end outer array

	$arr[$name]->query_replacements = array( "OR $wpdb->posts.post_author = [user_id] AND $wpdb->posts.post_status = 'private'" => "OR $wpdb->posts.post_status = 'private'" );
	
	if ( ! empty($_GET['preview']) ) {
		$arr[$name]->usage->statuses->access_type['front'] = array( 'draft', 'pending', 'future' ) + $arr[$name]->usage->statuses->access_type['front'];
		$arr[$name]->reqd_caps['read']['post'] = array ( 'draft' => array('edit_others_posts'), 'pending' => array('edit_others_posts'), 'future' => array('edit_others_posts') ) + $arr[$name]->reqd_caps['read']['post'];
		$arr[$name]->reqd_caps['read']['page'] = array ( 'draft' => array('edit_others_pages'), 'pending' => array('edit_others_pages'), 'future' => array('edit_others_pages') ) + $arr[$name]->reqd_caps['read']['page'];
	}
	
	/* Post data source and others following the "save_{src_name}", etc. pattern
		do not actually need to set these properties because these associations will be made by default
		
	$arr[$name]->admin_actions = (object) array(	
		'save_object' => "save_post",	'edit_object' => "edit_post", 
		'create_object' => '',			'delete_object' => "delete_post",
		'object_edit_ui' => '' );  // post data source defines an object_type-specific object_edit_ui hook
		
	$arr[$name]->admin_filters->pre_object_status = 'pre_post_status';
	*/
	
	// note: Inserting Role Scoper's role assignment interface into a Custom-defined Data Source's own item edit form
	// 		 requires two configuration steps:
	//			1. Set the object_edit_ui property to an action you call at the appropriate time
	//			2. Add the corresponding script name via 'item_edit_scripts_rs' filter
	//		 		The default value passed by that filter is array('wp-admin/post-new.php', 'wp-admin/post.php', 'wp-admin/page.php', 'wp-admin/page-new.php', 'wp-admin/categories.php') ); 
	
	// define html inserts for object role administration only if this is an admin URI
	if ( $is_admin ) {
		$arr['post']->display_name = __('Post', 'scoper');
		$arr['post']->display_name_plural = __('Posts', 'scoper');
		
		$arr['post']->object_types['post']->display_name = __('Post', 'scoper');
		$arr['post']->object_types['post']->display_name_plural = __('Posts', 'scoper');
		
		$arr['post']->object_types['page']->display_name = __('Page', 'scoper');
		$arr['post']->object_types['page']->display_name_plural = __('Pages', 'scoper');
		
		$arr['post']->edit_url = 'post.php?action=edit&amp;post=%d';  // xhtml validation fails with &post=
	
		// Sample code: Old pre-WP2.3 syntax for registering metabox action and markup can be used for custom data sources
		/*
			$arr['your_source_name']->admin_actions = (object) array( 'object_edit_ui' => 'your_action_name' );
			// - or -
			$arr['your_source_name']->object_types['your_object_type_name']->admin_actions = (object) array( 'object_edit_ui' => 'your_action_name' );
		
			$inserts = (object) array();
		
			$inserts->bottom->open = (object) array( 
				'container' => '<div class="dbx-b-ox-wrapper">' . "\r\n" . '<fieldset id="%s" class="dbx-box">', 
				'headline' => '<div class="dbx-h-andle-wrapper">' . "\r\n" . '<h3 class="dbx-handle">', 
				'content' => '<div class="dbx-c-ontent-wrapper">' . "\r\n" . '<div class="dbx-content">' );
			
			$inserts->bottom->close = (object) array( 
				'container' => '</fieldset></div>', 'headline' => '</h3></div>', 'content' => '</div></div>' );
				
			$arr['post']->admin_inserts = (object) $inserts;
		*/
		
	} //endif is_admin()
	
	if ( $is_admin || defined('XMLRPC_REQUEST') ) {
		$name = 'link';		
		$arr[$name] = (object) array(
		'table_basename' => 'links',		
		'cols' => (object) array(
			'id' => 'link_id', 				'name' => 'link_name', 			'type' => '', 
			'owner' => 'link_owner',		'status' => ''
			),
		'uses_taxonomies' => array( 'link_category' ),
		
		'query_hooks' => (object) array( 'request' => 'links_request' ),	
	
		'no_object_roles' => true
		); // end outer array
		$arr['link']->reqd_caps = array();		// object types with a single status store nullstring status key
		$arr['link']->reqd_caps['admin']['link'][''] = array( 'manage_links' );
		$arr['link']->reqd_caps['edit']['link'][''] = array( 'manage_links' );
		$arr['link']->terms_where_reqd_caps['admin']['link'][''] = array( 'manage_links' );
		
		if ( $is_admin ) {
			$arr['link']->display_name = __('Link', 'scoper');
			$arr['link']->display_name_plural = __('Links', 'scoper');
			$arr['link']->edit_url = 'link.php?action=edit&amp;link_id=%s';
		}
		
		
		//groups table 
		// scoper-defined with wp prefix by default, but can be customized to an existing non-WP table by editing db-config_rs.php
		$name = 'group';
		$arr[$name] = (object) array(
		'table_basename' => $wpdb->groups_basename,		
		'cols' => (object) array(
			'id' => $wpdb->groups_id_col,	'name' => $wpdb->groups_name_col
			),
		'uri_vars' => array( 'id' => 'id'),
		'http_post_vars' => array( 'id' => 'id'),
		
		'query_hooks' => (object) array( 'request' => 'groups_request' ),
		
		); // end outer array
		$arr['group']->reqd_caps = array();		// object types with a single status store nullstring status key
		$arr['group']->reqd_caps['admin']['group'][''] = array( 'manage_groups' );
		$arr['group']->reqd_caps['edit']['group'][''] = array( 'manage_groups' );
		
		if ( $is_admin ) {
			$arr['group']->display_name = _c('Group|of users', 'scoper');
			$arr['group']->display_name_plural = _c('Groups|of users', 'scoper');
			$arr['group']->edit_url = SCOPER_ADMIN_URL . '/groups.php&mode=edit&id=%d';
		}
	}
	
	
	// Sample code: use the following syntax to define a custom taxonomy source (that doesn't use the wp_term_taxonomy table)
	// ( WP_Scoped_Taxonomies::process will define default WP taxonomy source )
	/*
		// note: also requires 'taxonomies' definition(s)
		$name = 'your_taxonomy_source_name';
		$arr[$name] = (object) array(
		'table_basename' => 'categories',	'is_taxonomy' => true,		'taxonomy_only' => true,
		'cols' => (object) array(
			'id' => 'cat_ID',				'name' => 'cat_name',		'parent' => 'category_parent'
			),
		'http_post_vars' => (object) array( 'id' => 'cat_ID'),
		'uri_vars' => (object) array( 'id' => 'cat_ID' )
		); // end outer array
	*/
	
	return $arr;
}


function scoper_core_taxonomies() {
	$arr = array();

	$is_admin = is_admin();
	
	$name = 'category';
	$arr[$name] = (object) array(
		'requires_term' => true,	'uses_standard_schema' => true, 'hierarchical' => true, 'default_term_option' => 'default_category',	
		'admin_filters' => (object) array( 'pre_object_terms' => 'pre_post_category' ),
		'admin_actions' => (object) array( 
			'create_term' => 'create_category', 	'edit_term' => 'edit_category', 	'delete_term' => 'delete_category',		
			'save_term' => '',	'term_edit_ui' => 'edit_category_form'
		)
	); // end outer array
	
	if ( $is_admin ) {
		$arr['category']->display_name = __('Category', 'scoper');
		$arr['category']->display_name_plural = __('Categories', 'scoper');
		$arr['category']->edit_url = 'categories.php?action=edit&amp;cat_ID=%d';
	}
	
	// Sample code: use the following syntax to define custom taxonomies which use a custom source (not the wp_term_taxonomy table)
	/*
		$tx =& $arr['your_taxonomy_name'];
		$tx->requires_term = 1;
		$tx->uses_standard_schema = 0;	// this would also be set false by WP_Taxonomies::process
		
		$tx->source = 'category';  		// process_config will convert this to object reference
	
		$tx->table_term2obj_basename = 'post2cat';
		$tx->table_term2obj_alias = '';
		
		$tx->cols = (object) array( 
			'count' => 'category_count', 		'term2obj_tid' => 'category_id', 	'term2obj_oid' => 'post_id',
			'require_zero' => 'link_count', 	'require_nonzero' => '' );
			
		$tx->admin_actions->pre_object_terms = 'category_save_pre';
	*/

	// link filtering is only for management in wp-admin
	if ( $is_admin || defined('XMLRPC_REQUEST') ) {
		$name = 'link_category';  // note: also requires 'data_sources' definition
		$arr[$name] = (object) array(
			'requires_term' => true,
			'uses_standard_schema' => true, 'hierarchical' => false,
			'default_term_option' => 'default_link_category',
			'admin_actions' => array ( 'term_edit_ui' => 'edit_link_category_form' )
		); // end outer array
	
		$arr['link_category']->display_name = __('Link Category', 'scoper');
		$arr['link_category']->display_name_plural = __('Link Categories', 'scoper');
		$arr['link_category']->edit_url = 'categories.php?action=edit&amp;cat_ID=%d';
	}
	
	return $arr;
}


function scoper_core_cap_defs() {
	$arr = array(
	'read' =>  					(object) array( 'src_name' => 'post', 'object_type' => '', 'op_type' => OP_READ_RS,			'owner_privilege' => true,	'anon_user_has' => true, 'no_custom_remove' => true  ),
	
	'read_private_posts' =>  	(object) array( 'src_name' => 'post', 'object_type' => 'post', 'op_type' => OP_READ_RS, 	'owner_privilege' => true, 'status' => 'private' ),
	'edit_posts' =>  			(object) array( 'src_name' => 'post', 'object_type' => 'post', 'op_type' => OP_EDIT_RS,		'owner_privilege' => true, 'no_custom_remove' => true ),
	'edit_others_posts' =>  	(object) array( 'src_name' => 'post', 'object_type' => 'post', 'op_type' => OP_EDIT_RS, 	'attributes' => array('others'), 	'base_cap' => 'edit_posts', 'no_custom_add' => true  ),
	'edit_private_posts' =>  	(object) array( 'src_name' => 'post', 'object_type' => 'post', 'op_type' => OP_EDIT_RS,		'status' => 'private' ),
	'edit_published_posts' => 	(object) array( 'src_name' => 'post', 'object_type' => 'post', 'op_type' => OP_EDIT_RS,		'status' => 'published' ),
	'upload_files' => 			(object) array( 'src_name' => 'post', 'object_type' => '', 		'op_type' => ''	),
	'moderate_comments' => 		(object) array( 'src_name' => 'post', 'object_type' => '', 		'op_type' => '' ),
	
	'delete_posts' =>  			(object) array( 'src_name' => 'post', 'object_type' => 'post', 'op_type' => OP_DELETE_RS,	'owner_privilege' => true ),
	'delete_others_posts' =>  	(object) array( 'src_name' => 'post', 'object_type' => 'post', 'op_type' => OP_DELETE_RS, 	'attributes' => array('others'),	'base_cap' => 'delete_posts', 'no_custom_add' => true ),
	'delete_private_posts' =>  	(object) array( 'src_name' => 'post', 'object_type' => 'post', 'op_type' => OP_DELETE_RS,	'status' => 'private' ),
	'delete_published_posts' => (object) array( 'src_name' => 'post', 'object_type' => 'post', 'op_type' => OP_DELETE_RS,	'status' => 'published' ),
	'publish_posts' =>  		(object) array( 'src_name' => 'post', 'object_type' => 'post', 'op_type' => OP_PUBLISH_RS ),

	'read_private_pages' =>  	(object) array( 'src_name' => 'post', 'object_type' => 'page', 'op_type' => OP_READ_RS, 	'owner_privilege' => true, 'status' => 'private' ),
	'edit_pages' =>  			(object) array( 'src_name' => 'post', 'object_type' => 'page', 'op_type' => OP_EDIT_RS,		'owner_privilege' => true, 'no_custom_remove' => true ),
	'edit_others_pages' =>  	(object) array( 'src_name' => 'post', 'object_type' => 'page', 'op_type' => OP_EDIT_RS, 	'attributes' => array('others'),	'base_cap' => 'edit_pages', 'no_custom_remove' => true ),
	'edit_private_pages' =>  	(object) array( 'src_name' => 'post', 'object_type' => 'page', 'op_type' => OP_EDIT_RS,		'status' => 'private' ),
	'edit_published_pages' =>  	(object) array( 'src_name' => 'post', 'object_type' => 'page', 'op_type' => OP_EDIT_RS,		'status' => 'published' ),
	'delete_pages' =>  			(object) array( 'src_name' => 'post', 'object_type' => 'page', 'op_type' => OP_DELETE_RS,	'owner_privilege' => true ),
	'delete_others_pages' =>  	(object) array( 'src_name' => 'post', 'object_type' => 'page', 'op_type' => OP_DELETE_RS, 	'attributes' => array('others'),	'base_cap' => 'delete_pages', 'no_custom_add' => true ),
	'delete_private_pages' =>  	(object) array( 'src_name' => 'post', 'object_type' => 'page', 'op_type' => OP_DELETE_RS,	'status' => 'private' ),
	'delete_published_pages' => (object) array( 'src_name' => 'post', 'object_type' => 'page', 'op_type' => OP_DELETE_RS,	'status' => 'published' ),
	'publish_pages' =>  		(object) array( 'src_name' => 'post', 'object_type' => 'page', 'op_type' => OP_PUBLISH_RS ),	
	'create_child_pages' =>  	(object) array( 'src_name' => 'post', 'object_type' => 'page', 'op_type' => OP_ASSOCIATE_RS, 'no_custom_add' => true, 'no_custom_remove' => true ),
	
	'manage_links' =>  			(object) array( 'src_name' => 'link', 'object_type' => 'link', 'op_type' => OP_ADMIN_RS, 'no_custom_remove' => true ),
	
	// note: taxonomy caps carry src_name of their associated object source (in this case, post)
	'manage_categories' =>  	(object) array( 'src_name' => 'post', 'object_type' => 'category', 'op_type' => OP_ADMIN_RS, 'is_taxonomy_cap' => 'category' ),
	
	'manage_groups' =>   		(object) array( 'src_name' => 'group', 'object_type' => 'group', 'op_type' => OP_ADMIN_RS, 'no_custom_remove' => true )
	); // CapDefs array
	
	foreach ( array_keys($arr) as $cap_name )
		$arr[$cap_name]->defining_module_name = 'wordpress';
	
	// important: any rs-introduced caps in standard post / page roles must not be included in core role caps and must have no_custom_add, no_custom_remove properties set (otherwise would need to add code in get_contained_roles to disregard such caps) 
	$arr['create_child_pages']->defining_module_name = 'role-scoper';
	$arr['manage_groups']->defining_module_name = 'role-scoper';
	
	return $arr;
}

//note: rs_ is a role type prefix which is required for array key, but will be stripped off for name property
function scoper_core_role_caps() {
	// separate array is friendlier to php array function
	$arr = array(
		'rs_post_reader' => array(
			'read' => true
		),
		'rs_private_post_reader' => array(
			'read_private_posts' => true,
			'read' => true
		),
		'rs_post_contributor' => array(
			'edit_posts' => true,
			'delete_posts' => true,
			'read' => true
		),
		'rs_post_author' => array(
			'upload_files' => true,
			'publish_posts' => true,
			'edit_published_posts' => true,
			'delete_published_posts' => true,
			'edit_posts' => true,
			'delete_posts' => true,
			'read' => true
		),
		'rs_post_editor' => array(
			'moderate_comments' => true,
			'delete_others_posts' => true,
			'edit_others_posts' => true,
			'upload_files' => true,
			'publish_posts' => true,
			'delete_private_posts' => true,
			'edit_private_posts' => true,
			'delete_published_posts' => true,
			'edit_published_posts' => true,
			'delete_posts' => true,
			'edit_posts' => true,
			'read_private_posts' => true,
			'read' => true
		),
		
		'rs_page_reader' => array(
			'read' => true
		),
		'rs_private_page_reader' => array(
			'read_private_pages' => true,
			'read' => true
		),
		'rs_page_associate' => array(
			'create_child_pages' => true,
			'read' => true
		),
		// Note: create_child_pages should only be present in page associate role, which is used as an object-assigned alternate to blog-wide edit role
		// This way, blog-assignment of Page author role allows user to create new pages, but only as subpages of pages they can edit (or for which Associate role is object-assigned)
		
		'rs_page_contributor' => array(
			'edit_pages' => true,
			'delete_pages' => true,
			'read' => true
		),
		'rs_page_author' => array(
			'upload_files' => true,
			'publish_pages' => true,
			'edit_published_pages' => true,
			'delete_published_pages' => true,
			'edit_pages' => true,
			'delete_pages' => true,
			'read' => true
		),
		'rs_page_editor' => array(
			'moderate_comments' => true,
			'delete_others_pages' => true,
			'edit_others_pages' => true,
			'upload_files' => true,
			'publish_pages' => true,
			'delete_private_pages' => true,
			'edit_private_pages' => true,
			'delete_published_pages' => true,
			'edit_published_pages' => true,
			'delete_pages' => true,
			'edit_pages' => true,
			'read_private_pages' => true,
			'read' => true
		),
		
		'rs_link_editor' => array(
			'manage_links' => true
		),
		
		'rs_category_manager' => array(
			'manage_categories' => true
		),
		
		'rs_group_manager' => array(
			'manage_groups' => true
		)
		
	); // end role_caps array
	
	return $arr;
}
//
//
//note: rs_ is a role type prefix which is required for array key, but will be stripped off for name property
function scoper_core_role_defs() {
	$arr = array(
	// note: object scope converts 'others' cap requirements to base cap, so for object scope assignment, 'Authors' and 'Editors' are equivalent.  
	// Define 'Editors' roles for object assignment to avoid ambiguity with WP 'Post Author' / 'Page Author', who may have fewer caps on his object than the scoped "Authors".
	'rs_post_reader' => 		(object) array( 'valid_scopes' => array( 'blog' => true, 'term' => true ),  'object_type' => 'post', 'anon_user_blogrole' => true ),
	'rs_private_post_reader' =>	(object) array( 'objscope_equivalents' => array('rs_post_reader') ),

	'rs_post_contributor' =>	(object) array(),
	'rs_post_author' => 		(object) array( 'valid_scopes' => array( 'blog' => true, 'term' => true ) ),
	'rs_post_editor' => 		(object) array( 'objscope_equivalents' => array('rs_post_author') ),
	
	'rs_page_reader' => 		(object) array( 'valid_scopes' => array( 'blog' => true, 'term' => true ), 'object_type' => 'page', 'anon_user_blogrole' => true ),
	'rs_private_page_reader' =>	(object) array( 'objscope_equivalents' => array('rs_page_reader') ),

	'rs_page_contributor' =>	(object) array(),
	'rs_page_author' => 		(object) array( 'valid_scopes' => array( 'blog' => true, 'term' => true ) ),
	'rs_page_editor' => 		(object) array( 'objscope_equivalents' => array('rs_page_author') ),
    /* 'rs_page_associate' =>		(object) array(), //including this confuses determination of equiv. RS roles from WP blogrole */
													  // TODO: can we include it now after rc9.9222.b fix
	'rs_link_editor' =>			(object) array(),

	'rs_category_manager' =>	(object) array( 'no_custom_caps' => true ),
	
	'rs_group_manager' =>		(object) array()
	); // end role_defs array
	
	if ( is_admin() ) {
		$arr['rs_page_associate']->no_custom_caps = true;
		
		//display_name
		$arr['rs_post_reader']->display_name = 			_c('Post Reader|role', 'scoper');
		$arr['rs_private_post_reader']->display_name = 	_c('Private Post Reader|role', 'scoper');
		$arr['rs_post_contributor']->display_name = 	_c('Post Contributor|role', 'scoper');
		$arr['rs_post_author']->display_name = 			_c('Post Author|role', 'scoper');
		$arr['rs_post_editor']->display_name = 			_c('Post Editor|role', 'scoper');
		
		$arr['rs_page_reader']->display_name = 			_c('Page Reader|role', 'scoper');
		$arr['rs_private_page_reader']->display_name = 	_c('Private Page Reader|role', 'scoper');
		$arr['rs_page_associate']->display_name = 		_c('Page Associate|role', 'scoper');
		$arr['rs_page_contributor']->display_name = 	_c('Page Contributor|role', 'scoper');
		$arr['rs_page_author']->display_name = 			_c('Page Author|role', 'scoper');
		$arr['rs_page_editor']->display_name = 			_c('Page Editor|role', 'scoper');
	
		$arr['rs_link_editor']->display_name = 			_c('Link Admin|role', 'scoper');
		
		$arr['rs_category_manager']->display_name = 	_c('Category Manager|role', 'scoper');
		
		$arr['rs_group_manager']->display_name =		_c('Group Manager|role', 'scoper');
		
		//abbreviations
		$arr['rs_post_reader']->abbrev = 			_c('Readers|role', 'scoper');
		$arr['rs_private_post_reader']->abbrev = 	_c('Private Readers|role', 'scoper');
		$arr['rs_post_contributor']->abbrev = 		_c('Contributors|role', 'scoper');
		$arr['rs_post_author']->abbrev = 			_c('Authors|role', 'scoper');
		$arr['rs_post_editor']->abbrev = 			_c('Editors|role', 'scoper');
		
		$arr['rs_page_reader']->abbrev = 			_c('Readers|role', 'scoper');
		$arr['rs_private_page_reader']->abbrev = 	_c('Private Readers|role', 'scoper');
		$arr['rs_page_associate']->abbrev = 		_c('Associates|role', 'scoper');
		$arr['rs_page_contributor']->abbrev = 		_c('Contributors|role', 'scoper');
		$arr['rs_page_author']->abbrev = 			_c('Authors|role', 'scoper');
		$arr['rs_page_editor']->abbrev = 			_c('Editors|role', 'scoper');
	
		$arr['rs_link_editor']->abbrev = 			_c('Admins|role', 'scoper');
		
		$arr['rs_category_manager']->abbrev = 		_c('Managers|role', 'scoper');
			
		$arr['rs_group_manager']->abbrev =			_c('Managers|role', 'scoper');
		
		//micro abbreviations
		$arr['rs_post_reader']->micro_abbrev = 			_c('Reader|role', 'scoper');
		$arr['rs_private_post_reader']->micro_abbrev = 	_c('Pvt Reader|role', 'scoper');
		$arr['rs_post_contributor']->micro_abbrev = 	_c('Contrib|role', 'scoper');
		$arr['rs_post_author']->micro_abbrev = 			_c('Author|role', 'scoper');
		$arr['rs_post_editor']->micro_abbrev = 			_c('Editor|role', 'scoper');
		
		$arr['rs_page_reader']->micro_abbrev = 			_c('Reader|role', 'scoper');
		$arr['rs_private_page_reader']->micro_abbrev = 	_c('Pvt Reader|role', 'scoper');
		$arr['rs_page_associate']->micro_abbrev = 		_c('Assoc|role', 'scoper');
		$arr['rs_page_contributor']->micro_abbrev = 	_c('Contrib|role', 'scoper');
		$arr['rs_page_author']->micro_abbrev = 			_c('Author|role', 'scoper');
		$arr['rs_page_editor']->micro_abbrev = 			_c('Editor|role', 'scoper');
	
		$arr['rs_link_editor']->micro_abbrev = 			_c('Admin|role', 'scoper');
		
		$arr['rs_category_manager']->micro_abbrev = 	_c('Manager|role', 'scoper');
			
		$arr['rs_group_manager']->micro_abbrev =		_c('Manager|role', 'scoper');
		
		// We want the object-assigned reading role to enable the user/group regardless of post status setting.
		// But we don't want the caption to imply that assigning this object role MAKES the post_status private
		// Also want the "role from other scope" indication in post edit UI to reflect the post's current status
		$arr['rs_private_post_reader']->display_name_for_object_ui = _c('Post Reader|role', 'scoper');
		$arr['rs_private_post_reader']->abbrev_for_object_ui = _c('Readers|role', 'scoper');
		$arr['rs_private_post_reader']->micro_abbrev_for_object_ui = _c('Reader|role', 'scoper');
		$arr['rs_private_post_reader']->other_scopes_check_role = array( 'private' => 'rs_private_post_reader', '' => 'rs_post_reader' );
		
		$arr['rs_private_page_reader']->display_name_for_object_ui = _c('Page Reader|role', 'scoper');
		$arr['rs_private_page_reader']->abbrev_for_object_ui = _c('Readers|role', 'scoper');
		$arr['rs_private_page_reader']->micro_abbrev_for_object_ui = _c('Reader|role', 'scoper');
		$arr['rs_private_page_reader']->other_scopes_check_role = array( 'private' => 'rs_private_page_reader', '' => 'rs_page_reader' );
	} // endif is_admin
	
	foreach ( array_keys($arr) as $key )
		$arr[$key]->role_type = 'rs';
	
	return $arr;
}
?>