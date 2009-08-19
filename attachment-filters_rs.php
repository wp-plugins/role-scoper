<?php
if( basename(__FILE__) == basename($_SERVER['SCRIPT_FILENAME']) )
	die();

function agp_is_media_type( $mime_type ) {
	return ( false !== strpos( $mime_type, 'image/' ) ) || ( false !== strpos( $mime_type, 'video/' ) ) || ( false !== strpos( $mime_type, 'audio/' ) );
}

function agp_return_file( $file_path, $mime_type ) {

	$use_cache = ( ( filesize($file_path) < 9500000 ) && ( ! defined('SCOPER_NO_SERVER_CACHE') || ! SCOPER_NO_SERVER_CACHE ) );

	if ( $use_cache ) {
		$file_time = filemtime($file_path);
	
		global $is_apache;

		if ( $is_apache && function_exists('apache_request_headers') ) {
			// server caching code from Private Files plugin by James Low (http://jameslow.com/2008/01/28/private-files/),
	
			// If web server is apache, check HTTP If-Modified-Since header before sending content
			$ar = apache_request_headers();
			if ( ! empty($ar['If-Modified-Since']) && ( strtotime($ar['If-Modified-Since']) >= $file_time ) ) {
				// 304: "Browser, your cached version of image is OK; we're not sending anything new to you"
				header( 'Last-Modified: '.gmdate('D, d M Y H:i:s', $file_time).' GMT', true, 304 );
				return;
			}
		}

		// outputing Last-Modified header
		header( 'Last-Modified: '.gmdate('D, d M Y H:i:s', $file_time).' GMT', true, 200 );
	}

	if ( agp_is_media_type($mime_type) ) {
		if ( $use_cache ) {
			header('Cache-Control: maxage=3600');
			header('Cache-Control: must-revalidate');
		}
		header( "Content-Type: $mime_type" );
	} else {
		global $is_IE;
		if ( $is_IE ) {
			// Thanks to Eirik Hoem - http://eirikhoem.wordpress.com/2007/06/15/generated-pdfs-over-https-with-internet-explorer/
			// for the tip on header requirements for IE7 https download
			if ( $use_cache ) {
				header('Cache-Control: maxage=3600');
				header('Pragma: public');
			}
			header("Content-Description: File Transfer");
			header("Content-Transfer-Encoding: binary");
			header('Content-Type: application/x-msdownload');
			header('Content-Disposition: attachment; filename="' . basename($file_path) . '"');
		} else {
			if ( $use_cache )
				header('Cache-Control: must-revalidate');

			header( "Content-Type: $mime_type" );
		}
	}
	
	header( 'Content-Length: ' . filesize($file_path) );

	// this caused intermittant failure to return images
	//ob_clean();
	//flush();

	if ( ! $use_cache )
		ob_end_flush();
	
	readfile($file_path);
	//readfile_chunked($file_path);
}



class AttachmentFilters_RS {

