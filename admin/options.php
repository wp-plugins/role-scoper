<?php
if( basename(__FILE__) == basename($_SERVER['SCRIPT_FILENAME']) )
	die( 'This page cannot be called directly.' );

if ( ! current_user_can('manage_options') && ! current_user_can('activate_plugins') )
	wp_die(__('Cheatin&#8217; uh?'));

if ( isset($_POST['all_otype_options']) ) {
	wpp_cache_flush();

	global $wp_rewrite;
	if ( ! empty($wp_rewrite) )
		$wp_rewrite->flush_rules();	

	if ( isset($_POST['rs_role_resync']) )
		ScoperAdminLib::sync_wproles();
			
	if ( isset($_POST['rs_defaults']) )
		$msg = __('Role Scoper options were reset to defaults.', 'scoper');
		
	elseif ( isset($_POST['rs_flush_cache']) )
		$msg = __('The persistent cache was flushed.', 'scoper');
	else
		$msg = __('Role Scoper options were updated.', 'scoper');

	// submittee_rs.php fielded this submission, but output the message here.
	echo '<div id="message" class="updated fade"><p>';
	echo $msg;
	echo '</p></div>';
}

global $scoper;

define('SCOPER_REALM_ADMIN_RS', 1);  // We need to access all config items here, even if they are normally removed due to disabling
$scoper->load_config();

// scoper_default_otype_options is hookable for other plugins to add pertinent items for their data sources
global $scoper_default_otype_options;
$def_otype_options = $scoper_default_otype_options;

$all_options = array();
$all_otype_options = array();

$display_hints = scoper_get_option('display_hints');

echo '<form action="" method="post">';
wp_nonce_field( 'scoper-update-options' ); 
?>
<div class='wrap'>
<table width = "100%"><tr>
<td width = "90%">
<h2><?php _e('Role Scoper Options', 'scoper') ?></h2>
</td>
<td>
<div class="submit" style="border:none;float:right;margin:0;">
<input type="submit" name="rs_submit" value="<?php _e('Update &raquo;', 'scoper');?>" />
</div>
</td>
</tr></table>
<?php
$class_selected = 'agp-selected_agent_colorized agp-selected_agent agp-agent';
$class_unselected = 'agp-unselected_agent_colorized agp-unselected_agent agp-agent';

// todo: prevent line breaks in these links
$js_call = "agp_swap_display('rs-features', 'rs-realm', 'rs_show_features', 'rs_show_realm', '$class_selected', '$class_unselected');";
$js_call .= "agp_swap_display('', 'rs-roledefs', '', 'rs_show_roledefs', '$class_selected', '$class_unselected');";
$js_call .= "agp_swap_display('', 'rs-advanced', '', 'rs_show_advanced', '$class_selected', '$class_unselected');";
$js_call .= "agp_swap_display('', 'wp-roledefs', '', 'wp_show_roledefs', '$class_selected', '$class_unselected');";
echo "<ul class='rs-list_horiz' style='margin-bottom:-0.1em'>"
	. "<li class='$class_selected'>"
	. "<a id='rs_show_features' href='javascript:void(0)' onclick=\"$js_call\">" . __('Features', 'scoper') . '</a>'
	. '</li>';
	
$js_call = "agp_swap_display('rs-advanced', 'rs-features', 'rs_show_advanced', 'rs_show_features', '$class_selected', '$class_unselected');";
$js_call .= "agp_swap_display('', 'rs-realm', '', 'rs_show_realm', '$class_selected', '$class_unselected');";
$js_call .= "agp_swap_display('', 'rs-roledefs', '', 'rs_show_roledefs', '$class_selected', '$class_unselected');";
$js_call .= "agp_swap_display('', 'wp-roledefs', '', 'wp_show_roledefs', '$class_selected', '$class_unselected');";
echo "<li class='$class_unselected'>"
	. "<a id='rs_show_advanced' href='javascript:void(0)' onclick=\"$js_call\">" . __('Advanced', 'scoper') . '</a>'
	. '</li>';
	
$js_call = "agp_swap_display('rs-realm', 'rs-features', 'rs_show_realm', 'rs_show_features', '$class_selected', '$class_unselected');";
$js_call .= "agp_swap_display('', 'rs-roledefs', '', 'rs_show_roledefs', '$class_selected', '$class_unselected');";
$js_call .= "agp_swap_display('', 'rs-advanced', '', 'rs_show_advanced', '$class_selected', '$class_unselected');";
$js_call .= "agp_swap_display('', 'wp-roledefs', '', 'wp_show_roledefs', '$class_selected', '$class_unselected');";
echo "<li class='$class_unselected'>"
	. "<a id='rs_show_realm' href='javascript:void(0)' onclick=\"$js_call\">" . __('Realm', 'scoper') . '</a>'
	. '</li>';

if ( 'rs' == SCOPER_ROLE_TYPE ) {
	$js_call = "agp_swap_display('rs-roledefs', 'rs-features', 'rs_show_roledefs', 'rs_show_features', '$class_selected', '$class_unselected');";
	$js_call .= "agp_swap_display('', 'rs-realm', '', 'rs_show_realm', '$class_selected', '$class_unselected');";
	$js_call .= "agp_swap_display('', 'rs-advanced', '', 'rs_show_advanced', '$class_selected', '$class_unselected');";
	$js_call .= "agp_swap_display('', 'wp-roledefs', '', 'wp_show_roledefs', '$class_selected', '$class_unselected');";
	echo "<li class='$class_unselected'>"
		. "<a id='rs_show_roledefs' href='javascript:void(0)' onclick=\"$js_call\">" . __('RS Role Definitions', 'scoper') . '</a>'
		. '</li>';
}

$js_call = "agp_swap_display('wp-roledefs', 'rs-features', 'wp_show_roledefs', 'rs_show_features', '$class_selected', '$class_unselected');";
$js_call .= "agp_swap_display('', 'rs-realm', '', 'rs_show_realm', '$class_selected', '$class_unselected');";
$js_call .= "agp_swap_display('', 'rs-advanced', '', 'rs_show_advanced', '$class_selected', '$class_unselected');";
$js_call .= "agp_swap_display('', 'rs-roledefs', '', 'rs_show_roledefs', '$class_selected', '$class_unselected');";
echo "<li class='$class_unselected'>"
	. "<a id='wp_show_roledefs' href='javascript:void(0)' onclick=\"$js_call\">" . __('WP Role Definitions', 'scoper') . '</a>'
	. '</li></ul>';

// ------------------------- BEGIN Features tab ---------------------------------

echo "<div id='rs-features' style='clear:both;margin:0' class='rs-options'>";
	
if ( scoper_get_option('display_hints') ) {
	echo '<div class="rs-optionhint">';
	_e("This page enables <b>optional</b> adjustment of Role Scoper's features. For most installations, the default settings are fine.", 'scoper');
	echo '</div>';
}

$table_class = 'form-table rs-form-table';
?>

<table class="<?php echo($table_class);?>" id="rs-admin_table">

<?php 
								// --- FRONT END SECTION ---
$all_options []= 'strip_private_caption';?>
<tr valign="top">
<th scope="row"><?php _e('Front End', 'scoper') ?></th>
<td>
<label for="strip_private_caption">
<input name="strip_private_caption" type="checkbox" id="strip_private_caption" value="1" <?php checked('1', scoper_get_option('strip_private_caption'));?> />
<?php _e('Suppress "Private:" Caption', 'scoper') ?></label><br />
<span class="rs-subtext">
<?php if ( $display_hints) _e('Remove the "Private:" and "Protected" prefix from Post, Page titles', 'scoper');?>
</span>
<br /><br />

