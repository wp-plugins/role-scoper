<?php

// account for different contexts of get_terms calls 
// (Scoped roles can dictate different results for front end, edit page/post, manage categories)
function _cr_get_terms_reqd_caps( $taxonomy, $operation = '', $is_term_admin = false ) {
	global $scoper;
		
	if ( taxonomy_exists( $taxonomy ) )
		$src_name = 'post';
	else
		$src_name = $scoper->taxonomies->member_property( $taxonomy, 'object_source' );
			
	$full_uri = urldecode($_SERVER['REQUEST_URI']);
	$return_caps = array();

	$is_term_admin = $is_term_admin || strpos( $full_uri, "wp-admin/edit-tags.php" );	// possible TODO: abstract for non-WP taxonomies

	if ( $is_term_admin ) {
		// query pertains to the management of terms
		if ( 'post' == $src_name ) {
			$taxonomy_obj = get_taxonomy( $taxonomy );
			$return_caps[$taxonomy] = array( $taxonomy_obj->cap->manage_terms );
		} else {
			$return_caps[$taxonomy] = $scoper->cap_defs->get_matching( $src_name, $taxonomy, OP_ADMIN_RS );
		}	 	
	} else {
		// query pertains to reading or editing content within certain terms, or adding terms to content
		
		// if we are dealing with a specific object, only return required caps which apply to it
		if ( $object_id = $scoper->data_sources->detect( 'id', $src_name ) ) {
			// TODO: de-abstract this for post data source
			$owner_id = $scoper->data_sources->get_from_db('owner', $src_name, $object_id);

			$base_caps_only = ( $owner_id == $GLOBALS['current_user']->ID );
				
			$status = $scoper->data_sources->detect('status', $src_name, $object_id);
			$object_type = $scoper->data_sources->detect('type', $src_name, $object_id);
		} else {
			$base_caps_only = true;
			
			if ( 'post' == $src_name ) {
				$status = ( is_admin() ) ? 'draft' : 'published';	// we want to retrieve basic object access caps for current access type (possible TODO: define a default_status property)

				// terms query should be limited to a single object type for post.php, post-new.php, so only return caps for that object type (TODO: do this in wp-admin regardless of URI ?)
				if ( strpos( $full_uri, "wp-admin/post.php" ) || strpos( $full_uri, "wp-admin/post-new.php" ) )
					$object_type = awp_post_type_from_uri();
			} else
				$status = '';
		}
		
		// The return array will indicate term role enable / disable, as well as associated capabilities
		if ( ! empty($object_type) )
			$check_object_types = array( $object_type );
		else {
			if ( $check_object_types = (array) $scoper->data_sources->member_property( $src_name, 'object_types' ) )
				$check_object_types = array_keys( $check_object_types );
		}
			
		$enabled_object_types = array();
		foreach ( $check_object_types as $_object_type ) {
			if ( $use_term_roles = scoper_get_otype_option( 'use_term_roles', $src_name, $_object_type ) )
				if ( ! empty( $use_term_roles[$taxonomy] ) )
					$enabled_object_types []= $_object_type;
		}

		if ( empty($operation) )
			$operation = ( $scoper->is_front() || strpos($_SERVER['SCRIPT_NAME'], 'p-admin/profile.php') ) ? 'read' : 'edit';  // hack to support subscribe2 categories checklist

		foreach( $enabled_object_types as $object_type ) {
			$return_caps[$object_type] = _cr_get_reqd_caps( $src_name, $operation, $object_type, $status, $base_caps_only );	
		}
	}
	
	return $return_caps;
}

