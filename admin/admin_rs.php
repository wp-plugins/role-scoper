<?php
// menu icons by Jonas Rask: http://www.jonasraskdesign.com/
if( basename(__FILE__) == basename($_SERVER['SCRIPT_FILENAME']) )
	die();

require_once( 'admin_lib_rs.php' );

require_once( 'admin_ui_lib_rs.php' );

define ('SCOPER_URLPATH', WP_CONTENT_URL . '/plugins/' . SCOPER_FOLDER);

define ('ROLE_ASSIGNMENT_RS', 'role_assignment');
define ('ROLE_RESTRICTION_RS', 'role_restriction');

define ('REMOVE_ASSIGNMENT_RS', '');
define ('ASSIGN_FOR_ENTITY_RS', 'entity');
define ('ASSIGN_FOR_CHILDREN_RS', 'children');
define ('ASSIGN_FOR_BOTH_RS', 'both');

class ScoperAdmin
{
	var $role_assigner;	//object reference
	var $scoper;

	function ScoperAdmin() {
		global $scoper;
		$this->scoper =& $scoper;
		
		add_action('admin_head', array(&$this, 'admin_head_base'));

		if ( ! defined('DISABLE_QUERYFILTERS_RS') || is_administrator_rs() ) {
			add_action('admin_head', array(&$this, 'admin_head'));
			
			if ( ! defined('XMLRPC_REQUEST') && ! strpos($_SERVER['SCRIPT_NAME'], 'p-admin/async-upload.php' ) ) {
				add_action('admin_menu', array(&$this,'build_menu'));
				
				if ( strpos($_SERVER['SCRIPT_NAME'], 'p-admin/plugins.php') )
					add_filter( 'plugin_action_links', array(&$this, 'flt_plugin_action_links'), 10, 2 );
				
				//if ( ! awp_ver('2.7') ) {
					add_filter('ozh_adminmenu_menu', array(&$this, 'ozh_adminmenu_hack') );
					add_filter('ozh_adminmenu_altmenu', array(&$this, 'ozh_altmenu_hack') );
				//}
			}
		}

		if ( ( strpos($_SERVER['SCRIPT_NAME'], 'p-admin/categories.php') || ( isset($_SERVER['HTTP_REFERER']) && strpos($_SERVER['HTTP_REFERER'], 'p-admin/categories.php') ) ) && awp_is_plugin_active('subscribe2') )
			require_once('subscribe2_helper_rs.php');
	}

	// adds an Options link next to Deactivate, Edit in Plugins listing
	function flt_plugin_action_links($links, $file) {
		if ( $file == SCOPER_BASENAME )
			$links[] = "<a href='admin.php?page=" . SCOPER_FOLDER . "/admin/options.php'><b>" . __('Options', 'scoper') . "</b></a>";

		return $links;
	}
	
	function admin_head_base() {
		if ( ! defined('DISABLE_ATTACHMENT_FILTERING') ) {
			if ( isset( $_POST['rs_defaults'] ) ) {
				// User asked to restore default options, and attachment filtering is not manually disabled,
				// so restore htaccess rule for attachment filtering
				global $wp_rewrite;
				if ( ! empty($wp_rewrite) ) // non-object error in scoper_version_check on some installations
					$wp_rewrite->flush_rules();	
			}
		}
	}
	
	function admin_head() {
		echo '<link rel="stylesheet" href="' . SCOPER_URLPATH . '/admin/role-scoper.css" type="text/css" />'."\n";
		
		echo "\n" . "<script type='text/javascript' src='" . SCOPER_URLPATH . "/admin/agapetry.js'></script>";
		echo "\n" . "<script type='text/javascript' src='" . SCOPER_URLPATH . "/admin/role-scoper.js'></script>";

		if ( false !== strpos($_SERVER['REQUEST_URI'], SCOPER_FOLDER . '/admin/options.php') ) {
			if ( scoper_get_option('version_update_notice') ) {
				require_once('misc/version_notice_rs.php');
				scoper_new_version_notice();
			}

		} elseif ( false !== strpos($_SERVER['REQUEST_URI'], SCOPER_FOLDER . '/admin/about.php') ) {
			echo '<link rel="stylesheet" href="' . SCOPER_URLPATH . '/admin/about/about.css" type="text/css" />'."\n";
		}
				
		// dynamically set checkbox titles for user/group object role selection
		if ( isset($_GET['src_name']) && isset($_GET['object_type']) ) {
			$src_name = $_GET['src_name'];
			$object_type = $_GET['object_type'];
			$src = $this->scoper->data_sources->get($src_name);
			$otype_def = $this->scoper->data_sources->member_property($src_name, 'object_types', $object_type);
		} else {
			$context = $this->get_context();
			if ( ! empty($context->source) && ! empty($context->object_type_def) ) {
				$src = $context->source;
				$otype_def = $context->object_type_def;
			}
		}
		
		if ( ! empty($src) && ! empty($src->cols->parent) && ! empty($otype_def->ignore_object_hierarchy) ) {
			$obj_title = sprintf( __('assign role for this %s', 'scoper'), strtolower($otype_def->display_name) );
			$child_title = sprintf( __('assign role for sub-%s', 'scoper'), strtolower($otype_def->display_name_plural) );
		
			$js_params = "var role_for_object_title = '$obj_title';"
					. "var role_for_children_title = '$child_title';";
	
			echo "\n" . '<script type="text/javascript">' . $js_params . '</script>';
			echo "\n" . "<script type='text/javascript' src='" . SCOPER_URLPATH . "/admin/rs-objrole-cbox-maint.js'></script>";
		}

		add_filter( 'contextual_help_list', array(&$this, 'flt_contextual_help_list'), 10, 2 );
	}
	