<?php $all_options []= 'no_frontend_admin'; ?>
<label for="no_frontend_admin">
<input name="no_frontend_admin" type="checkbox" id="no_frontend_admin" value="1" <?php checked('1', scoper_get_option('no_frontend_admin'));?> />
<?php _e('Assume No Front-end Admin', 'scoper') ?></label><br />
<span class="rs-subtext">
<?php if ( $display_hints) _e('Reduce memory usage for front-end access by assuming no content, categories or users will be created or edited there. Worst case scenario if you assume wrong: manually assign roles/restrictions to new content or re-sync user roles via plugin re-activation.', 'scoper');?>
</span>
</td></tr>



<tr valign="top">
<th scope="row"><?php 		// --- PAGES LISTING SECTION ---
_e('Pages Listing', 'scoper') ?></th>
<td>

<?php
	$option_name = "private_items_listable";
	$all_otype_options []= $option_name;
	if ( isset($def_otype_options[$option_name]) ) {
		if ( ! $opt_vals = get_option( 'scoper_' . $option_name ) )
			$opt_vals = array();
			
		$opt_vals = array_merge($def_otype_options[$option_name], $opt_vals);
		
		foreach ( $opt_vals as $src_otype => $val ) {
			$display_name_plural = $scoper->admin->interpret_src_otype($src_otype, true); //arg: use plural display name
				
			$id = str_replace(':', '_', $option_name . '-' . $src_otype);
?>
<label for="<?php echo($id);?>">
<input name="<?php echo($id);?>" type="checkbox" id="<?php echo($id);?>" value="1" <?php checked('1', $val);?> />
<?php 
			printf( __('Include Private %s in listing if user can read them', 'scoper'), $display_name_plural );
			echo ('</label><br />');
		} // end foreach src_otype
?>
<span class="rs-subtext">
<?php if ( $display_hints) _e('Determines whether administrators, editors and users who have been granted access to a private page will see it in their sidebar or topbar page listing.', 'scoper');?>
</span>
<?php
	} // endif default option isset
?>
<br /><br />

<?php
$all_options []= 'remap_page_parents';
$do_remap = scoper_get_option('remap_page_parents');
$js_call = "agp_display_if('enforce_actual_page_depth_div', 'remap_page_parents');";
echo '<label for="remap_page_parents">';
echo "<input name='remap_page_parents' type='checkbox' onclick=\"$js_call\" id='remap_page_parents' value='1' ";
checked('1', $do_remap);
echo ' /> ';
_e('Remap pages to visible ancestor', 'scoper');
?>
</label><br />
<span class="rs-subtext">
<?php if ( $display_hints) _e('If a page\'s parent is not visible to the user, it will be listed below a visible grandparent instead.', 'scoper');?>
</span>

<?php
$all_options []= 'enforce_actual_page_depth';
$css_display = ( $do_remap ) ? 'block' : 'none';
echo "<div id='enforce_actual_page_depth_div' style='display:$css_display; margin-top: 1em;'>";
?>
<label for="enforce_actual_page_depth">
<input name="enforce_actual_page_depth" type="checkbox" id="enforce_actual_page_depth" value="1" <?php checked('1', scoper_get_option('enforce_actual_page_depth'));?> />
<?php _e('Enforce actual page depth', 'scoper') ?></label><br />
<span class="rs-subtext">
<?php if ( $display_hints) _e('When remapping page parents, apply any depth limits to the actual depth below the requested root page.  If disabled, depth limits apply to apparant depth following remap.', 'scoper');?>
</span>
</div>

</td></tr>



<tr valign="top">
<th scope="row"><?php 		// --- CATEGORIES LISTING SECTION ---
_e('Categories Listing', 'scoper') ?></th>
<td>

<?php
$all_options []= 'remap_term_parents';
$do_remap = scoper_get_option('remap_term_parents');
$js_call = "agp_display_if('enforce_actual_term_depth_div', 'remap_term_parents');";
echo '<label for="remap_term_parents">';
echo "<input name='remap_term_parents' type='checkbox' onclick=\"$js_call\" id='remap_term_parents' value='1' ";
checked('1', $do_remap);
echo ' /> ';
_e('Remap terms to visible ancestor', 'scoper');?>
</label><br />
<span class="rs-subtext">
<?php if ( $display_hints) _e('If a category\'s parent is not visible to the user, it will be listed below a visible grandparent instead.', 'scoper');?>
</span>

<?php
$all_options []= 'enforce_actual_term_depth';
$css_display = ( $do_remap ) ? 'block' : 'none';
echo "<div id='enforce_actual_term_depth_div' style='display:$css_display; margin-top: 1em;'>";
?>
<label for="enforce_actual_term_depth">
<input name="enforce_actual_term_depth" type="checkbox" id="enforce_actual_term_depth" value="1" <?php checked('1', scoper_get_option('enforce_actual_term_depth'));?> />
<?php _e('Enforce actual term depth', 'scoper') ?></label><br />
<span class="rs-subtext">
<?php if ( $display_hints) _e('When remapping category parents, apply any depth limits to the actual depth below the requested root category.  If disabled, depth limits apply to apparant depth following remap.', 'scoper');?>
</span>
</div>

</td></tr>



<tr valign="top">
<th scope="row"><?php 
								// --- CONTENT MAINTENANCE SECTION ---
_e('Content Maintenance', 'scoper') ?></th>

<?php $all_options []= 'pending_revisions';?>
<td>
<div class="agp-vspaced_input">
<label for="pending_revisions">
<input name="pending_revisions" type="checkbox" id="pending_revisions" value="1" <?php checked('1', scoper_get_option('pending_revisions'));?> />
<?php _e('Pending Revisions', 'scoper') ?></label>
<br />
<span class="rs-subtext">
<?php if ( $display_hints) _e('Users with a Contributor role for a published post or page can edit it for review. For Administrators, these revisions are listed alongside regular pending content, but link to the revision editor, where they can be "restored".', 'scoper');?>
</span>
</div>


<br />
<?php 
$id = 'role_admin_blogwide_editor_only';
$all_options []= $id;
$current_setting = strval( scoper_get_option($id) );  // force setting and corresponding keys to string, to avoid quirks with integer keys

?>
<div class="agp-vspaced_input">
<label for="role_admin_blogwide_editor_only">
<?php
_e( 'Roles and Restrictions can be set:', 'scoper' );

$captions = array( '0' => __('by the Author or Editor of any Post/Category/Page', 'scoper'), '1' => __('by blog-wide Editors and Administrators', 'scoper'), 'admin' => __('by Administrators only', 'scoper') );
foreach ( $captions as $key => $value) {
	$key = strval($key);
	echo "<div style='margin: 0 0 0.5em 2em;'><label for='{$id}_{$key}'>";
	$checked = ( $current_setting === $key ) ? "checked='checked'" : '';

	echo "<input name='$id' type='radio' id='{$id}_{$key}' value='$key' $checked />";
	echo $value;
	echo '</label></div>';
}
?>
<span class="rs-subtext">
<?php if ( $display_hints) _e('Specify which users can assign and restrict roles <strong>for their content</strong> - via Post/Page Edit Form or Roles/Restrictions sidebar menu.', 'scoper');?>
</span>
</div>


