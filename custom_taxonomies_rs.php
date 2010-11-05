<?php

function scoper_force_distinct_taxonomy_caps() { // but only if the taxonomy has RS usage enabled
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

	foreach( array_keys($wp_taxonomies) as $taxonomy ) {
		if ( 'yes' == $wp_taxonomies[$taxonomy]->public ) {	// clean up a GD Taxonomies quirk (otherwise wp_get_taxonomy_object will fail when filtering for public => true)
			$wp_taxonomies[$taxonomy]->public = true;
		
		} elseif ( ( '' === $wp_taxonomies[$taxonomy]->public ) && ( ! empty( $wp_taxonomies[$taxonomy]->query_var_bool ) ) ) { // clean up a More Taxonomies quirk (otherwise wp_get_taxonomy_object will fail when filtering for public => true)
			$wp_taxonomies[$taxonomy]->public = true;
		}
		
		if ( empty( $use_taxonomies[$taxonomy] ) || empty( $wp_taxonomies[$taxonomy]->public ) || in_array( $taxonomy, $core_taxonomies ) )
			continue;

		$tx_caps = (array) $wp_taxonomies[$taxonomy]->cap;

		// don't allow any capability defined for this taxonomy to match any capability defined for category or post tag (unless this IS category or post tag)
		foreach( $tx_specific_caps as $cap_property => $replacement_cap_format ) {
			if ( ! empty($tx_caps[$cap_property]) && in_array( $tx_caps[$cap_property], $used_values ) )
				$wp_taxonomies[$taxonomy]->cap->$cap_property = str_replace( 'terms', "{$taxonomy}s", $replacement_cap_format );
				
			$used_values []= $tx_caps[$cap_property];
		}
	}
}


function scoper_add_custom_taxonomies(&$taxonomies) {
	//global $scoper;
	//$taxonomies =& $scoper->taxonomies->members;
	
	// note: use_term_roles elements are auto-created (and thus eligible for scoping activation via Roles > Realm) based on registered WP taxonomies
	$use_taxonomies = scoper_get_option( 'use_taxonomies' );

	$core_taxonomies = array( 'category', 'link_category', 'nav_menu' );
	
	// Detect and support additional WP taxonomies (just require activation via Role Scoper options panel)
	if ( ! empty($use_taxonomies) || strpos( $_SERVER['REQUEST_URI'], 'admin.php?page=rs-options' ) || strpos( $_SERVER['REQUEST_URI'], 'admin.php?page=rs-site_options' ) ) {
		global $scoper, $wp_taxonomies, $wp_post_types;
		
		if ( defined( 'CUSTAX_DB_VERSION' ) ) {	// Extra support for Custom Taxonomies plugin
			global $wpdb;
			if ( ! empty($wpdb->custom_taxonomies) ) {
				$custom_taxonomies = array();
				$results = $wpdb->get_results( "SELECT * FROM $wpdb->custom_taxonomies" );  // * to support possible future columns
				foreach ( $results as $row )
					$custom_taxonomies[$row->slug] = $row;
			}
		} else
			$custom_taxonomies = array();

		foreach ( $wp_taxonomies as $taxonomy => $wp_tax ) {
			if ( in_array( $taxonomy, $core_taxonomies ) )
				continue;
			
			// taxonomy must be approved for scoping and have a Scoper-defined object type
			if ( ! empty($use_taxonomies[$taxonomy]) || strpos( $_SERVER['REQUEST_URI'], 'admin.php?page=rs-options' ) || strpos( $_SERVER['REQUEST_URI'], 'admin.php?page=rs-site_options' ) ) { // always load taxonomy ID data for Realm Options display
				$tx_otypes = (array) $wp_tax->object_type;

				foreach ( $tx_otypes as $wp_tax_object_type ) {
				
					if ( isset($wp_post_types[$wp_tax_object_type]) || isset( $scoper->data_sources->members['post']->object_types[$wp_tax_object_type] ) )
						$src_name = 'post';
					elseif ( $scoper->data_sources->is_member($wp_tax_object_type) ) 
						$src_name = $wp_tax_object_type;
					elseif ( ! $src_name = $scoper->data_sources->is_member_alias($wp_tax_object_type) )  // in case the 3rd party plugin uses a taxonomy->object_type property different from the src_name we use for RS data source definition
						continue;
						
					// create taxonomies definition if necessary (additional properties will be set later)
					$taxonomies[$taxonomy] = (object) array(
						'name' => $taxonomy,								
						'uses_standard_schema' => 1,	'autodetected_wp_taxonomy' => 1,
						'hierarchical' => $wp_tax->hierarchical,
						'object_source' => $scoper->data_sources->get( $src_name )
					);
					
					$taxonomies[$taxonomy]->requires_term = $wp_tax->hierarchical;	// default all hierarchical taxonomies to strict, non-hierarchical to non-strict

					if ( isset( $custom_taxonomies[$taxonomy] ) && ! empty( $custom_taxonomies[$taxonomy]->plural ) ) {
						$taxonomies[$taxonomy]->display_name = $custom_taxonomies[$taxonomy]->name;
						$taxonomies[$taxonomy]->display_name_plural = $custom_taxonomies[$taxonomy]->plural;
						
						// possible future extension to Custom Taxonomies plugin: ability to specify "required" property apart from hierarchical property (and enforce it in Edit Forms)
						if ( isset( $custom_taxonomies[$taxonomy]->required ) )
							$taxonomies[$taxonomy]->requires_term = $custom_taxonomies[$taxonomy]->required;
					} else {
						$taxonomies[$taxonomy]->display_name = ( ! empty( $wp_tax->singular_label ) ) ? $wp_tax->singular_label : ucwords( __( preg_replace('/[_-]/', ' ', $taxonomy) ) );
						$taxonomies[$taxonomy]->display_name_plural = ( ! empty( $wp_tax->label ) ) ? $wp_tax->label : $taxonomies[$taxonomy]->display_name;			
					}
				}	
			} // endif scoping is enabled for this taxonomy
		} // end foreach taxonomy known to WP core
	} // endif any taxonomies have scoping enabled		
}


