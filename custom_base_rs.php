<?php

	function scoper_establish_status_caps() {
		global $wp_post_types;
		
		$use_post_types = scoper_get_option( 'use_post_types' );
		
		$post_types = array_diff_key( get_post_types( array( 'public' => true ) ), array( 'attachment' => true ) );
		
		$stati = get_post_stati( array( 'internal' => false ), 'object' );

		foreach( $post_types as $post_type ) {
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
							$type_caps[$posts_cap_name] = "{$op}_{$status_string}_{$post_type}s";
						} else {
							// default to this post type's own equivalent private or published cap
							if ( $status_obj->private )
								$type_caps[$posts_cap_name] = "{$op}_private_{$post_type}s";
								
							elseif ( $status_obj->public )
								$type_caps[$posts_cap_name] = "{$op}_published_{$post_type}s";
						}
					}
				} // end foreach op (read/edit/delete)
				
				// also define a "set_status" cap for custom statuses (to accompany "publish_posts" cap requirement when setting or removing this post status)
				if ( ! in_array( $status, array( 'publish', 'private' ) ) ) {
					$posts_cap_name = "set_{$status}_posts";
					if ( empty( $type_caps[$posts_cap_name] ) ) {
						if ( ! empty( $status_obj->customize_caps ) ) {	// TODO: RS Options to set this
							// this status was marked for full enforcement of custom capabilities
							$type_caps[$posts_cap_name] = "set_{$status}_{$post_type}s";
						} elseif( $status_obj->public || $status_obj->private ) {
							$type_caps[$posts_cap_name] = "publish_{$post_type}s";
						}
					}
				}

			} // end foreach front end status 
			
			if ( empty( $type_caps['delete_posts'] ) )
				$type_caps['delete_posts'] = "delete_{$post_type}s";
							
			if ( empty( $type_caps['delete_others_posts'] ) )
				$type_caps['delete_others_posts'] = "delete_others_{$post_type}s";

			if ( $is_attachment_type )
				$post_type = 'attachment';

			$wp_post_types[$post_type]->cap = (object) $type_caps;
		} // end foreach post type
	}

?>