	function flt_contextual_help_list ($help, $screen) {
		$link_section = '';
		
		if ( strpos( $screen, 'role-scoper/admin/' ) ) {
			$match = array();
			if ( ! preg_match( "/admin_page_role-scoper\/admin[^@]*/", $screen, $match ) )
				if ( ! preg_match( "/_page_role-scoper\/admin[^@]*/", $screen, $match ) )
					preg_match( "/role-scoper\/admin[^@]*/", $screen, $match );

			if ( $match )
				if ( $pos = strpos( $match[0], 'role-scoper/admin/' ) )
					$link_section = substr( $match[0], $pos + strlen('role-scoper/admin/') );

		} elseif ( ('post' == $screen) || ('page' == $screen) ) {
			$link_section = $screen;
		}

		if ( $link_section ) {
			$link_section = str_replace( '.php', '', $link_section);
			$link_section = str_replace( '/', '~', $link_section);
			
			if ( ! isset($help[$screen]) )
				$help[$screen] = '';
			
			$help[$screen] .= sprintf(__('%1$s Role Scoper Documentation%2$s', 'scoper'), "<a href='http://agapetry.net/downloads/RoleScoper_UsageGuide.htm#$link_section' target='_blank'>", '</a>')
			. ', ' . sprintf(__('%1$s Role Scoper Support Forum%2$s', 'scoper'), "<a href='http://agapetry.net/forum/' target='_blank'>", '</a>');
		}

		return $help;
	}
			
