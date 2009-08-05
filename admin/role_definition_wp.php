<?php

if( basename(__FILE__) == basename($_SERVER['SCRIPT_FILENAME']) )
	die( 'This page cannot be called directly.' );
	
global $scoper;

?>
<div id="wp-roledefs" style='clear:both;margin:0;' class='rs-options agp_js_hide'>
<?php

if ( scoper_get_option('display_hints') ) {
	echo '<div class="rs-optionhint">';
	echo '<p style="margin-top:0">';
	_e('These WordPress role definitions may be modified via the Role Manager plugin.', 'scoper');
	echo '</p>';
	
	if ( 'rs' == SCOPER_ROLE_TYPE ) {
		echo '<p style="margin-top:0">';
		_e('To understand how your WordPress roles relate to type-specific RS Roles, see <a href="#wp_rs_equiv">WP/RS Role Equivalence</a>.', 'scoper');
		echo '</p>';
	}
	
	echo '</div>';
}

	$roles = $scoper->role_defs->get_matching( 'wp', '', '' );

	echo '<h3>' . __('WordPress Roles', 'scoper'), '</h3>';
?>
<table class='widefat rs-backwhite'>
<thead>
<tr class="thead">
	<th width="15%"><?php _e('Role', 'scoper') ?></th>
	<th><?php _e('Capabilities', 'scoper');?></th>
</tr>
</thead>
<tbody>
<?php		
	$style = '';

	foreach ( $roles as $role_handle => $role_def ) {
		$style = ( ' class="alternate"' == $style ) ? '' : ' class="alternate"';
	
		if ( empty($scoper->role_defs->role_caps[$role_handle]) )
			continue;

		$cap_names = array_keys($scoper->role_defs->role_caps[$role_handle]);	
		sort($cap_names);
		$cap_display_names = array();
		foreach($cap_names as $cap_name)
			$cap_display_names[] = ucwords( str_replace('_', ' ', $cap_name) );
		
		$caplist = "<li>" . implode("</li><li>", $cap_display_names) . "</li>";

		echo "\n\t"
			. "<tr$style><td>$role_def->display_name</td><td><ul class='rs-cap_list'>$caplist</ul></td></tr>";
	} // end foreach role

	echo '</tbody></table>';
	echo '<br /><br />';
	
if ( 'rs' == SCOPER_ROLE_TYPE ) {
	echo '<a name="wp_rs_equiv"></a>';
	echo '<h3>' . __('WP / RS Role Equivalence', 'scoper'), '</h3>';
?>
<table class='widefat rs-backwhite'>
<thead>
<tr class="thead">
	<th width="15%"><?php _e('WP Role', 'scoper') ?></th>
	<th><?php _e('Contained RS Roles', 'scoper');?></th>
</tr>
</thead>
<tbody>
<?php	
	$style = '';

	foreach ( $roles as $role_handle => $role_def ) {
		$style = ( ' class="alternate"' == $style ) ? '' : ' class="alternate"';
	
		$display_names = array();
		$contained_roles_handles = $scoper->role_defs->get_contained_roles($role_handle, false, 'rs');
	
		foreach( array_keys($contained_roles_handles) as $contained_role_handle )
			$display_names[] = $scoper->role_defs->member_property($contained_role_handle, 'display_name');
		
		$list = "<li>" . implode("</li><li>", $display_names) . "</li>";

		echo "\n\t"
			. "<tr$style><td>$role_def->display_name</td><td><ul class='rs-cap_list'>$list</ul></td></tr>";
	} // end foreach role

	echo '</tbody></table>';
	echo '<br /><br />';
} // endif 'rs' == SCOPER_ROLE_TYPE
?>
</div>