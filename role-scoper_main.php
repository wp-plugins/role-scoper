<?php
if( basename(__FILE__) == basename($_SERVER['SCRIPT_FILENAME']) )
	die( 'This page cannot be called directly.' );
	
/**
 * Scoper PHP class for the WordPress plugin Role Scoper
 * role-scoper_main.php
 * 
 * @author 		Kevin Behrens
 * @copyright 	Copyright 2010
 * 
 */
class Scoper
{
	var $data_sources;			// object ref - WP_Scoped_Data_Sources
	var $cap_defs;				// object ref - WP_Scoped_Capabilities
	var $role_defs;				// object ref - WP_Scoped_Roles
	var $taxonomies;			// object ref - WP_Scoped_Taxonomies
	var $access_types;			// object ref - AGP_Config_Items
	
	var $admin;					// object ref - ScoperAdmin
	var $filters_admin;			// object ref - ScoperAdminFilters
	var $filters_admin_ui;		// object ref - ScoperAdminFiltersUI
	var $filters_admin_item_ui; // object ref - ScoperAdminFiltersItemUI
	var $cap_interceptor;		// object ref - CapInterceptor_RS
	var $query_interceptor;		// object ref - QueryInterceptor_RS
	var $users_interceptor;		// object ref - UsersInterceptor_RS
	var $template_interceptor;	// object ref - TemplateInterceptor_RS
	var $attachment_interceptor; // object ref - AttachmentInterceptor_RS
	var $feed_interceptor;		// object ref - FeedInterceptor_RS
	
	// === Temporary status variables ===
	var $user_cache = array();	  //$user_cache[query] = results;
	var $ignore_object_roles;
	var $direct_file_access;

	// role usage tracking for template functions, Manage Posts/Pages custom columns
	var $teaser_ids;
	var $objscoped_ids;
	var $termscoped_ids;
	var $have_objrole_ids;
	var $have_termrole_ids;
	
	// these properties used by Query_Interceptor; problems setting array properies an that object
	var $last_request = array();
	
	var $listed_ids = array();  // $listed_ids[src_name][object_id] = array of colname=>value : general purpose memory cache
								// If a 3rd party loads it with listing results for a scoper-defined otype, those will be used to buffer subsequent current_user_can/flt_user_has_cap queries

	var $default_restrictions = array();
			
	function any_custom_registrations() {
		if ( ! awp_ver( '2.9' ) )
			return array();
		
		$any_custom['type'] = (bool) get_post_types( array( 'public' => true, '_builtin' => false ) );

		if ( awp_ver( '3.0' ) )
			$any_custom['taxonomy'] = (bool) get_taxonomies( array( 'public' => true, '_builtin' => false ) );
		else
			$any_custom['taxonomy'] = (bool) get_custom_taxonomies_rs();
			
		return $any_custom;
	}
						
	// minimal config retrieval to support pre-init usage by WP_Scoped_User before text domain is loaded
	function Scoper() {
		log_mem_usage_rs( 'new Scoper' );
		
		require_once('defaults_rs.php');
		require_once('capabilities_rs.php');
		require_once('roles_rs.php');
		
		log_mem_usage_rs( 'initial Scoper require_once' );
				
		$any_custom = $this->any_custom_registrations();

		if ( awp_ver( '3.0' ) ) {
			require_once( 'custom_base_rs.php' );
			scoper_establish_status_caps();
			
			if ( $any_custom['type'] ) {
				require_once( 'custom_types_rs.php' );
				scoper_force_distinct_post_caps();

				// register a map_meta_cap filter to handle the type-specific meta caps we are forcing
				require_once( 'meta_caps_rs.php' );	
			}

			if ( $any_custom['taxonomy'] ) {
				require_once( 'custom_taxonomies_rs.php' );
				scoper_force_distinct_taxonomy_caps();
			}

		} elseif ( awp_ver( '2.9' ) ) {
			if ( $any_custom['type']  ) {
				require_once( 'custom_types-29_rs.php' );
				
				require_once( 'meta_caps-29_rs.php' );
			}

			if ( $any_custom['taxonomy'] )
				require_once( 'custom_taxonomies-29_rs.php' );
		} else
			require_once( 'meta_caps-legacy_rs.php' );

		$this->cap_defs = new WP_Scoped_Capabilities();
		$this->cap_defs = apply_filters('define_capabilities_rs', $this->cap_defs);
		$this->cap_defs->add_member_objects( scoper_core_cap_defs( $any_custom ) );  // core capdefs (and other core config) are not intended to be altered by other plugins
		$this->cap_defs->lock(); // prevent inadvertant improper API usage
		
		log_mem_usage_rs( 'cap_defs' );
		
		global $scoper_role_types;
		$this->role_defs = new WP_Scoped_Roles($this->cap_defs, $scoper_role_types);
		
		log_mem_usage_rs( 'roles' );
		
		$this->load_role_caps();
		$this->role_defs->add_member_objects( scoper_core_role_defs( $any_custom ) );
		
		log_mem_usage_rs( 'role_defs->add_member_objects' );

		foreach ( $this->role_defs->get_all_keys() as $role_handle ) {
			if ( ! empty($this->role_defs->members[$role_handle]->objscope_equivalents) ) {
				foreach( $this->role_defs->members[$role_handle]->objscope_equivalents as $equiv_key => $equiv_role_handle ) {
					
					if ( scoper_get_option( "{$equiv_role_handle}_role_objscope" ) ) {	// If "Additional Object Role" option is set for this role, treat it as a regular direct-assigned Object Role

						if ( isset($this->role_defs->members[$equiv_role_handle]->valid_scopes) )
							$this->role_defs->members[$equiv_role_handle]->valid_scopes = array('blog' => 1, 'term' => 1, 'object' => 1);

						unset( $this->role_defs->members[$role_handle]->objscope_equivalents[$equiv_key] );
				
						if ( ! defined( 'DISABLE_OBJSCOPE_EQUIV_' . $role_handle ) )
							define( 'DISABLE_OBJSCOPE_EQUIV_' . $role_handle, true );	// prevent Role Caption / Abbrev from being substituted from equivalent role
					}
				}
			}

			$this->role_defs = apply_filters('define_roles_rs', $this->role_defs);
			//$this->role_defs->remove_invalid(); // currently don't allow additional custom-defined post, page or link roles
		}
				
		// To support merging in of WP role assignments, always note actual WP-defined roles 
		// regardless of which role type we are scoping with.
		$this->role_defs->populate_with_wp_roles();
		$this->role_defs->lock(); // prevent inadvertant improper API usage
		
		log_mem_usage_rs( 'role_defs - WP roles' );
	}
	
