<?php
add_filter( 'map_meta_cap', '_map_meta_cap_rs', 99, 4 );

// (not currently intended as a replacement except for internal use with post, page, user)
function _map_meta_cap_rs( $caps, $meta_cap, $user_id, $args ) {
	static $meta_caps;

	$caps = (array) $caps;
	
	if ( count($meta_cap) > 1 )
		return $caps;
	
	// support usage by RS users_who_can function, which needs to remap meta caps to simple equivalent but builds owner cap adjustment into DB query
	$adjust_for_user = ( -1 !== $user_id );

	if ( ! $caps ) {
		// note: user metacap conversion for internal use with users_who_can filtering; not intended as a replacement for WP core map_meta_cap
		switch ( $meta_cap ) {
			case 'edit_user' :
				if ( isset( $args[0] ) && $user_id == $args[0] )
					return array();
				else
					return array( 'edit_users' );
					
			case 'delete_user' :
				return array( 'delete_users' );
				
			case 'remove_user' :
				return array( 'remove_users' );
				
			case 'promote_user':
				return array( 'promote_users' );
		} // end switch
	}
	
	if ( ! isset( $meta_caps ) ) {
		$meta_caps = array();

		$post_types = array_diff_key( get_post_types( array( 'public' => true ), 'object' ), array( 'attachment' => true ) );

		foreach( $post_types as $type => $post_type_obj ) {
			$meta_caps ['read'] []= $post_type_obj->cap->read_post;
			$meta_caps ['edit'] []= $post_type_obj->cap->edit_post;
			$meta_caps ['delete'] []= $post_type_obj->cap->delete_post;
		}
	}

	$matched_op = false;
	foreach( array_keys($meta_caps) as $op ) {
		if ( in_array( reset($caps), $meta_caps[$op] ) ) {
			$caps = array_diff( $caps, $meta_caps[$op] );
			$matched_op = $op;
			break;	
		}
	}
	
	
	if ( $matched_op ) {
		if ( ! $post = get_post( $args[0] ) )
			return $caps;

		if ( ! $post_type_obj = get_post_type_object( $post->post_type ) )
			return $caps;
			
		if ( ! $post_status_obj = get_post_status_object( $post->post_status ) )
			return $caps;
			
		// no need to modify meta caps for post/page checks with built-in status
		if ( in_array( $post->post_type, array( 'post', 'page' ) ) && $post_status_obj->_builtin && $adjust_for_user && $caps )
			return $caps;

		if ( ! $adjust_for_user ) {
			$is_post_author = true;

		} elseif ( ! $post->post_author ) {
			$is_post_author = true;	//No author set yet so treat current user as author for cap checks
			//$require_all_status_caps = true;
		} else {
			$post_author_data = get_userdata( $post->post_author );
			$is_post_author = ( $user_id == $post_author_data->ID );
			//$require_all_status_caps = ! defined( 'SCOPER_LEGACY_META_CAPS' );
		}

		switch ( $op ) {
		case 'read':
			if ( ! empty($post_status_obj->private) ) {
				if ( $is_post_author )
					$caps[] = 'read';
				else
					$caps[] = $post_type_obj->cap->read_private_posts;
			} else
				$caps[] = 'read';

			break;
			
		case 'edit' :
			$caps[] = $post_type_obj->cap->edit_posts;
		
			// The post is public, extra cap required.
			if ( ! empty($post_status_obj->public) ) {
				$caps[] = $post_type_obj->cap->edit_published_posts;
			
			} elseif ( 'trash' == $post->post_status ) {
				if ('publish' == get_post_meta($post->ID, '_wp_trash_meta_status', true) )
					$caps[] = $post_type_obj->cap->edit_published_posts;
			}
				
			// note: as of 3.0, WP core requires edit_published_posts, but not edit_private_posts, when logged user is the post author.  That's inconsistent when used in conjunction with custom statuses
			if ( ! empty($post_status_obj->private) && ! $is_post_author )
				$caps[] = $post_type_obj->cap->edit_private_posts;
				
			if ( ! $is_post_author )
				$caps[] = $post_type_obj->cap->edit_others_posts;	// The user is trying to edit someone else's post.

			break;
			
		case 'delete' :
			$caps[] = $post_type_obj->cap->delete_posts;
		
			// The post is public, extra cap required.
			if ( ! empty($post_status_obj->public) ) {
				$caps[] = $post_type_obj->cap->delete_published_posts;
			
			} elseif ( 'trash' == $post->post_status ) {
				if ('publish' == get_post_meta($post->ID, '_wp_trash_meta_status', true) )
					$caps[] = $post_type_obj->cap->delete_published_posts;
			}
				
			// note: as of 3.0, WP core requires delete_published_posts, but not delete_private_posts, when logged user is the post author.  That's inconsistent when used in conjunction with custom statuses
			if ( ! empty($post_status_obj->private) && ! $is_post_author )
				$caps[] = $post_type_obj->cap->delete_private_posts;
				
			if ( ! $is_post_author )
				$caps[] = $post_type_obj->cap->delete_others_posts;	// The user is trying to delete someone else's post.

			break;
		} // end switch
		
		// if a capability is defined for this custom status, require it also
		//if ( $require_all_status_caps ) {
			if ( empty($post_status_obj->_builtin) ) {
				$status_cap_name = "{$op}_{$post->post_status}_posts";
				if ( ! empty( $post_type_obj->cap->$status_cap_name ) )
					$caps []= $post_type_obj->cap->$status_cap_name;
			}
		//}
			
		$caps = array_unique( $caps );
		
		//print_r($caps);
	}
			
	return apply_filters( 'map_meta_cap_rs', $caps, $meta_cap, $user_id, $args );
}
?>