	// handle access to uploaded file where request was a direct file URL, which was rewritten according to our .htaccess addition
	function parse_query_for_direct_access ( &$query ) {
	
		if ( empty($query->query_vars['attachment']) || ( false === strpos($_SERVER['QUERY_STRING'], 'scoper_rewrite') ) )
			return;

		$file = $query->query_vars['attachment'];
		$file_path = WP_UPLOAD_DIR_RS . "/$file";
		
		//rs_errlog( $file_path );
		
		// don't filter the direct file URL request if filtering is disabled, or if the request is from wp-admin
		// DISABLE_ATTACHMENT_FILTERING check here is only pertinent until next htaccess rule flush
		if ( defined('DISABLE_ATTACHMENT_FILTERING') || defined('DISABLE_QUERYFILTERS_RS')
		|| ( ! empty($_SERVER['HTTP_REFERER']) && ( false !== strpos($_SERVER['HTTP_REFERER'], '/wp-admin' ) ) && ( false !== strpos($_SERVER['HTTP_REFERER'], get_option('siteurl') . '/wp-admin' ) ) ) ) {
			// note: image links from wp-admin should now never get here due to http_referer RewriteRule, but leave above check just in case - inexpensive since we're checking for wp-admin before calling get_option

			$mime_type = wp_check_filetype($file_path);
			
			//rs_errlog("skipping filtering for $mime_type");
			
			if ( is_array($mime_type) && isset($mime_type['type']) )
				$mime_type = $mime_type['type'];

			agp_return_file($file_path, $mime_type);
			exit;
		}
		
		if ( file_exists( $file_path ) ) {
			//rs_errlog("$file_path exists.");
		
			$file_url = WP_UPLOAD_URL_RS . "/$file";

			// Resized copies have -NNNxNNN suffix, but the base filename is stored as attachment.  Strip the suffix out for db query.
			$orig_file_url = preg_replace( "/-[0-9]{2,4}x[0-9]{2,4}./", '.', $file_url );

			//rs_errlog("orig file URL: $orig_file_url");
			
			global $wpdb, $wp_query;
			$qry = "SELECT * FROM $wpdb->posts WHERE post_type = 'attachment' AND guid = '$orig_file_url' AND post_parent > 0";
			$results = scoper_get_results( $qry );
			$mime_type = '';
			$matched_published_post = array();

			if ( empty($results) ) {
				if ( ! defined('SCOPER_BLOCK_UNATTACHED_UPLOADS') || ! SCOPER_BLOCK_UNATTACHED_UPLOADS )
					$return_file = true;
			} else {
				foreach ( $results as $attachment ) {
					//rs_errlog( "found attachment: " . serialize($attachment) );
				
					if ( empty($mime_type) )
						$mime_type = $attachment->post_mime_type;

					if ( is_administrator_rs() )
						$return_file = true;

					if ( $attachment->post_parent ) {
						if ( $parent_post = scoper_get_row( "SELECT post_type, post_status FROM $wpdb->posts WHERE ID = '$attachment->post_parent' LIMIT 1" ) ) {
							$object_type = $parent_post->post_type;
							$containing_post_status = $parent_post->post_status;
							
							// Only return content that is attached to published (potentially including private) posts/pages
							// If some other statuses for published posts are introduced in later WP versions, 
							// the failure mode here will be to overly suppress attachments
							if ( ( 'publish' == $containing_post_status ) || ( 'private' == $containing_post_status ) ) {
								if ( current_user_can( "read_$object_type", $attachment->post_parent ) ) {
									$return_file = true;
									break;
								} else {
									$matched_published_post[$object_type] = $attachment->post_name;
								}
							}
						}
					}
				}
			}
			
			if ( $return_file ) {
				//rs_errlog( "returning: $file_path" );
				agp_return_file($file_path, $mime_type);
				exit;
			} 
			
			if ( $matched_published_post && scoper_get_otype_option('do_teaser', 'post') ) {
				foreach ( array_keys($matched_published_post) as $object_type ) {
					if ( $use_teaser_type = scoper_get_otype_option('use_teaser', 'post',  $object_type) ) {
						if ( $matched_published_post[$object_type] ) {
							if ( ! defined('SCOPER_QUIET_FILE_404') || ! agp_is_media_type( $mime_type ) ) {
								// note: subsequent act_attachment_access will call impose_post_teaser()
								$will_tease = true; // will_tease flag only used within this function
								$wp_query->query_vars['attachment'] = $matched_published_post[$object_type];
								break;
							}
						}
					}
				}
			} 
			
			status_header(401); // Unauthorized
			
			if ( empty($will_tease) ) {
				// User is not qualified to access the requested attachment, and no teaser will apply
				
				// Normally, allow the function to return for WordPress 404 handling 
				// But end script execution here if requested attachment is a media type (or if definition set)
				// Linking pages won't want WP html returned in place of inaccessable image / video
				if ( defined('SCOPER_QUIET_FILE_404') ) {
					exit;
				}
				
				// this may not be necessary
				$wp_query->is_404 = true;
				$wp_query->is_single = true;
				$wp_query->is_singular = true;
				$wp_query->query_vars['is_single'] = true;
			}
		}
	}
	