	function load_role_caps() {
		$any_custom = $this->any_custom_registrations();
		$this->role_defs->role_caps = apply_filters('define_role_caps_rs', scoper_core_role_caps( $any_custom ) );

		//if ( ! is_admin() || ! defined('SCOPER_REALM_ADMIN_RS') ) { // don't remove items if the "disabled" settings are being editied
		
		if ( $user_role_caps = scoper_get_option('user_role_caps') )
			$this->role_defs->add_role_caps( $user_role_caps );

		if ( $disabled_role_caps = scoper_get_option('disabled_role_caps') )
			$this->role_defs->remove_role_caps( $disabled_role_caps );
	}
	
	function init_users_interceptor() {
		if ( ! isset($this->users_interceptor) ) {
			require_once('users-interceptor_rs.php');
			
			log_mem_usage_rs( 'require users-interceptor_rs' );
			
			$this->users_interceptor = new UsersInterceptor_RS();
			
			log_mem_usage_rs( 'init Users Interceptor' );
		}
		
		return $this->users_interceptor;
	}
	
	// potential usage during plugin activate / deactivate ( filtered config data without loading filters )
	function load_config() {	
		log_mem_usage_rs( 'Scoper load_config' );
		
		require_once('lib/agapetry_config_items.php');
		$this->access_types = new AGP_Config_Items();
		$this->access_types->add_member_objects( scoper_core_access_types() );  // 'front' and 'admin' are the hardcoded access types
		$this->access_types->lock(); // prevent inadvertant improper API usage

		log_mem_usage_rs( 'access types' );

		$access_name = ( is_admin() || defined('XMLRPC_REQUEST') ) ? 'admin' : 'front';
		$access_name = apply_filters( 'scoper_access_name', $access_name );		// others plugins can apply additional criteria for treating a particular URL with wp-admin or front-end filtering
		if ( ! defined('CURRENT_ACCESS_NAME_RS') )
			define('CURRENT_ACCESS_NAME_RS', $access_name);

		if ( ! is_admin() || ! defined('SCOPER_REALM_ADMIN_RS') ) {		// don't remove items if the "disabled" settings are being editied
			if ( $disabled_access_types = scoper_get_option('disabled_access_types') )
				$this->access_types->remove_members_by_key($disabled_access_types, true);
		}
		
		// If the detected access type (admin, front or custom) were "disabled",
		// they are still detected, but we note that query filters should not be applied
		if ( ! $this->access_types->is_member($access_name) )
			define('DISABLE_QUERYFILTERS_RS', true);


		global $current_user;
		
		if ( empty($current_user->assigned_blog_roles) ) {
			foreach ($this->role_defs->get_anon_role_handles() as $role_handle) {
				$current_user->assigned_blog_roles[ANY_CONTENT_DATE_RS][$role_handle] = true;
				$current_user->blog_roles[ANY_CONTENT_DATE_RS][$role_handle] = true;
			}
		}
		
		$any_custom = $this->any_custom_registrations();
		
		require_once('data_sources_rs.php');
		$this->data_sources = new WP_Scoped_Data_Sources();
		$this->data_sources->add_member_objects( scoper_core_data_sources($any_custom) );
		$this->data_sources = apply_filters('define_data_sources_rs', $this->data_sources);
		$this->data_sources->lock();			// prevent inadvertant improper API usage
		
		log_mem_usage_rs( 'data sources' );
		
		require_once('taxonomies_rs.php');
		log_mem_usage_rs( 'require taxonomies' );
		$this->taxonomies = new WP_Scoped_Taxonomies( $this->data_sources );
		
		$this->taxonomies->add_member_objects( scoper_core_taxonomies($any_custom) );
		$this->taxonomies = apply_filters('define_taxonomies_rs', $this->taxonomies);
		$this->taxonomies->lock();
		
		log_mem_usage_rs( 'new WP_Scoped_Taxonomies' );
		
		$this->role_defs->lock();
		
		// clean up after 3rd party plugins (such as Role Scoping for NGG) which don't set object type and src_name properties for roles
		if ( has_filter( 'define_roles_rs' ) ) {
			require_once( 'extension-helper_rs.php' );
			scoper_adjust_legacy_extension_cfg( $this->role_defs, $this->cap_defs );
		}
		
		do_action('config_loaded_rs');
	}
	
	function refresh_blogroles( $user = '' ) {
		if ( ! is_object($user) )
			$user = $GLOBALS['current_user'];

		if ( method_exists( $user, 'merge_scoped_blogcaps' ) )  // workaround for fatal error on role-scoper.php bailout
			$user->merge_scoped_blogcaps();
		
		if ( $user->ID ) {
			foreach ( array_keys($user->assigned_blog_roles) as $date_key )
				$user->blog_roles[$date_key] = $this->role_defs->add_contained_roles( $user->assigned_blog_roles[$date_key] );
		}
	}
	
