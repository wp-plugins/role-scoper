<?php

// test_cap = 'edit_posts' or 'manage_terms'
// default_cap_prefix = 'edit_' or 'manage_'
//
// note: valid usage is after force_distinct_post_caps() and force_distinct_taxonomy_caps have been applied to object
function plural_name_from_cap_rs( $type_obj ) {
	if ( isset( $type_obj->cap->edit_posts ) ) {
		$test_cap = $type_obj->cap->edit_posts;
		$default_cap_prefix = 'edit_';
	} elseif( isset( $type_obj->cap->manage_terms ) ) {
		$test_cap = $type_obj->cap->manage_terms;
		$default_cap_prefix = 'manage_';
	} else
		return isset( $type_obj->name ) ? $type_obj->name . 's' : '';

	if ( ( 0 === strpos( $test_cap, $default_cap_prefix ) ) 
	&& ( false === strpos( $test_cap, '_', strlen($default_cap_prefix) ) ) )
		return substr( $test_cap, strlen($default_cap_prefix) );
	else
		return $type_obj->name . 's';
} 

class WP_Cap_Helper_CR {
	function establish_status_caps() {
		global $wp_post_types;
		
		$use_post_types = scoper_get_option( 'use_post_types' );
		
		$post_types = array_diff_key( get_post_types( array( 'public' => true ) ), array( 'attachment' => true ) );
		
		$stati = get_post_stati( array( 'internal' => false ), 'object' );

		foreach( $post_types as $post_type ) {
			$plural_name = plural_name_from_cap_rs( $wp_post_types[$post_type] );

			// copy existing cap values so we don't overwrite them
			$type_caps = (array) $wp_post_types[$post_type]->cap;
			
			if ( 'attachment' == $post_type ) {
				$is_attachment_type = true;
				$post_type = 'post';
			} else {
				$is_attachment_type = false;
				
				if ( empty( $use_post_types[$post_type] ) )
					continue;
			}
				
			// force edit_published, edit_private, delete_published, delete_private cap definitions
			foreach ( $stati as $status => $status_obj ) {
				if ( empty($status_obj->moderation) && ! $status_obj->public && ! $status_obj->private ) // don't mess with draft or future
					continue;
		
				foreach( array( 'read', 'edit', 'delete' ) as $op ) {		
					if ( ( 'read' == $op ) && ( $status_obj->public || ! empty( $status_obj->moderation ) ) )
						continue;

					$status_string = ( 'publish' == $status ) ? 'published' : $status;
					$posts_cap_name = "{$op}_{$status_string}_posts";
					
					// only alter the cap setting if it's not already set
					if ( empty( $type_caps[$posts_cap_name] ) ) {
						if ( ! empty( $status_obj->customize_caps ) ) {	// TODO: RS Options to set this
							// this status is built in or was marked for full enforcement of custom capabilities
							$type_caps[$posts_cap_name] = "{$op}_{$status_string}_{$plural_name}";
						} else {
							// default to this post type's own equivalent private or published cap
							if ( $status_obj->private )
								$type_caps[$posts_cap_name] = "{$op}_private_{$plural_name}";
								
							elseif ( $status_obj->public )
								$type_caps[$posts_cap_name] = "{$op}_published_{$plural_name}";
						}
					}
				} // end foreach op (read/edit/delete)
				
				// also define a "set_status" cap for custom statuses (to accompany "publish_posts" cap requirement when setting or removing this post status)
				if ( ! in_array( $status, array( 'publish', 'private' ) ) ) {
					$posts_cap_name = "set_{$status}_posts";
					if ( empty( $type_caps[$posts_cap_name] ) ) {
						if ( ! empty( $status_obj->customize_caps ) ) {	// TODO: RS Options to set this
							// this status was marked for full enforcement of custom capabilities
							$type_caps[$posts_cap_name] = "set_{$status}_{$plural_name}";
						} elseif( $status_obj->public || $status_obj->private ) {
							$type_caps[$posts_cap_name] = "publish_{$plural_name}";
						}
					}
				}

			} // end foreach front end status 
			
			if ( empty( $type_caps['delete_posts'] ) )
				$type_caps['delete_posts'] = "delete_{$plural_name}";
							
			if ( empty( $type_caps['delete_others_posts'] ) )
				$type_caps['delete_others_posts'] = "delete_others_{$plural_name}";

			if ( $is_attachment_type )
				$post_type = 'attachment';

			$wp_post_types[$post_type]->cap = (object) $type_caps;
		} // end foreach post type

	}

