<?php
if( basename(__FILE__) == basename($_SERVER['SCRIPT_FILENAME']) )
	die();

require_once('hardway/cache-persistent.php');


// htaccess directive intercepts direct access to uploaded files, converts to WP call with custom args to be caught by subsequent parse_query filter
// parse_query filter will return content only if user can read a containing post/page
if ( ! defined('DISABLE_ATTACHMENT_FILTERING') )
	add_filter('mod_rewrite_rules', 'scoper_mod_rewrite_rules');
	
function scoper_version_check() {
	$ver_change = false;
	
	$ver = get_option('scoper_version');
	
	if ( empty($ver['db_version']) || version_compare( SCOPER_DB_VERSION, $ver['db_version'], '>') ) {
		$ver_change = true;
		
		require_once('db-setup_rs.php');
		scoper_db_setup($ver['db_version']);
	}
	
	// These maintenance operations only apply when a previous version of RS was installed 
	// To force in unusual circumstances, define SCOPER_NEED_ROLE_SYNC
	if ( ! empty($ver['version']) || defined('SCOPER_NEED_ROLE_SYNC') ) {
		
		if ( version_compare( SCOPER_VERSION, $ver['version'], '>') || defined('SCOPER_NEED_ROLE_SYNC') ) {
			$ver_change = true;
			
			if ( function_exists( 'wpp_cache_flush' ) )
				wpp_cache_flush();
				
			// added WP role metagroups in v0.9.9
			if ( ( ! empty($ver['version']) && version_compare( $ver['version'], '0.9.9', '<') ) || defined('SCOPER_NEED_ROLE_SYNC') ) {
				global $wp_roles;
				
				if ( empty($wp_roles) ) {
					// lost interest in pursuing the mystery of why init / set_current_user sometimes fires 
					// once before wp_roles or user_id are set, then again after
					if ( ! defined('SCOPER_NEED_ROLE_SYNC') )
						define ('SCOPER_NEED_ROLE_SYNC', true);  
				} else {
					require_once('admin/admin_lib_rs.php');
					ScoperAdminLib::sync_wproles();
				}
			}
			
			// fixed failure to properly maintain scoper_page_ancestors options in 1.0.0-rc5
			if ( version_compare( $ver['version'], '1.0.0-rc5', '<') ) {
				delete_option('scoper_page_children');
				delete_option('scoper_page_ancestors');
			}
	
			// changed default teaser_hide_private otype option to separate entries for posts, pages in v1.0.0-rc4 / 1.0.0
			if ( version_compare( $ver['version'], '1.0.0-rc4', '<') ) {
				$teaser_hide_private = get_option('scoper_teaser_hide_private');
	
				if ( isset($teaser_hide_private['post']) && ! is_array($teaser_hide_private['post']) ) {
					if ( $teaser_hide_private['post'] )
						// despite "for posts and pages" caption, previously this option caused pages to be hidden but posts still teased
						update_option( 'scoper_teaser_hide_private', array( 'post:post' => 0, 'post:page' => 1 ) );
					else
						update_option( 'scoper_teaser_hide_private', array( 'post:post' => 0, 'post:page' => 0 ) );
				}
			}
			
			// htaccess rules modified in v1.0.0-rc9.9303
			if ( version_compare( $ver['version'], '1.0.0-rc9.9303', '<') ) {
				global $wp_rewrite;
				if ( ! empty($wp_rewrite) ) // non-object error in scoper_version_check on some installations
					$wp_rewrite->flush_rules();	
			}
			
			if ( version_compare( $ver['version'], '0.9.15', '<') || defined('SCOPER_FIX_PARENT_RECURSION') ) { // 0.9.15 eliminated ability to set recursive page parents
				require_once('admin/update_rs.php');
				scoper_fix_page_parent_recursion();
			}
			
			if ( version_compare( $ver['version'], '1.0.0-rc2', '>=') ) {
				// In rc2 through rc4, we forced invalid img src attribute for image attachments on servers deemed non-apache
				// note: false === stripos( php_sapi_name(), 'apache' ) was the criteria used by the offending code
				// Need to update all affected post_content to convert attachment_id URL to file URL
				if ( false === stripos( php_sapi_name(), 'apache' ) && ! get_option('scoper_fixed_img_urls') ) {
					global $wpdb, $wp_rewrite;
	
					if ( ! empty($wp_rewrite) ) {
						$blog_url = get_bloginfo('url');
						if ( $results = $wpdb->get_results( "SELECT ID, guid, post_parent FROM $wpdb->posts WHERE post_type = 'attachment' && post_date > '2008-12-7'" ) ) {
							foreach ( $results as $row ) {
								$data = array();
								$data['post_content'] = $wpdb->get_var( "SELECT post_content FROM $wpdb->posts WHERE ID = '$row->post_parent'" );
								
								if ( $row->guid ) {
									$attachment_link_raw = $blog_url . "/?attachment_id={$row->ID}";
									$data['post_content'] = str_replace('src="' . $attachment_link_raw, 'src="' . $row->guid, $data['post_content']);
									
									$attachment_link = get_attachment_link($row->ID);
									$data['post_content'] = str_replace('src="' . $attachment_link, 'src="' . $row->guid, $data['post_content']);
								}
		
								if ( ! empty($data['post_content']) ) {
									$wpdb->update($wpdb->posts, $data, array("ID" => $row->post_parent) );
								}
							}
						}
					
						update_option('scoper_fixed_img_urls', true);
					}
				}
			} // endif last RS version might have corrupted stored img attributes
			
		} // endif RS version has increased since last execution (or the maintenance operations are being forced)
		
	} // endif we have a record of some previous RS version (or the maintenance operations are being forced)
	
	if ( $ver_change ) {
		$ver = array(
			'version' => SCOPER_VERSION, 
			'db_version' => SCOPER_DB_VERSION
		);
		
		update_option( 'scoper_version', $ver );
	}
}