	function init() {
		scoper_version_check();
		
		if ( ! isset($this->data_sources) )
			$this->load_config();
		
		$is_administrator = is_content_administrator_rs();
		
		if ( $doing_cron = defined('DOING_CRON') )
			if ( ! defined('DISABLE_QUERYFILTERS_RS') )
				define('DISABLE_QUERYFILTERS_RS', true);
		
		if ( is_admin() )
			$this->add_admin_ui_filters( $is_administrator );

		if ( ! $this->direct_file_access = strpos($_SERVER['QUERY_STRING'], 'rs_rewrite') )
			$this->add_main_filters();

		// ===== Special early exit if this is a plugin install script
		if ( is_admin() ) {
			$script_name = $_SERVER['SCRIPT_NAME'];
			if ( strpos($script_name, 'p-admin/plugins.php') || strpos($script_name, 'p-admin/plugin-install.php') || strpos($script_name, 'p-admin/plugin-editor.php') ) {
				// flush RS cache on activation of any plugin, in case we cached results based on its presence / absence
				if ( ( ! empty($_POST) ) || ( ! empty($_REQUEST['action']) ) ) {
					if ( ! empty($_POST['networkwide']) || ( 'plugin-editor.php' == $GLOBALS['pagenow'] ) )
						wpp_cache_flush_all_sites();
					else
						wpp_cache_flush();
				}

				do_action( 'scoper_init' );
				return; // no further filtering on WP plugin maintenance scripts
			}
		}
		// =====

		require_once('attachment-interceptor_rs.php');
		$this->attachment_interceptor = new AttachmentInterceptor_RS(); // .htaccess file is always there, so we always need to handle its rewrites
				
		// ===== Content Filters to limit/enable the current user
		$disable_queryfilters = defined('DISABLE_QUERYFILTERS_RS');

		if ( $disable_queryfilters ) {
			// Some wp-admin pages need to list pages or categories based on front-end access.  Classic example is Subscribe2 categories checklist, included in Subscriber profile
			// In that case, filtering will be applied even if wp-admin filtering is disabled.  API hook enables other plugins to defined their own "always filter" URIs.
			$always_filter_uris = apply_filters('scoper_always_filter_uris', array( 'p-admin/profile.php' ) );
			foreach ( $always_filter_uris as $uri_sub ) {
				if ( strpos(urldecode($_SERVER['REQUEST_URI']), $uri_sub) ) {
					$disable_queryfilters = false;
					break;
				}
			}
		}

		if ( ! $disable_queryfilters ) {
			 if ( ! $is_administrator ) {
				if ( $this->direct_file_access ) {
					require_once('cap-interceptor-basic_rs.php');  // only need to support basic read_post / read_page check for direct file access
					add_filter('user_has_cap', array('CapInterceptorBasic_RS', 'flt_user_has_cap'), 99, 3);
				} else {
					require_once('cap-interceptor_rs.php');
					$this->cap_interceptor = new CapInterceptor_RS();
				}
			}

			// (also use content filters on front end to FILTER IN private content which WP inappropriately hides from administrators)
			if ( ( ! $is_administrator ) || $this->is_front() ) {
				require_once('query-interceptor_rs.php');
				$this->query_interceptor = new QueryInterceptor_RS();
			}

			if ( ( ! $this->direct_file_access ) && ( ! $is_administrator || ! defined('XMLRPC_REQUEST') ) ) // don't tempt trouble by adding hardway filters on XMLRPC for logged administrator
				$this->add_hardway_filters();

		} // endif query filtering not disabled for this access type

		do_action( 'scoper_init' );
			
		// ===== end Content Filters
		
	} // end function init
	
	
	// filters which are only needed for the wp-admin UI
	function add_admin_ui_filters( $is_administrator ) {
		$script_name = $_SERVER['SCRIPT_NAME'];
		
		// ===== Admin filters (menu and other basics) which are (almost) always loaded 
		require_once('admin/admin_rs.php');
		$this->admin = new ScoperAdmin();
		
		if ( ! strpos($script_name, 'p-admin/async-upload.php' ) ) {
			if ( ! defined('DISABLE_QUERYFILTERS_RS') || $is_administrator ) {
				require_once( 'admin/filters-admin-ui_rs.php' );
				$this->filters_admin_ui = new ScoperAdminFiltersUI();
			}
		}
		// =====

		// ===== Script-specific Admin filters 
		if ( strpos($script_name, 'p-admin/users.php') ) {
			require_once( 'admin/filters-admin-users_rs.php' );
			
		} elseif ( strpos($script_name, 'p-admin/edit.php') || strpos($script_name, 'p-admin/edit-pages.php') ) {
			if ( ! defined('DISABLE_QUERYFILTERS_RS') || $is_administrator )
				require_once( 'admin/filters-admin-ui-listing_rs.php' );
		} elseif ( strpos($script_name, 'p-admin/edit-tags.php') ) {
			if ( ! defined('DISABLE_QUERYFILTERS_RS') )
				require_once( 'admin/filters-admin-terms_rs.php' );
		}
		// =====
		
		if ( scoper_get_option( 'group_ajax' ) && ( isset( $_GET['rs_user_search'] ) || isset( $_GET['rs_group_search'] ) ) ) {
			require_once( 'admin/user_query_rs.php' );
			exit;	
		} 
	}
	
	
	function add_hardway_filters() {
		$script_name = $_SERVER['SCRIPT_NAME'];
		
		// port or low-level query filters to work around limitations in WP core API
		if ( awp_ver( '3.0' ) )
			require_once('hardway/hardway_rs.php'); // need get_pages() filtering to include private pages for some 3rd party plugin config UI (Simple Section Nav)
		else
			require_once('hardway/hardway-legacy_rs.php');
			
		// buffering of taxonomy children is disabled with non-admin user logged in
		// But that non-admin user may add cats.  Don't allow unfiltered admin to rely on an old copy of children
		global $wp_taxonomies;
		if ( ! empty($wp_taxonomies) ) {
			foreach ( array_keys($wp_taxonomies) as $taxonomy )
				add_filter ( "option_{$taxonomy}_children", create_function( '$option_value', "return rs_get_terms_children('$taxonomy', " . '$option_value );') );
				//add_filter("option_{$taxonomy}_children", create_function( '', "return rs_get_terms_children('$taxonomy');") );
		}

		if ( is_admin() || defined('XMLRPC_REQUEST') ) {
            if ( ! strpos( urldecode($_SERVER['REQUEST_URI']), 'p-admin/plugin-editor.php' ) && ! strpos( urldecode($_SERVER['REQUEST_URI']), 'p-admin/plugins.php' ) ) {
				// low-level filtering for miscellaneous admin operations which are not well supported by the WP API
				$hardway_uris = array(
				'p-admin/index.php',		'p-admin/revision.php',			'admin.php?page=rvy-revisions',
				'p-admin/post.php', 		'p-admin/post-new.php', 		'p-admin/page.php', 		'p-admin/page-new.php', 
				'p-admin/link-manager.php', 'p-admin/edit.php', 			'p-admin/edit-pages.php', 	'p-admin/edit-comments.php', 
				'p-admin/categories.php', 	'p-admin/link-category.php', 	'p-admin/edit-link-categories.php', 'p-admin/upload.php',
				'p-admin/edit-tags.php', 	'p-admin/profile.php',			'p-admin/link-add.php',	'p-admin/admin-ajax.php',
				'p-admin/media-upload.php'   );

				$hardway_uris = apply_filters('scoper_admin_hardway_uris', $hardway_uris);

				$uri = urldecode($_SERVER['REQUEST_URI']);
				foreach ( $hardway_uris as $uri_sub ) {	// index.php can only be detected by index.php, but 3rd party-defined hooks may include arguments only present in REQUEST_URI
					if ( defined('XMLRPC_REQUEST') || strpos($script_name, $uri_sub) || strpos($uri, $uri_sub) ) {
						require_once('hardway/hardway-admin_rs.php');
						break;
					}
				}
        	}
		} // endif is_admin or xmlrpc
	}
	
	
	// add filters which were skipped due to direct file access, but are now needed for the error page display
	function add_main_filters() {
		$script_name = $_SERVER['SCRIPT_NAME'];
		$is_admin = is_admin();
		$is_administrator = is_content_administrator_rs();
		$disable_queryfilters = defined('DISABLE_QUERYFILTERS_RS');
		$frontend_admin = false;

		if ( ! defined('DOING_CRON') ) {
			if ( $this->is_front() ) {
				if ( ! $disable_queryfilters )
					require_once('query-interceptor-front_rs.php');
	
				if ( ! $is_administrator ) {
					require_once('qry-front_non-administrator_rs.php');
					$this->feed_interceptor = new FeedInterceptor_RS(); // file already required in role-scoper.php
				}
	
				require_once('template-interceptor_rs.php');
				$this->template_interceptor = new TemplateInterceptor_RS();
	
				$frontend_admin = ! scoper_get_option('no_frontend_admin'); // potential performance enhancement	
			}
				
			// ===== Filters which are always loaded (except on plugin scripts), for any access type
			include_once( 'hardway/wp-patches_agp.php' ); // simple patches for WP
			
			if ( $this->is_front() || strpos($script_name, 'p-admin/edit.php') || strpos($script_name, 'p-admin/edit-pages.php') ) {
				require_once('query-interceptor-base_rs.php');
				$this->query_interceptor_base = new QueryInterceptorBase_RS();  // listing filter used for role status indication in edit posts/pages and on front end by template functions
			}
		}
		
		// ===== Filters which support automated role maintenance following content creation/update
		// Require an explicitly set option to skip these for front end access, just in case other plugins modify content from the front end.
		if ( ( $is_admin || defined('XMLRPC_REQUEST') || $frontend_admin || defined('DOING_CRON') ) ) {
			require_once( 'admin/cache_flush_rs.php' );
			require_once( 'admin/filters-admin_rs.php' );
			$this->filters_admin = new ScoperAdminFilters();
		}
		// =====
	}
	