<br />
<?php $all_options []= 'admin_others_unattached_files';?>
<div class="agp-vspaced_input">
<label for="admin_others_unattached_files">
<input name="admin_others_unattached_files" type="checkbox" id="admin_others_unattached_files" value="1" <?php checked('1', scoper_get_option('admin_others_unattached_files'));?> />
<?php _e('Non-editors see other users\' unattached uploads', 'scoper') ?></label><br />
<span class="rs-subtext">
<?php if ( $display_hints) _e('If enabled, users who are not blog-wide Editors will see only their own unattached uploads in the Media Library.', 'scoper');?>
</span>
</div>


</td>
</tr>


<tr valign="top">
<th scope="row"><?php 
								// --- ATTACHMENTS SECTION ---
								// todo: attacher utility, link to http://wordpress.org/extend/plugins/search-and-replace/
								
_e('Attachments', 'scoper') ?></th>
<td>
<?php
$site_url = untrailingslashit( get_option('siteurl') );
if ( defined('DISABLE_ATTACHMENT_FILTERING') )
	$content_dir_notice = __('<b>Note</b>: Direct access to uploaded file attachments will not be filtered because DISABLE_ATTACHMENT_FILTERING is defined, perhaps in wp-config.php or role-scoper.php', 'scoper');
elseif ( false === strpos(WP_UPLOAD_URL_RS, $site_url) )
	$content_dir_notice = __('<b>Note</b>: Direct access to uploaded file attachments cannot be filtered because your WP_CONTENT_DIR is not in the WordPress branch.', 'scoper');
else {
	global $wp_rewrite;
	if ( empty($wp_rewrite->permalink_structure) )
		$content_dir_notice = __('<b>Note</b>: Direct access to uploaded file attachments cannot be filtered because WordPress permalinks are set to default.', 'scoper');
}

$attachment_filtering = ( ! defined('DISABLE_ATTACHMENT_FILTERING') ) && got_mod_rewrite() && empty($content_dir_notice);

$caption = __('To change setting, edit wp-config.php and WP .htaccess files as described in the ATTACHMENT FILTERING NOTE in role-scoper.php. Note that any echoed PHP warnings (as with WP_DEBUG set) will break filtered image display.', 'scoper');?>
<label for="filter_attachments" title="<?php echo $caption?>">
<input name="filter_attachments" type="checkbox" disabled="disabled" id="filter_attachments" value="1" <?php checked(true, $attachment_filtering );?> />
<?php _e('Filter Uploaded File Attachments', 'scoper') ?></label>
<br />
<div class="rs-subtext">
<?php
if ( $display_hints) 
	_e('Grant read access to images and other uploaded files in the WordPress uploads folder only if the logged user has read access to a containing post/page.', 'scoper');
if ( $attachment_filtering ) {
	if ( $display_hints) {
		
		echo '</div><div class="agp-vspaced_input rs-subtext" style="margin-top: 1em">';
		_e("To disable, add the following line to wp-config.php:<br />&nbsp;&nbsp;&nbsp; define( 'DISABLE_ATTACHMENT_FILTERING', true);<br />Then force an .htaccess regeneration by re-saving your WP permalink settings or de/re-activating Role Scoper.", 'scoper');
	}
} elseif ( ! empty($content_dir_notice) ) {
	echo '<br /><span class="rs-warning">';
	echo $content_dir_notice;
	echo '</span>';
}
?>
</div>
<?php
printf( _c('<strong>Note:</strong> FTP-uploaded files will not be filtered correctly until you run the %1$sAttachments Utility%2$s.|arguments are link open, link close', 'scoper'), "<a href='" . SCOPER_ADMIN_URL . "/attachments_utility.php'>", '</a>');
?>
<br />
</td></tr>


<?php
								// --- PERSISTENT CACHE SECTION ---
$all_options []= 'persistent_cache';

$cache_selected = scoper_get_option('persistent_cache');
$cache_enabled = $cache_selected && defined('ENABLE_PERSISTENT_CACHE') && ! defined('DISABLE_PERSISTENT_CACHE');
?>
<tr valign="top">
<th scope="row"><?php _e('Internal Cache', 'scoper') ?></th>
<td>
<label for="persistent_cache">
<input name="persistent_cache" type="checkbox" id="persistent_cache" value="1" <?php checked(true, $cache_enabled);?> />
<?php _e('Cache roles and groups to disk', 'scoper') ?></label>
<br />
<span class="rs-subtext">
<?php 
if ( $display_hints) _e('Group membership, role restrictions, role assignments and some filtered results (including term listings and WP page, category and bookmark listings) will be stored to disk, on a user-specific or group-specific basis where applicable.  This does not cache content such as post listings or page views.', 'scoper');
echo '</span>';

$cache_msg = '';
if ( $cache_selected && ! wpp_cache_test( $cache_msg, 'scoper' ) ) {
	echo '<div class="agp-vspaced_input"><span class="rs-warning">';
	echo $cache_msg;
	echo '</span></div>';
} elseif ( $cache_enabled && ! file_exists('../rs_cache_flush.php') && ! file_exists('..\rs_cache_flush.php') ) {
	echo '<div class="agp-vspaced_input"><span class="rs-warning">';	
	_e('<strong>Note:</strong> the internal cache may be susceptible to corruption in multi-author installations. For manual or automatic recovery, copy rs_cache_flush.php into your WP root directory and execute directly.', 'scoper');
	echo '</span></div>';
}
?>

<?php if($cache_enabled):?>
<br />
<span class="submit" style="border:none;float:left;margin-top:0">
<input type="submit" name="rs_flush_cache" value="<?php _e('Flush Cache', 'scoper') ?>" />
</span>
<?php endif;?>
</td></tr>


<?php 
								// --- VERSION SECTION ---
$all_options []= 'version_update_notice';?>
<tr valign="top">
<th scope="row"><?php _e('Version', 'scoper') ?></th>
<td>
<?php
printf( __( "Role Scoper Version: %s", 'scoper'), SCOPER_VERSION);
echo '<br />';
printf( __( "Database Schema Version: %s", 'scoper'), SCOPER_DB_VERSION);
echo '<br />';
?>
<label for="version_update_notice">
<input name="version_update_notice" type="checkbox" id="version_update_notice" value="1" <?php checked('1', scoper_get_option('version_update_notice'));?> />
<?php _e('Notify on Version Updates.', 'scoper') ?></label>
</td></tr>


<tr valign="top">
<th scope="row"><?php 
								// --- RSS FEEDS SECTION ---
_e('RSS Feeds', 'scoper') ?></th>
<td>
<?php
if ( ! defined('HTTP_AUTH_DISABLED_RS') ) {
	$id = 'feed_link_http_auth';
	$all_options []= $id;
	$current_setting = scoper_get_option($id);
	
	_e( 'HTTP Authentication Request in RSS Feed Links', 'scoper' );

	echo "&nbsp;<select name='$id' id='$id'>";
	$captions = array( 0 => __('never', 'scoper'), 1 => __('always', 'scoper'), 'logged' => __('for logged users', 'scoper') );
	foreach ( $captions as $key => $value) {
		$selected = ( $current_setting == $key ) ? 'selected="selected"' : '';
		echo "\n\t<option value='$key' " . $selected . ">$captions[$key]</option>";
	}
	echo '</select>&nbsp;';
	echo '</label>';
	echo "<br />";
	echo '<span class="rs-subtext">';
	if ( $display_hints ) _e('Suffix RSS feed links with an extra parameter to trigger required HTTP authentication. Note that anonymous and cookie-based RSS will still be available via the standard feed URL.', 'scoper');
	echo '</span>';
} else {
	echo '<span class="rs-warning">';
	_e( 'cannot use HTTP Authentication for RSS Feeds because another plugin has already defined the function "get_currentuserinfo"', 'scoper' );
	echo '</span>';
}

