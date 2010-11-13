<?php

// NOTE: scoper_get_option() applies these defaults, but pre-load them for options UI display
function supplement_default_options_rs( $def ) {
	$post_types = array_diff_key( get_post_types( array( 'public' => true ), 'object' ), array( 'attachment' => true ) );
	foreach ( $post_types as $type => $type_obj ) {
		$def['use_post_types'][$type] = 1;

		//if ( $type_obj->hierarchical )
		//	$def['lock_top_level'][$type] = 0;
	}

	if ( awp_ver( '3.0' ) )
		$taxonomies = get_taxonomies( array( 'public' => true ) );
	else {
		global $wp_taxonomies;
		$taxonomies = array();
		
		foreach( array_keys($wp_taxonomies) as $taxonomy ) {
			if ( ! empty( $wp_taxonomies[$taxonomy]->public ) )
				$wp_taxonomies[] = $taxonomy;
		}	
	}	
		
	foreach ( $taxonomies as $taxonomy ) {
		if ( ! in_array( $taxonomy, array( 'link_category', 'ngg_tag', 'nav_menu' ) ) )
			$def['use_taxonomies'][$taxonomy] = 1;
	}

	$def['use_taxonomies']['post_tag'] = 0;
	
	return $def;
}

?>