<?php

	function scoper_establish_status_caps() {
		global $wp_post_types;
	
		$use_post_types = scoper_get_option( 'use_post_types' );
		
		$post_types = array_diff_key( get_post_types( array( 'public' => true ), 'object' ), array( 'attachment' => true ) );
		
		$front_end_statuses = get_post_stati( array( 'internal' => null, 'protected' => null ), 'object' );

		foreach( $post_types as $post_type => $post_type_obj ) {
			if ( empty( $use_post_types[$post_type] ) )
				continue;
			
			// force edit_published, edit_private, delete_published, delete_private cap definitions
			foreach ( $front_end_statuses as $status => $status_obj ) {	
				foreach( array( 'read', 'edit', 'delete' ) as $op ) {		
					if ( ( 'read' == $op ) && ( ! empty($status_obj->public) ) )
						continue;

					$status_string = ( 'publish' == $status ) ? 'published' : $status;
					$posts_cap_name = "{$op}_{$status_string}_posts";
					
					// only alter the cap setting if it's not already set
					if ( empty( $post_type_obj->cap->$posts_cap_name ) ) {
						
						if ( in_array( $status, array('publish', 'private') ) || ! empty($status_obj->customize_caps) ) {	// TODO: RS Options to set this
							// this status is built in or was marked for full enforcement of custom capabilities
							$wp_post_types[$post_type]->cap->$posts_cap_name = "{$op}_{$status_string}_{$post_type}s";
						} else {
							// default to this post type's own equivalent private or published cap
							if ( ! empty($status_obj->private) )
								$wp_post_types[$post_type]->cap->$posts_cap_name = "{$op}_private_{$post_type}s";
							elseif ( ! empty($status_obj->public) )
								$wp_post_types[$post_type]->cap->$posts_cap_name = "{$op}_published_{$post_type}s";
						}
					}
				} // end foreach op (read/edit/delete)
				
				// also define a "set_status" cap for custom statuses (to accompany "publish_posts" cap requirement when setting or removing this post status)
				if ( ! in_array( $status, array( 'publish', 'private' ) ) ) {
					$posts_cap_name = "set_{$status}_posts";
					if ( empty( $post_type_obj->cap->$posts_cap_name ) ) {
						if ( ! empty($status_obj->customize_caps) ) {	// TODO: RS Options to set this
							// this status was marked for full enforcement of custom capabilities
							$wp_post_types[$post_type]->cap->$posts_cap_name = "set_{$status}_{$post_type}s";
						} else {
							$wp_post_types[$post_type]->cap->$posts_cap_name = "publish_{$post_type}s";
						}
					}
				}

			} // end foreach front end status 
			
			if ( empty( $post_type_obj->cap->delete_posts ) )
				$wp_post_types[$post_type]->cap->delete_posts = "delete_{$post_type}s";
							
			if ( empty( $post_type_obj->cap->delete_others_posts ) )
				$wp_post_types[$post_type]->cap->delete_others_posts = "delete_others_{$post_type}s";
		} // end foreach post type
	}

function scoper_force_distinct_post_caps() {  // but only if the post type has RS usage enabled
	global $wp_post_types;
	
	//scoper_refresh_default_otype_options();
	
	$use_post_types = scoper_get_option( 'use_post_types' );
	
	$generic_caps = array();
	foreach( array( 'post', 'page' ) as $post_type )
		$generic_caps[$post_type] = array_values( get_object_vars( $wp_post_types[$post_type]->cap ) );
		
	foreach( array_keys($wp_post_types) as $type ) {
		if ( empty( $use_post_types[$type] ) )
			continue;

		$wp_post_types[$type]->capability_type = $type;
			
		// don't allow any capability defined for this type to match any capability defined for post or page (unless this IS post or page type)
		foreach( get_object_vars( $wp_post_types[$type]->cap ) as $cap_property => $type_cap )
			foreach( array( 'post', 'page' ) as $generic_type )
				if ( ( $type != $generic_type ) & in_array( $type_cap, $generic_caps[$generic_type] ) )
					$wp_post_types[$type]->cap->$cap_property = str_replace( 'post', $type, $cap_property );	
	}
}