echo "<br /><br />";

$all_options []= 'rss_private_feed_mode'; 

echo ( _c( 'Display|prefix to RSS content dropdown', 'scoper' ) );
echo '&nbsp;<select name="rss_private_feed_mode" id="rss_private_feed_mode">';

$captions = array( 'full_content' => __("Full Content", 'scoper'), 'excerpt_only' => __("Excerpt Only", 'scoper'), 'title_only' => __("Title Only", 'scoper') );
foreach ( $captions as $key => $value) {
	$selected = ( scoper_get_option('rss_private_feed_mode') == $key ) ? 'selected="selected"' : '';
	echo "\n\t<option value='$key' " . $selected . ">$captions[$key]</option>";
}
echo '</select>&nbsp;';
echo ( _c( 'for readable private posts|suffix to RSS content dropdown', 'scoper' ) );
echo "<br />";

$all_options []= 'rss_nonprivate_feed_mode'; 
echo ( _c( 'Display|prefix to RSS content dropdown', 'scoper' ) );
echo '&nbsp;<select name="rss_nonprivate_feed_mode" id="rss_nonprivate_feed_mode">';

$captions = array( 'full_content' => __("Full Content", 'scoper'), 'excerpt_only' => __("Excerpt Only", 'scoper'), 'title_only' => __("Title Only", 'scoper') );
foreach ( $captions as $key => $value) {
	$selected = ( scoper_get_option('rss_nonprivate_feed_mode') == $key ) ? 'selected="selected"' : '';
	echo "\n\t<option value='$key' " . $selected . ">$captions[$key]</option>";
}
echo '</select>&nbsp;';
echo ( _c( 'for readable non-private posts|suffix to RSS content dropdown', 'scoper' ) );

echo "<br />";
?>
<span class="rs-subtext">
<?php if ( $display_hints ) _e('Since some browsers will cache feeds without regard to user login, block RSS content even for qualified users.', 'scoper');?>
</span>
<br /><br />

<?php
$id = 'feed_teaser';
$all_options []= $id;
$val = htmlspecialchars( scoper_get_option($id) );

echo "<label for='$id'>";
_e ( 'Feed Replacement Text (use %permalink% for post URL)', 'scoper' );
echo "<br /><textarea name='$id' cols=60 rows=1 id='$id'>$val</textarea>";
echo "</label>";
?>
</td></tr>


<tr valign="top">
<th scope="row"><?php 
								// --- HIDDEN CONTENT TEASER SECTION ---
