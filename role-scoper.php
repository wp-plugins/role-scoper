<?php
/*
Plugin Name: Role Scoper
Plugin URI: http://agapetry.net/
Description: CMS-like permissions for reading and editing. Content-specific restrictions and roles supplement/override WordPress roles. User groups optional.
Version: 1.0.5
Author: Kevin Behrens
Author URI: http://agapetry.net/
Min WP Version: 2.5
License: GPL version 2 - http://www.opensource.org/licenses/gpl-license.php
*/

/*
Copyright (c) 2009, Kevin Behrens.

This program is free software; you can redistribute it and/or
modify it under the terms of the GNU General Public License
version 2 as published by the Free Software Foundation.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
*/

if( basename(__FILE__) == basename($_SERVER['SCRIPT_FILENAME']) )
	die( 'This page cannot be called directly.' );

define ('SCOPER_VERSION', '1.0.5');
define ('SCOPER_DB_VERSION', '1.0.2');

define( 'ENABLE_PERSISTENT_CACHE', true );

/* --- ATTACHMENT FILTERING NOTE ---
Read access to uploaded file attachments is normally filtered to match post/page access.
To disable this attachment filtering:
(1) Copy the following line to wp-config.php
	define('DISABLE_ATTACHMENT_FILTERING', true);

(2) If running on an Apache server, remove the following line from the .htaccess file in your WP folder:
	RewriteRule ^(.*)wp-content/uploads/(.*) http://YOUR_WP_URL/index.php?attachment=$2&scoper_rewrite=1 [NC]
	
To reinstate attachment filtering, remove the definition from wp-config.php and deactivate/reactivate Role Scoper.

To fail with a null response (no WP 404 screen, but still includes a 404 in response header), copy the folling line to wp-config.php:
	define ('SCOPER_QUIET_FILE_404', true);

Normally, files which are in the uploads directory but have no post/page attachment will not be blocked.
To block such files, copy the following line to wp-config.php:
	define('SCOPER_BLOCK_UNATTACHED_UPLOADS', true);
	
The Hidden Content Teaser may be configured to display the first X characters of a post/page if no excerpt or more tag is available.
To specify the number of characters (default is 50), copy the following line to wp-config.php:
	define('SCOPER_TEASER_NUM_CHARS', 100); // set to any number of your choice
*/

// define URL
define ('SCOPER_BASENAME', plugin_basename(__FILE__) );
define ('SCOPER_FOLDER', dirname( plugin_basename(__FILE__) ) );

if ( ! defined('WP_CONTENT_URL') )
	define( 'WP_CONTENT_URL', get_option('siteurl') . '/wp-content');
	
if ( ! defined('WP_CONTENT_DIR') )
	define( 'WP_CONTENT_DIR', str_replace('\\', '/', ABSPATH) . 'wp-content' );

// Set wp_upload_dir output as constants to avoid redundant calls or conflicting values within the same session
// These will be used in mod_rewrite rule and parse_query filter to handle direct access requests for uploaded files
$upload_info = wp_upload_dir();

if ( empty($upload_info['error']) && ! empty($upload_info['basedir']) && ! empty($upload_info['baseurl']) ) {
	define( 'WP_UPLOAD_DIR_RS', $upload_info['basedir'] ); // RS suffix in case another plugin set WP_UPLOAD_DIR directly without regard to wp_upload_dir() and the 'upload_dir' filter
	define( 'WP_UPLOAD_URL_RS', $upload_info['baseurl'] );
} else {
	// if something went wrong with wp_upload_dir() call, assume default uploads directory
	define( 'WP_UPLOAD_DIR_RS', WP_CONTENT_DIR . '/uploads' );
	define( 'WP_UPLOAD_URL_RS', WP_CONTENT_URL . '/uploads' );
}

define ('SCOPER_ABSPATH', WP_CONTENT_DIR . '/plugins/' . SCOPER_FOLDER);
define ('SCOPER_ADMIN_URL', 'admin.php?page=' . SCOPER_FOLDER . '/admin');

define ('ANON_ROLEHANDLE_RS', 'wp_public_reader');

define ('BLOG_SCOPE_RS', 'blog');
define ('TERM_SCOPE_RS', 'term');
define ('OBJECT_SCOPE_RS', 'object');

define ('OP_READ_RS', 'read');
define ('OP_ASSOCIATE_RS', 'associate');
define ('OP_EDIT_RS', 'edit');
define ('OP_PUBLISH_RS', 'publish');
define ('OP_DELETE_RS', 'delete');
define ('OP_ADMIN_RS', 'admin');

define ('ROLE_BASIS_GROUPS', 'groups');
define ('ROLE_BASIS_USER', 'user');
define ('ROLE_BASIS_USER_AND_GROUPS', 'ug');

global $scoper_role_types;
$scoper_role_types = array('rs', 'wp', 'wp_cap');

define ('COLS_ALL_RS', 0);
define ('COL_ID_RS', 1);
define ('COLS_ID_DISPLAYNAME_RS', 2);
define ('COL_TAXONOMY_ID_RS', 3);
define ('COL_COUNT_RS', 4);

define ('UNFILTERED_RS', 0);
define ('FILTERED_RS', 1);
define ('ADMIN_TERMS_FILTER_RS', 2);

define ('BASE_CAPS_RS', 1);

define ('STATUS_ANY_RS', -1);

define ('ORDERBY_NAME_RS', 'name');
define ('ORDERBY_COUNT_RS', 'count');
define ('ORDERBY_HIERARCHY_RS', 'hierarchy');


if ( defined('RS_DEBUG') )
	include_once('lib/debug.php');