function scoper_activate() {
	// set_current_user may have triggered DB setup already
	global $scoper_db_setup_done;
	if ( empty ($scoper_db_setup_done) ) {
		require_once('db-setup_rs.php');
		scoper_db_setup('');  // TODO: is it safe to call get_option here to pass in last DB version, avoiding unnecessary ALTER TABLE statement?
	}
	
	require_once('admin/admin_lib_rs.php');
	ScoperAdminLib::sync_wproles();
	
	global $wp_rewrite;
	if ( ! empty($wp_rewrite) ) // non-object error in scoper_version_check on some installations
		$wp_rewrite->flush_rules();	
}

function scoper_load_textdomain() {
	if ( defined('SCOPER_TEXTDOMAIN_LOADED') )
		return;

	load_plugin_textdomain('scoper', PLUGINDIR . '/' . SCOPER_FOLDER . '/languages');

	define('SCOPER_TEXTDOMAIN_LOADED', true);
}

function scoper_log_init_action() {
	define ('SCOPER_INIT_ACTION_DONE', true);
	
	if ( is_admin() )
		scoper_load_textdomain();

	elseif ( defined('XMLRPC_REQUEST') )
		require_once('xmlrpc_rs.php');
}

// since sequence of set_current_user and init actions seems unreliable, make sure our current_user is loaded first
function scoper_maybe_init() {
	if ( defined('SCOPER_INIT_ACTION_DONE') )
		scoper_init();
	else
		add_action('init', 'scoper_init', 2);
}

function scoper_init() {
	global $scoper, $scoper_default_options, $scoper_default_otype_options;
	
	// these were set with pre-init hardcoded defaults in role-scoper.php startup code
	$scoper_default_options = apply_filters( 'default_options_rs' , $scoper_default_options );
	$scoper_default_otype_options = apply_filters( 'default_otype_options_rs' , $scoper_default_otype_options );

	// For 'options' and 'realm' admin panels, handle updated options right after current_user load (and before scoper init).
	// By then, check_admin_referer is available, but Scoper config and WP admin menu has not been loaded yet.
	if ( ! empty($_POST) 
	&& ( isset($_POST['rs_submit']) || isset($_POST['rs_defaults']) || isset($_POST['rs_flush_cache']) ) )
		scoper_handle_submission();

	require_once('role-scoper_main.php');

	if ( empty($scoper) )		// set_current_user may have already triggered scoper creation and role_cap load
		$scoper = new Scoper();

	$scoper->init();
}