_e('Hidden Content Teaser', 'scoper') ?></th>
<td>
<?php
	// a "do teaser checkbox for each data source" that has a def_otype_options	entry
	// TODO: js to hide configuration UI for unchecked data sources
	$option_basename = 'do_teaser';
	if ( isset($def_otype_options[$option_basename]) ) {
		$all_otype_options []= $option_basename;
	
		$opt_vals = get_option( 'scoper_' . $option_basename );
		if ( ! $opt_vals || ! is_array($opt_vals) )
			$opt_vals = array();
		$do_teaser = array_merge( $def_otype_options[$option_basename], $opt_vals );
		
		$option_hide_private = 'teaser_hide_private';
		$all_otype_options []= $option_hide_private;
		$opt_vals = get_option( 'scoper_' . $option_hide_private );
		if ( ! $opt_vals || ! is_array($opt_vals) )
			$opt_vals = array();
		$hide_private = array_merge( $def_otype_options[$option_hide_private], $opt_vals );
		
		$option_use_teaser = 'use_teaser';
		$all_otype_options []= $option_use_teaser;
		$opt_vals = get_option( 'scoper_' . $option_use_teaser );
		if ( ! $opt_vals || ! is_array($opt_vals) )
			$opt_vals = array();
		$use_teaser = array_merge( $def_otype_options[$option_use_teaser], $opt_vals );
		
		$option_logged_only = 'teaser_logged_only';
		$all_otype_options []= $option_logged_only;
		$opt_vals = get_option( 'scoper_' . $option_logged_only );
		if ( ! $opt_vals || ! is_array($opt_vals) )
			$opt_vals = array();
		$logged_only = array_merge( $def_otype_options[$option_logged_only], $opt_vals );
		
		// loop through each source that has a default do_teaser setting defined
		foreach ( $do_teaser as $src_name => $val ) {
			$id = $option_basename . '-' . $src_name;
			
			echo '<div class="agp-vspaced_input">';
			echo "<label for='$id'>";
			$checked = ( $val ) ? ' checked="checked"' : '';
			$js_call = "agp_display_if('teaserdef-$src_name', '$id');agp_display_if('teaser_usage-$src_name', '$id');agp_display_if('teaser-pvt-$src_name', '$id');";
			echo "<input name='$id' type='checkbox' onclick=\"$js_call\" id='$id' value='1' $checked /> ";

			$display = $scoper->admin->display_otypes_or_source_name($src_name);
			printf(__("Enable teaser for %s", 'scoper'), $display);
			echo ('</label><br />');
			
			$css_display = ( $do_teaser[$src_name] ) ? 'block' : 'none';

			$style = "style='margin-left: 1em;'";
			echo "<div id='teaser_usage-$src_name' style='display:$css_display;'>";
			
			// loop through each object type (for current source) to provide a use_teaser checkbox
			foreach ( $use_teaser as $src_otype => $teaser_setting ) {
				if ( $src_name != $scoper->admin->src_name_from_src_otype($src_otype) )
					continue;
				
				if ( is_bool($teaser_setting) )
					$teaser_setting = intval($teaser_setting);
					
				$id = str_replace(':', '_', $option_use_teaser . '-' . $src_otype);
				
				echo '<div class="agp-vspaced_input">';
				echo "<label for='$id' style='margin-left: 2em;'>";
				
				$display_name = $scoper->admin->interpret_src_otype($src_otype, true);
				printf(__("%s:", 'scoper'), $display_name);
				
				echo "<select name='$id' id='$id'>";
				$num_chars = ( defined('SCOPER_TEASER_NUM_CHARS') ) ? SCOPER_TEASER_NUM_CHARS : 50;
				$captions = array( 0 => __("no teaser", 'scoper'), 1 => __("fixed teaser (specified below)", 'scoper'), 'excerpt' => __("excerpt as teaser", 'scoper'), 'more' => __("excerpt or pre-more as teaser", 'scoper'), 'x_chars' => sprintf(__("excerpt, pre-more or first %s chars", 'scoper'), $num_chars) );
				foreach ( $captions as $teaser_option_val => $teaser_caption) {
					$selected = ( $teaser_setting == $teaser_option_val ) ? 'selected="selected"' : '';
					echo "\n\t<option value='$teaser_option_val' $selected>$teaser_caption</option>";
				}
				echo '</select></label><br />';
				
				// Checkbox option to skip teaser for anonymous users
				$id = str_replace(':', '_', $option_logged_only . '-' . $src_otype);
				echo "<span style='margin-left: 6em'>";
				echo( _c( 'for:|teaser: anonymous, logged or both', 'scoper') );
				echo "&nbsp;&nbsp;<label for='{$id}_logged'>";
				$checked = ( ! empty($logged_only[$src_otype]) && 'anon' == $logged_only[$src_otype] ) ? ' checked="checked"' : '';
				echo "<input name='$id' type='radio' id='{$id}_logged' value='anon' $checked />";
				echo "";
				_e( "anonymous", 'scoper');
				echo '</label></span>';

				// Checkbox option to skip teaser for logged users
				echo "<span style='margin-left: 1em'><label for='{$id}_anon'>";
				$checked = ( ! empty($logged_only[$src_otype]) && 'anon' != $logged_only[$src_otype] ) ? ' checked="checked"' : '';
				echo "<input name='$id' type='radio' id='{$id}_anon' value='1' $checked />";
				echo "";
				_e( "logged", 'scoper');
				echo '</label></span>';

				// Checkbox option to do teaser for BOTH logged and anon users
				echo "<span style='margin-left: 1em'><label for='{$id}_all'>";
				$checked = ( empty($logged_only[$src_otype]) ) ? ' checked="checked"' : '';
				echo "<input name='$id' type='radio' id='{$id}_all' value='0' $checked />";
				echo "";
				_e( "both", 'scoper');
				echo '</label></span>';
				
				echo ('</div>');
			}
			echo '</div>';

			if ( empty($displayed_teaser_caption) ) {
				echo '<span class="rs-subtext">';
				if ( $display_hints) {
					_e('If content is blocked, display replacement text instead of hiding it completely.', 'scoper');
					echo '<br />';
					_e('<strong>Note:</strong> the prefix and suffix settings below will always be applied unless the teaser mode is "no teaser".', 'scoper');
				}
				echo '</span>';
					
				$displayed_teaser_caption = true;
			}

			// provide hide private (instead of teasing) checkboxes for each pertinent object type
			echo '<br /><br />';
			$display_style = ( $do_teaser[$src_name] ) ? '' : "style='display:none;'";
			echo "<div id='teaser-pvt-$src_name' $display_style>";
			foreach ( $hide_private as $src_otype => $teaser_setting ) {
				if ( $src_name != $scoper->admin->src_name_from_src_otype($src_otype) )
					continue;

				$id = str_replace(':', '_', $option_hide_private . '-' . $src_otype);
				
				echo "<label for='$id'>";
				$checked = ( $teaser_setting ) ? ' checked="checked"' : '';
				echo "<input name='$id' type='checkbox' id='$id' value='1' $checked /> ";
				
				$display_name = $scoper->admin->interpret_src_otype($src_otype, true);
				printf(__("Hide private %s (instead of teasing)", 'scoper'), $display_name);
				echo ('</label><br />');
			}
			echo '<span class="rs-subtext">';
			if ( $display_hints) _e('Hide private content completely, while still showing a teaser for content which is published with restrictions.  <strong>Note:</strong> Private posts hidden in this way will reduce the total number of posts on their "page" of a blog listing.', 'scoper');
			echo '</span>';
			echo '</div>';
		} // end foreach source's do_teaser setting
?>
</div>
<?php		
		// now draw the teaser replacement / prefix / suffix input boxes
		$user_suffixes = array('_anon', '');
		$item_actions = array(	'name' => 	array('prepend', 'append'), 
						'content' => array('replace', 'prepend', 'append'), 
						'excerpt' => array('replace', 'prepend', 'append') ); 
		
		$items_display = array( 'name' => __('name', 'scoper'), 'content' => __('content', 'scoper'), 'excerpt' => __('excerpt', 'scoper') );
		$actions_display = array( 'replace' => __('replace with (if using fixed teaser, or no excerpt available):', 'scoper'), 'prepend' => __('prefix with:', 'scoper'), 'append' => __('suffix with:', 'scoper') );	
		
		// first determine all src:otype keys
		$src_otypes = array();
		foreach ( $user_suffixes as $anon )
			foreach ( $item_actions as $item => $actions )
				foreach ( $actions as $action ) {
					if ( ! empty($def_otype_options["teaser_{$action}_{$item}{$anon}"]) )
						$src_otypes = array_merge($src_otypes, $def_otype_options["teaser_{$action}_{$item}{$anon}"]);
				}
		
		$last_src_name = '';	
		foreach ( array_keys($src_otypes) as $src_otype ) {
			$src_name = $scoper->admin->src_name_from_src_otype($src_otype);
			if ( $src_name != $last_src_name ) {
				if ( $last_src_name )
					echo '</div>';
				
				$last_src_name = $src_name;
				$css_display = ( $do_teaser[$src_name] ) ? 'block' : 'none';
				echo "<div id='teaserdef-$src_name' style='display:$css_display; margin-top: 2em;'>";
			}
			
			$display_name = $scoper->admin->interpret_src_otype($src_otype);
			
			// separate input boxes to specify teasers for anon users and unpermitted logged users
			foreach ( $user_suffixes as $anon ) {
				$user_descript = ( $anon ) ?  __('anonymous users', 'scoper') : __('logged users', 'scoper');
				
				echo '<strong>';
				printf( __('%1$s Teaser Text (%2$s):', 'scoper'), $display_name, $user_descript );
				echo '</strong>';
				echo ('<ul class="rs-textentries">');
			
				// items are name, content, excerpt
				foreach ( $item_actions as $item => $actions ) {
					echo ('<li>' . $items_display[$item] . ':');
					echo '<ul>';
					
					// actions are prepend / append / replace
					foreach( $actions as $action ) {
						$option_name = "teaser_{$action}_{$item}{$anon}";
						if ( ! $opt_vals = get_option( 'scoper_' . $option_name ) )
							$opt_vals = array();
						
						$all_otype_options []= $option_name;
							
						if ( ! empty($def_otype_options["teaser_{$action}_{$item}{$anon}"]) )
							$opt_vals = array_merge($def_otype_options[$option_name], $opt_vals);
							
						if ( isset($opt_vals[$src_otype]) ) {
							$val = htmlspecialchars($opt_vals[$src_otype]);
							$id = str_replace(':', '_', $option_name . '-' . $src_otype);
							
							echo "<label for='$id'>";
							echo( "<li>$actions_display[$action]" );
?>
<input name="<?php echo($id);?>" type="text" style="width: 95%" id="<?php echo($id);?>" value="<?php echo($val);?>" />
</label><br /></li>
<?php
						} // endif isset($opt_vals)
					} // end foreach actions
					
					echo ('</ul></li>');
				} // end foreach item_actions
				
				echo ("</ul><br />");
			} // end foreach user_suffixes
			
		} // end foreach src_otypes
		
		echo '</div>';
	} // endif any default otype_options for do_teaser
?>
</td>
</tr>
</table>

</div>
<?php
// ------------------------- END Features tab ---------------------------------

// ------------------------- BEGIN Advanced tab ---------------------------------
?>

<div id="rs-advanced" style='clear:both;margin:0' class='rs-options agp_js_hide'>
<?php
if ( $display_hints ) {
	echo '<div class="rs-optionhint">';
	_e("<strong>Note:</strong> for most installations, the default settings are fine.", 'scoper');
	echo '</div>';
}
?>
<table class="<?php echo($table_class);?>" id="rs-advanced_table">

<?php
$all_options []= 'define_usergroups';
?>
<tr valign="top">
<th scope="row"><?php 
								// --- USER GROUPS SECTION ---
