<?php
if( basename(__FILE__) == basename($_SERVER['SCRIPT_FILENAME']) )
	die();

/**
 * CapInterceptor_RS PHP class for the WordPress plugin Role Scoper
 * cap-interceptor_rs.php
 * 
 * @author 		Kevin Behrens
 * @copyright 	Copyright 2010
 * 
 */
class CapInterceptor_RS
{	
	var $in_process = false;

	var $skip_id_generation = false;
	var $skip_any_term_check = false;
	var $skip_any_object_check = false;

	function CapInterceptor_RS() {
		// Since scoper installation implies that this plugin should take custody
		// of access control, set priority high so we have the final say on group-controlled caps.
		// This filter will not mess with any caps which are not scoper-defined.
		//
		// (note: custom caps from other plugins can be scoper-controlled if they are defined via a Role Scoper Extension plugin)
		add_filter('user_has_cap', array(&$this, 'flt_user_has_cap'), 99, 3);  // scoping will be defeated if our filter isn't applied last
	}

	// hook to wrapper function to avoid recursion
	function flt_user_has_cap($wp_blogcaps, $orig_reqd_caps, $args) {
		if ( $this->in_process )
			return $wp_blogcaps;
			
		$this->in_process = true;
		$return = $this->_flt_user_has_cap($wp_blogcaps, $orig_reqd_caps, $args);
		$this->in_process = false;
		return $return;
	}
	
	
	// CapInterceptor_RS::flt_user_has_cap
	//
	// Capability filter applied by WP_User->has_cap (usually via WP current_user_can function)
	// Pertains to logged user's capabilities blog-wide, or for a single item
	//
	// $wp_blogcaps = current user's blog-wide capabilities
	// $reqd_caps = primitive capabilities being tested / requested
	// $args = array with:
	// 		$args[0] = original capability requirement passed to current_user_can (possibly a meta cap)
	// 		$args[1] = user being tested
	// 		$args[2] = object id (could be a postID, linkID, catID or something else)
	//
	// The intent here is to add to (or take away from) $wp_blogcaps based on scoper role assignments
	// (only offer an opinion on scoper-defined caps, with others left in $allcaps array as blog-wide caps)
	//
	function _flt_user_has_cap($wp_blogcaps, $orig_reqd_caps, $args)	{
		global $scoper;
		
		//rs_errlog(serialize($args));
		log_mem_usage_rs( '_flt_user_has_cap' );
		
		// =============================== STATIC VARIABLE DECLARATION AND INITIALIZATION (to memcache filtering results) =====================
		static $tested_object_ids;
		static $scoped_where_clause;
		static $hascap_object_ids;	//	$hascap_object_ids[src_name][object_type][capreqs key] = array of object ids for which user has the required caps
									// 		capreqs key = md5(sorted array of required capnames)
							
		if ( empty($tested_object_ids) ) {
			$scoped_where_clause = array();
			$tested_object_ids = array();
		}
		// =====================================================================================================================================
		
		
		// =============================================== TEMPORARY DEBUG CODE ================================================================
		//d_echo( 'orig reqd_caps: ' );
		//dump($orig_reqd_caps);
		//dump($args);
		
		//if ( strpos( $_SERVER['REQUEST_URI'], 'ajax' ) ) {
			//rs_errlog( serialize($_SERVER) );
			//rs_errlog( serialize($_REQUEST) );
			//rs_errlog( '' );
			//rs_errlog('flt_user_has_cap');
			//rs_errlog(serialize($orig_reqd_caps));
			//rs_errlog(serialize($args));
			//rs_errlog('');
			//rs_errlog(' ');
		//}

		// ============================================= (end temporary debug code) ============================================================
		
		
		// convert 'rs_role_name' to corresponding caps (and also make a tinkerable copy of orig_reqd_caps)
		$orig_reqd_caps = $scoper->role_defs->role_handles_to_caps($orig_reqd_caps);
		
		//dump($reqd_caps);
		//dump($_SERVER);
		
		//if ( 'delete_post' == $_reqd_caps[0] )
			//dump($reqd_caps);
		
		
		// ================= EARLY EXIT CHECKS (if the provided reqd_caps do not need filtering or need special case filtering ==================
		
		// Disregard caps which are not defined in Role Scoper config
		if ( ! $rs_reqd_caps = array_intersect( $orig_reqd_caps, $scoper->cap_defs->get_all_keys() ) ) {
			return $wp_blogcaps;		
		}

		// log initial set of RS-filtered caps (in case we swap in equivalent caps for intermediate processing)
		$orig_reqd_caps = $rs_reqd_caps;
		
		// permitting this filter to execute early in an attachment request resets the found_posts record, preventing display in the template
		if ( is_attachment() && ! is_admin() && ! did_action('template_redirect') ) {
			if ( empty( $GLOBALS['scoper_checking_attachment_access'] ) ) {
				return $wp_blogcaps;
			}
		}
		
		// work around bug in mw_EditPost method (requires publish_pages AND publish_posts cap)
		if ( defined('XMLRPC_REQUEST') && ( 'publish_posts' == $orig_reqd_caps[0] ) ) {
			if ( 'page' == $GLOBALS['xmlrpc_post_type_rs'] ) {
				return array( 'publish_posts' => true );
			}
		}
		
		// backdoor to deal with rare cases where one of the caps included in RS role defs cannot be filtered properly
		if ( defined('UNSCOPED_CAPS_RS') && ! array_diff( $orig_reqd_caps, explode( ',', UNSCOPED_CAPS_RS ) ) ) {
			return $wp_blogcaps;
		}
		
		// custom workaround to reveal all private / restricted content in all blogs if logged into main blog 
		if ( defined( 'SCOPER_MU_MAIN_BLOG_RULES' ) ) {
			include_once( 'mu-custom.php' );
			if ( ! array_diff( $orig_reqd_caps, array( 'read', 'read_private_pages', 'read_private_posts' ) ) )
				if ( $return_caps = ScoperMU_Custom::current_user_logged_into_main( $wp_blogcaps, $orig_reqd_caps ) ) {
					return $return_caps;
				}
		}
		// =================================================== (end early exit checks) ======================================================
		
		
		// ============================ GLOBAL VARIABLE DECLARATIONS, ARGUMENT TRANSLATION AND STATUS DETECTION =============================
		global $current_user;
		
		$script_name = $_SERVER['SCRIPT_NAME'];
		
		$user_id = ( isset($args[1]) ) ? $args[1] : 0;

		if ( $user_id && ($user_id != $current_user->ID) )
			$user = new WP_Scoped_User($user_id);
		else
			$user = $current_user;

		// currently needed for filtering async-upload.php
		if ( empty($user->blog_roles ) || empty($user->blog_roles[''] ) )
			$scoper->refresh_blogroles();
			
		$object_id = ( isset($args[2]) ) ? (int) $args[2] : 0;

		
		// since WP user_has_cap filter does not provide an object type / data source arg,
		// we determine data source and object type based on association to required cap(s)
		$object_types = $scoper->cap_defs->object_types_from_caps($rs_reqd_caps);
		
		// If an object id was provided, all required caps must share a common data source (object_types array is indexed by src_name)
		if ( count($object_types) > 1 || ! count($object_types) ) {
			
			if ( $object_type = $scoper->data_sources->get_from_uri('type', 'post', $object_id) ) {
				$object_types = array( 'post' => array( $object_type => true ) );
			} else {
				if ( isset( $object_types['post'] ) )
					$object_types = array( 'post' => $object_types['post'] );
				else {
					rs_notice ( 'Error: user has_cap call is not valid for specified object_id because required capabilities pertain to more than one data source.' . ' ' . implode(', ', $orig_reqd_caps) );
					$in_process = false;
					return array();
				}
			}
		}
		
		$src_name = key($object_types);
		if ( ! $src = $scoper->data_sources->get($src_name) ) {
			rs_notice ( sprintf( 'Role Scoper Config Error (%1$s): Data source (%2$s) is not defined', 'flt_user_has_cap', $src_name ) );  
			$in_process = false;
			return array();
		}
		
		// If cap definition(s) did not specify object type (as with "read" cap), enlist help detecting it
		if ( count($object_types[$src_name]) > 1 )
			$object_types[$src_name] = array_diff_key( $object_types[$src_name], array( '' => '' ) );   // ignore nullstring object_type property associated with some caps

		reset($object_types);
		if ( (count($object_types[$src_name]) == 1) && key($object_types[$src_name]) )
			$object_type = key($object_types[$src_name]);
		else
			$object_type = $scoper->data_sources->detect('type', $src, $object_id);
		
		$uri_object_type = cr_find_post_type();
		
		$doing_admin_menus = is_admin() && did_action( '_admin_menu' ) && ! did_action( 'admin_menu' ); // ! did_action('admin_notices');
		
		// backports from RS 1.3 pertaining to custom post types under WP 3.0
		if ( awp_ver('3.0') && ( 'post' != $uri_object_type ) ) {
			if ( $object_type_obj = get_post_type_object( $uri_object_type ) ) {
				// Replace edit_posts requirement with corresponding type-specific requirement, but only after admin menu is drawn, or on a submission before the menu is drawn
				$replace_post_caps = array( 'edit_posts', 'publish_posts' );
				
				if ( strpos($_SERVER['REQUEST_URI'], 'media-upload.php' ) )
					$replace_post_caps = array_merge( $replace_post_caps, array( 'edit_others_posts', 'edit_published_posts' ) );
						
				foreach( $replace_post_caps as $post_cap_name ) {
					$key = array_search( $post_cap_name, $rs_reqd_caps );

					if ( ( false !== $key ) && ! $doing_admin_menus && ( strpos($script_name, 'p-admin/edit.php') || strpos($script_name, 'p-admin/post.php') || strpos($script_name, 'p-admin/post-new.php') || strpos($_SERVER['REQUEST_URI'], 'p-admin/admin-ajax.php') ) ) {				
						$rs_reqd_caps[$key] = $object_type_obj->cap->$post_cap_name;
						$modified_caps = true;
						$object_type = $uri_object_type;
					}
				}
			}
		} // end backports from RS 1.3

		
		if ( defined( 'RVY_VERSION' ) ) {
			require_once( 'revisionary-helper_rs.php' );
			$rs_reqd_caps = Rvy_Helper::convert_post_edit_caps( $rs_reqd_caps, $object_type );
		}
		

		// WP core quirk workaround: edit_others_posts is required as preliminary check for populating authors dropdown for pages
		// (but we are doing are own validation, so just short circuit the WP get_editable_user_ids safeguard 
		
		//d_echo( "orig reqd:" );
		//dump($reqd_caps);
		
		if ( in_array( 'edit_others_posts', $orig_reqd_caps ) && ( strpos($script_name, 'p-admin/page.php') || strpos($script_name, 'p-admin/page-new.php') || ( ! empty( $_GET['post_type'] ) && 'page' == $_GET['post_type'] ) ) ) {
			$key = array_search( 'edit_others_posts', $rs_reqd_caps );
	
			$object_types = $scoper->cap_defs->object_types_from_caps($rs_reqd_caps);
			$object_type = key($object_types);
			
			require_once( 'lib/agapetry_wp_admin_lib.php' ); // function awp_metaboxes_started()
			
			// Allow contributors to edit published post/page, with change stored as a revision pending review
			if ( ! awp_metaboxes_started() && ! strpos($_SERVER['SCRIPT_NAME'], 'p-admin/revision.php') && false === strpos(urldecode($_SERVER['REQUEST_URI']), 'page=revisions' )  ) // don't enable contributors to view/restore revisions
				$rs_reqd_caps[$key] = 'edit_pages';
			else
				$rs_reqd_caps[$key] = 'edit_published_pages';
		}
		
		// also short circuit any unnecessary edit_posts checks within page edit form, but only after admin menu is drawn
		if ( in_array( 'edit_posts', $orig_reqd_caps ) && ( strpos($script_name, 'p-admin/page.php') || strpos($script_name, 'p-admin/page-new.php') || ( ! empty( $_GET['post_type'] ) && 'page' == $_GET['post_type'] ) ) && did_action('admin_notices') ) {
			$key = array_search( 'edit_posts', $rs_reqd_caps );
		
			$wp_blogcaps = array_merge( $wp_blogcaps, array('edit_posts' => true) );

			if ( ! empty($args[2]) ) // since we're in edit page form, convert id-specific edit_posts requirement to edit_pages
				$rs_reqd_caps[$key] = 'edit_pages';
		}
		
		// don't apply object-specific filtering for auto-drafts
		if ( 'post' == $src_name ) {
			if ( ! empty($args[2]) ) {
				if ( $_post = get_post($args[2]) ) {
					if ( ( 'auto-draft' == $_post->post_status ) && ! empty($_POST['action']) ) { // && ( 'autosave' == $_POST['action'] ) ) {
						$args[2] = 0;
						
						if ( ! $doing_admin_menus )
							$this->skip_id_generation = true;	
					}
				}
			} elseif ( ( ! empty( $GLOBALS['post'] ) ) && ( 'auto-draft' == $GLOBALS['post']->post_status ) && ! $doing_admin_menus )
				$this->skip_id_generation = true;	
		}

		// If no object id was passed in, we won't do much.
		if ( empty($args[2]) ) {
			if ( ! $this->skip_id_generation && ! $doing_admin_menus && ! defined('XMLRPC_REQUEST') && ! strpos( $script_name, 'p-admin/media-upload.php' ) && ! strpos( $script_name, 'p-admin/async-upload.php' ) ) {  // lots of superfluous queries in media upload popup otherwise

				log_mem_usage_rs( 'generate_missing_object_id' );
			
				if ( $gen_id = $this->generate_missing_object_id( $orig_reqd_caps[0]) ) {
					if ( ! is_array($gen_id) ) {
						// Special case for upload scripts: don't do scoped role query if the post doesn't have any categories saved yet
						/*
						if ( strpos($script_name, 'p-admin/media-upload.php') || strpos($script_name, 'p-admin/async-upload.php') ) {
							if ( ! wp_get_post_categories($gen_id) )
								$gen_id = 0;
						}
						*/
	
						if ( $gen_id ) {
							$args[2] = $gen_id;
						}
					}
				}
			} else
				$this->skip_id_generation = false; // too risky to leave this set
				
			if ( empty($args[2]) ) {
				if ( $missing_caps = array_diff($rs_reqd_caps, array_keys($wp_blogcaps) ) ) {
					
					log_mem_usage_rs( 'any_term_check' );
					
					// These checks are only relevant since no object_id was provided.  
					// Otherwise (in the main body of this function), taxonomy and object caps will be credited via scoped query
				
					// If we are about to fail the blogcap requirement, credit a missing cap if 
					// the user has it by term role for ANY term.
					// This prevents failing initial UI entrance exams that assume blogroles-only					
					if ( ! $this->skip_any_term_check )
						if ( $tax_caps = $this->user_can_for_any_term($missing_caps) ) {
							$wp_blogcaps = array_merge($wp_blogcaps, $tax_caps);
						}
							
					log_mem_usage_rs( 'any_term_check done' );
						
					// If we are about to fail the blogcap requirement, credit a missing scoper-defined cap if 
					// the user has it by object role for ANY object.
					// (i.e. don't bar user from edit-pages.php if they have edit_pages cap for at least one page)
					if ( $missing_caps = array_diff($rs_reqd_caps, array_keys($wp_blogcaps) ) ) {	
						if ( ! $this->skip_any_object_check ) {  // credit object role assignment for menu visibility check and Dashboard Post/Page total, but not for Dashboard "Write Post" / "Write Page" links
							
							log_mem_usage_rs( 'any_obj_check' );
						
							// Complication due to the dual usage of 'edit_posts' / 'edit_pages' caps for creation AND editing permission:
							// We don't want to allow a user to create a new page or post simply because they have an editing role assigned directly to some other post/page
							$any_objrole_skip_uris = array( 'p-admin/page-new.php', 'p-admin/post-new.php' );
							$any_objrole_skip_uris = apply_filters( 'any_objrole_skip_uris_rs', $any_objrole_skip_uris );
							
							$skip = false;
							foreach ( $any_objrole_skip_uris as $uri_sub ) {
								if ( strpos(urldecode($_SERVER['REQUEST_URI']), $uri_sub) ) {
									$skip = true;
									break;
								}
							}
							
							if ( ! $skip ) {
								//$any_objrole_caps = array( 'edit_posts', 'edit_pages', 'edit_comments', 'manage_links', 'manage_categories', 'manage_groups', 'recommend_group_membership', 'request_group_membership', 'upload_files' );
								//$any_objrole_caps = apply_filters( 'caps_granted_from_any_objrole_rs', $any_objrole_caps );

								//$missing_caps = array_intersect($missing_caps, $any_objrole_caps);

								$this->skip_any_object_check = true;
							
								if ( $object_caps = $this->user_can_for_any_object( $missing_caps ) )
									$wp_blogcaps = array_merge($wp_blogcaps, $object_caps);
									
								$this->skip_any_object_check = false;
							}
							
							log_mem_usage_rs( 'any_obj_check done' );
						}
					}
				}

				$in_process = false;
				
				if ( $restore_caps = array_diff($orig_reqd_caps, $rs_reqd_caps ) )  // restore original reqd_caps which we substituted for the type-specific scoped query
					$wp_blogcaps = array_merge( $wp_blogcaps, array_fill_keys($restore_caps, true) );

				return $wp_blogcaps;
			}
		} else { // endif no object_id provided
			if ( 'post' == $src_name ) {
				if ( ( 'page' == $object_type ) || defined( 'SCOPER_LOCK_OPTION_ALL_TYPES' ) && ! is_content_administrator_rs() ) {
					if ( awp_ver( '3.0' ) && ! empty($object_type_obj) )
						$delete_metacap = ( $object_type_obj->hierarchical ) ? $object_type_obj->cap->delete_post : '';
					else
						$delete_metacap = 'delete_page';	

					// if the top level page structure is locked, don't allow non-administrator to delete a top level page either
					if ( $delete_metacap == $args[0] ) {
						if ( '1' === scoper_get_option( 'lock_top_pages' ) ) {	  // stored value of 1 means only Administrators are allowed to modify top-level page structure
							if ( $page = get_post( $args[2] ) ) {
								if ( empty( $page->post_parent ) ) {
									$in_process = false;
									return false;
								}
							}
						}
					}
				}
			}
		}

		$object_id = (int) $args[2];
		
		global $wpdb;
		
		// if this is a term administration request, route to user_can_admin_terms()
		if ( ! isset($src->object_types[$object_type]) && $scoper->taxonomies->is_member($object_type) ) {
			if ( count($rs_reqd_caps) == 1 ) {  // technically, should support multiple caps here
				if ( $cap_def = $scoper->cap_defs->get( $orig_reqd_caps[0] ) ) {  
					if ( $cap_def->op_type == OP_ADMIN_RS ) {
						// always pass through any assigned blog caps which will not be involved in this filtering
						$rs_reqd_caps = array_fill_keys( $rs_reqd_caps, 1 );
						$undefined_reqd_caps = array_diff_key( $wp_blogcaps, $rs_reqd_caps);
					
						require_once( 'admin/permission_lib_rs.php' );
						if ( user_can_admin_terms_rs($object_type, $object_id, $user) ) {
							$in_process = false;
							return array_merge($undefined_reqd_caps, $rs_reqd_caps);
						} else {
							$in_process = false;
							return $undefined_reqd_caps;	// required caps we scrutinized are excluded from this array
						}
					}
				}
			}
		}
		
		//log_mem_usage_rs( 'cap_int - 1 ' );
		
		// Workaround to deal with WP core's checking of publish cap prior to storing categories
		// Store terms to DB in advance of any cap-checking query which may use those terms to qualify an operation
		if ( $object_id ) {
			if ( awp_ver( '3.0' ) ) {
				if ( $object_type_obj = get_post_type_object( $uri_object_type ) )
					$check_caps = array( 'publish_posts', 'edit_posts', $object_type_obj->cap->publish_posts, $object_type_obj->cap->edit_posts );
			} else
				$check_caps = array( 'publish_posts', 'edit_posts', "publish_{$uri_object_type}s", "edit_{$uri_object_type}s" );
		}

		if ( array_intersect( $check_caps, $rs_reqd_caps) && ! empty($_POST) && $object_id ) {
			$uses_taxonomies = scoper_get_taxonomy_usage( $src_name, $object_type );
			
			foreach ( $uses_taxonomies as $taxonomy ) {
				$stored_terms = $scoper->get_terms($taxonomy, UNFILTERED_RS, COL_ID_RS, $object_id);

				$post_var = isset( $src->http_post_vars->$taxonomy ) ? $src->http_post_vars->$taxonomy : $taxonomy;
				
				$selected_terms =  isset( $_POST[$post_var] ) ? $_POST[$post_var] : array();
				
				if ( $set_terms = $scoper->filters_admin->flt_pre_object_terms($selected_terms, $taxonomy) ) {
					$set_terms = array_map('intval', $set_terms);
					$set_terms = array_unique($set_terms);

					if ( $set_terms != $stored_terms )
						wp_set_object_terms( $object_id, $set_terms, $taxonomy );
						
					// delete any buffered cap check results which were queried prior to storage of these object terms
					if ( isset($hascap_object_ids[$src_name][$object_type]) )
						unset($hascap_object_ids[$src_name][$object_type]);
				}
			}
		}

		
		//log_mem_usage_rs( 'cap_int - 2 ' );
		
		// If caps pertain to more than one object type, filter will probably return empty set, but let it pass in case of strange and unanticipated (yet valid) usage
		
		// Before querying for caps on this object, check whether it was put in the
		// global buffer (page_cache / post_cache / listed_ids).  If so, run the same
		// query for ALL the pages/posts/entities in the buffer, and buffer the results. 
		//
		// (This is useful when front end code must check caps for each post 
		// to determine whether to display 'edit' link, etc.)

		// now that object type is known, retrieve / construct memory cache of all ids which satisfy capreqs
		$arg_append = '';
		$arg_append .= ( $scoper->query_interceptor->require_full_object_role ) ? '-require_full_object_role-' : '';
		$arg_append .= ( ! empty( $GLOBALS['revisionary']->skip_revision_allowance ) ) ? '-skip_revision_allowance-' : '';

		sort($rs_reqd_caps);
		$capreqs_key = md5( serialize($rs_reqd_caps) . $arg_append );  // see ScoperAdmin::user_can_admin_object
		
		// is the requested object a revision or attachment?
		$maybe_revision = ( 'post' == $src_name && ! isset($hascap_object_ids[$src_name][$object_type][$capreqs_key][$object_id]) );

		$maybe_attachment = strpos($_SERVER['SCRIPT_NAME'], 'p-admin/upload.php') || strpos($_SERVER['SCRIPT_NAME'], 'p-admin/media.php');

		if ( $object_id && ( $maybe_revision || $maybe_attachment ) ) {
			if ( ! $_post = wp_cache_get($object_id, 'posts') ) {	
				if ( $_post = & scoper_get_row($wpdb->prepare("SELECT * FROM $wpdb->posts WHERE ID = %d LIMIT 1", $object_id)) )
					wp_cache_add($_post->ID, $_post, 'posts');
			}
		
			if ( $_post ) {
				if ( 'revision' == $_post->post_type ) {
					require_once( 'lib/revisions_lib_rs.php' );					
					$revisions = rs_get_post_revisions($_post->post_parent, 'inherit', array( 'fields' => COL_ID_RS, 'return_flipped' => true ) );						
				}

				//todo: eliminate redundant post query (above by detect method to determine object type)
				if ( ( 'revision' == $_post->post_type ) || ( 'attachment' == $_post->post_type ) ) {
					$object_id = $_post->post_parent;
				
					if ( ! $_parent = wp_cache_get($_post->post_parent, 'posts') ) {
						if ( $object_id )
							if ( $_parent = & scoper_get_row($wpdb->prepare("SELECT * FROM $wpdb->posts WHERE ID = %d LIMIT 1", $_post->post_parent)) )
								wp_cache_add($_post->post_parent, $_parent, 'posts');
					}
				
					if ( $_parent ) {
						$object_type = $_parent->post_type;
					
						// compensate for WP's requirement of posts cap for attachment editing, regardless of whether it's attached to a post or page
						if ( ( $maybe_attachment || ( 'revision' == $_post->post_type ) ) && ( 'page' == $object_type ) ) {
							if ( 'edit_others_posts' == $rs_reqd_caps[0] )
								$rs_reqd_caps[0] = 'edit_others_pages';
								
							elseif ( 'delete_others_posts' == $rs_reqd_caps[0] )
								$rs_reqd_caps[0] = 'delete_others_pages';
								
							elseif ( 'edit_posts' == $rs_reqd_caps[0] )
								$rs_reqd_caps[0] = 'edit_pages';
								
							elseif ( 'delete_posts' == $rs_reqd_caps[0] )
								$rs_reqd_caps[0] = 'delete_pages';
						}
					} elseif ( 'attachment' == $_post->post_type ) {
						// special case for unattached uploads: uploading user should have their way with them
						if ( $_post->post_author == $current_user->ID ) {
							$rs_reqd_caps[0] = 'read';

							if ( $restore_caps = array_diff($orig_reqd_caps, array_keys($rs_reqd_caps) ) )  // restore original reqd_caps which we substituted for the type-specific scoped query
								$wp_blogcaps = array_merge( $wp_blogcaps, array_fill_keys($restore_caps, true) );
									
							return $wp_blogcaps;
						}
					}
				}
			}
		}

		//log_mem_usage_rs( 'cap_int - 3 ' );
		
		//$force_refresh = false; // strpos( $_SERVER['REQUEST_URI'], 'async-upload.php' );
		
		// Page refresh following publishing of new page by users who can edit by way of Term Role fails without this workaround
		if ( ! empty( $_POST ) && ( defined( 'SCOPER_CACHE_SAFE_MODE' ) || ( strpos($_SERVER['REQUEST_URI'], 'p-admin/post.php') && ( $args[0] == $object_type_obj->cap->edit_post ) ) ) ) {
			$force_refresh = true;
			$tested_object_ids = array();
			$scoped_where_clause = array();
			$hascap_object_ids = array();
		} else
			$force_refresh = false;
		
		//log_mem_usage_rs( 'cap_int - 4 ' );
			
		if ( $force_refresh || ! isset($hascap_object_ids[$src_name][$object_type][$capreqs_key]) || ! isset($tested_object_ids[$src_name][$object_type][$capreqs_key][$object_id]) ) {
		//if ( ! isset($tested_object_ids[$src_name][$object_type][$capreqs_key][$object_id]) ) {
			// Check whether Object ids meeting specified capreqs were already memcached during this http request
			if ( 'post' == $src_name ) {
				global $wp_object_cache;
			}

			// there's too much happening on the dashboard (and too much low-level query filtering) to buffer listed IDs reliably
			if ( ! strpos($script_name, 'p-admin/index.php') ) {
				// If we have a cache of all currently listed object ids, limit capreq query to those ids
				if ( isset($scoper->listed_ids[$src_name]) )
					$listed_ids = array_keys($scoper->listed_ids[$src_name]);
				else // note: don't use wp_object_cache because it includes posts not present in currently displayed resultset listing page
					$listed_ids = array();
			} else
				$listed_ids = array();
			
			// make sure this object is in the list
			$listed_ids[] = $object_id;
			
			if ( isset( $tested_object_ids[$src_name][$object_type][$capreqs_key] ) )
				$tested_object_ids[$src_name][$object_type][$capreqs_key] = $tested_object_ids[$src_name][$object_type][$capreqs_key] + array_fill_keys($listed_ids, true);
			else
				$tested_object_ids[$src_name][$object_type][$capreqs_key] = array_fill_keys($listed_ids, true);
				
			// If a listing buffer exists, query on its IDs.  Otherwise just for this object_id
			$id_in = " AND $src->table.{$src->cols->id} IN ('" . implode("', '", array_unique($listed_ids)) . "')";
			
			// As of 1.1, using subselects in where clause instead
			//$join = $scoper->query_interceptor->flt_objects_join('', $src_name, $object_type, $this_args );
			
			if ( isset($args['use_term_roles']) )
				$use_term_roles = $args['use_term_roles'];
			else
				$use_term_roles = scoper_get_otype_option( 'use_term_roles', $src_name, $object_type );	

			$use_object_roles = ( empty($src->no_object_roles) ) ? scoper_get_otype_option( 'use_object_roles', $src_name, $object_type ) : false;
			
			$this_args = array('object_type' => $object_type, 'user' => $user, 'use_term_roles' => $use_term_roles, 'use_object_roles' => $use_object_roles, 'skip_teaser' => true );

			log_mem_usage_rs( 'cap_int - objects_where_role_clauses' );
			
			$where = $scoper->query_interceptor->objects_where_role_clauses($src_name, $rs_reqd_caps, $this_args );
		
			log_mem_usage_rs( 'cap_int - objects_where_role_clauses done' );
			
			if ( $use_object_roles && $scoper->query_interceptor->require_full_object_role )
				$this->require_full_object_role = false;	// return just-used temporary switch back to normal
			
			if ( $where )
				$where = "AND ( $where )";

			$query = "SELECT $src->table.{$src->cols->id} FROM $src->table WHERE 1=1 $where $id_in";

			if ( isset( $hascap_object_ids[$query] ) )
				$okay_ids = array_keys( $hascap_object_ids[$query] );
			else {
				$okay_ids = scoper_get_col($query);
			}
			
			// If set of listed ids is not known, each current_user_can call will generate a new query construction
			// But if the same query is generated, use buffered result
			if ( ! empty($okay_ids) )
				$okay_ids = array_fill_keys($okay_ids, true);
			
			// bulk post/page deletion is broken by hascap buffering
			if ( empty($_GET['doaction']) || ( ('delete_post' != $args[0]) && ('delete_page' != $args[0]) ) )
				$hascap_object_ids[$src_name][$object_type][$capreqs_key] = $okay_ids;
				
			$hascap_object_ids[$query] = $okay_ids;
		} else {
			// results of this same has_cap inquiry are already stored (from another call within current http request)
			$okay_ids = $hascap_object_ids[$src_name][$object_type][$capreqs_key];
		}

		//log_mem_usage_rs( 'cap_int - 5 ' );
		
		// if we redirected the cap check to revision parent, credit all the revisions for passing results
		if ( isset($okay_ids[$object_id]) && ! empty($revisions) ) {
			$okay_ids = $okay_ids + $revisions;

			// bulk post/page deletion is broken by hascap buffering
			if ( empty($_GET['doaction']) || ( ('delete_post' != $args[0]) && ('delete_page' != $args[0]) ) )
				$hascap_object_ids[$src_name][$object_type][$capreqs_key] = $okay_ids;
			
			if ( ! empty($query_key) )
				$hascap_object_ids[$query_key] = $okay_ids;
		}
		
		//log_mem_usage_rs( 'cap_int - 6 ' );
		
		//dump($okay_ids);
		
		$rs_reqd_caps = array_fill_keys( $rs_reqd_caps, true );
		
		if ( ! $okay_ids || ! isset($okay_ids[$object_id]) ) {
			//rs_errlog( "object_id $object_id not okay!" );
			
			$in_process = false;
			return array_diff_key( $wp_blogcaps, $rs_reqd_caps);	// required caps we scrutinized are excluded from this array
		} else {
			if ( $restore_caps = array_diff($orig_reqd_caps, array_keys($rs_reqd_caps) ) )
				$rs_reqd_caps = $rs_reqd_caps + array_fill_keys($restore_caps, true);

			//d_echo("object_id $object_id OK!<br />" );
			//$test = array_merge( $wp_blogcaps, $rs_reqd_caps );
			//dump($test);
			
			//rs_errlog( 'RETURNING:' );
			//rs_errlog( serialize(array_merge($wp_blogcaps, $rs_reqd_caps)) );

			$in_process = false;
			return array_merge($wp_blogcaps, $rs_reqd_caps);
		}
	}
	
	
	// Try to generate missing has_cap object_id arguments for problematic caps
	// Ideally, this would be rendered unnecessary by updated current_user_can calls in WP core or other offenders
	function generate_missing_object_id($required_cap) {
		global $scoper;
		
		if ( has_filter('generate_missing_object_id_rs') ) {
			if ( $object_id = apply_filters('generate_missing_object_id_rs', 0, $required_cap) )
				return $object_id;
		}

		if ( $is_taxonomy_cap = $scoper->cap_defs->member_property( $required_cap, 'is_taxonomy_cap' ) ) {
			if ( ! $src_name = $scoper->taxonomies->member_property( $is_taxonomy_cap, 'source', 'name') )
				return;

			if ( awp_ver( '3.0' ) ) {
				//if ( ! empty($_POST['action']) && ( 'add-tag' == $_POST['action'] ) ) {
					if ( ! empty($_POST['parent']) && ( $_POST['parent'] > 0 ) )
						return $_POST['parent'];
					
					elseif ( ( 'term' == $src_name ) && ! empty($_REQUEST['tag_ID']) )
						return $_REQUEST['tag_ID'];
					else
						return 0;
				//}
			}
		}
		
		// WP core edit_post function requires edit_published_posts or edit_published_pages cap to save a post to "publish" status, but does not pass a post ID
		// Similar situation with edit_others_posts, publish_posts.
		// So... insert the object ID from POST vars
		if ( empty($src_name) )
			$src_name = $scoper->cap_defs->member_property($required_cap, 'src_name');
		
		if ( ! empty( $_POST ) ) {
			// special case for comment post ID
			if ( ! empty( $_POST['comment_post_ID'] ) )
				$_POST['post_ID'] = $_POST['comment_post_ID'];
				
			if ( ! $id = $scoper->data_sources->get_from_http_post('id', $src_name) ) {

				if ( strpos( $_SERVER['SCRIPT_NAME'], 'p-admin/async-upload.php' ) ) {
					if ( $attach_id = $scoper->data_sources->get_from_http_post('attachment_id', $src_name) ) {
						if ( $attach_id ) {
							global $wpdb;
							$id = scoper_get_var( "SELECT post_parent FROM $wpdb->posts WHERE post_type = 'attachment' AND ID = '$attach_id'" );
							if ( $id > 0 )
								return $id;
						}
					}
				} elseif ( ! $id && ! empty($_POST['id']) ) // in case normal POST variable differs from ajax variable
					$id = $_POST['id'];
			}

			/* on the moderation page, admin-ajax tests for moderate_comments without passing any ID */
			if ( ('moderate_comments' == $required_cap) )
				if ( $comment = get_comment( $id ) )
					return $comment->comment_post_ID;
			
			if ( ! empty($id) )
				return $id;
				
			// special case for adding categories
			if ( ( 'manage_categories' == $required_cap ) ) {
				if ( ! empty($_POST['newcat_parent']) )
					return $_POST['newcat_parent'];
				elseif ( ! empty($_POST['category_parent']) )
					return $_POST['category_parent'];
			}
				
			
		} elseif ( defined('XMLRPC_REQUEST') ) {
			global $xmlrpc_post_id_rs;
			if ( ! empty($xmlrpc_post_id_rs) )
				return $xmlrpc_post_id_rs;
		} else {
			//rs_errlog("checking uri for source $src_name");
			$id = $scoper->data_sources->get_from_uri('id', $src_name);
			if ( $id > 0 )
				return $id;
		}
	}
	
	
	// Some users with term or object roles are now able to view and edit certain 
	// content, if only the unscoped core would let them in the door.  For example, you can't 
	// load edit-pages.php unless current_user_can('edit_pages') blog-wide.
	//
	// This policy is sensible for unscoped users, as it hides stuff they can't have.
	// But it is needlessly oppressive to those who walk according to the law of the scoping. 
	// Subvert the all-or-nothing paradigm by reporting a blog-wide cap if the user has 
	// the capability for any taxonomy.
	//
	// Due to subsequent query filtering, this does not unlock additional content blog-wide.  
	// It merely enables us to run all pertinent content through our gauntlet (rather than having 
	// some contestants disqualified before we arrive at the judging stand).
	//
	// A happy side effect is that, in a fully scoped blog, all non-administrator users can be set
	// to "Subscriber" blogrole so the failure state upon accidental Role Scoper disabling 
	// is overly narrow access, not overly open.
	function user_can_for_any_term($reqd_caps, $user = '') {
		global $scoper;

		if ( ! is_object($user) ) {
			global $current_user;
			$user = $current_user;
		}

		// Instead of just intersecting the missing reqd_caps with termcaps from all term_roles,
		// require each subset of caps with matching src_name, object type and op_type to 
		// all be satisfied by the same role (any assigned term role).  This simulates flt_objects_where behaviour.
		
		$grant_caps = array();

		$caps_by_otype = $scoper->cap_defs->organize_caps_by_otype($reqd_caps);
		
		foreach ( $caps_by_otype as $src_name => $otypes ) {
			$src = $scoper->data_sources->get($src_name);
		
			// deal with upload_files and other capabilities which have no specific object type
			if ( ! array_diff_key( $otypes, array( '' => true ) ) ) {
				$otypes['post'] = $otypes[''];
				$otypes['page'] = $otypes[''];
				unset( $otypes[''] );
			}
			
			// deal with term management caps
			if ( isset($caps_by_otype['post']) ) {
				$first_otype = key( $caps_by_otype['post'] );
				if ( ( 'category' == $first_otype ) || ( awp_ver('3.0') && taxonomy_exists( $first_otype ) ) ) {
					$_taxonomies = scoper_get_option( 'use_taxonomies' );
					if ( ! empty( $_taxonomies[ $first_otype ] ) ) {
						$otypes[$first_otype] = $reqd_caps;
						$uses_taxonomies = array( $first_otype );
					}
				}
			}

			if ( ! isset($uses_taxonomies) )
				$uses_taxonomies = scoper_get_taxonomy_usage( $src_name, array_keys($otypes) );

			// this ensures we don't credit term roles on custom taxonomies which have been disabled
			$uses_taxonomies = array_intersect( $uses_taxonomies, $scoper->taxonomies->get_all_keys() );
				
			if ( empty($uses_taxonomies) )
				continue;
				
			//dump($scoper->taxonomies->members);

			// this ensures we don't credit term roles on custom taxonomies which have been disabled
			$uses_taxonomies = array_intersect( $uses_taxonomies, $scoper->taxonomies->get_all_keys() );

			foreach ( $otypes as $this_otype_caps ) { // keyed by object_type
				$caps_by_op = $scoper->cap_defs->organize_caps_by_op($this_otype_caps);

				foreach ( $caps_by_op as $this_op_caps ) { // keyed by op_type
					$roles = $scoper->role_defs->qualify_roles($this_op_caps);

					foreach ($uses_taxonomies as $taxonomy) {
						if ( ! isset($user->term_roles[$taxonomy]) )
							$user->term_roles[$taxonomy] = $user->get_term_roles_daterange($taxonomy);				// call daterange function populate term_roles property - possible perf enhancement for subsequent code even though we don't conider content_date-limited roles here
							
						//dump($user->term_roles[$taxonomy]);
							
						if ( array_intersect_key($roles, agp_array_flatten( $user->term_roles[$taxonomy], false ) ) )	// okay to include all content date ranges because can_for_any_term checks are only preliminary measures to keep the admin UI open
							$grant_caps = array_merge($grant_caps, $this_op_caps);
					}
				}
			}
		}	
		
		if ( $grant_caps )
			return array_fill_keys($reqd_caps, true);
		else
			return array();
	}
	