function scoper_force_distinct_taxonomy_caps() { // but only if the taxonomy has RS usage enabled
	global $wp_taxonomies;

	$use_taxonomies = scoper_get_option( 'use_taxonomies' );
	
	// note: we are allowing the 'assign_terms' property to retain its default value of 'edit_posts'.  The RS user_has_cap filter will convert it to the corresponding type-specific cap as needed.
	$type_specific_caps = array( 'edit_terms' => 'manage_terms', 'manage_terms' => 'manage_terms', 'delete_terms' => 'manage_terms' );
	$used_values = array();
	
	// currently, disallow category and post_tag cap use by custom taxonomies, but don't require category and post_tag to have different caps
	$core_taxonomies = array( 'category', 'post_tag' );
	foreach( $core_taxonomies as $taxonomy )
		foreach( array_keys($type_specific_caps) as $cap_property )
			$used_values []= $wp_taxonomies[$taxonomy]->cap->$cap_property;

	$used_values = array_unique( $used_values );

	foreach( $wp_taxonomies as $taxonomy => $taxonomy_obj ) {
		if ( empty( $use_taxonomies[$taxonomy] ) )
			continue;

		if ( empty( $taxonomy_obj->public ) || in_array( $taxonomy, $core_taxonomies ) )
			continue;
		elseif( 'yes' == $taxonomy_obj->public )	// clean up a GD Taxonomies quirk (otherwise wp_get_taxonomy_object will fail when filtering for public => true)
			$wp_taxonomies[$taxonomy]->public = true;

		// don't allow any capability defined for this taxonomy to match any capability defined for category or post tag (unless this IS category or post tag)
		foreach( $type_specific_caps as $cap_property => $replacement_cap_format ) {
			if ( ! empty($taxonomy_obj->cap->$cap_property) && in_array( $taxonomy_obj->cap->$cap_property, $used_values ) )
				$wp_taxonomies[$taxonomy]->cap->$cap_property = str_replace( 'terms', "{$taxonomy}s", $replacement_cap_format );
				
			$used_values []= $taxonomy_obj->cap->$cap_property;
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
	if ( ! empty($use_taxonomies) || strpos( $_SERVER['REQUEST_URI'], 'admin.php?page=rs-options' ) ) {
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
			if ( ! empty($use_taxonomies[$taxonomy]) || strpos( $_SERVER['REQUEST_URI'], 'admin.php?page=rs-options' ) ) { // always load taxonomy ID data for Realm Options display
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

function scoper_add_custom_data_sources(&$data_sources) {
	//global $scoper;
	//$data_sources =& $scoper->data_sources->members;

	global $wp_post_types;
	
	$custom_types = get_post_types( array(), 'object' );

	$core_types = array( 'post', 'page', 'attachment', 'revision', 'nav_menu_item' );
	
	foreach ( $custom_types as $name => $otype ) {
		if ( ! in_array( $name, $core_types ) ) {
			$captype = $name;
			
			$singular_label = ( ! empty($otype->labels->singular_name) ) ? $otype->labels->singular_name : $otype->singular_label;
			$data_sources['post']->object_types[$name] = (object) array( 'val' => $name, 'uri' => array( "wp-admin/add-{$name}.php", "wp-admin/manage-{$name}.php" ), 'display_name' => $singular_label, 'display_name_plural' => $otype->label, 'ignore_object_hierarchy' => true, 'admin_default_hide_empty' => true, 'admin_max_unroled_objects' => 100 );
			
			$data_sources['post']->reqd_caps['read'][$name] = array(
				"published" => 	array( "read" ), 	
				"private" => 	array( "read", "read_private_{$captype}s" )
			);

			$data_sources['post']->reqd_caps['edit'][$name] = array(
				"published" =>	array( "edit_others_{$captype}s", "edit_published_{$captype}s" ),
				"private" => 	array( "edit_others_{$captype}s", "edit_published_{$captype}s", "edit_private_{$captype}s" ), 
				"draft" => 		array( "edit_others_{$captype}s" ),
				"pending" => 	array( "edit_others_{$captype}s" ),
				"future" => 	array( "edit_others_{$captype}s" ),
				"trash" => 		array( "edit_others_{$captype}s" )
			);	
	
			$data_sources['post']->reqd_caps['admin'] = $data_sources['post']->reqd_caps['edit'];
		}
	}
}

function scoper_add_custom_post_cap_defs( &$cap_defs ) {	
	$post_types = get_post_types( array( 'public' => true, '_builtin' => false ), 'object' );
	$use_post_types = scoper_get_option( 'use_post_types' );
	
	foreach ( $post_types as $name => $post_type_obj ) {
		if ( empty( $use_post_types[$name] ) )
			continue;
		
		$cap = $post_type_obj->cap;

		if ( ! isset( $cap_defs[$cap->read_private_posts] ) )
			$cap_defs[$cap->read_private_posts] =	(object) array( 'src_name' => 'post', 'object_type' => $name, 'op_type' => OP_READ_RS, 		'owner_privilege' => true, 			'status' => 'private' );
		
		if ( ! isset( $cap_defs[$cap->edit_posts] ) )
			$cap_defs[$cap->edit_posts] = 			(object) array( 'src_name' => 'post', 'object_type' => $name, 'op_type' => OP_EDIT_RS,		'owner_privilege' => true, 			'no_custom_remove' => true );
		
		if ( ! isset( $cap_defs[$cap->edit_others_posts] ) )
			$cap_defs[$cap->edit_others_posts] =  	(object) array( 'src_name' => 'post', 'object_type' => $name, 'op_type' => OP_EDIT_RS, 		'attributes' => array('others'), 	'base_cap' => $cap->edit_posts, 		'no_custom_remove' => true  );
		
		if ( ! isset( $cap_defs[$cap->edit_private_posts] ) )
			$cap_defs[$cap->edit_private_posts] =  	(object) array( 'src_name' => 'post', 'object_type' => $name, 'op_type' => OP_EDIT_RS,		'owner_privilege' => true, 			'status' => 'private' );
		
		if ( ! isset( $cap_defs[$cap->edit_published_posts] ) )
			$cap_defs[$cap->edit_published_posts] = (object) array( 'src_name' => 'post', 'object_type' => $name, 'op_type' => OP_EDIT_RS,		'status' => 'published' );
		
		if ( ! isset( $cap_defs[$cap->delete_posts] ) )
			$cap_defs[$cap->delete_posts] =  		(object) array( 'src_name' => 'post', 'object_type' => $name, 'op_type' => OP_DELETE_RS,	'owner_privilege' => true );
		
		if ( ! isset( $cap_defs[$cap->delete_others_posts] ) )
			$cap_defs[$cap->delete_others_posts] =  (object) array( 'src_name' => 'post', 'object_type' => $name, 'op_type' => OP_DELETE_RS, 	'attributes' => array('others'),	'base_cap' => $cap->delete_posts );
		
		if ( ! isset( $cap_defs[$cap->delete_private_posts] ) )
			$cap_defs[$cap->delete_private_posts] = (object) array( 'src_name' => 'post', 'object_type' => $name, 'op_type' => OP_DELETE_RS,	'status' => 'private' );
		
		if ( ! isset( $cap_defs[$cap->delete_published_posts] ) )
			$cap_defs[$cap->delete_published_posts] = (object) array( 'src_name' => 'post', 'object_type' => $name, 'op_type' => OP_DELETE_RS,	'status' => 'published' );
		
		if ( ! isset( $cap_defs[$cap->publish_posts] ) )
			$cap_defs[$cap->publish_posts] = 		(object) array( 'src_name' => 'post', 'object_type' => $name, 'op_type' => OP_PUBLISH_RS );
			
		if ( $post_type_obj->hierarchical )
			$cap_defs["create_child_{$name}s"] = (object) array( 'src_name' => 'post', 'object_type' => $name, 'op_type' => OP_ASSOCIATE_RS, 'no_custom_add' => true, 'no_custom_remove' => true, 'defining_module' => 'role-scoper' );
	}
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


function scoper_add_custom_post_role_caps( &$role_caps ) {
	$post_types = get_post_types( array( 'public' => true, '_builtin' => false ), 'object' );
	$use_post_types = scoper_get_option( 'use_post_types' );
	
	foreach ( $post_types as $name => $post_type_obj ) {
		if ( empty( $use_post_types[$name] ) )
			continue;
		
		$cap = $post_type_obj->cap;
		
		$role_caps["rs_{$name}_reader"] = array(
			"read" => true
		);
		$role_caps["rs_private_{$name}_reader"] = array(
			$cap->read_private_posts => true,
			"read" => true
		);
		$role_caps["rs_{$name}_contributor"] = array(
			$cap->edit_posts => true,
			$cap->delete_posts => true,
			"read" => true
		);
		
		if ( defined( 'RVY_VERSION' ) )
			$role_caps["rs_{$name}_revisor"] = array(
				$cap->edit_posts => true,
				$cap->delete_posts => true,
				"read" => true,
				$cap->read_private_posts => true,
				$cap->edit_others_posts => true
			);
		
		$role_caps["rs_{$name}_author"] = array(
			"upload_files" => true,
			$cap->publish_posts => true,
			$cap->edit_published_posts => true,
			$cap->delete_published_posts => true,
			$cap->edit_posts => true,
			$cap->delete_posts => true,
			"read" => true
		);
		$role_caps["rs_{$name}_editor"] = array(
			"moderate_comments" => true,
			$cap->delete_others_posts => true,
			$cap->edit_others_posts => true,
			"upload_files" => true,
			$cap->publish_posts => true,
			$cap->delete_private_posts => true,
			$cap->edit_private_posts => true,
			$cap->delete_published_posts => true,
			$cap->edit_published_posts => true,
			$cap->delete_posts => true,
			$cap->edit_posts => true,
			$cap->read_private_posts => true,
			"read" => true
		);
		
		// Note: create_child_pages should only be present in associate role, which is used as an object-assigned alternate to blog-wide edit role
		// This way, blog-assignment of author role allows user to create new pages, but only as subpages of pages they can edit (or for which Associate role is object-assigned)
		if ( $post_type_obj->hierarchical ) {
			$arr["rs_{$name}_associate"] = array( 
				"create_child_{$name}s" => true,
				'read' => true
			);
		}
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


function scoper_add_custom_post_role_defs( &$role_defs ) {
	$post_types = get_post_types( array( 'public' => true, '_builtin' => false ), 'object' );
	$use_post_types = scoper_get_option( 'use_post_types' );
	
	foreach ( $post_types as $name => $post_type_obj ) {
		if ( empty( $use_post_types[$name] ) )
			continue;
		
		$role_defs["rs_{$name}_reader"] = 			(object) array( 'valid_scopes' => array( 'blog' => true, 'term' => true ),  'object_type' => $name, 'anon_user_blogrole' => true );
		$role_defs["rs_private_{$name}_reader"] =	(object) array( 'objscope_equivalents' => array("rs_{$name}_reader"),  'object_type' => $name );
	
		$role_defs["rs_{$name}_contributor"] =		(object) array( 'objscope_equivalents' => array("rs_{$name}_revisor"),  'object_type' => $name );
		$role_defs["rs_{$name}_author"] =			(object) array( 'valid_scopes' => array( 'blog' => true, 'term' => true,  'object_type' => $name ) );
		
		if ( defined( 'RVY_VERSION' ) )
			$role_defs["rs_{$name}_revisor"] = 			(object) array( 'valid_scopes' => array( 'blog' => true, 'term' => true,  'object_type' => $name ) );
		
		$role_defs["rs_{$name}_editor"] = 			(object) array( 'objscope_equivalents' => array("rs_{$name}_author"),  'object_type' => $name );
		
		if ( $post_type_obj->hierarchical ) {												
			$arr["rs_{$name}_associate"] =	(object) array( 'object_type' => $name );

			if ( is_admin() )
				$arr["rs_{$name}_associate"]->no_custom_caps = true;
		}
		
		$role_defs["rs_private_{$name}_reader"]->other_scopes_check_role = array( 'private' => "rs_private_{$name}_reader", '' => "rs_{$name}_reader",  'object_type' => $name );
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