function scoper_deactivate() {
	if ( function_exists( 'wpp_cache_flush' ) )
		wpp_cache_flush();
	
	delete_option('scoper_page_children');
	delete_option('scoper_page_ancestors');
	
	global $wp_taxonomies;
	if ( ! empty($wp_taxonomies) ) {
		foreach ( array_keys($wp_taxonomies) as $taxonomy ) {
			delete_option("{$taxonomy}_children");
			delete_option("{$taxonomy}_children_rs");
		}
	}
	
	global $wp_rewrite;
	remove_filter('mod_rewrite_rules', 'scoper_mod_rewrite_rules');
	if ( ! empty($wp_rewrite) ) // non-object error in scoper_version_check on some installations
		$wp_rewrite->flush_rules();	
}

// called by Extension plugins if data_rs table is required
function scoper_db_setup_data_table() {
	require_once('db-setup_rs.php');
	return scoper_update_supplemental_schema('data_rs');
}

function scoper_use_posted_init_options() {
	if ( ! isset( $_POST['role_type'] ) || ! strpos( $_SERVER['REQUEST_URI'], SCOPER_FOLDER ) || defined('SCOPER_ROLE_TYPE') )
		return;
	
	if ( isset( $_POST['rs_defaults'] ) ) {
		$arr = scoper_default_options();
		
		// arr['role_type'] is numeric input index on update, string value on defaults.
		$posted_role_type = $arr['role_type'];
	} else {
		$arr = $_POST;
		
		global $scoper_role_types;
		$posted_role_type = $scoper_role_types[ $arr['role_type'] ];
	}
	
	define ( 'SCOPER_ROLE_TYPE', $posted_role_type);
	define ( 'SCOPER_CUSTOM_USER_BLOGCAPS', ! empty( $arr['custom_user_blogcaps'] ) );
	
	define ( 'DEFINE_GROUPS_RS', ! empty($arr['define_usergroups']) );
	define ( 'GROUP_ROLES_RS', ! empty($arr['define_usergroups']) && ! empty($arr['enable_group_roles']) );
	define ( 'USER_ROLES_RS', ! empty($arr['enable_user_roles']) );
	
	if ( empty ($arr['persistent_cache']) && ! defined('DISABLE_PERSISTENT_CACHE') )
		define ( 'DISABLE_PERSISTENT_CACHE', true );

	wpp_cache_init();
}

function scoper_handle_submission() {
	require_once('submittee_rs.php');	
	$handler = new Scoper_Submittee();
	
	if ( isset($_POST['rs_submit']) )
		$handler->handle_submission('update');
	elseif ( isset($_POST['rs_defaults']) )
		$handler->handle_submission('default');
	elseif ( isset($_POST['rs_flush_cache']) )
		$handler->handle_submission('flush');
}

function scoper_get_init_options() {
	define ( 'SCOPER_ROLE_TYPE', scoper_get_option('role_type') );
	define ( 'SCOPER_CUSTOM_USER_BLOGCAPS', scoper_get_option('custom_user_blogcaps') );
	
	$define_groups = scoper_get_option('define_usergroups');
	define ( 'DEFINE_GROUPS_RS', $define_groups );
	define ( 'GROUP_ROLES_RS', $define_groups && scoper_get_option('enable_group_roles') );
	define ( 'USER_ROLES_RS', scoper_get_option('enable_user_roles') );
	
	if ( ! defined('DISABLE_PERSISTENT_CACHE') && ! scoper_get_option('persistent_cache') )
		define ( 'DISABLE_PERSISTENT_CACHE', true );
	
	wpp_cache_init();
}