	// Primarily for internal use. Drops some features of WP core get_terms while adding the following versatility:
	// - supports any RS-defined taxonomy, with or without WP taxonomy schema
	// - optionally return term_id OR term_taxonomy_id as single column
	// - specify filtered or unfiltered via argument
	// - optionally get terms for a specific object
	// - option to order by term hierarchy (but structure as flat array)
	function get_terms($taxonomy, $filtering = true, $cols = COLS_ALL_RS, $object_id = 0, $args = array()) {
		if ( ! $tx = $this->taxonomies->get($taxonomy) )
			return array();
		
		global $wpdb;

		$defaults = array( 'order_by' => '', 'use_object_roles' => false ); // IMPORTANT to default access_name to nullstring
		$args = array_merge( $defaults, (array) $args );
		extract($args);

		if ( $filtering && is_administrator_rs($tx->source) )
			$filtering = 0;

		// try to pull it out of wpcache
		$ckey = md5( $taxonomy . $cols . $object_id . serialize($args) . $order_by );
		
		if ( $filtering ) {
			$src_name = $this->taxonomies->member_property($taxonomy, 'object_source', 'name');
			
			if ( ADMIN_TERMS_FILTER_RS === $filtering ) {
				if ( $reqd_caps = $this->cap_defs->get_matching($src_name, $taxonomy, OP_ADMIN_RS) ) {
					$args['reqd_caps_by_otype'] = array();
					$args['reqd_caps_by_otype'][$src_name] = array_keys($reqd_caps);
				}
			} else {
				$args['reqd_caps_by_otype'] = $this->get_terms_reqd_caps($taxonomy);
			}

			$ckey = md5( $ckey . serialize($reqd_caps) ); ; // can vary based on request URI
		
			global $current_user;
			$cache_flag = 'rs_scoper_get_terms';
			$cache = $current_user->cache_get($cache_flag);
		} else {			
			$cache_flag = "all_terms";
			$cache_id = 'all';
			$cache = wpp_cache_get( $cache_id, $cache_flag );
		}

		if ( isset( $cache[ $ckey ] ) ) {
			return $cache[ $ckey ];
		}
			
		// call base class method to build query
		$terms_only = ( ! $filtering || empty($use_object_roles) );
	
		$query_base = $this->taxonomies->get_terms_query($taxonomy, $cols, $object_id, $terms_only );

		if ( ! $query_base )
			return array();

		$query = ( $filtering ) ? apply_filters('terms_request_rs', $query_base, $taxonomy, $args) : $query_base;

		// avoid sending alarms to SQL purists if this query was not modified by RS filter
		if ( $query_base == $query )
			$query = str_replace( 'WHERE 1=1 AND', 'WHERE', $query );
		
		if ( COL_ID_RS == $cols )
			$results = scoper_get_col($query);
		elseif ( COL_COUNT_RS == $cols )
			$results = intval( scoper_get_var($query) );
		else {
			// TODO: why is this still causing an extra (and costly) scoped query?
			/*
			// for COLS_ALL query, need to call core get_terms call in case another plugin is translating term names
			if ( has_filter( 'get_terms', array('ScoperHardwayTaxonomy', 'flt_get_terms') ) ) {
				remove_filter( 'get_terms', array('ScoperHardwayTaxonomy', 'flt_get_terms'), 1, 3 );
				$all_terms = get_terms('category');
				add_filter( 'get_terms', array('ScoperHardwayTaxonomy', 'flt_get_terms'), 1, 3 );

				$term_names = scoper_get_property_array( $all_terms, 'term_id', 'name' );
			}
			*/
			
			$results = scoper_get_results($query);

			//scoper_restore_property_array( $results, $term_names, 'term_id', 'name' );
				
			if ( ORDERBY_HIERARCHY_RS == $order_by ) {
				require_once('admin/admin_lib_rs.php');
				if ( $src = $this->taxonomies->member_property($taxonomy, 'source') ) {
					if ( ! empty($src->cols->id) && ! empty($src->cols->parent) ) {
						require_once( 'admin/admin_lib-bulk-parent_rs.php');
						$results = ScoperAdminBulkParent::order_by_hierarchy($results, $src->cols->id, $src->cols->parent);
					}
				}
			}
		}
		
		$cache[ $ckey ] = $results;

		if ( $results || empty( $_POST ) ) { // todo: why do we get an empty array for unfiltered request for object terms early in POST processing? (on submission of a new post by a contributor)
			if ( $filtering )
				$current_user->cache_force_set( $cache, $cache_flag );
			else
				wpp_cache_force_set( $cache_id, $cache, $cache_flag );	
		}
		
		return $results;
	}
	