_e('User Groups', 'scoper') ?></th>
<td>
<label for="define_usergroups">
<input name="define_usergroups" type="checkbox" id="define_usergroups" value="1" <?php checked('1', scoper_get_option('define_usergroups'));?> />
<?php _e('Enabled (but not necessarily used)', 'scoper') ?></label>
<br />
<br />
</td></tr>

<?php $all_options []= 'enable_group_roles';?>
<?php $all_options []= 'enable_user_roles';?>
<tr valign="top">
<th scope="row"><?php _e('Role Basis', 'scoper') ?></th>
<td>
<?php if( DEFINE_GROUPS_RS ): ?>
<div class="agp-vspaced_input">
<label for="enable_group_roles">
<input name="enable_group_roles" type="checkbox" id="enable_group_roles" value="1" <?php checked('1', scoper_get_option('enable_group_roles'));?> />
<?php _e('Apply Group Roles', 'scoper') ?></label></div>
<?php endif;?>

<div class="agp-vspaced_input">
<label for="enable_user_roles">
<input name="enable_user_roles" type="checkbox" id="enable_user_roles" value="1" <?php checked('1', scoper_get_option('enable_user_roles'));?> />
<?php _e('Apply User Roles', 'scoper') ?></label>
</div>
</td></tr>

<?php 
								// --- ROLE TYPE SECTION ---
$all_options []= 'role_type';?>
<tr valign="top">
<th scope="row"><?php _e('Role Type', 'scoper') ?></th>
<td>
<?php _e('Within any scope, each user or group has:', 'scoper') ?><br />
<select name="role_type" id="role_type">
<?php
	$captions = array( 'rs' => __("Multiple RS Roles (type-specific, plugin-defined)", 'scoper'), 'wp' => __("Single WP Role (comprehensive, WP-defined)", 'scoper'));
	foreach ( $scoper->role_defs->role_types as $key => $value) {
		if ( 'wp_cap' == $value ) continue;
		$selected = ( scoper_get_option('role_type') == $value ) ? 'selected="selected"' : '';
		echo "\n\t<option value='$key' $selected>$captions[$value]</option>";
	}
?>
</select><br />
<span class="rs-subtext">
<?php if ( $display_hints) _e('Either role type can be scoped (assigned for specific terms or objects).  However, RS roles allow finer control.  A user\'s main WordPress role will be applied by default regardless of this selection.', 'scoper') ?>
</span>
<br />
<?php
$all_options []= 'custom_user_blogcaps';
$val = scoper_get_option('custom_user_blogcaps');
?>
<?php if( $val || ScoperAdminLib::any_custom_caps_assigned() ):?>
<br />
<div class="agp-vspaced_input">
<label for="custom_user_blogcaps">
<input name="custom_user_blogcaps" type="checkbox" id="custom_user_blogcaps" value="1" <?php checked('1', $val);?> />
<?php _e('Support WP Custom User Caps', 'scoper') ?></label><br />
<span class="rs-subtext">
<?php if ( $display_hints) _e('WordPress core allows users to be granted individual blog-wide capabilities, in addition to their blog-wide role assignment.', 'scoper');?>
</span>
</div>
<?php endif;?>
</td></tr>


<?php 
								// --- PAGE STRUCTURE SECTION ---
$all_options []= 'lock_top_pages';?>

<tr valign="top">
<th scope="row"><?php _e('Page Structure', 'scoper') ?></th>
<td>

<label for="lock_top_pages">
<input name="lock_top_pages" type="checkbox" id="lock_top_pages" value="1" <?php checked('1', scoper_get_option('lock_top_pages'));?> />
<?php _e('Lock Top Level Page Structure', 'scoper') ?></label>

<br />
<span class="rs-subtext">
<?php if ( $display_hints) _e('<strong>If selected</strong>, only Administrators can create new top-level pages.  <strong>Otherwise,</strong> top level pages can be created by any user with the <strong>edit_pages</strong> and <strong>edit_others_pages</strong> capability in their WordPress Role or RS General Role(s).', 'scoper') ?>
</span>

</td></tr>


<?php 
								// --- USER PROFILE SECTION ---
$all_options []= 'display_user_profile_groups';
$all_options []= 'display_user_profile_roles';
?>

<tr valign="top">
<th scope="row"><?php _e('User Profile', 'scoper') ?></th>
<td>

<div class="agp-vspaced_input">
<label for="display_user_profile_groups">
<input name="display_user_profile_groups" type="checkbox" id="display_user_profile_groups" value="1" <?php checked('1', scoper_get_option('display_user_profile_groups'));?> />
<?php _e('Display User Groups', 'scoper') ?></label>
</div>

<div class="agp-vspaced_input">
<label for="display_user_profile_roles">
<input name="display_user_profile_roles" type="checkbox" id="display_user_profile_roles" value="1" <?php checked('1', scoper_get_option('display_user_profile_roles'));?> />
<?php _e('Display User Roles', 'scoper') ?></label>
</div>

</td></tr>


<tr valign="top">
<th scope="row"><?php 
								// --- LIMITED EDITING ELEMENTS SECTION ---
_e('Limited Editing Elements', 'scoper') ?></th>
<td>

<div class="agp-vspaced_input">
<?php
	if ( $display_hints) {
		echo ('<div class="agp-vspaced_input">');
		_e('Remove Edit Form elements with these html IDs from users who do not have full editing capabilities for the post/page. Separate with&nbsp;;', 'scoper');
		echo '</div>';
	}
?>
</div>
<?php
	$option_name = 'admin_css_ids';
	if ( isset($def_otype_options[$option_name]) ) {
		if ( ! $opt_vals = get_option( 'scoper_' . $option_name ) )
			$opt_vals = array();
			
		$opt_vals = array_merge($def_otype_options[$option_name], $opt_vals);
		
		$all_otype_options []= $option_name;
		
		$sample_ids = array();

		$sample_ids['post:post'] = '<span id="rs_sample_ids_post:post" class="rs-gray" style="display:none">' . 'rs_private_post_reader; rs_post_contributor; categorydiv; password-span; slugdiv; authordiv; commentstatusdiv; postcustom; trackbacksdiv; tagsdiv-post_tag; postexcerpt' . '</span>';
		$sample_ids['post:page'] = '<span id="rs_sample_ids_post:page" class="rs-gray" style="display:none">' . 'rs_private_page_reader; rs_page_contributor; rs_page_associate; pagepassworddiv; pageslugdiv; pageauthordiv; pagecommentstatusdiv; pagecustomdiv; pageparentdiv' . '</span>';
		
		foreach ( $opt_vals as $src_otype => $val ) {
			$id = str_replace(':', '_', $option_name . '-' . $src_otype);
			$display = $scoper->admin->interpret_src_otype($src_otype, false);
			echo('<div class="agp-vspaced_input">');
			echo('<span class="rs-vtight">');
			printf(__('%s Edit Form HTML IDs:', 'scoper'), $display);
?>
<label for="<?php echo($id);?>">
<input name="<?php echo($id);?>" type="text" size="45" style="width: 95%" id="<?php echo($id);?>" value="<?php echo($val);?>" />
</label>
</span>
<br />
<?php
if ( isset($sample_ids[$src_otype]) ) {
	$js_call = "agp_set_display('rs_sample_ids_$src_otype', 'inline');";
	printf(__('%1$s sample IDs:%2$s %3$s', 'scoper'), "<a href='javascript:void(0)' onclick=\"$js_call\">", '</a>', $sample_ids[$src_otype] );
}
?>
</div>
<?php
		} // end foreach optval
	} // endif any default admin_css_ids options