	function build_menu() {
		if ( ! defined('USER_ROLES_RS') && isset( $_POST['role_type'] ) )
			scoper_use_posted_init_options();
	
		global $current_user;
		
		$is_administrator = is_administrator_rs();
		
		/*
		// optional hack to prevent roles / restrictions menu for non-Administrators
		//
		// This is now handled as a Role Scoper Option.
		// On the Realm tab, Access Types section: deselect "editing and administering content"
		//
		// end optional hack
		*/

		$can_manage_options = $is_administrator || current_user_can('manage_options');
		$can_edit_users = $is_administrator || current_user_can('edit_users');

		$can_admin_objects = array();
		$can_admin_terms = array();
		
		$require_blogwide_editor = scoper_get_option('role_admin_blogwide_editor_only');
		
		// which object types does this user have any administration over?
		foreach ( $this->scoper->data_sources->get_all() as $src_name => $src ) {
			if ( ! empty($src->no_object_roles) || ! empty($src->taxonomy_only) || ('group' == $src_name) )
				continue;
			
			$object_types = ( isset($src->object_types) ) ? $src->object_types : array( $src_name => true );

			foreach ( array_keys($object_types) as $object_type ) {
				if ( is_administrator_rs($src) 
				|| ( $this->user_can_admin_object($src_name, $object_type, 0, true) && ( ! $require_blogwide_editor || $this->user_can_edit_blogwide($src_name, $object_type) ) ) )
					if ( scoper_get_otype_option('use_object_roles', "$src_name:$object_type") )
						$can_admin_objects[$src_name][$object_type] = true;
			}
		}
		
		// which taxonomies does this user have any administration over?
		foreach ( $this->scoper->taxonomies->get_all() as $taxonomy => $tx ) {
			if ( is_administrator_rs($tx->source) || $this->user_can_admin_terms($taxonomy) )
				if ( scoper_get_otype_option('use_term_roles', $tx->object_source->name) )
					$can_admin_terms[$taxonomy] = true;
		}
		
		/*
		global $_wp_menu_nopriv;
		if ( ! empty($can_admin_objects['post']['post']) || ! empty($can_admin_terms['category']) )
			if ( isset($_wp_menu_nopriv['edit.php']) )
				unset($_wp_menu_nopriv['edit.php']);
				
		if ( ! empty($can_admin_objects['post']['page']) )
			if ( isset($_wp_menu_nopriv['edit-pages.php']) )
				unset($_wp_menu_nopriv['edit-pages.php']);
		
		dump($_wp_menu_nopriv);
		*/
		
		$can_manage_groups = DEFINE_GROUPS_RS && ( $is_administrator || current_user_can('manage_groups') );
		
		// Users Tab
		if ( DEFINE_GROUPS_RS && $can_manage_groups ) {
			$rs_blogroles = scoper_get_option('rs_blog_roles');
			$cap_req = ( $can_edit_users ) ? 'read' : 'manage_groups';

			add_submenu_page('users.php', __('User Groups', 'scoper'), __('Groups', 'scoper'), $cap_req, SCOPER_FOLDER . '/admin/groups.php');
		}


		// the rest of this function pertains to Roles and Restrictions menus
		if ( ! $is_administrator && ! $can_manage_options && ! $can_admin_terms && ! $can_edit_users && ! $can_admin_objects )
			return;
	
		$general_roles = ('rs' == SCOPER_ROLE_TYPE) && $can_edit_users && scoper_get_option('rs_blog_roles');
		
		// determine the official WP-registered URL for roles and restrictions menus
		$object_submenus_first = false;
		if ( $is_administrator ) {
			$roles_menu = SCOPER_FOLDER . '/admin/options.php';
			$restrictions_menu = SCOPER_FOLDER . '/admin/restrictions/category';
		} else {
			if ( ! empty($can_admin_terms['category']) ) {
				$roles_menu = SCOPER_FOLDER . '/admin/roles/category';
				$restrictions_menu = SCOPER_FOLDER . '/admin/restrictions/category';

			} elseif ( ! empty($can_admin_objects['post']['post']) ) {
				$roles_menu = SCOPER_FOLDER . '/admin/roles/post/post';
				$restrictions_menu = SCOPER_FOLDER . '/admin/restrictions/post/post';
				$object_submenus_first = true;
				
			} elseif ( ! empty($can_admin_objects['post']['page']) ) {
				$roles_menu = SCOPER_FOLDER . '/admin/roles/post/page';
				$restrictions_menu = SCOPER_FOLDER . '/admin/restrictions/post/page';
				$object_submenus_first = true;

			} elseif ( $can_admin_terms ) {
				$taxonomy = key($can_admin_terms);
				$roles_menu = SCOPER_FOLDER . "/admin/roles/$taxonomy";
				$restrictions_menu = SCOPER_FOLDER . "/admin/restrictions/$taxonomy";

			} elseif ( $can_admin_objects ) {
				$src_name = key($can_admin_objects);
				$object_type = key($can_admin_objects[$src_name]);
				$roles_menu = SCOPER_FOLDER . "/admin/roles/$src_name/$object_type";
				$restrictions_menu = SCOPER_FOLDER . "/admin/restrictions/$src_name/$object_type";
				$object_submenus_first = true;
			} else {
				// shouldn't ever need this
				$roles_menu = SCOPER_FOLDER . '/admin/roles/post/post';
				$restrictions_menu = SCOPER_FOLDER . '/admin/restrictions/post/post';
				$object_submenus_first = true;
			}

			if ( $general_roles )
				$roles_menu = SCOPER_FOLDER . '/admin/general_roles.php';
		}

		$roles_link = $roles_menu;
		$restrictions_link = $restrictions_menu;
		
		$uri = $_SERVER['SCRIPT_NAME'];

		// For convenience in WP 2.5 - 2.6, set the primary menu link (i.e. default submenu) based on current URI
		// When viewing Category Roles, make Restriction menu default-link to Category Restrictions submenu (and likewise for other terms/objects)
		// (ozh plugin breaks this, and it is not needed in 2.7+ due to core JS dropdown)
		if ( ! awp_ver('2.7-dev') && ! awp_is_plugin_active('wp_ozh_adminmenu.php') ) { 
			if ( strpos($uri, 'role-scoper/admin/restrictions/') )
				$roles_link = str_replace('/admin/restrictions/', '/admin/roles/', $uri);
			
			elseif ( strpos($uri, 'role-scoper/admin/roles/') )
				$restrictions_link = str_replace('/admin/roles/', '/admin/restrictions/', $uri);

			elseif ( ! empty($can_admin_objects['post']['post']) && ( strpos($uri, 'p-admin/edit.php') || strpos($uri, 'p-admin/post.php') || strpos($uri, 'p-admin/post-new.php') ) ) {
				$roles_link = SCOPER_ADMIN_URL . '/roles/post/post';
				$restrictions_link = SCOPER_ADMIN_URL . '/restrictions/post/post';
			
			} elseif ( ! empty($can_admin_objects['post']['page']) && ( strpos($uri, 'p-admin/edit-pages.php') || strpos($uri, 'p-admin/page.php') || strpos($uri, 'p-admin/page-new.php') ) ) {
				$roles_link = SCOPER_ADMIN_URL . '/roles/post/page';
				$restrictions_link = SCOPER_ADMIN_URL . '/restrictions/post/page';
			
			} elseif ( ! empty($can_admin_terms['category']) && strpos($uri, 'p-admin/categories.php') ) {
				$roles_link = SCOPER_ADMIN_URL . '/roles/category';
				$restrictions_link = SCOPER_ADMIN_URL . '/restrictions/category';
			
			} elseif ( ! empty($can_admin_terms['link_category']) && strpos($uri, 'p-admin/edit-link-categories.php') ) {
				$roles_link = SCOPER_ADMIN_URL . '/roles/link_category';
				$restrictions_link = SCOPER_ADMIN_URL . '/restrictions/link_category';
			}
		}
		
		if ( $is_administrator ) {
			global $_registered_pages;
			 $_registered_pages['admin_page_role-scoper/admin/options'] = SCOPER_FOLDER . "/admin/options.php";
		}
		
		// Register the menus with WP using URI and links determined above
		global $menu;
		$tweak_menu = false; // don't mess with menu order unless we know we can get away with it in current WP version
		
		if ( awp_ver('2.8-dev') ) {
			if ( ! awp_ver('2.9') ) { // review and increment this with each WP version until there's a clean way to force menu proximity to 'Users'
				$tweak_menu = true;
				$restrictions_menu_key = 71;
				$roles_menu_key = 72;
			}
		} elseif ( awp_ver('2.7-dev') && empty($menu[51]) && empty($menu[52]) ) {
			$tweak_menu = true;
			$restrictions_menu_key = 51;
			$roles_menu_key = 52;
	
		} elseif ( empty($menu[37]) && empty($menu[38]) && empty($menu[39]) ) {
			$tweak_menu = true;
			$restrictions_menu_key = 38;
			$roles_menu_key = 39;
			
			$menu[37] = $menu[40];	//move Users menu so we can put Roles, Restrictions after it
			unset($menu[40]);
		}

		//$roles_cap = ( $roles_link == $roles_menu ) ? 'manage_options' : 'read';
		$roles_cap = 'read'; // TODO: review this 
		$restrictions_caption = __('Restrictions', 'scoper');
		$roles_caption = __('Roles', 'scoper');
		if ( $tweak_menu ) {
			// note: as of WP 2.7-near-beta, custom content dir for menu icon is not supported
			$menu[$restrictions_menu_key] = array( '0' => $restrictions_caption, 'read', $restrictions_link, __('Role Restrictions', 'scoper'), 'menu-top' );
			$menu[$restrictions_menu_key][6] = '../wp-content/plugins/' . SCOPER_FOLDER . '/admin/images/menu/restrictions.png';
			$menu[$roles_menu_key] = array( '0' => $roles_caption, $roles_cap, $roles_link, __('Role Scoper Options', 'scoper'), 'menu-top' );
			$menu[$roles_menu_key][6] = '../wp-content/plugins/' . SCOPER_FOLDER . '/admin/images/menu/roles.png';
		} else {
			add_menu_page($restrictions_caption, __('Restrictions', 'scoper'), 'read', $restrictions_link, '', '../wp-content/plugins/' . SCOPER_FOLDER . '/admin/images/menu/restrictions.png' );
			add_menu_page($roles_caption, __('Roles', 'scoper'), $roles_cap, $roles_link, '', '../wp-content/plugins/' . SCOPER_FOLDER . '/admin/images/menu/roles.png');
		}

		global $submenu;

		
		if ( $general_roles )
			add_submenu_page($roles_menu, __('General Roles', 'scoper'), __('General', 'scoper'), 'read', SCOPER_FOLDER . '/admin/general_roles.php');

		$path = SCOPER_ABSPATH;
		
		$require_blogwide_editor = scoper_get_option('role_admin_blogwide_editor_only');
		
		$submenu_types = ( $object_submenus_first ) ? array( 'object', 'term' ) : array( 'term', 'object' );
		foreach ( $submenu_types as $scope ) {
			if ( 'term' == $scope ) {
				// Term Roles and Restrictions (will only display objects user can edit)
				if ( $can_admin_terms ) {
					// Will only allow assignment to terms for which current user has admin cap
					// Term Roles page also prevents assignment or removal of roles current user doesn't have
					foreach ( $this->scoper->taxonomies->get_all() as $taxonomy => $tx ) {
						if ( empty($can_admin_terms[$taxonomy]) )
							continue;
						
						if ( $require_blogwide_editor ) {
							global $current_user;
							if ( empty( $current_user->allcaps['edit_others_posts'] ) && empty( $current_user->allcaps['edit_others_pages'] ) )
								continue;
						}
							
						$show_roles_menu = true;
					
						$func = "include_once('$path' . '/admin/section_roles.php');scoper_admin_section_roles('$taxonomy');";
						add_action("admin_page_role-scoper/admin/roles/$taxonomy", create_function( '', $func ) );	
						
						if ( ! awp_ver('2.6') )
							add_action("_page_role-scoper/admin/roles/$taxonomy", create_function( '', $func ) );	
						
						add_submenu_page($roles_menu, sprintf(__('%s Roles', 'scoper'), $tx->display_name), $tx->display_name_plural, 'read', SCOPER_FOLDER . "/admin/roles/$taxonomy");
					
						if ( ! empty($tx->requires_term) ) {
							$show_restrictions_menu = true;
						
							$func = "include_once('$path' . '/admin/section_restrictions.php');scoper_admin_section_restrictions('$taxonomy');";
							add_action("admin_page_role-scoper/admin/restrictions/$taxonomy", create_function( '', $func ) );	
						
							if ( ! awp_ver('2.6') )
								add_action("_page_role-scoper/admin/restrictions/$taxonomy", create_function( '', $func ) );	
							
							add_submenu_page($restrictions_menu, sprintf(__('%s restrictions', 'scoper'), $tx->display_name), $tx->display_name_plural, 'read', SCOPER_FOLDER . "/admin/restrictions/$taxonomy");
						}
					} // end foreach taxonomy
				} // endif can admin terms
			
			} else {
				// Object Roles (will only display objects user can edit)
				if ( $can_admin_objects ) {
					foreach ( $this->scoper->data_sources->get_all() as $src_name => $src ) {
						if ( ! empty($src->no_object_roles) || ! empty($src->taxonomy_only) || ('group' == $src_name) )
							continue;
						
						$object_types = ( isset($src->object_types) ) ? $src->object_types : array( $src_name => true );
		
						foreach ( array_keys($object_types) as $object_type ) {
							if ( empty($can_admin_objects[$src_name][$object_type]) )
								continue;
		
							if ( $require_blogwide_editor ) {
								$required_cap = ( 'page' == $object_type ) ? 'edit_others_pages' : 'edit_others_posts';
								
								global $current_user;
								if ( empty( $current_user->allcaps[$required_cap] ) )
									continue;
							}
								
							$show_roles_menu = true;
							$show_restrictions_menu = true;
						
							$func = "include_once('$path' . '/admin/object_roles.php');scoper_admin_object_roles('$src_name', '$object_type');";
							add_action("admin_page_role-scoper/admin/roles/$src_name/$object_type", create_function( '', $func ) );
						
							if ( ! awp_ver('2.6') )
								add_action("_page_role-scoper/admin/roles/$src_name/$object_type", create_function( '', $func ) );
							
							$func = "include_once('$path' . '/admin/object_restrictions.php');scoper_admin_object_restrictions('$src_name', '$object_type');";
							add_action("admin_page_role-scoper/admin/restrictions/$src_name/$object_type", create_function( '', $func ) );	
					
							if ( ! awp_ver('2.6') )
								add_action("_page_role-scoper/admin/restrictions/$src_name/$object_type", create_function( '', $func ) );	
							
							$src_otype = ( isset($src->object_types) ) ? "{$src_name}:{$object_type}" : $src_name;
							$display_name = $this->interpret_src_otype($src_otype, false);
							$display_name_plural = $this->interpret_src_otype($src_otype, true);
		
							add_submenu_page($roles_menu, sprintf(__('%s Roles', 'scoper'), $display_name), $display_name_plural, 'read', SCOPER_FOLDER . "/admin/roles/$src_name/$object_type");
		
							add_submenu_page($restrictions_menu, sprintf(__('%s restrictions', 'scoper'), $display_name), $display_name_plural, 'read', SCOPER_FOLDER . "/admin/restrictions/$src_name/$object_type");
						} // end foreach obj type
					} // end foreach data source
				} // endif can admin objects
			} // endif drawing object scope submenus
		} // end foreach submenu scope
		
		if ( $is_administrator )
			add_submenu_page($roles_menu, __('About Role Scoper', 'scoper'), __('About', 'scoper'), 'read', SCOPER_FOLDER . "/admin/about.php");
		
		// Change Role Scoper Options submenu title from default "Roles" to "Options"
		if ( $is_administrator ) {
			global $submenu;
			
			if ( isset($submenu[$roles_menu][0][2]) && ( $roles_menu == $submenu[$roles_menu][0][2] ) )
				$submenu[$roles_menu][0][0] = __('Options', 'scoper');

		} elseif ( empty($show_restrictions_menu) || empty($show_roles_menu) ) {
			// Remove Roles or Restrictions menu if it has no submenu
			if ( $tweak_menu ) {
				if ( empty($show_restrictions_menu) && isset($menu[$restrictions_menu_key]) )
					unset($menu[$restrictions_menu_key]);
					
				if ( empty($show_roles_menu) && isset($menu[$roles_menu_key]) )
					unset($menu[$roles_menu_key]);
				
			} else {
				global $menu;
				foreach ( array_keys($menu) as $key ) {
					if ( isset( $menu[$key][0]) )
						if ( empty($show_roles_menu) && ( $roles_caption == $menu[$key][0] ) )
							unset($menu[$key]);
						elseif ( empty($show_restrictions_menu) && ( $restrictions_caption == $menu[$key][0] ) )
							unset($menu[$key]);
				}
			}
		}
		
		
		// workaround for WP 2.7's universal inclusion of "Add New" in Posts, Pages menu
		if ( awp_ver('2.7') ) {
			if ( isset($submenu['edit-pages.php']) ) {
				foreach ( $submenu['edit-pages.php'] as $key => $arr ) {
					if ( isset($arr['2']) && ( 'page-new.php' == $arr['2'] ) ) {
						$this->scoper->cap_interceptor->skip_id_generation = true;
						$this->scoper->cap_interceptor->skip_any_object_check = true;	
	
						if ( ! current_user_can('edit_pages') )
							unset( $submenu['edit-pages.php'][$key]);
							
						$this->scoper->cap_interceptor->skip_id_generation = false;
						$this->scoper->cap_interceptor->skip_any_object_check = false;
					}
				}
			}
			
			if ( isset($submenu['edit.php']) ) {
				foreach ( $submenu['edit.php'] as $key => $arr ) {
					if ( isset($arr['2']) && ( 'post-new.php' == $arr['2'] ) ) {
						$this->scoper->cap_interceptor->skip_id_generation = true;
						$this->scoper->cap_interceptor->skip_any_object_check = true;	
	
						if ( ! current_user_can('edit_posts') )
							unset( $submenu['edit.php'][$key]);
							
						$this->scoper->cap_interceptor->skip_id_generation = false;
						$this->scoper->cap_interceptor->skip_any_object_check = false;
					}
				}
			}
		}
	}

	
	function interpret_src_otype($src_otype, $use_plural_display_name = true) {
		if ( ! $arr_src_otype = explode(':', $src_otype) )
			return $display_name;
	
		$display_name_prop = ( $use_plural_display_name ) ? 'display_name_plural' : 'display_name';
		
		if ( isset( $arr_src_otype[1]) )
			$display_name = $this->scoper->data_sources->member_property($arr_src_otype[0], 'object_types', $arr_src_otype[1], $display_name_prop);
		else
			$display_name = $this->scoper->data_sources->member_property($arr_src_otype[0], $display_name_prop);
			
		if ( ! $display_name )	// in case of data sources definition error, cryptic fallback better than nullstring
			$display_name = $src_otype;
			
		return $display_name;
	}
	