function scoper_refresh_options() {
	global $scoper_options;
	$scoper_options = array();
}

function scoper_retrieve_options() {
	global $wpdb, $scoper_options;
	
	$scoper_options = array();
	
	if ( $results = scoper_get_results("SELECT * FROM $wpdb->options WHERE option_name LIKE 'scoper_%'") )
		foreach ( $results as $row )
			$scoper_options[$row->option_name] = $row->option_value;

	return apply_filters( 'options_rs', $scoper_options );
}

function scoper_get_option($option_name) {
	global $scoper_options;
	
	if ( empty($scoper_options) )
		$scoper_options = scoper_retrieve_options();

	if ( isset($scoper_options["scoper_$option_name"]) )
		$optval = $scoper_options["scoper_$option_name"];
	else {
		global $scoper_default_options;

		if ( isset($scoper_default_options[$option_name]) )
			$optval = $scoper_default_options[$option_name];
		else
			$optval = '';
	}
	
	return maybe_unserialize($optval);
}

function scoper_get_otype_option( $option_main_key, $src_name, $object_type = '', $access_name = '')  {
	static $otype_options;

	$key = "$option_main_key,$src_name,$object_type,$access_name";

	if ( empty($otype_options) )
		$otype_options = array();
	elseif ( isset($otype_options[$key]) )
		return $otype_options[$key];

	global $scoper_default_otype_options;
	
	$stored_option = scoper_get_option($option_main_key);

	$optval = awp_blend_option_array( 'scoper_', $option_main_key, $scoper_default_otype_options, 1, $stored_option );

	// note: access_name-specific entries are not valid for most otype options (but possibly for teaser text front vs. rss)
	if ( isset ( $optval[$src_name] ) )
		$retval = $optval[$src_name];
	
	if ( $object_type && isset( $optval["$src_name:$object_type"] ) )
		$retval = $optval["$src_name:$object_type"];
	
	if ( $object_type && $access_name && isset( $optval["$src_name:$object_type:$access_name"] ) )
		$retval = $optval["$src_name:$object_type:$access_name"];
	

	// if no match was found for a source request, accept any non-empty otype match
	if ( ! $object_type && ! isset($retval) )
		foreach ( $optval as $src_otype => $val )
			if ( $val && ( 0 === strpos( $src_otype, "$src_name:" ) ) )
				$retval = $val;

	if ( ! isset($retval) )
		$retval = array();
		
	$otype_options[$key] = $retval;
	return $retval;
}

function scoper_get_role_handle($role_name, $role_type) {
	return $role_type . '_' . str_replace(' ', '_', $role_name);
}

function scoper_role_names_to_handles($role_names, $role_type, $fill_keys = false) {
	if ( ! is_array($role_names) )
		$role_names = array($role_names);	

	$role_handles = array();
	foreach ( $role_names as $role_name )
		if ( $fill_keys )
			$role_handles[ $role_type . '_' . str_replace(' ', '_', $role_name) ] = 1;
		else
			$role_handles[]= $role_type . '_' . str_replace(' ', '_', $role_name);
			
	return $role_handles;
}

function scoper_explode_role_handle($role_handle) {
	global $scoper_role_types;
	$arr = (object) array();
	
	foreach ( $scoper_role_types as $role_type ) {
		if ( 0 === strpos($role_handle, $role_type . '_') ) {
			$arr->role_type = $role_type;
			$arr->role_name = substr($role_handle, strlen($role_type) + 1);
			break;
		}
	}
	
	return $arr;
}

function scoper_role_handles_to_names($role_handles) {
	global $scoper_role_types;

	$role_names = array();
	foreach ( $role_handles as $role_handle ) {
		foreach ( $scoper_role_types as $role_type )
			$role_handle = str_replace( $role_type . '_', '', $role_handle);
			
		$role_names[] = $role_handle;
	}
	
	return $role_names;
}

