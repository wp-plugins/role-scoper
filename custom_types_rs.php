<?php

function scoper_force_distinct_post_caps() {  // but only if the post type has RS usage enabled
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

		$wp_post_types[$post_type]->capability_type = $post_type;
			
		$type_caps = (array) $wp_post_types[$post_type]->cap;
		
		// don't allow any capability defined for this type to match any capability defined for post or page (unless this IS post or page type)
		foreach( $type_caps as $cap_property => $type_cap )
			foreach( array( 'post', 'page' ) as $generic_type )
				if ( ( $post_type != $generic_type ) & in_array( $type_cap, $generic_caps[$generic_type] ) )
					$type_caps[$cap_property] = str_replace( 'post', $post_type, $cap_property );

		$wp_post_types[$post_type]->cap = (object) $type_caps;
	}
}

function scoper_add_custom_post_types(&$data_sources) {
	//global $scoper;
	//$data_sources =& $scoper->data_sources->members;

	global $wp_post_types;
	
	$custom_types = get_post_types( array(), 'object' );

	$core_types = array( 'post', 'page', 'attachment', 'revision', 'nav_menu_item' );
	
	foreach ( $custom_types as $name => $otype ) {
		if ( ! $name )
			continue;
		
		if ( ! in_array( $name, $core_types ) ) {
			$captype = $name;
			
			$singular_label = ( ! empty($otype->labels->singular_name) ) ? $otype->labels->singular_name : $otype->singular_label;
			$data_sources['post']->object_types[$name] = (object) array( 'val' => $name, 'uri' => array( "wp-admin/add-{$name}.php", "wp-admin/manage-{$name}.php" ), 'display_name' => $singular_label, 'display_name_plural' => $otype->label, 'ignore_object_hierarchy' => true, 'admin_default_hide_empty' => true, 'admin_max_unroled_objects' => 100 );
			
			if ( ! awp_ver( '3.0' ) ) {
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
			"unfiltered_html" => true,
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
			$role_caps["rs_{$name}_associate"] = array( 
				"create_child_{$name}s" => true,
				'read' => true
			);
		}
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
		$role_defs["rs_{$name}_author"] =			(object) array( 'valid_scopes' => array( 'blog' => true, 'term' => true ),  'object_type' => $name );
		
		if ( defined( 'RVY_VERSION' ) )
			$role_defs["rs_{$name}_revisor"] = 			(object) array( 'valid_scopes' => array( 'blog' => true, 'term' => true),  'object_type' => $name );
		
		$role_defs["rs_{$name}_editor"] = 			(object) array( 'objscope_equivalents' => array("rs_{$name}_author"),  'object_type' => $name );
		
		if ( $post_type_obj->hierarchical ) {												
			$role_defs["rs_{$name}_associate"] =	(object) array( 'object_type' => $name );

			if ( is_admin() )
				$role_defs["rs_{$name}_associate"]->no_custom_caps = true;
		}
		
		$role_defs["rs_private_{$name}_reader"]->other_scopes_check_role = array( 'private' => "rs_private_{$name}_reader", '' => "rs_{$name}_reader" );
	}
}

?>