	function src_name_from_src_otype($src_otype) {
		if ( $arr_src_otype = explode(':', $src_otype) )
			return $arr_src_otype[0];
	}
	
	function display_otypes_or_source_name($src_name) {
		if ( $object_types = $this->scoper->data_sources->member_property($src_name, 'object_types') ) {
			$display_names = array();
			foreach ( $object_types as $object_type)
				$display_names[] = $object_type->display_name_plural;
			$display = implode(', ', $display_names);
		} else {
			$display_name = $this->scoper->data_sources->member_property($src_name, 'display_name_plural');
			$display = sprintf(__("%s data source", 'scoper'), $display_name);
		}
		
		return $display;
	}
	
	function user_can_admin_role($role_handle, $item_id, $src_name = '', $object_type = '', $user = '' ) {
		if ( is_administrator_rs() )
			return true;

		static $role_ops;

		if ( ! isset($role_ops) )
			$role_ops = array();
		
		if ( ! isset($role_ops[$role_handle]) )
			$role_ops[$role_handle] = $this->scoper->role_defs->get_role_ops($role_handle);

		// user can't view or edit role assignments unless they have all rolecaps
		// however, if this is a new post, allow read role to be assigned even if contributor doesn't have read_private cap blog-wide
		if ( $item_id || ( $role_ops[$role_handle] != array( 'read' => 1 ) ) ) {
			static $require_blogwide_edit;
			static $can_edit_blogwide;
			static $reqd_caps;
			
			if ( ! isset($require_blogwide_edit) )
				$require_blogwide_edit = scoper_get_option('role_admin_blogwide_editor_only');
			
			if ( ! isset($can_edit_blogwide) )
				$can_edit_blogwide = array();
				
			if ( ! isset($can_edit_blogwide[$src_name][$object_type]) )
				$can_edit_blogwide[$src_name][$object_type] = $this->user_can_edit_blogwide($src_name, $object_type, OP_EDIT_RS);

			if ( ! isset($reqd_caps) )
				$reqd_caps = array();
				
			if ( ! isset($reqd_caps[$role_handle]) )
				$reqd_caps[$role_handle] = $this->scoper->role_defs->role_caps[$role_handle];

			if ( ! awp_user_can(array_keys($reqd_caps[$role_handle]), $item_id) )
				return false;

			// a user must have a blog-wide edit cap to modify editing role assignments (even if they have Editor role assigned for some current object)
			if ( isset($role_ops[$role_handle][OP_EDIT_RS]) || isset($role_ops[$role_handle][OP_ASSOCIATE_RS]) )
				if ( $require_blogwide_edit && ! $can_edit_blogwide[$src_name][$object_type] )
					return false;
		}
		
		return true;
	}
	