	// used by flt_user_has_cap prior to failing blogcaps requirement
	// Note that this is not to be called if an object_id was provided to (or detected by) flt_user_has_cap
	// This is primarily a way to ram open a closed gate prior to selectively re-closing it ourself
	function user_can_for_any_object($reqd_caps, $user = '') {
		global $scoper;

		if ( ! empty( $scoper->ignore_object_roles ) ) {
			// use this to force cap via blog/term role for Write Menu item
			$scoper->ignore_object_roles = false;
			return array();
		}

		if ( ! is_object($user) ) {
			global $current_user;
			$user = $current_user;
		}
		
		if ( $roles = $scoper->qualify_object_roles( $reqd_caps, '', $user, true ) )  // arg: convert 'edit_others', etc. to equivalent owner base cap
			return array_fill_keys($reqd_caps, true);
		
		return array();
	}
}
 

// equivalent to current_user_can, 
// except it supports array of reqd_caps, supports non-current user, and does not support numeric reqd_caps
function _cr_user_can( $reqd_caps, $object_id = 0, $user_id = 0, $meta_flags = array() ) {	
	// $meta_flags currently used for 'skip_revision_allowance', 'skip_any_object_check', 'skip_any_term_check', 'skip_id_generation', 'require_full_object_role'
	// For now, skip array_merge with defaults, for perf
	if ( $user_id )
		$user = new WP_User($user_id);  // don't need Scoped_User because only using allcaps property (which contain WP blogcaps).  flt_user_has_cap will instantiate new WP_Scoped_User based on the user_id we pass
	else
		$user = wp_get_current_user();
	
	if ( empty($user) )
		return false;

	$reqd_caps = (array) $reqd_caps;
	$check_caps = $reqd_caps;
	foreach ( $check_caps as $cap_name ) {
		if ( $meta_caps = map_meta_cap($cap_name, $user->ID, $object_id) ) {
			$reqd_caps = array_diff( $reqd_caps, array($cap_name) );
			$reqd_caps = array_unique( array_merge( $reqd_caps, $meta_caps ) );
		}
	}
	
	if ( 'blog' === $object_id ) { // legacy API support
		$meta_flags['skip_any_object_check'] = true;
		$meta_flags['skip_any_term_check'] = true;
		$meta_flags['skip_id_generation'] = true;
	}

	if ( $meta_flags ) {
		// handle special case revisionary flag
		if ( ! empty($meta_flags['skip_revision_allowance']) ) {
			if ( defined( 'RVY_VERSION' ) ) {
				global $revisionary;
				$revisionary->skip_revision_allowance = true;	// this will affect the behavior of Role Scoper's user_has_cap filter
			}
			
			unset( $meta_flags['skip_revision_allowance'] );	// no need to set this flag on cap_interceptor
		}
	
		// set temporary flags for use by our user_has_cap filter
		global $scoper;
		if ( isset($scoper) ) {
			foreach( $meta_flags as $flag => $value )
				$scoper->cap_interceptor->$flag = $value;
		} else
			$meta_flags = array();
	}

	$_args = ( 'blog' == $object_id ) ? array( $reqd_caps, $user->ID, 0 ) : array( $reqd_caps, $user->ID, $object_id );
	$capabilities = apply_filters('user_has_cap', $user->allcaps, $reqd_caps, $_args );

	if ( $meta_flags ) {
		// clear temporary flags
		foreach( $meta_flags as $flag => $value )
			$scoper->cap_interceptor->$flag = false;
	}
	
	if ( ! empty($revisionary) )
		$revisionary->skip_revision_allowance = false;

	foreach ( $reqd_caps as $cap_name ) {
		if( empty($capabilities[$cap_name]) || ! $capabilities[$cap_name] ) {
			// if we're about to fail due to a missing create_child_pages cap, honor edit_pages cap as equivalent
			// TODO: abstract this with cap_defs property
			if ( 'create_child_pages' == $cap_name ) {
				$alternate_cap_name = 'edit_pages';
				$_args = array( array($alternate_cap_name), $user->ID, $object_id );
				$capabilities = apply_filters('user_has_cap', $user->allcaps, array($alternate_cap_name), $_args);
				
				if ( empty($capabilities[$alternate_cap_name]) || ! $capabilities[$alternate_cap_name] )
					return false;
			} else
				return false;
		}
	}

	return true;
}

?>