	// Filter attacment page content prior to display by attachment template.
	// Note: teaser-subject direct file URL requests also land here
	function attachment_access() {
		if ( is_admin() || defined('DISABLE_ATTACHMENT_FILTERING') || defined('DISABLE_QUERYFILTERS_RS') || is_administrator_rs() )
			return;
			
		global $post, $wpdb;
	
		// if ( is_attachment() ) {  as of WP 2.6, is_attachment() returns false for custom permalink attachment URL 
		if ( is_attachment_rs() ) {
			if ( empty($post) ) {
				global $wp_query;
				if ( ! empty($wp_query->query_vars['attachment_id']) )
					$post = scoper_get_row("SELECT * FROM $wpdb->posts WHERE post_type = 'attachment' AND ID = '{$wp_query->query_vars['attachment_id']}'");
				elseif ( ! empty($wp_query->query_vars['attachment']) )
					$post = scoper_get_row("SELECT * FROM $wpdb->posts WHERE post_type = 'attachment' AND post_name = '{$wp_query->query_vars['attachment']}'");
			}
			
			if ( ! empty($post) ) {
				$object_type = scoper_get_var("SELECT post_type FROM $wpdb->posts WHERE ID = '$post->post_parent'");

				// default to 'post' object type if retrieval failed for some reason
				if ( empty($object_type) )
					$object_type = 'post';
				
				if ( ! current_user_can( "read_$object_type", $post->post_parent ) ) {
					if ( scoper_get_otype_option('do_teaser', 'post') ) {
						if ( $use_teaser_type = scoper_get_otype_option('use_teaser', 'post',  $object_type) )
							AttachmentFilters_RS::impose_post_teaser($post, $object_type, $use_teaser_type);
						else
							unset( $post );
					} else
						unset( $post ); // WordPress generates 404 if teaser is not enabled
				}
			}
		}
	}
	
	function impose_post_teaser(&$object, $object_type, $use_teaser_type = 'fixed') {
		global $current_user, $scoper, $wp_query;

		require_once('teaser_rs.php');
		
		$src_name = 'post';
		
		$teaser_replace = array();
		$teaser_prepend = array();
		$teaser_append = array();
		
		$teaser_replace[$object_type]['post_content'] = ScoperTeaser::get_teaser_text( 'replace', 'content', $src_name, $object_type, $current_user );

		$teaser_replace[$object_type]['post_excerpt'] = ScoperTeaser::get_teaser_text( 'replace', 'excerpt', $src_name, $object_type, $current_user );
		$teaser_prepend[$object_type]['post_excerpt'] = ScoperTeaser::get_teaser_text( 'prepend', 'excerpt', $src_name, $object_type, $current_user );
		$teaser_append[$object_type]['post_excerpt'] = ScoperTeaser::get_teaser_text( 'append', 'excerpt', $src_name, $object_type, $current_user );

		$teaser_prepend[$object_type]['post_name'] = ScoperTeaser::get_teaser_text( 'prepend', 'name', $src_name, $object_type, $current_user );
		$teaser_append[$object_type]['post_name'] = ScoperTeaser::get_teaser_text( 'append', 'name', $src_name, $object_type, $current_user );
	
		$force_excerpt = array();
		$force_excerpt[$object_type] = ( 'excerpt' == $use_teaser_type );
		
		$args = array( 'col_excerpt' => 'post_excerpt', 'col_content' => 'post_content', 'col_id' => 'ID',
		'teaser_prepend' => $teaser_prepend, 		'teaser_append' => $teaser_append, 	'teaser_replace' => $teaser_replace, 
		'force_excerpt' => $force_excerpt );
		
		ScoperTeaser::apply_teaser( $object, $src_name, $object_type, $args );
		
		$wp_query->is_404 = false;
		$wp_query->is_attachment = true;
		$wp_query->is_single = true;
		$wp_query->is_singular = true;
		$object->ancestors = array( $object->post_parent );
		
		$wp_query->post_count = 1;
		$wp_query->is_attachment = true;
		$wp_query->posts[] = $object;
		
		if ( isset($wp_query->query_vars['error']) )
			unset( $wp_query->query_vars['error'] );
		
		if ( isset($wp_query->query['error']) )
			$wp_query->query['error'] = '';
	}

} // end class
?>