	function get_default_restrictions($scope, $args = '') {
		$defaults = array( 'force_refresh' => false );
		$args = array_merge( $defaults, (array) $args );
		extract($args);
		
		if ( isset($this->default_restrictions[$scope]) && ! $force_refresh )
			return $this->default_restrictions[$scope];
		
		if ( empty($force_refresh) ) {
			$cache_flag = "rs_{$scope}_def_restrictions";
			$cache_id = md5('');	// maintain default id generation from previous versions

			$default_strict = wpp_cache_get($cache_id, $cache_flag);
		}
		
		if ( $force_refresh || ! is_array($default_strict) ) {
			global $wpdb;

			$qry = "SELECT src_or_tx_name, role_name FROM $wpdb->role_scope_rs WHERE role_type = 'rs' AND topic = '$scope' AND max_scope = '$scope' AND obj_or_term_id = '0'";

			$default_strict = array();
			if ( $results = scoper_get_results($qry) ) {
				foreach ( $results as $row ) {
					$role_handle = scoper_get_role_handle($row->role_name, 'rs');
					$default_strict[$row->src_or_tx_name][$role_handle] = true;
					
					if (OBJECT_SCOPE_RS == $scope) {
						if ( $objscope_equivalents = $this->role_defs->member_property($role_handle, 'objscope_equivalents') )
							foreach ( $objscope_equivalents as $equiv_role_handle )
								$default_strict[$row->src_or_tx_name][$equiv_role_handle] = true;
					}
					
				}
			}
		}
		
		$this->default_restrictions[$scope] = $default_strict;

		wpp_cache_set($cache_id, $default_strict, $cache_flag);
		
		return $default_strict;
	}
	