function scoper_add_custom_taxonomy_cap_defs( &$cap_defs ) {	
	$taxonomies = get_taxonomies( array( 'public' => true, '_builtin' => false ), 'object' );
	$use_taxonomies = scoper_get_option( 'use_taxonomies' );
	
	foreach ( $taxonomies as $name => $tx_obj ) {
		if ( empty( $use_taxonomies[$name] ) )
			continue;
		
		$cap = $tx_obj->cap;
		
		if ( ! isset( $cap_defs[$cap->manage_terms] ) )
			$cap_defs[ $cap->manage_terms ] = 	(object) array( 'src_name' => 'post', 'object_type' => $name, 'op_type' => OP_ADMIN_RS, 'is_taxonomy_cap' => true );  // possible TODO: set src_name as taxonomy source instead?
		
		if ( ! isset( $cap_defs[$cap->edit_terms] ) )
			$cap_defs[ $cap->edit_terms ] = 	(object) array( 'src_name' => 'post', 'object_type' => $name, 'op_type' => OP_ADMIN_RS, 'is_taxonomy_cap' => true );
		
		if ( ! isset( $cap_defs[$cap->delete_terms] ) )
			$cap_defs[ $cap->delete_terms ] = 	(object) array( 'src_name' => 'post', 'object_type' => $name, 'op_type' => OP_ADMIN_RS, 'is_taxonomy_cap' => true );
	}
}


function scoper_add_custom_taxonomy_role_caps( &$role_caps ) {
	$taxonomies = get_taxonomies( array( 'public' => true, '_builtin' => false ), 'object' );
	$use_taxonomies = scoper_get_option( 'use_taxonomies' );
	
	foreach ( $taxonomies as $name => $tx_obj ) {
		if ( empty( $use_taxonomies[$name] ) )
			continue;
		
		$cap = $tx_obj->cap;
		
		$role_caps["rs_{$name}_manager"] = array(
			$cap->manage_terms => true,
			$cap->edit_terms => true,
			$cap->delete_terms => true
		);
	}
}


function scoper_add_custom_taxonomy_role_defs( &$role_defs ) {
	$taxonomies = get_taxonomies( array( 'public' => true, '_builtin' => false ), 'object' );
	$use_taxonomies = scoper_get_option( 'use_taxonomies' );
	
	foreach ( $taxonomies as $name => $tx_obj ) {
		if ( empty( $use_taxonomies[$name] ) )
			continue;
		
		$role_defs["rs_{$name}_manager"] = (object) array( 'src_name' => 'post', 'object_type' => $name, 'no_custom_caps' => true );
	}
}

?>