	function user_can_admin_object($src_name, $object_type, $object_id = false, $any_obj_role_check = false, $user = '') {
		if ( ! is_object($user) ) {
			global $current_user;
			$user = $current_user;
		}

		if ( ! empty($user->is_administrator) )
			return true;
		
		if ( $new_object = ! $object_id && ( false !== $object_id ) ) {
			//for new objects, default to requiring caps for 1st defuned status (=published for posts)
			$src = $this->scoper->data_sources->get($src_name);
			reset ($src->statuses);
			$status_name = key($src->statuses);
		} else {
			$status_name = $this->scoper->data_sources->detect('status', $src_name, $object_id);
		}
		
		// insert_role_assignments passes array from get_role_attributes
		if ( is_array($object_type) ) {
			if ( count($object_type) == 1 )
				$object_type = reset($object_type);
			else
				// only WP roles should ever have multiple sources / otypes
				$object_type = $this->scoper->data_sources->get_from_db('type', $src_name, $object_id);
		}

		if ( ! $new_object && isset($src->reqd_caps[OP_ADMIN_RS][$object_type][$status_name]) )
			$reqd_caps = $src->reqd_caps[OP_ADMIN_RS][$object_type][$status_name];
		else {
			$base_caps_only = $new_object;
			$admin_caps = $this->scoper->cap_defs->get_matching($src_name, $object_type, OP_ADMIN_RS, $status_name, $base_caps_only);
			$delete_caps = $this->scoper->cap_defs->get_matching($src_name, $object_type, OP_DELETE_RS, $status_name, $base_caps_only);
			$reqd_caps = array_merge( array_keys($admin_caps), array_keys($delete_caps) );
		}
		
		if ( ! $reqd_caps )
			return true;	// apparantly this src/otype has no admin caps, so no restriction to apply
			
		// pass this parameter the ugly way because I'm afraid to include it in user_has_cap args array
		// Normally we want to disregard "others" cap requirements if a role is assigned directly for an object
		// This is an exception - we need to retain a "delete_others" cap requirement in case it is the
		// distinguishing cap of an object administrator
		
		$this->scoper->cap_interceptor->require_full_object_role = true;
		$return = awp_user_can($reqd_caps, $object_id);
		$this->scoper->cap_interceptor->require_full_object_role = false;
		
		if ( ! $return && ! $object_id && $any_obj_role_check ) {
			$admin_caps = $this->scoper->cap_defs->get_matching($src_name, $object_type, OP_ADMIN_RS, STATUS_ANY_RS);
			$delete_caps = $this->scoper->cap_defs->get_matching($src_name, $object_type, OP_DELETE_RS, STATUS_ANY_RS);
			
			if ( $reqd_caps = array_merge( array_keys($admin_caps), array_keys($delete_caps) ) ) {
				if ( ! defined('DISABLE_QUERYFILTERS_RS') && $this->scoper->cap_interceptor->user_can_for_any_object( $reqd_caps ) )
					$return = true;
			}
		}
		
		return $return;
	}
	
