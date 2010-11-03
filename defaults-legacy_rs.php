<?php

// for WP < 2.9
function scoper_default_otype_options_legacy() {
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

	$def['teaser_replace_content']		['post:post'] = scoper_po_trigger( "Sorry, this content requires additional permissions.  Please contact an administrator for help." );
	$def['teaser_replace_content_anon']	['post:post'] = scoper_po_trigger( "Sorry, you don't have access to this content.  Please log in or contact a site administrator for help." );
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
	$def['teaser_append_excerpt']		['post:post'] = "<br /><small>" . scoper_po_trigger( "note: This content requires a higher login level." ) . "</small>";
	$def['teaser_append_excerpt_anon']	['post:post'] = "<br /><small>" . scoper_po_trigger( "note: This content requires site login." ) . "</small>";
	
	$def['teaser_replace_content']		['post:page'] = scoper_po_trigger( "Sorry, this content requires additional permissions.  Please contact an administrator for help." );
	$def['teaser_replace_content_anon']	['post:page'] = scoper_po_trigger( "Sorry, you don't have access to this content.  Please log in or contact a site administrator for help." );
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
	$def['teaser_append_excerpt']		['post:page'] = "<br /><small>" . scoper_po_trigger( "note: This content requires a higher login level." ) . "</small>";
	$def['teaser_append_excerpt_anon']	['post:page'] = "<br /><small>" . scoper_po_trigger( "note: This content requires site login." ) . "</small>";

	$def['admin_css_ids'] ['post:post'] = 'password-span; slugdiv; edit-slug-box; authordiv; commentstatusdiv; trackbacksdiv; postcustom; revisionsdiv';
	$def['admin_css_ids'] ['post:page'] = 'password-span; pageslugdiv; edit-slug-box; pageauthordiv; pageparentdiv; pagecommentstatusdiv; pagecustomdiv; revisionsdiv';
	
	$def['use_term_roles']['post:post']['category'] = 1;
	$def['use_term_roles']['post:page']['category'] = 0;  // Wordpress core does not categorize pages by default
	$def['use_term_roles']['link:link']['link_category'] = 1;
	
	$def['use_object_roles']['post:post'] = 1;
	$def['use_object_roles']['post:page'] = 1;
	
	$def['limit_object_editors']['post:post'] = 0;
	$def['limit_object_editors']['post:page'] = 0;
	
	$def['private_items_listable']['post:page'] = 1;
	
	$def['default_private']['post:post'] = 0;
	$def['default_private']['post:page'] = 0;
	
	$def['sync_private']['post:post'] = 0;
	$def['sync_private']['post:page'] = 0;
	
	$def['restrictions_column']['post:post'] = 1;
	$def['restrictions_column']['post:page'] = 1;
	
	$def['term_roles_column']['post:post'] = 1;
	$def['term_roles_column']['post:page'] = 1;
	
	$def['object_roles_column']['post:post'] = 1;
	$def['object_roles_column']['post:page'] = 1;

	return $def;	
}

?>