else
	include_once('lib/debug_shell.php');

//if ( version_compare( phpversion(), '5.2', '<' ) )	// some servers (Ubuntu) return irregular version string format
if ( ! function_exists("array_fill_keys") )
	require_once('lib/php4support_rs.php');

require_once('db-config_rs.php');
require_once('role-scoper_init.php');	// Contains activate, deactivate, init functions. Adds mod_rewrite_rules.

require_once('lib/agapetry_lib.php');
require_once('lib/agapetry_wp_lib.php');

if ( is_admin() || defined('XMLRPC_REQUEST') ) {
	require_once('lib/agapetry_wp_admin_lib.php');

	// Early bailout for problematic 3rd party plugin ajax calls
	if ( strpos($_SERVER['SCRIPT_NAME'], 'wp-wall-ajax.php') )
		return;
		
	// skip WP version check and init operations when a WP plugin auto-update is in progress
	if ( false !== strpos($_SERVER['SCRIPT_NAME'], 'update.php') ) {
		register_activation_hook(__FILE__, 'scoper_activate');
		register_deactivation_hook(__FILE__, 'scoper_deactivate');
		return;
	}
} else 
	require_once('feed-interceptor_rs.php'); // must define get_currentuserinfo early

if ( defined('RS_DEBUG') )
	add_action( 'admin_footer', 'awp_echo_usage_message' );
	
$bail = 0;

if ( ! awp_ver('2.5') ) {
	rs_notice('Sorry, this version of Role Scoper requires WordPress 2.5.0 or higher.  Please upgrade Wordpress or deactivate Role Scoper.  If you must run WP 2.2 or 2.3, try <a href="http://agapetry.net/downloads/role-scoper_legacy">Role Scoper 0.9</a>.');
	$bail = 1;
}

// If someone else plugs set_current_user, we're going to take our marbles and go home - but first make sure they can't play either.
// set_current_user() is a crucial entry point to instantiate extended class WP_Scoped_User and set it as global current_user.
// There's no way to know that another set_current_user replacement will retain the set_current_user hook.
if ( ( function_exists('wp_set_current_user') || function_exists('set_current_user') ) && ! function_exists('scoped_user_testfunc') ) {  //if is_administrator_rs exists, then these functions scoped_user.php somehow already executed (and plugged set_current_user) 
	
	define( 'SCOPER_BAILED', true );

	// this is the normal situation on first pass after activation
	if ( ! strpos($_SERVER['SCRIPT_NAME'], 'p-admin/plugins.php') || ( function_exists('is_plugin_active') && is_plugin_active(SCOPER_FOLDER . '/' . SCOPER_BASENAME) ) ) {
		rs_notice('Role Scoper cannot operate because another plugin or theme has already declared the function "set_current_user" or "wp_set_current_user".  All posts, pages and links are currently hidden.  <br />Please remove the offending plugin, or deactivate Role Scoper to revert to blog-wide Wordpress roles.');
	}
	
	// To prevent inadverant content exposure, default to blocking all content if another plugin steals wp_set_current_user definition.
	if ( ! strpos($_SERVER['SCRIPT_NAME'], 'p-admin/plugins.php') ) {
		add_filter('posts_where', create_function('$a', "return 'AND 1=2';"), 99);
		add_filter('posts_results', create_function('$a', "return array();"), 1);
		add_filter('get_pages', create_function('$a', "return array();"), 99);
		add_filter('get_bookmarks', create_function('$a', "return array();"), 99);
		add_filter('get_categories', create_function('$a', "return array();"), 99);
		add_filter('get_terms', create_function('$a', "return array();"), 99);
		
		// Also run interference for all custom-defined where_hook, request_filter or results_filter
		require_once('role-scoper_main.php');
		
		global $scoper, $wpdb;
		$scoper = new Scoper();
		$scoper->load_config();
		
		foreach( $scoper->data_sources->get_all() as $src ) {
			if ( ! empty($src->query_hooks->request) )
				add_filter($src->query_hooks->request, create_function('$a', "return 'SELECT * FROM $wpdb->posts WHERE 1=2';"), 99);
		
			if ( ! empty($src->query_hooks->where) )
				add_filter($src->query_hooks->where, create_function('$a', "return 'AND 1=2';"), 99);
		
			if ( ! empty($src->query_hooks->results) )
				add_filter($src->query_hooks->results, create_function('$a', "return array();"), 1);
		}
	}
	
	$bail = 1;
}

if ( ! $bail ) {
	require_once('defaults_rs.php');
	
	global $scoper_default_options;
	$scoper_default_options = scoper_default_options();

	global $scoper_default_otype_options;
	$scoper_default_otype_options = scoper_default_otype_options();

	// if role options were just updated via http POST, use new values rather than loading old option values from DB
	// These option values are used in WP_Scoped_User constructor
	if ( isset( $_POST['role_type'] ) && strpos( $_SERVER['REQUEST_URI'], SCOPER_FOLDER )  ) {
		scoper_use_posted_init_options();
	} else
		scoper_get_init_options();
	
	// WP_Scoped_User needs this prior to normal options loading / defaults filtering
	define ( 'RS_BLOG_ROLES', scoper_get_option('rs_blog_roles') );
	
	require_once('user-plug_rs.php');

	// since sequence of set_current_user and init actions seems unreliable, make sure our current_user is loaded first
	add_action('set_current_user', 'scoper_maybe_init', 2);
	add_action('init', 'scoper_log_init_action', 1);
}

register_activation_hook(__FILE__, 'scoper_activate');
register_deactivation_hook(__FILE__, 'scoper_deactivate');
?>