	function user_can_admin_terms($taxonomy = '', $term_id = '', $user = '') {
		if ( ! is_object($user) ) {
			global $current_user;
			$user = $current_user;
		}
		
		if ( ! empty($user->is_administrator) )
			return true;
		
		$qualifying_caps = array();
		
		$taxonomies = array();
		foreach ( $this->scoper->cap_defs->get_all() as $cap_name => $capdef )
			if ( (OP_ADMIN_RS == $capdef->op_type) && $this->scoper->taxonomies->is_member($capdef->object_type) ) {
				if ( ! $taxonomy || ( $capdef->object_type == $taxonomy ) ) {
					$qualifying_caps[$cap_name] = 1;
					$taxonomies[$capdef->object_type] = 1;
				}
			}

		if ( empty($qualifying_caps) )
			return false;

		// does current user have any blog-wide admin caps for term admin?
		$qualifying_roles = $this->scoper->role_defs->qualify_roles(array_flip($qualifying_caps), SCOPER_ROLE_TYPE);
		
		if ( $user_blog_roles = array_intersect_key($user->blog_roles, $qualifying_roles) ) {
			if ( $term_id ) {
				$strict_terms = $this->scoper->get_restrictions(TERM_SCOPE_RS, $taxonomy);
			
				foreach ( array_keys($user_blog_roles) as $role_handle ) {
					// can't blend in blog role if term requires term role assignment
					if ( isset($strict_terms['unrestrictions'][$role_handle][$term_id])
					|| ( ! is_array($strict_terms['unrestrictions'][$role_handle]) && ! isset($strict_terms['restrictions'][$role_handle][$term_id]) ) )
						return true;
				}
			} else {
				// todo: more precision by checking whether ANY terms are non-strict for the qualifying role(s)
				return true;
			}
		}
		
		// does current user have any term-specific admin caps for term admin?
		if ( $taxonomies ) {
			foreach ( array_keys($taxonomies) as $taxonomy ) {
				if ( ! isset($current_user->term_roles[$taxonomy]) )
					$user->get_term_roles($taxonomy);
				
				if ( ! empty($user->term_roles[$taxonomy]) ) {
					foreach ( array_keys($user->term_roles[$taxonomy]) as $role_handle ) {
						if ( ! empty($this->scoper->role_defs->role_caps[$role_handle]) ) {
							if ( array_intersect_key($qualifying_caps, $this->scoper->role_defs->role_caps[$role_handle]) ) {
								if ( ! $term_id || in_array($term_id, $user->term_roles[$taxonomy][$role_handle]) )
									return true;
							}
						}
					}
				}
			}
		} // endif any taxonomies have cap defined
	} // end function
	
	
	function user_can_edit_blogwide( $src_name = '', $object_type = '', $qualifying_ops = '' ) {
		global $current_user;
		
		if ( is_administrator_rs($src_name) )
			return true;
		
		if ( empty($qualifying_ops) )
			$qualifying_ops = array( 'delete', 'admin' );

		if ( ! is_array($qualifying_ops) )
			$qualifying_ops = (array) $qualifying_ops;
		
		//  i.e. if user has blog-wide edit_posts, they can see admin divs in Page Edit form based on a page role assignment. )
		foreach ( array_keys($current_user->blog_roles) as $role_handle ) {
			if ( $role_ops = $this->scoper->role_defs->get_role_ops($role_handle) ) {
				//if ( isset($role_ops[$required_op]) ) {
				if ( array_intersect( array_keys($role_ops), $qualifying_ops ) ) {
					if ( ! $src_name && ! $object_type )
						return true;
					else {
						$role_attribs = $this->scoper->role_defs->get_role_attributes($role_handle);
						if ( in_array($src_name, $role_attribs->src_names) && ( ! $object_type || in_array($object_type, $role_attribs->object_types) ) )
							return true;
					}
				}
			}
		}
	}
	
