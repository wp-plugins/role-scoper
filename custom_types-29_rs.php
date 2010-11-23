<?php

function scoper_add_custom_post_types(&$data_sources) {
	//global $scoper;
	//$data_sources =& $scoper->data_sources->members;

	$custom_types = get_post_types( array(), 'object' );

	$core_types = array( 'post', 'page', 'attachment', 'revision', 'nav_menu_item' );
	
	foreach ( $custom_types as $otype ) {
		if ( ! in_array( $otype->name, $core_types ) ) {
			$name = $otype->name;	
			$captype = $otype->capability_type;
			
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
	if ( awp_ver( '2.9' ) ) {
		$custom_types = get_post_types( array(), 'object' );

		$core_types = array( 'post', 'page', 'attachment', 'revision', 'nav_menu_item' );
		
		foreach ( $custom_types as $otype ) {
			if ( ! in_array( $otype->name, $core_types ) ) {
				$name = $otype->name;
				$captype = $otype->capability_type;

				if ( $captype && ( 'post' != $captype ) ) {
					$cap_defs["read_private_{$captype}s"] =		(object) array( 'src_name' => 'post', 'object_type' => $name, 'op_type' => OP_READ_RS, 		'owner_privilege' => true, 			'status' => 'private' );
					$cap_defs["edit_{$captype}s"] = 			(object) array( 'src_name' => 'post', 'object_type' => $name, 'op_type' => OP_EDIT_RS,		'owner_privilege' => true, 			'no_custom_remove' => true );
					$cap_defs["edit_others_{$captype}s"] =  	(object) array( 'src_name' => 'post', 'object_type' => $name, 'op_type' => OP_EDIT_RS, 		'attributes' => array('others'), 	'base_cap' => "edit_{$captype}s", 		'no_custom_remove' => true  );
					$cap_defs["edit_private_{$captype}s"] =  	(object) array( 'src_name' => 'post', 'object_type' => $name, 'op_type' => OP_EDIT_RS,		'owner_privilege' => true, 			'status' => 'private' );
					$cap_defs["edit_published_{$captype}s"] = 	(object) array( 'src_name' => 'post', 'object_type' => $name, 'op_type' => OP_EDIT_RS,		'status' => 'published' );
					$cap_defs["delete_{$captype}s"] =  			(object) array( 'src_name' => 'post', 'object_type' => $name, 'op_type' => OP_DELETE_RS,	'owner_privilege' => true );
					$cap_defs["delete_others_{$captype}s"] =  	(object) array( 'src_name' => 'post', 'object_type' => $name, 'op_type' => OP_DELETE_RS, 	'attributes' => array('others'),	'base_cap' => "delete_{$captype}s" );
					$cap_defs["delete_private_{$captype}s"] =  	(object) array( 'src_name' => 'post', 'object_type' => $name, 'op_type' => OP_DELETE_RS,	'status' => 'private' );
					$cap_defs["delete_published_{$captype}s"] = (object) array( 'src_name' => 'post', 'object_type' => $name, 'op_type' => OP_DELETE_RS,	'status' => 'published' );
					$cap_defs["publish_{$captype}s"] = 			(object) array( 'src_name' => 'post', 'object_type' => $name, 'op_type' => OP_PUBLISH_RS );
				}
			}
		}
	}
}

function scoper_add_custom_post_role_caps( &$role_caps ) {
	$custom_types = get_post_types( array(), 'object' );

	$core_types = array( 'post', 'page', 'attachment', 'revision', 'nav_menu_item' );
	
	foreach ( $custom_types as $otype ) {
		if ( ! in_array( $otype->name, $core_types ) ) {
			$captype = $otype->capability_type;

			$role_caps["rs_{$captype}_reader"] = array(
				"read" => true
			);
			
			$role_caps["rs_private_{$captype}_reader"] = array(
				"read_private_{$captype}s" => true,
				"read" => true
			);
			$role_caps["rs_{$captype}_contributor"] = array(
				"edit_{$captype}s" => true,
				"delete_{$captype}s" => true,
				"read" => true
			);
			$role_caps["rs_{$captype}_revisor"] = array(
				"edit_{$captype}s" => true,
				"delete_{$captype}s" => true,
				"read" => true,
				"read_private_{$captype}s" => true,
				"edit_others_{$captype}s" => true
			);
			$role_caps["rs_{$captype}_author"] = array(
				"upload_files" => true,
				"publish_{$captype}s" => true,
				"edit_published_{$captype}s" => true,
				"delete_published_{$captype}s" => true,
				"edit_{$captype}s" => true,
				"delete_{$captype}s" => true,
				"read" => true
			);
			$role_caps["rs_{$captype}_editor"] = array(
				"moderate_comments" => true,
				"delete_others_{$captype}s" => true,
				"edit_others_{$captype}s" => true,
				"upload_files" => true,
				"publish_{$captype}s" => true,
				"delete_private_{$captype}s" => true,
				"edit_private_{$captype}s" => true,
				"delete_published_{$captype}s" => true,
				"edit_published_{$captype}s" => true,
				"delete_{$captype}s" => true,
				"edit_{$captype}s" => true,
				"read_private_{$captype}s" => true,
				"read" => true
			);
		}
	}
}

function scoper_add_custom_post_role_defs( &$role_defs ) {
	$custom_types = get_post_types( array(), 'object' );
	
	$core_types = array( 'post', 'page', 'attachment', 'revision', 'nav_menu_item' );
	
	foreach ( $custom_types as $otype ) {
		if ( ! in_array( $otype->name, $core_types ) ) {
			$name = $otype->name;
			
			$role_defs["rs_{$name}_reader"] = 			(object) array( 'valid_scopes' => array( 'blog' => true, 'term' => true ),  'object_type' => $name, 'anon_user_blogrole' => true );
			$role_defs["rs_private_{$name}_reader"] =	(object) array( 'objscope_equivalents' => array("rs_{$name}_reader") );
		
			$role_defs["rs_{$name}_contributor"] =		(object) array( 'objscope_equivalents' => array("rs_{$name}_revisor") );
			$role_defs["rs_{$name}_author"] =			(object) array( 'valid_scopes' => array( 'blog' => true, 'term' => true ) );
			$role_defs["rs_{$name}_revisor"] = 			(object) array( 'valid_scopes' => array( 'blog' => true, 'term' => true ) );
			$role_defs["rs_{$name}_editor"] = 			(object) array( 'objscope_equivalents' => array("rs_{$name}_author") );
			
			$role_defs["rs_private_{$name}_reader"]->other_scopes_check_role = array( 'private' => "rs_private_{$name}_reader", '' => "rs_{$name}_reader" );
		}
	}
}
?>