function rs_notice($message) {
	awp_notice( $message, 'Role Scoper' );
}

// htaccess directive intercepts direct access to uploaded files, converts to WP call with custom args to be caught by subsequent parse_query filter
// parse_query filter will return content only if user can read a containing post/page
function scoper_mod_rewrite_rules ( $rules ) {
	$site_url = untrailingslashit( get_option('siteurl') );

	// the WP .htaccess file can only run interference for files in the WP directory branch
	if ( empty($site_url) || ! defined('WP_UPLOAD_URL_RS') || ! WP_UPLOAD_URL_RS || ( false === strpos(WP_UPLOAD_URL_RS, $site_url) ) )
		return $rules;

	$home_root = parse_url(get_option('home'));
	$home_root = trailingslashit( $home_root['path'] );
	
	if ( $uploads_subdir = str_replace( "$site_url/", '', WP_UPLOAD_URL_RS ) ) {

		$new_rule = "RewriteEngine on\n";
		
		// workaround for HTTP Authentication with PHP running as CGI
		$new_rule .= "RewriteCond %{HTTP:Authorization} ^(.*)\n";
		$new_rule .= "RewriteRule ^(.*) - [E=HTTP_AUTHORIZATION:%1]\n";

		// The main attachment rewrite rule:
		//RewriteRule ^(.*)wp-content/uploads/(.*) /wp/index.php?attachment=$2&scoper_rewrite=1 [NC,L]
		$new_rule .= "RewriteCond %{HTTP_REFERER} !^{$site_url}/wp-admin\n";
		$new_rule .= "RewriteRule ^(.*)$uploads_subdir/(.*) {$home_root}index.php?attachment=$2&scoper_rewrite=1 [NC,L]\n";

		if ( $pos_endif = strpos( $rules, '</IfModule>' ) )
			$rules = substr( $rules, 0, $pos_endif ) . $new_rule . substr($rules, $pos_endif);
		else
			$rules .= $new_rule;
	}
	
	return $rules;
}

// db wrapper methods allow us to easily avoid re-filtering our own query
function scoper_db_method($method_name, $query) {
	global $wpdb;
	
	if ( is_admin() ) { // Low-level query filtering is necessary due to WP API limitations pertaining to admin GUI.
						// But make sure we don't chew our own cud (currently not an issue for front end)
		global $scoper_status;
	
		if ( empty($scoper_status) )
			$scoper_status = (object) array();
		
		$scoper_status->querying_db = true;
		$results = call_user_func( array(&$wpdb, $method_name), $query );
		$scoper_status->querying_db = false;
		
		return $results;
	} else
		return call_user_func( array(&$wpdb, $method_name), $query );
}

function scoper_get_results($query) {
	return scoper_db_method('get_results', $query);
}

function scoper_get_row($query) {
	return scoper_db_method('get_row', $query);
}

function scoper_get_col($query) {
	return scoper_db_method('get_col', $query);
}

function scoper_get_var($query) {
	return scoper_db_method('get_var', $query);
}

function scoper_query($query) {
	return scoper_db_method('query', $query);
}

function scoper_querying_db() {
	global $scoper_status;
	if ( isset($scoper_status) )
		return ! empty($scoper_status->querying_db);
}


function scoper_buffer_property( $arr, $id_prop, $buffer_prop ) {
	if ( ! is_array($arr) )
		return;

	$buffer = array();
		
	foreach ( array_keys($arr) as $key )
		$buffer[ $arr[$key]->$id_prop ] = $arr[$key]->$buffer_prop;

	return $buffer;
}

function scoper_restore_property( &$target_arr, $buffer_arr, $id_prop, $buffer_prop ) {
	if ( ! is_array($target_arr) || ! is_array($buffer_arr) )
		return;
		
	foreach ( array_keys($target_arr) as $key )
		if ( isset( $buffer_arr[ $target_arr[$key]->$id_prop ] ) )
			$target_arr[$key]->$buffer_prop = $buffer_arr[ $target_arr[$key]->$id_prop ];
}
?>