	// primary use is to account for different contexts of users query
	function get_context($src_name = '', $reqd_caps_only = false) {
		$full_uri = $_SERVER['REQUEST_URI'];
		$matched = array();
		
		foreach ( $this->scoper->data_sources->get_all_keys() as $_src_name ) {
			if ( $src_name)
				$_src_name = $src_name;  // if a src_name arg was passed in, short-circuit the loop
			
			if ( $arr = $this->scoper->data_sources->member_property($_src_name, 'users_where_reqd_caps', CURRENT_ACCESS_NAME_RS) ) {

				foreach ( $arr as $uri_sub => $reqd_caps ) {	// if no uri substrings match, use default (nullstring key), but only if data source was passed in
					if ( ( $uri_sub && strpos($full_uri, $uri_sub) )
					|| ( $src_name && ! $uri_sub && ! $matched ) ) {
						$matched['reqd_caps'] = $reqd_caps;
						
						if ( ! $reqd_caps_only )
							$matched['source'] = $this->scoper->data_sources->get($_src_name);
						
						if ( $uri_sub) break;
					}
				}
			}
			
			if ( $matched || $src_name) // if a src_name arg was passed in, short-circuit the loop
				break;
		} // data sources loop
		
		if ( $matched && ! $reqd_caps_only ) {
			if ( isset($matched['source']->object_types) ) {
				// if this data source has more than one object type defined, 
				// use the reqd_caps to determine object type for this context
				if ( count($matched['source']->object_types) > 1 ) {
					$src_otypes = $this->scoper->cap_defs->object_types_from_caps($matched['reqd_caps']);
					if ( isset($src_otypes[$_src_name]) && (count($src_otypes[$_src_name]) == 1) ) {
						reset($src_otypes[$_src_name]);
						$matched['object_type_def'] = $matched['source']->object_types[ key($src_otypes[$_src_name]) ];
					}
				} else
					$matched['object_type_def'] = reset( $matched['source']->object_types );
			}
		}
	
		return (object) $matched;
	}
	