	// for any given role requirement, a strict term is one which won't blend in blog role assignments
	// (i.e. a term which requires the specified role to be assigned as a term role or object role)
	//
	// returns $arr['restrictions'][role_handle][obj_or_term_id] = array( 'assign_for' => $row->assign_for, 'inherited_from' => $row->inherited_from ),
	//				['unrestrictions'][role_handle][obj_or_term_id] = array( 'assign_for' => $row->assign_for, 'inherited_from' => $row->inherited_from )
	function get_restrictions($scope, $src_or_tx_name, $args = '') {
		$def_cols = COL_ID_RS;

		// Note: propogating child restrictions are always directly assigned to the child term(s).
		// Use include_child_restrictions to force inclusion of restrictions that are set for child items only,
		// for direct admin of these restrictions and for propagation on term/object creation.
		$defaults = array( 	'id' => 0,					'include_child_restrictions' => false,
						 	'force_refresh' => false,
						 	'cols' => $def_cols,		'return_array' => false );
		$args = array_merge( $defaults, (array) $args );
		extract($args);
		
		//if ( $return_array )
		//	$force_refresh = true;	// wpcache contains require_for value only
		
		$cache_flag = "rs_{$scope}_restrictions_{$src_or_tx_name}";
		$cache_id = md5($src_or_tx_name . $cols . strval($return_array) . strval($include_child_restrictions) );

		if ( ! $force_refresh ) {
			$items = wpp_cache_get($cache_id, $cache_flag);

			if ( is_array($items) ) {
				if ( $id ) {
					foreach ( $items as $setting_type => $roles )
						foreach ( array_keys($roles) as $role_handle )
							$items[$setting_type][$role_handle] = array_intersect_key( $items[$setting_type][$role_handle], array( $id => true ) );
				}

				return $items;
			}
		}
		
		if ( ! isset($this->default_restrictions[$scope]) )
			$this->default_restrictions[$scope] = $this->get_default_restrictions($scope);

		global $wpdb;

		if ( ! empty($this->default_restrictions[$scope][$src_or_tx_name]) ) {
			if ( $strict_roles = array_keys($this->default_restrictions[$scope][$src_or_tx_name]) ) {
				if ( OBJECT_SCOPE_RS == $scope ) {
					// apply default_strict handling to objscope equivalents of each strict role
					foreach ( $strict_roles as $role_handle )
						if ( $objscope_equivalents = $this->role_defs->member_property($role_handle, 'objscope_equivalents') )
							$strict_roles = array_merge($strict_roles, $objscope_equivalents);
							
					$strict_roles = array_unique($strict_roles);
				}
			}
			
			$strict_role_in = "'" . implode("', '", scoper_role_handles_to_names($strict_roles) ) . "'";
		} else
			$strict_role_in = '';
		
		$items = array();				
		if ( ! empty($strict_roles) ) {
			foreach ( $strict_roles as $role_handle )
				$items['unrestrictions'][$role_handle] = array();  // calling code will use this as an indication that the role is default strict
		}
		
		$default_strict_modes = array( false );
		
		if ( $strict_role_in )
			$default_strict_modes []= true;

		foreach ( $default_strict_modes as $default_strict ) {
			$setting_type = ( $default_strict ) ? 'unrestrictions' : 'restrictions';

			if ( TERM_SCOPE_RS == $scope )
				$max_scope = ( $default_strict ) ? 'blog' : 'term';  // note: max_scope='object' entries are treated as separate, overriding requirements
			else
				$max_scope = ( $default_strict ) ? 'blog' : 'object'; // Storage of 'blog' max_scope as object restriction does not eliminate any term restrictions.  It merely indicates, for data sources that are default strict, that this object does not restrict roles
				
			if ( $default_strict )
				$role_clause = "AND role_name IN ($strict_role_in)";
			elseif ($strict_role_in)
				$role_clause = "AND role_name NOT IN ($strict_role_in)";
			else
				$role_clause = '';

			$for_clause = ( $include_child_restrictions ) ? '' : "AND require_for IN ('entity', 'both')";
			
			$qry_base = "FROM $wpdb->role_scope_rs WHERE role_type = 'rs' AND topic = '$scope' AND max_scope = '$max_scope' AND src_or_tx_name = '$src_or_tx_name' $for_clause $role_clause";
			
			if ( COL_COUNT_RS == $cols )
				$qry = "SELECT role_name, count(obj_or_term_id) AS item_count, require_for $qry_base GROUP BY role_name";
			else
				$qry = "SELECT role_name, obj_or_term_id, require_for AS assign_for, inherited_from $qry_base";

			if ( $results = scoper_get_results($qry) ) {
				foreach( $results as $row) {
					$role_handle = scoper_get_role_handle($row->role_name, 'rs');
					
					if ( COL_COUNT_RS == $cols )
						$items[$setting_type][$role_handle] = $row->item_count;
					elseif ( $return_array )
						$items[$setting_type][$role_handle][$row->obj_or_term_id] = array( 'assign_for' => $row->assign_for, 'inherited_from' => $row->inherited_from );
					else
						$items[$setting_type][$role_handle][$row->obj_or_term_id] = $row->assign_for;
				}
			}
			
		} // end foreach default_strict_mode

		wpp_cache_force_set($cache_id, $items, $cache_flag);

		if ( $id ) {
			foreach ( $items as $setting_type => $roles )
				foreach ( array_keys($roles) as $role_handle )
					$items[$setting_type][$role_handle] = array_intersect_key( $items[$setting_type][$role_handle], array( $id => true ) );
		}
		
		return $items;
	}
	
	
	// wrapper for back-compat with calling code expecting array without date limit dimension
	function qualify_terms($reqd_caps, $taxonomy = 'category', $qualifying_roles = '', $args = '') {
		$terms = $this->qualify_terms_daterange( $reqd_caps, $taxonomy, $qualifying_roles, $args );
		
		if ( isset($terms['']) && is_array($terms['']) )
			return $terms[''];
		else
			return array();
	}