?>

<br />
<?php $all_options []= 'hide_non_editor_admin_divs';?>
<div class="agp-vspaced_input">
<label for="hide_non_editor_admin_divs">
<input name="hide_non_editor_admin_divs" type="checkbox" id="hide_non_editor_admin_divs" value="1" <?php checked('1', scoper_get_option('hide_non_editor_admin_divs'));?> />
<?php _e('Require a blog-wide Contributor / Author / Editor role to edit the specified element IDs', 'scoper') ?></label><br />
<span class="rs-subtext">
<?php if ( $display_hints) _e('Also limit access to the specified editing elements if the user has only a Reading role blog-wide.', 'scoper');?>
</span>
</div>

</td>
</tr>


<?php 
$all_options []= 'indicate_blended_roles';
$all_options []= 'display_hints';
$all_options []= 'user_role_assignment_csv';
?>
<tr valign="top">
<th scope="row"><?php 
								// --- ROLE ASSIGNMENT INTERFACE SECTION ---
_e('Role Assignment Interface', 'scoper') ?></th>
<td>
<?php
	$option_name = "limit_object_editors";
	$all_otype_options []= $option_name;
	if ( isset($def_otype_options[$option_name]) ) {
		if ( ! $opt_vals = get_option( 'scoper_' . $option_name ) )
			$opt_vals = array();
			
		$opt_vals = array_merge($def_otype_options[$option_name], $opt_vals);
		
		foreach ( $opt_vals as $src_otype => $val ) {
			$display_name = $scoper->admin->interpret_src_otype($src_otype, false); //arg: use singluar display name
				
			$id = str_replace(':', '_', $option_name . '-' . $src_otype);
?>
<div class="agp-vspaced_input">
<label for="<?php echo($id);?>">
<input name="<?php echo($id);?>" type="checkbox" id="<?php echo($id);?>" value="1" <?php checked('1', $val);?> />
<?php 
			printf( __('Limit eligible users for %s-specific editing roles', 'scoper'), $display_name );
			echo ('</label></div>');
		} // end foreach src_otype
?>
<span class="rs-subtext">
<?php if ( $display_hints) _e('Role Scoper can enable any user to edit a post or page you specify, regardless of their blog-wide WordPress role.  If that\'s not a good thing, check above options to require basic editing capability blog-wide or category-wide.', 'scoper');?>
</span>
<?php
	} // endif default option isset
	echo('<br /><br />');
?>
	
<div class="agp-vspaced_input">
<label for="indicate_blended_roles">
<input name="indicate_blended_roles" type="checkbox" id="indicate_blended_roles" value="1" <?php checked('1', scoper_get_option('indicate_blended_roles'));?> />
<?php _e('Indicate blended roles', 'scoper') ?></label>
<br />
<span class="rs-subtext">
<?php if ( $display_hints) _e('In the Edit Post/Edit Page roles tabs, decorate user/group name with colors and symbols if they have the role implicitly via group, general role, category role, or a superior post/page role.', 'scoper');?>
</span>
</div>
<br />

<div class="agp-vspaced_input">
<label for="display_hints">
<input name="display_hints" type="checkbox" id="display_hints" value="1" <?php checked('1', scoper_get_option('display_hints'));?> />
<?php _e('Display Administrative Hints', 'scoper') ?></label>
<br />
<span class="rs-subtext">
<?php if ( $display_hints) _e('Display introductory descriptions at the top of various role assignment / definition screens.', 'scoper');?>
</span>
</div>
<br />

<div class="agp-vspaced_input">
<label for="user_role_assignment_csv">
<input name="user_role_assignment_csv" type="checkbox" id="user_role_assignment_csv" value="1" <?php checked('1', scoper_get_option('user_role_assignment_csv'));?> />
<?php _e('Users CSV Entry', 'scoper') ?></label>
<br />
<span class="rs-subtext">
<?php if ( $display_hints) _e('Accept entry of user names or IDs via comma-separated text instead of individual checkboxes.', 'scoper');?>
</span>
</div>

</td></tr>


<?php 
if ( 'rs' == SCOPER_ROLE_TYPE ):
	$objscope_equiv_roles = array( 'rs_post_reader' => 'rs_private_post_reader', 'rs_post_author' => 'rs_post_editor', 'rs_page_reader' => 'rs_private_page_reader', 'rs_page_author' => 'rs_page_editor' );
	foreach ( $objscope_equiv_roles as $role_handle => $equiv_role_handle ) {
		$all_options []= "{$role_handle}_role_objscope";
	}
	?>
	<tr valign="top">
	<th scope="row"><?php _e('Additional Object Roles', 'scoper') ?></th>
	<td>
	<?php 
	foreach ( $objscope_equiv_roles as $role_handle => $equiv_role_handle ) {
		$id = "{$role_handle}_role_objscope";
		$checked = ( scoper_get_option( $id ) ) ? "checked='checked'" : '';
		echo '<div class="agp-vspaced_input">';
		echo "<label for='$id'>";
		echo "<input name='$id' type='checkbox' id='$id' value='1' $checked /> ";
		echo $scoper->role_defs->member_property($role_handle, 'display_name');
		echo '</label></div>';
	}
	?>
	<span class="rs-subtext">
	<?php 
	if ( $display_hints) {
		_e('By default, the above roles are not available for object-specific assignment because other roles (Private Reader, Editor) are usually equivalent. However, the distinctions may be useful if you propagate roles to sub-Pages, set Default Roles or customize RS Role Definitions.', 'scoper');
		echo '<br /><br />';
		_e('Note: Under the default configuration, the tabs labeled "Reader" in the Post/Page Edit Form actually assign the corresponding Private Reader role.', 'scoper');
	}	
	?>
	</span>
	</td></tr>
<?php
endif; // rs role type
?>

</table>
</div>


<?php
// ------------------------- BEGIN Realms tab ---------------------------------
$topics = array('data_sources', 'object_types', 'taxonomies');
?>

<div id="rs-realm" style='clear:both;margin:0' class='rs-options agp_js_hide'>
<?php
if ( $display_hints ) {
	echo '<div class="rs-optionhint">';
	_e("These <b>optional</b> settings allow advanced users to adjust Role Scoper's sphere of influence. For most installations, the default settings are fine.", 'scoper');
	echo '</div>';
}
?>

<table class="<?php echo($table_class);?>" id="rs-realm_table">

<tr valign="top">
<th scope="row"><?php 
								// --- TAXONOMY USAGE SECTION ---