	function force_distinct_post_caps() {  // but only if the post type has RS usage enabled
		global $wp_post_types;
		
		$type_caps = array();
		
		//scoper_refresh_default_otype_options();
		
		$use_post_types = scoper_get_option( 'use_post_types' );
		
		$generic_caps = array();
		foreach( array( 'post', 'page' ) as $post_type )
			$generic_caps[$post_type] = (array) $wp_post_types[$post_type]->cap;
			
		foreach( array_keys($wp_post_types) as $post_type ) {
			if ( empty( $use_post_types[$post_type] ) )
				continue;

			if ( 'post' === $wp_post_types[$post_type]->capability_type )
				$wp_post_types[$post_type]->capability_type = $post_type;

			$type_caps = (array) $wp_post_types[$post_type]->cap;
			
			/* $plural_name =  */	// as of WP 3.1, no basis for determinining this unless type-specific caps are set
			
			// don't allow any capability defined for this type to match any capability defined for post or page (unless this IS post or page type)
			foreach( $type_caps as $cap_property => $type_cap )
				foreach( array( 'post', 'page' ) as $generic_type )
					if ( ( $post_type != $generic_type ) && in_array( $type_cap, $generic_caps[$generic_type] ) )
						$type_caps[$cap_property] = str_replace( $generic_type, $post_type, $cap_property );
	
			$wp_post_types[$post_type]->cap = (object) $type_caps;
		}
	}
	
	function force_distinct_taxonomy_caps() {
		global $wp_taxonomies;
	
		$use_taxonomies = scoper_get_option( 'use_taxonomies' );
		
		// note: we are allowing the 'assign_terms' property to retain its default value of 'edit_posts'.  The RS user_has_cap filter will convert it to the corresponding type-specific cap as needed.
		$tx_specific_caps = array( 'edit_terms' => 'manage_terms', 'manage_terms' => 'manage_terms', 'delete_terms' => 'manage_terms' );
		$used_values = array();
		
		// currently, disallow category and post_tag cap use by custom taxonomies, but don't require category and post_tag to have different caps
		$core_taxonomies = array( 'category' );
		foreach( $core_taxonomies as $taxonomy )
			foreach( array_keys($tx_specific_caps) as $cap_property )
				$used_values []= $wp_taxonomies[$taxonomy]->cap->$cap_property;
	
		$used_values = array_unique( $used_values );
	
		$use_taxonomies['nav_menu'] = true;
	
		foreach( array_keys($wp_taxonomies) as $taxonomy ) {
			if ( 'yes' == $wp_taxonomies[$taxonomy]->public ) {	// clean up a GD Taxonomies quirk (otherwise wp_get_taxonomy_object will fail when filtering for public => true)
				$wp_taxonomies[$taxonomy]->public = true;
			
			} elseif ( ( '' === $wp_taxonomies[$taxonomy]->public ) && ( ! empty( $wp_taxonomies[$taxonomy]->query_var_bool ) ) ) { // clean up a More Taxonomies quirk (otherwise wp_get_taxonomy_object will fail when filtering for public => true)
				$wp_taxonomies[$taxonomy]->public = true;
			}
			if ( empty( $use_taxonomies[$taxonomy] ) || in_array( $taxonomy, $core_taxonomies ) )
				continue;
	
			$tx_caps = (array) $wp_taxonomies[$taxonomy]->cap;
	
			$plural_name = $taxonomy . 's';	// as of WP 3.1, no basis for determinining this unless type-specific caps are set
	
			// don't allow any capability defined for this taxonomy to match any capability defined for category or post tag (unless this IS category or post tag)
			foreach( $tx_specific_caps as $cap_property => $replacement_cap_format ) {
				if ( ! empty($tx_caps[$cap_property]) && in_array( $tx_caps[$cap_property], $used_values ) )
					$wp_taxonomies[$taxonomy]->cap->$cap_property = str_replace( 'terms', $plural_name, $replacement_cap_format );

				$used_values []= $tx_caps[$cap_property];
			}
		}
	}
} // end class WP_Cap_Helper_CR
?>