	// $qualifying_roles = array[role_handle] = 1 : qualifying roles
	// returns array of term_ids (terms which have at least one of the qualifying roles assigned)
	function qualify_terms_daterange($reqd_caps, $taxonomy = 'category', $qualifying_roles = '', $args = '') {
		$defaults = array( 'user' => '', 'return_id_type' => COL_ID_RS, 'use_blog_roles' => true, 
							'alternate_roles' => '', 'override_roles' => '', 'ignore_restrictions' => false );

		if ( isset($args['qualifying_roles']) )
			unset($args['qualifying_roles']);
			
		if ( isset($args['reqd_caps']) )
			unset($args['reqd_caps']);
			
		$args = array_merge( $defaults, (array) $args );
		extract($args);

		if ( ! $qualifying_roles )  // calling function might save a little work or limit to a subset of qualifying roles
			$qualifying_roles = $this->role_defs->qualify_roles($reqd_caps);
		
		if ( ! $this->taxonomies->is_member($taxonomy) )
			return array( '' => array() );
		
		if ( ! is_object($user) ) {
			global $current_user;
			$user = $current_user;
		}
		
		// If the taxonomy does not require objects to have at least one term, there are no strict terms.
		// Therefore, blogrole blending is not per-term and is handled in the calling function rather than here.
		if ( ! $this->taxonomies->member_property($taxonomy, 'requires_term') )
			$use_blog_roles = false;
			
		if ( $override_roles )
			$qualifying_roles = $override_roles;
		
		if ( ! is_array($qualifying_roles) )
			$qualifying_roles = array($qualifying_roles => 1);	

		if ( $alternate_roles )
			$qualifying_roles = array_unique( array_merge($qualifying_roles, $alternate_roles) );
			
		// no need to serialize and md5 the whole user object
		if ( ! empty($user) )
			$args['user'] = $user->ID;

		// try to pull previous result out of memcache
		ksort($qualifying_roles);
		$rolereq_key = md5( serialize($reqd_caps) . serialize( array_keys($qualifying_roles) ) . serialize($args) );
		
		if ( isset($user->qualified_terms[$taxonomy][$rolereq_key]) )
			return $user->qualified_terms[$taxonomy][$rolereq_key];
			
		if ( ! $qualifying_roles )
			return array( '' => array() );

		$all_terms = $this->get_terms($taxonomy, UNFILTERED_RS, COL_ID_RS); // returns term_id, even for WP > 2.3

		if ( ! isset($user->term_roles[$taxonomy]) )
			$user->get_term_roles_daterange($taxonomy);  // returns term_id for categories

		$good_terms = array( '' => array() );
			
		if ( $user->term_roles[$taxonomy] ) {
			foreach ( array_keys($user->term_roles[$taxonomy]) as $date_key ) {
				//narrow down to roles which satisfy this call AND are owned by current user
				if ( $good_terms[$date_key] = array_intersect_key( $user->term_roles[$taxonomy][$date_key], $qualifying_roles ) )
					// flatten from term_roles_terms[role_handle] = array of term_ids
					// to term_roles_terms = array of term_ids
					$good_terms[$date_key] = agp_array_flatten( $good_terms[$date_key] );
			}
		}
	
		if ( $use_blog_roles ) {
			foreach ( array_keys($user->blog_roles) as $date_key ) {	
				$user_blog_roles = array_intersect_key( $user->blog_roles[$date_key], $qualifying_roles );
				
				// Also include user's WP blogrole(s) which correspond to the qualifying RS role(s)
				if ( $wp_qualifying_roles = $this->role_defs->qualify_roles($reqd_caps, 'wp') ) {
					
					if ( $user_blog_roles_wp = array_intersect_key( $user->blog_roles[$date_key], $wp_qualifying_roles ) ) {
					
						// Credit user's qualifying WP blogrole via equivalent RS role(s)
						// so we can also enforce "term restrictions", which are based on RS roles
						$user_blog_roles_via_wp = $this->role_defs->get_contained_roles( array_keys($user_blog_roles_wp), false, 'rs' );
						$user_blog_roles_via_wp = array_intersect_key( $user_blog_roles_via_wp, $qualifying_roles );
						$user_blog_roles = array_merge( $user_blog_roles, $user_blog_roles_via_wp );
					}
				}
				
				if ( $user_blog_roles ) {
					if ( empty($ignore_restrictions) ) {
						// array of term_ids that require the specified role to be assigned via taxonomy or blog role (user blog caps ignored)
						$strict_terms = $this->get_restrictions(TERM_SCOPE_RS, $taxonomy);
					} else
						$strict_terms = array();
					
					foreach ( array_keys($user_blog_roles) as $role_handle ) {
						if ( isset($strict_terms['restrictions'][$role_handle]) && is_array($strict_terms['restrictions'][$role_handle]) )
							$terms_via_this_role = array_diff( $all_terms, array_keys($strict_terms['restrictions'][$role_handle]) );
					
						elseif ( isset($strict_terms['unrestrictions'][$role_handle]) && is_array($strict_terms['unrestrictions'][$role_handle]) )
							$terms_via_this_role = array_intersect( $all_terms, array_keys( $strict_terms['unrestrictions'][$role_handle] ) );
						
						else
							$terms_via_this_role = $all_terms;
							
						if( $good_terms[$date_key] )
							$good_terms[$date_key] = array_merge( $good_terms[$date_key], $terms_via_this_role );
						else
							$good_terms[$date_key] = $terms_via_this_role;
					}
				}
			}
		}

		foreach ( array_keys($good_terms) as $date_key ) {
			if ( $good_terms[$date_key] = array_intersect( $good_terms[$date_key], $all_terms ) )  // prevent orphaned category roles from skewing access
				$good_terms[$date_key] = array_unique( $good_terms[$date_key] );
		
			// if COL_TAXONOMY_ID_RS, return a term_taxonomy_id instead of term_id
			if ( $good_terms[$date_key] && (COL_TAXONOMY_ID_RS == $return_id_type) && taxonomy_exists($taxonomy) ) {
				$all_terms_cols = $this->get_terms( $taxonomy, UNFILTERED_RS );
				$good_tt_ids = array();
				foreach ( $good_terms[$date_key] as $term_id )
					foreach (array_keys($all_terms_cols) as $termkey)
						if ( $all_terms_cols[$termkey]->term_id == $term_id ) {
							$good_tt_ids []= $all_terms_cols[$termkey]->term_taxonomy_id;
							break;
						}
						
				$good_terms[$date_key] = $good_tt_ids;
			}
		}
		
		$user->qualified_terms[$taxonomy][$rolereq_key] = $good_terms;

		return $good_terms;
	}
	
	// account for different contexts of get_terms calls 
	// (Scoped roles can dictate different results for front end, edit page/post, manage categories)
	function get_terms_reqd_caps( $taxonomy, $operation = '', $is_term_admin = false ) {
		if ( awp_ver( '3.0' ) ) {
			require_once( 'reqd_caps_cr.php' );
			return _cr_get_terms_reqd_caps( $taxonomy, $operation, $is_term_admin );
		} else {
			$src_name = $this->taxonomies->member_property( $taxonomy, 'object_source', 'name' );

			require_once( 'reqd_caps-legacy_rs.php' );
			return ScoperReqdCapsLegacy::get_terms_reqd_caps( $src_name );	
		}
	}
	