	// only used for WP < 2.7
	function ozh_altmenu_hack($altmenu) {
		// not sure why ozh adds extra page argument to these URLs:
		$bad_string = '?page=' . get_option('siteurl') . 'p-admin/admin.php?page=';
		
		foreach ( array_keys($altmenu) as $key )
			if ( isset($altmenu[$key]['url']) && strpos( $altmenu[$key]['url'], $bad_string ) )
				$altmenu[$key]['url'] = str_replace( $bad_string, '?page=', $altmenu[$key]['url'] );

		return $altmenu;
	}
	
	// only used for WP < 2.7
	function ozh_adminmenu_hack($menu) {
		if ( current_user_can('edit_posts') ) {
			$menu[5][0] = __("Write");
			$menu[5][1] = "edit_posts";
			$menu[5][2] = "post-new.php";
			$menu[10][0] = __("Manage");
			$menu[10][1] = "edit_posts";
			$menu[10][2] = "edit.php";
		} else {
			$menu[5][0] = __("Write");
			$menu[5][1] = "edit_pages";
			$menu[5][2] = ( strpos( $_SERVER['SCRIPT_NAME'], 'p-admin/edit-pages.php') ) ? "post-new.php" : "page-new.php";  //don't ask.
			$menu[10][0] = __("Manage");
			$menu[10][1] = "edit_pages";
			$menu[10][2] = "edit-pages.php";
		}

		return $menu;
	}
	
	
} // end class ScoperAdmin
?>