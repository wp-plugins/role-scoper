<?php

// (not currently intended as a replacement except for internal use with post, page, user)
function _map_meta_cap_rs( $caps, $meta_cap, $user_id, $args ) {
	$args = array_slice( func_get_args(), 2 );
	$caps = array();

	// support usage by RS users_who_can function, which needs to remap meta caps to simple equivalent but builds owner cap adjustment into DB query
	$adjust_for_user = ( -1 !== $user_id );

	switch ( $meta_cap ) {
	case 'delete_user':
		$caps[] = 'delete_users';
		break;
	case 'edit_user':
		if ( !isset( $args[0] ) || $user_id != $args[0] ) {
			$caps[] = 'edit_users';
		}
		break;
	case 'delete_post':
		if ( $adjust_for_user )
			$author_data = get_userdata( $user_id );
		
		//echo "post ID: {$args[0]}<br />";
		$post = get_post( $args[0] );
		if ( 'page' == $post->post_type ) {
			$args = array_merge( array( 'delete_page', $user_id ), $args );
			return call_user_func_array( 'map_meta_cap_rs', $args );
		}
		
		if ( $adjust_for_user )
			$post_author_data = get_userdata( $post->post_author );
		
		//echo "current user id : $user_id, post author id: " . $post_author_data->ID . "<br />";
		// If the user is the author...
		if ( $adjust_for_user && ( $user_id == $post_author_data->ID ) ) {
			// If the post is published...
			if ( 'publish' == $post->post_status )
				$caps[] = 'delete_published_posts';
			else
				// If the post is draft...
				$caps[] = 'delete_posts';
		} else {
			// The user is trying to edit someone else's post.
			$caps[] = 'delete_others_posts';
			// The post is published, extra cap required.
			if ( 'publish' == $post->post_status )
				$caps[] = 'delete_published_posts';
			elseif ( 'private' == $post->post_status )
				$caps[] = 'delete_private_posts';
		}
		break;
	case 'delete_page':
		if ( $adjust_for_user )
			$author_data = get_userdata( $user_id );
		
		//echo "post ID: {$args[0]}<br />";
		$page = get_page( $args[0] );
		
		if ( $adjust_for_user )
			$page_author_data = get_userdata( $page->post_author );

		//echo "current user id : $user_id, page author id: " . $page_author_data->ID . "<br />";
		// If the user is the author...
		if ( $adjust_for_user && ( $user_id == $page_author_data->ID ) ) {
			// If the page is published...
			if ( $page->post_status == 'publish' )
				$caps[] = 'delete_published_pages';
			else
				// If the page is draft...
				$caps[] = 'delete_pages';
		} else {
			// The user is trying to edit someone else's page.
			$caps[] = 'delete_others_pages';
			// The page is published, extra cap required.
			if ( $page->post_status == 'publish' )
				$caps[] = 'delete_published_pages';
			elseif ( $page->post_status == 'private' )
				$caps[] = 'delete_private_pages';
		}
		break;
		// edit_post breaks down to edit_posts, edit_published_posts, or
		// edit_others_posts
	case 'edit_post':
		if ( $adjust_for_user )
			$author_data = get_userdata( $user_id );
		
		//echo "post ID: {$args[0]}<br />";
		$post = get_post( $args[0] );
		if ( 'page' == $post->post_type ) {
			$args = array_merge( array( 'edit_page', $user_id ), $args );
			return call_user_func_array( 'map_meta_cap_rs', $args );
		}
		
		if ( $adjust_for_user )
			$post_author_data = get_userdata( $post->post_author );
		
		//echo "current user id : $user_id, post author id: " . $post_author_data->ID . "<br />";
		// If the user is the author...
		if ( $adjust_for_user && ( $user_id == $post_author_data->ID ) ) {
			// If the post is published...
			if ( 'publish' == $post->post_status )
				$caps[] = 'edit_published_posts';
			else
				// If the post is draft...
				$caps[] = 'edit_posts';
		} else {
			// The user is trying to edit someone else's post.
			$caps[] = 'edit_others_posts';
			// The post is published, extra cap required.
			if ( 'publish' == $post->post_status )
				$caps[] = 'edit_published_posts';
			elseif ( 'private' == $post->post_status )
				$caps[] = 'edit_private_posts';
		}
		break;
	case 'edit_page':
		if ( $adjust_for_user )
			$author_data = get_userdata( $user_id );
		
		//echo "post ID: {$args[0]}<br />";
		$page = get_page( $args[0] );
		
		if ( $adjust_for_user )
			$page_author_data = get_userdata( $page->post_author );
		
		//echo "current user id : $user_id, page author id: " . $page_author_data->ID . "<br />";
		// If the user is the author...
		if ( $adjust_for_user && ( $user_id == $page_author_data->ID ) ) {
			// If the page is published...
			if ( 'publish' == $page->post_status )
				$caps[] = 'edit_published_pages';
			else
				// If the page is draft...
				$caps[] = 'edit_pages';
		} else {
			// The user is trying to edit someone else's page.
			$caps[] = 'edit_others_pages';
			// The page is published, extra cap required.
			if ( 'publish' == $page->post_status )
				$caps[] = 'edit_published_pages';
			elseif ( 'private' == $page->post_status )
				$caps[] = 'edit_private_pages';
		}
		break;
	case 'read_post':
		$post = get_post( $args[0] );
		if ( 'page' == $post->post_type ) {
			$args = array_merge( array( 'read_page', $user_id ), $args );
			return call_user_func_array( 'map_meta_cap_rs', $args );
		}

		if ( 'private' != $post->post_status ) {
			$caps[] = 'read';
			break;
		}

		if ( $adjust_for_user ) {
			$author_data = get_userdata( $user_id );
			$post_author_data = get_userdata( $post->post_author );
		}
			
		if ( $adjust_for_user && ( $user_id == $post_author_data->ID ) )
			$caps[] = 'read';
		else
			$caps[] = 'read_private_posts';
		break;
	case 'read_page':
		$page = get_page( $args[0] );

		if ( 'private' != $page->post_status ) {
			$caps[] = 'read';
			break;
		}

		if ( $adjust_for_user ) {
			$author_data = get_userdata( $user_id );
			$page_author_data = get_userdata( $page->post_author );
		}
		
		if ( $adjust_for_user && ( $user_id == $page_author_data->ID ) )
			$caps[] = 'read';
		else
			$caps[] = 'read_private_pages';
		break;
	default:
		// If no meta caps match, return the original cap.
		$caps[] = $meta_cap;
	}

	return apply_filters('map_meta_cap_rs', $caps, $meta_cap, $user_id, $args);
}
?>