function _cr_get_reqd_caps( $src_name, $op, $object_type = '-1', $status = '-1', $base_caps_only = false ) {
	if ( ( $object_type == -1 ) && ( $status == -1 ) && ! $base_caps_only ) {
		// only set / retrieve the static buffer for default Query_Interceptor usage
		static $reqd_caps;
		
		if ( ! isset($reqd_caps) )
			$reqd_caps = array();
	} else
		$reqd_caps = array();
	
	if ( ! isset( $reqd_caps[$src_name][$op] ) ) {
		$arr = array();
		
		switch ( $src_name ) {
		case 'post' :
			$property = "{$op}_posts";
			$others_property = "{$op}_others_posts";

			if ( ( -1 != $object_type ) && post_type_exists($object_type) )
				$post_types = array( $object_type => get_post_type_object($object_type) );
			else
				$post_types = array_diff_key( get_post_types( array( 'public' => true ), 'object' ), array( 'attachment' => true ) );
			
			if ( ( -1 != $status ) && isset($GLOBALS['wp_post_statuses'][$status]) ) {
				$post_statuses = array( $status => get_post_status_object($status) );
			} else {
				$post_statuses = get_post_stati( array( 'internal' => null ), 'object' );
				$post_statuses []= get_post_status_object( 'trash' );
			}

			foreach ( $post_types as $_post_type => $post_type_obj ) {	
				$cap = $post_type_obj->cap;
		
				if ( 'read' != $op ) {
					// for "delete" op, select a value from stored cap property in this order of preference, if the proerpty is defined: delete_others_posts, delete_posts, edit_others_posts, edit_posts
					if ( ! empty($cap->$others_property) && ! $base_caps_only )
						$main_cap = $cap->$others_property;
					elseif ( ! empty($cap->$property) )
						$main_cap = $cap->$property;
					elseif ( ! empty($cap->edit_others_posts) && ! $base_caps_only )
						$main_cap = $cap->edit_others_posts;
					else
						$main_cap = $cap->edit_posts;
				}
				
				$use_statuses = ( ! empty( $post_type_obj->statuses ) ) ? $post_type_obj->statuses : $post_statuses;
	
				$use_statuses = array_intersect_key( $use_statuses, $post_statuses );
				
				foreach( $use_statuses as $status_obj ) {
					//$_status = $status_obj->name;
					$_status = ( 'publish' == $status_obj->name ) ? 'published' : $status_obj->name;
					
					if ( ( 'read' == $op ) ) {
						if ( 'trash' == $_status )
							continue;
	
						if ( ! $status_obj->protected && ! $status_obj->internal ) {
							// read
							$arr['read'][$_post_type][$_status] = array( 'read' );
							
							if ( ! $base_caps_only ) {
								// read_private_posts
								if ( $status_obj->private )
									$arr['read'][$_post_type][$_status] []= $cap->read_private_posts;
									
								// read_{$_status}_posts (if defined)
								if ( 'publish' != $_status ) {
									$status_cap = "read_{$_status}_{$_post_type}s";
									if ( ! empty( $cap->$status_cap ) )
										$arr['read'][$_post_type][$_status] []= $status_cap;
								}
			
								$arr['read'][$_post_type][$_status] = array_unique( $arr['read'][$_post_type][$_status] );
							}
						
						} elseif ( ! empty($_GET['preview']) && ( 'trash' != $_status ) ) {
							// preview supports non-published statuses, but requires edit capability
							//  array ( 'draft' => array($cap->edit_others_posts), 'pending' => array('edit_others_posts'), 'future' => array('edit_others_posts'), 'publish' => array('read'), 'private' => array('read', 'read_private_posts') );
							if ( $base_caps_only )
								$arr['read'][$_post_type][$_status] = array( $cap->edit_posts );
							else
								$arr['read'][$_post_type][$_status] = array( $cap->edit_others_posts );
						}
					
					} else { // op == delete / edit / other
						// edit_posts / edit_others_posts
						$arr[$op][$_post_type][$_status] []= $main_cap;
	
						// edit_published_posts
						if ( $status_obj->public || $status_obj->private ) {
							$property = "{$op}_published_posts";
							$arr[$op][$_post_type][$_status] []= $cap->$property;
						}
						
						if ( ! $base_caps_only ) {
							// edit private posts
							if ( $status_obj->private ) {
								$property = "{$op}_private_posts";
								$arr[$op][$_post_type][$_status] []= $cap->$property;
							}
								
							// edit_{$_status}_posts (if defined)

							$status_cap = "{$op}_{$status}_{$_post_type}s";
							if ( ! empty( $cap->$status_cap ) )
								$arr[$op][$_post_type][$_status] []= $status_cap;
						}
							
						$arr[$op][$_post_type][$_status] = array_unique( $arr[$op][$_post_type][$_status] );
					}
					
				} // end foreach status
			} // end foreach post type
		
			// TODO: re-implement OP_ADMIN distinction with dedicated admin caps
			//$arr['admin'] = $arr['edit'];
			
		break;
		case 'link' :
			$arr['edit']['link'][''] = array( 'manage_links' );		// object types with a single status store nullstring status key
			//$arr['admin']['link'][''] = array( 'manage_links' );
			
		break;
		case 'group' :
			$arr['edit']['group'][''] = array( 'manage_groups' );
			//$arr['admin']['group'][''] = array( 'manage_groups' );
		
		break;
		default:
			global $scoper;
			if ( $src = $scoper->data_sources->get( $src_name ) ) {
				if ( isset( $src->reqd_caps ) )	// legacy API support
					$arr = $src->reqd_caps;
			}
		} // end src_name switch
	
		if ( empty( $arr[$op] ) )
			$arr[$op] = array();

		$reqd_caps[$src_name][$op] = apply_filters( 'define_required_caps_rs', $arr[$op], $src_name, $op );

	} // endif pulling from static buffer
	
	if ( ( -1 != $status ) && ( -1 != $object_type ) ) {
		if ( isset( $reqd_caps[$src_name][$op][$object_type][$status] ) )
			return $reqd_caps[$src_name][$op][$object_type][$status];
		else
			return array();
			
	} elseif ( -1 != $object_type ) {
		if ( isset( $reqd_caps[$src_name][$op][$object_type] ) )
			return $reqd_caps[$src_name][$op][$object_type];
		else
			return array();
	} else {
		if( isset( $reqd_caps[$src_name][$op] ) )
			return $reqd_caps[$src_name][$op];
		else
			return array();
	}
}

?>