_e('Taxonomy Usage', 'scoper');
echo '<br /><span style="font-size: 0.9em; font-style: normal; font-weight: normal">(&nbsp;<a href="#scoper_notes">' . __('see notes', 'scoper') . '</a>&nbsp;)</span>';
?></th>
<td>
<?php
	global $wp_taxonomies;

	_e('Specify which WordPress taxonomies can have Restrictions and Roles:', 'scoper');
	echo '<br />';
	$option_name = "enable_wp_taxonomies";
	$enabled = scoper_get_option($option_name);
	
	$all = implode(',', array_keys($wp_taxonomies) );
	echo "<input type='hidden' name='all_wp_taxonomies' value='$all' />";
	
	$locked = array();
	
	// Detect and support any WP taxonomy
	foreach ( $wp_taxonomies as $taxonomy => $wtx ) {					// in case the 3rd party plugin uses a taxonomy->object_type property different from the src_name we use for RS data source definition
		if ( ! $scoper->data_sources->is_member($wtx->object_type) && ! $scoper->data_sources->is_member_alias($wtx->object_type) )
			continue;

		$disabled_attrib = ( $scoper->taxonomies->is_member($taxonomy) && ! $scoper->taxonomies->member_property($taxonomy, 'autodetected_wp_taxonomy') ) ? 'disabled="disabled"' : '';
		
		$id = $option_name . '-' . $taxonomy;
		$val = isset($enabled[$taxonomy]);
		
		if ( $disabled_attrib && $val )
			$locked[$taxonomy] = 1;
?>
<div class="agp-vspaced_input">
<label for="<?php echo($id);?>">
<input name="<?php echo($option_name);?>[]" type="checkbox" id="<?php echo($id);?>" <?php echo($disabled_attrib);?> value="<?php echo($taxonomy);?>" <?php checked(true, $val);?> />
<?php
		if ( ! $taxonomy_display_name = $scoper->taxonomies->member_property($taxonomy, 'display_name') )
			$taxonomy_display_name = __( ucwords(str_replace('_', ' ', $taxonomy)) );
		
		echo("$taxonomy_display_name</label></div>");
	} // end foreach wp tax
	
	$locked = implode(',', array_keys($locked) );
	echo "<input type='hidden' name='locked_wp_taxonomies' value='$locked' />";
?>
<br />
</td></tr>

<tr valign="top">
<th scope="row"><?php 
								// --- ROLE SCOPES SECTION ---
_e('Role Scopes', 'scoper');
echo '<br /><span style="font-size: 0.9em; font-style: normal; font-weight: normal">(&nbsp;<a href="#scoper_notes">' . __('see notes', 'scoper') . '</a>&nbsp;)</span>';
?></th>
<td>
<?php
	$scopes = array( 'term', 'object');
	foreach ( $scopes as $scope ) {
		$option_name = "use_{$scope}_roles";
		$all_otype_options []= $option_name;
		
		if ( isset($def_otype_options[$option_name]) ) {
			if ( ! $opt_vals = get_option( 'scoper_' . $option_name ) )
				$opt_vals = array();
					
			$opt_vals = array_merge($def_otype_options[$option_name], $opt_vals);
		
			foreach ( $opt_vals as $src_otype => $val ) {
				$id = str_replace(':', '_', $option_name . '-' . $src_otype);
?>
<div class="agp-vspaced_input">
<label for="<?php echo($id);?>">
<input name="<?php echo($id);?>" type="checkbox" id="<?php echo($id);?>" value="1" <?php checked('1', $val);?> />
<?php 
				if ( TERM_SCOPE_RS == $scope ) {
					$src_name = $scoper->admin->src_name_from_src_otype($src_otype);
					if ( $uses_taxonomies = $scoper->data_sources->member_property($src_name, 'uses_taxonomies') ) {
						$taxonomy = reset( $uses_taxonomies );
						$tx_display = $scoper->taxonomies->member_property($taxonomy, 'display_name');
					} else
						$tx_display = __('Section', 'scoper');
					
					$display_name_plural = $scoper->admin->interpret_src_otype($src_otype);
					
					if ( $scoper->taxonomies->member_property($taxonomy, 'requires_term') )
						printf( _c('%1$s Restrictions and Roles for %2$s|Category Restrictions and Roles for Posts', 'scoper'), $tx_display, $display_name_plural );
					else
						printf( _c('%1$s Roles for %2$s|Category Roles for Posts', 'scoper'), $tx_display, $display_name_plural );
				} else {
					$display_name = $scoper->admin->interpret_src_otype($src_otype, false);
					printf( _c('%1$s Restrictions and Roles|Page Restrictions and Roles', 'scoper'), $display_name );
				}
				echo ('</label></div>');
			} // end foreach src_otype
		} // endif default option isset
		echo('<br />');
	} // end foreach scope
?>
</td>
</tr>

<tr valign="top">
<th scope="row"><?php 
								// --- ACCESS TYPES SECTION ---
_e('Access Types', 'scoper') ?></th>
<td>
<?php
	_e('Apply Roles and Restrictions for:', 'scoper');
	echo '<br />';
	$topic = "access_types";
	$opt_vals = get_option("scoper_disabled_{$topic}");
	
	$all = implode(',', $scoper->access_types->get_all_keys() );
	echo "<input type='hidden' name='all_access_types' value='$all' />";
	
	foreach ( $scoper->access_types->get_all() as $access_name => $access_type) {
		$id = $topic . '-' . $access_name;
		$val = ! $opt_vals[$access_name];
?>
<div class="agp-vspaced_input">
<label for="<?php echo($id);?>">
<input name="<?php echo($id);?>[]" type="checkbox" id="<?php echo($id);?>" value="<?php echo($access_name);?>" <?php checked('1', $val);?> />
<?php
		if ( 'front' == $access_name )
			_e('Viewing content (front-end)', 'scoper');
		elseif ( 'admin' == $access_name )
			_e('Editing and administering content (admin)', 'scoper');
		else
			echo($access_type->display_name);
		echo('</label></div>');
	} // end foreach access types
?>
<br />
</td></tr>

<?php
	// NOTE: Access Types section (for disabling data sources / otypes individually) was removed due to complication with hardway filtering.
	// For last source code, see 1.0.0-rc8
?>

</table>

<?php
echo '<h4 style="margin-bottom:0.1em"><a name="scoper_notes"></a>' . __("Notes", 'scoper') . ':</h4><ul class="rs-notes">';

echo '<li>';
_e('The &quot;Post Tag&quot; taxonomy cannot be used to define restrictions because tags are not mandatory. For most installations, categories are a better mechanism to define roles. Note that <strong>Role Scoper does not filter tag storage</strong> based on the editing user\'s access.  As with any other custom-defined taxonomy, use this option at your own discretion.', 'scoper');
echo '</li>';

echo '<li>';
_e('By default, WordPress does not support page categories.  The corresponding &quot;Role Scopes&quot; option is only meaningful if the WP core or another plugin has added this support.', 'scoper');
echo '</li>';

echo '</ul>';
echo '</div>';

// ------------------------- END Realms tab ---------------------------------


if ( 'rs' == SCOPER_ROLE_TYPE ) {
	// RS Role Definitions Tab
	include('role_definition.php');
}

// WP Role Definitions Tab
include('role_definition_wp.php');

// this was required for Access Types section, which was removed
//$all = implode(',', $all_otypes);
//echo "<input type='hidden' name='all_object_types' value='$all' />";

$all_options = implode(',', $all_options);
$all_otype_options = implode(',', array_unique( $all_otype_options ) );
echo "<input type='hidden' name='all_options' value='$all_options' />";
echo "<input type='hidden' name='all_otype_options' value='$all_otype_options' />";

echo "<input type='hidden' name='rs_submission_topic' value='options' />";
?>
<p class="submit" style="border:none;float:right">
<input type="submit" name="rs_submit" value="<?php _e('Update &raquo;', 'scoper');?>" />
</p>
<p class="submit" style="border:none;float:left">
<input type="submit" name="rs_defaults" value="<?php _e('Revert to Defaults', 'scoper') ?>" />
</p>
<p style='clear:both'>
</p>
</div>
</form>