	function users_who_can($reqd_caps, $cols = COLS_ALL_RS, $object_src_name = '', $object_id = 0, $args = '' ) {
		// if there are not capability requirements, no need to load Users_Interceptor filtering class
		if ( ! $reqd_caps ) {
			if ( COL_ID_RS == $cols )
				$qcols = 'ID';
			elseif ( COLS_ID_DISPLAYNAME_RS == $cols )
				$qcols = "ID, display_name";
			elseif ( COLS_ALL_RS == $cols )
				$qcols = "*";
			else
				$qcols = $cols;
				
			global $wpdb;
				
			$orderby = ( $cols == COL_ID_RS ) ? '' : 'ORDER BY display_name';

			if ( IS_MU_RS && ! scoper_get_option( 'mu_sitewide_groups' ) && ! defined( 'FORCE_ALL_SITE_USERS_RS' ) )
				$qry = "SELECT $qcols FROM $wpdb->users INNER JOIN $wpdb->usermeta AS um ON $wpdb->users.ID = um.user_id AND um.meta_key = '{$wpdb->prefix}capabilities' $orderby";
			else
				$qry = "SELECT $qcols FROM $wpdb->users $orderby";

			if ( COL_ID_RS == $cols )
				return scoper_get_col( $qry );
			else
				return scoper_get_results( $qry );	
			
		} else {
			$defaults = array( 'where' => '', 'orderby' => '', 'disable_memcache' => false, 'group_ids' => '', 'force_refresh' => false, 'force_all_users' => false );
			$args = array_merge( $defaults, (array) $args );
			extract($args);
	
			$cache_flag = "rs_users_who_can";
			$cache_id = md5(serialize($reqd_caps) . $cols . 'src' . $object_src_name . 'id' . $object_id . serialize($args) );
		
			if ( ! $force_refresh ) {
				// if we already have the results cached, no need to load Users_Interceptor filtering class
				$users = wpp_cache_get($cache_id, $cache_flag);
	
				if ( is_array($users) )
					return $users;
			}
			
			$this->init_users_interceptor();
			$users = $this->users_interceptor->users_who_can($reqd_caps, $cols, $object_src_name, $object_id, $args );
		
			wpp_cache_set($cache_id, $users, $cache_flag);
			return $users;
		}
	}
	
	function groups_who_can($reqd_caps, $cols = COLS_ALL_RS, $object_src_name = '', $object_id = 0, $args = '' ) {
		$this->init_users_interceptor();
		return $this->users_interceptor->groups_who_can($reqd_caps, $cols, $object_src_name, $object_id, $args );
	}
	
	function is_front() {
		return ( defined('CURRENT_ACCESS_NAME_RS') && ( 'front' == CURRENT_ACCESS_NAME_RS ) );
	}
	
	// returns array of role names which have the required caps (or their basecap equivalent)
	// AND have been applied to at least one object, for any user or group
	function qualify_object_roles( $reqd_caps, $object_type = '', $user = '', $base_caps_only = false ) {
		$roles = array();

		if ( $base_caps_only )
			$reqd_caps = $this->cap_defs->get_base_caps($reqd_caps);

		$roles = $this->role_defs->qualify_roles($reqd_caps, 'rs', $object_type);

		return $this->confirm_object_scope( $roles, $user );
	}

	// $roles[$role_handle] = array
	// returns arr[$role_handle] 
	function confirm_object_scope( $roles, $user = '' ) {
		foreach ( array_keys($roles) as $role_handle ) {
			if ( empty( $this->role_defs->members[$role_handle]->valid_scopes['object'] ) )
				unset( $roles[$role_handle] );
		}
				
		if ( ! $roles )
			return array();
		
		if ( is_object($user) )
			$applied_obj_roles = $this->get_applied_object_roles( $user );
		elseif ( empty($user) ) {
			$applied_obj_roles = $this->get_applied_object_roles( $GLOBALS['current_user'] );
		} else // -1 value passed to indicate check for all users
			$applied_obj_roles = $this->get_applied_object_roles();

		return array_intersect_key( $roles, $applied_obj_roles );	
	}
	
	
	// returns array of role_handles which have been applied to any object
	// if $user arg is supplied, returns only roles applied for that user (or that user's groups) 
	function get_applied_object_roles( $user = '' ) {
		if ( is_object( $user ) ) {
			$cache_flag = 'rs_object-roles';			// v 1.1: changed cache key from "object_roles" to "object-roles" to match new key format for blog, term roles
			$cache = $user->cache_get($cache_flag);

			$limit = '';
			$u_g_clause = $user->get_user_clause('');
			
		} else {
			$cache_flag = 'rs_applied_object-roles';	// v 1.1: changed cache key from "object_roles" to "object-roles" to match new key format for blog, term roles
			$cache_id = 'all';
			$cache = wpp_cache_get($cache_id, $cache_flag);
			
			$u_g_clause = '';
		}
		
		if ( is_array($cache) )
			return $cache;
		
		$role_handles = array();
			
		global $wpdb;
		
		// object roles support date limits, but content date limits (would be redundant and a needless performance hit)
		$duration_clause = scoper_get_duration_clause( '', $wpdb->user2role2object_rs );
		
		if ( $role_names = scoper_get_col("SELECT DISTINCT role_name FROM $wpdb->user2role2object_rs WHERE role_type='rs' AND scope='object' $duration_clause $u_g_clause") ) {
			
			$role_handles = scoper_role_names_to_handles($role_names, 'rs', true); //arg: return role keys as array key
			
			//$role_handles = array_intersect_key($role_handles, $this->members);
		}
		
		if ( is_object($user) ) {
			$user->cache_force_set($role_handles, $cache_flag);
		} else
			wpp_cache_force_set($cache_id, $role_handles, $cache_flag);
		
		return $role_handles;
	}
	
	function user_can_edit_blogwide( $src_name = '', $object_type = '', $args = '' ) {
		if ( is_administrator_rs($src_name) )
			return true;
	
		require_once( 'admin/permission_lib_rs.php' );
		return user_can_edit_blogwide_rs($src_name, $object_type, $args);
	}
} // end Scoper class

// (needed to stop using shared core library function with Revisionary due to changes in meta_flag handling)
if ( ! function_exists('awp_user_can') ) {
function awp_user_can( $reqd_caps, $object_id = 0, $user_id = 0, $meta_flags = array() ) {	
	return cr_user_can( $reqd_caps, $object_id, $user_id, $meta_flags );
}
}

// equivalent to current_user_can, 
// except it supports array of reqd_caps, supports non-current user, and does not support numeric reqd_caps
function cr_user_can( $reqd_caps, $object_id = 0, $user_id = 0, $meta_flags = array() ) {	
	if ( function_exists('is_super_admin') && is_super_admin() ) 
		return true;
		
	if ( is_content_administrator_rs() )
		return current_user_can( $reqd_caps );

	return _cr_user_can( $reqd_caps, $object_id, $user_id, $meta_flags );
}

?>