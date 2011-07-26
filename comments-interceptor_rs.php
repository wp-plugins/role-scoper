<?php
add_filter( 'comments_clauses', array( 'CommentsInterceptor_RS', 'flt_comments_clauses' ), 10, 2 );

class CommentsInterceptor_RS {
	function flt_comments_clauses( $clauses, &$qry_obj ) {
		global $wpdb;
		
		if ( is_content_administrator_rs() ) {
			$stati = array_merge( get_post_stati( array( 'public' => true ) ), get_post_stati( array( 'private' => true ) ) );
			$status_csv = "'" . implode( "','", $stati ) . "'";
			$clauses['where'] = preg_replace( "/\s*AND\s*{$wpdb->posts}.post_status\s*=\s*[']?publish[']?/", "AND {$wpdb->posts}.post_status IN ($status_csv)", $clauses['where'] );
			return $clauses;
		}

		if ( empty( $clauses['join'] ) )
			$clauses['join'] = "JOIN $wpdb->posts ON $wpdb->posts.ID = $wpdb->comments.comment_post_ID";
		
		// for WP 3.1 and any manual 3rd-party join construction (subsequent filter will expand to additional statuses as appropriate)
		$clauses['where'] = preg_replace( "/ post_status\s*=\s*[']?publish[']?/", " $wpdb->posts.post_status = 'publish'", $clauses['where'] );

		// performance enhancement: simplify comments query if there are no attachment comments to filter (TODO: cache this result)
		$qry_any_attachment_comments = "SELECT ID FROM $wpdb->posts AS p INNER JOIN $wpdb->comments AS c ON p.ID = c.comment_post_ID WHERE p.post_type = 'attachment' LIMIT 1";

		$post_type_arg = ( isset( $qry_obj->query_vars['post_type'] ) ) ? $qry_obj->query_vars['post_type'] : '';
		
		$post_id = ( ! empty( $qry_obj->query_vars['post_id'] ) ) ? $qry_obj->query_vars['post_id'] : 0;

		$attachment_query = ( 'attachment' == $post_type_arg ) || ( $post_id && ( 'attachment' == get_post_field( 'post_type', $post_id ) ) );

		if ( ! $attachment_query && ( $post_id || defined('SCOPER_NO_ATTACHMENT_COMMENTS') || ( ! defined('SCOPER_ATTACHMENT_COMMENTS') && ! scoper_get_var($qry_any_attachment_comments) ) ) ) {
			$clauses['where'] = " AND " . $clauses['where'];
			$clauses['where'] = "1=1" . apply_filters('objects_where_rs', $clauses['where'], 'post', $post_type_arg, array('skip_teaser' => true) );
		} else {
			if ( false === strpos( $clauses['fields'], 'DISTINCT ' ) )
				$clauses['fields'] = 'DISTINCT ' . $clauses['fields'];
			
			if ( $post_type_arg )
				$post_types = (array) $post_type_arg;
			else
				$post_types = array_diff( get_post_types( array( 'public' => true ) ), array( 'attachment' ) );
			
			$post_type_in = "'" . implode( "','", $post_types ) . "'";

			$clauses['join'] .= " LEFT JOIN $wpdb->posts as parent ON parent.ID = {$wpdb->posts}.post_parent AND parent.post_type IN ($post_type_in) AND $wpdb->posts.post_type = 'attachment'";
			
			$use_post_types = scoper_get_option( 'use_post_types' );
			
			$where = array();
			foreach( $post_types as $type ) {
				if ( ! empty( $use_post_types[$type] ) )
					$where_post = apply_filters('objects_where_rs', '', 'post', $type, array('skip_teaser' => true) );
				else
					$where_post = "AND 1=1";
				
				$where[]= "$wpdb->posts.post_type = '$type' $where_post";
				$where[]= "$wpdb->posts.post_type = 'attachment' AND parent.post_type = '$type' " . str_replace( "$wpdb->posts.", "parent.", $where_post );
			}
			
			$clauses['where'] = preg_replace( "/\s*AND\s*{$wpdb->posts}.post_status\s*=\s*[']?publish[']?/", "", $clauses['where'] );
			$clauses['where'] .= ' AND ( ' . agp_implode( ' ) OR ( ', $where, ' ( ', ' ) ' ) . ' )';
		}
		
		return $clauses;
	}
}
?>