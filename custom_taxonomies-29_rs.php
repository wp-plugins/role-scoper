<?php


function scoper_add_custom_taxonomies(&$taxonomies) {
	//global $scoper;
	//$taxonomies =& $scoper->taxonomies->members;
	
	// note: use_term_roles elements are auto-created (and thus eligible for scoping activation via Roles > Realm) based on registered WP taxonomies
	$arr_use_wp_taxonomies = array();

	if ( ! $use_term_roles = get_option( 'scoper_use_term_roles' ) ) {  // TODO: why does scoper_get_option not reflect values updated via scoper_update_option in version update function earlier in same request?
		global $scoper_default_otype_options;		// TODO: is this necessary?
		
		scoper_refresh_default_otype_options();
		$use_term_roles = $scoper_default_otype_options['use_term_roles'];
	}	

	$core_taxonomies = array( 'category', 'link_category', 'nav_menu' );

	foreach( array_keys($use_term_roles) as $src_otype ) 
		if ( is_array( $use_term_roles[$src_otype] ) ) {
			foreach ( array_keys($use_term_roles[$src_otype]) as $taxonomy )
				if ( $use_term_roles[$src_otype][$taxonomy] && ! in_array( $taxonomy, $core_taxonomies ) )
					$arr_use_wp_taxonomies[$taxonomy] = true;
		}

	// Detect and support additional WP taxonomies (just require activation via Role Scoper options panel)
	if ( ! empty($arr_use_wp_taxonomies) ) {
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
			if ( isset($arr_use_wp_taxonomies[$taxonomy]) || strpos( $_SERVER['REQUEST_URI'], 'admin.php?page=rs-options' ) ) { // always load taxonomy ID data for Realm Options display
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
	return;	
}

function scoper_add_custom_taxonomy_role_caps( &$cap_defs ) {
	return;	
}

function scoper_add_custom_taxonomy_role_defs( &$role_defs ) {
	return;	
}


?>