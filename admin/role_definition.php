<?php

if( basename(__FILE__) == basename($_SERVER['SCRIPT_FILENAME']) )
	die( 'This page cannot be called directly.' );
	
global $scoper;
?>
<div id="rs-roledefs" style='clear:both;margin:0;' class='rs-options agp_js_hide'>
<?php

if ( scoper_get_option('display_hints') ) {
	echo '<div class="rs-optionhint">';
	echo '<p style="margin-top:0">';
	_e('These roles are defined by Role Scoper (and possibly other plugins) for your use in designating content-specific access.  Although the default capabilities are ideal for most installations, you may modify them at your discretion.', 'scoper');
	echo '</p>';
	
	echo '<p>';
	_e('Since Role Scoper role definitions pertain to a particular object type, available capabilities are defined by the provider of that object type. Wordress core or plugins can add or revise default role definitions based on available capabilities.', 'scoper');
	echo '</p>';
	
	echo '<p>';
	_e('Wordpress Role assignments function as a default which may be supplemented or overriden by blog-wide or content-specific assignment of these RS Roles.', 'scoper');  

	echo '</p>';
	echo '</div>';
}

echo "<input type='hidden' name='rs_role_defs' value='1' />";

foreach ( $scoper->data_sources->get_all() as $src_name => $src) {
	
	$include_taxonomy_otypes = true;

	foreach ( $src->object_types as $object_type => $otype ) {
		$otype_roles = array();
		$otype_display_names = array();
		
		if ( $obj_roles = $scoper->role_defs->get_matching( 'rs', $src_name, $object_type ) ) {
			$otype_roles[$object_type] = $obj_roles;
			$otype_display_names[$object_type] = $otype->display_name;
		}
		
		if ( $include_taxonomy_otypes ) {
			if ( scoper_get_otype_option('use_term_roles', $src_name, $object_type) ) {
				foreach ( $src->uses_taxonomies as $taxonomy) {
					$tx_display_name = $scoper->taxonomies->member_property($taxonomy, 'display_name');
				
					if ( $tx_roles = $scoper->role_defs->get_matching( 'rs', $src_name, $taxonomy ) ) {
						$otype_roles[$taxonomy] = $tx_roles;
						$otype_display_names[$taxonomy] = $tx_display_name;
					}
				}	
				$include_taxonomy_otypes = false;
			}	
		}
		
		if ( ! $otype_roles )
			continue;

		foreach ( $otype_roles as $object_type => $roles ) {
			//display each role which has capabilities for this object type
			echo '<br />';
			echo '<h3>' . sprintf( __('%s Roles'), $otype_display_names[$object_type] ) . '</h3>';
?>
<table class='widefat rs-backwhite'>
<thead>
<tr class="thead">
	<th width="15%"><?php _e('Role', 'scoper') ?></th>
	<th><?php _e('Capabilities (defaults are bolded)', 'scoper');?></th>
</tr>
</thead>
<tbody>
<?php		
			$style = '';

			$default_role_caps = apply_filters('define_role_caps_rs', scoper_core_role_caps() );

			foreach ( $roles as $role_handle => $role_def ) {
				$style = ( ' class="alternate"' == $style ) ? '' : ' class="alternate"';

				echo "\n\t"
					. "<tr$style><td>$role_def->display_name</td><td><ul class='rs-cap_list'>";

				$active_cap_names = array_keys($scoper->role_defs->role_caps[$role_handle]);

				if ( ! empty($role_def->anon_user_blogrole) || ! empty($role_def->no_custom_caps) ) {
					$disabled_role = 'disabled="disabled"';
					$available_cap_names = $active_cap_names;
				} else {
					$disabled_role = '';
					$available_caps = $scoper->cap_defs->get_matching($src_name, $object_type, '', STATUS_ANY_RS);
					$available_cap_names = array_keys($available_caps);
					sort($available_cap_names);
				}

				foreach($available_cap_names as $cap_name) {
					$checked = ( in_array($cap_name, $active_cap_names) ) ? 'checked="checked"' : '';
					$is_default = ! empty($default_role_caps[$role_handle][$cap_name]);
					$disabled_cap = $disabled_role || ( $is_default && ! empty($available_caps[$cap_name]->no_custom_remove) ) || ( ! $is_default && ! empty($available_caps[$cap_name]->no_custom_add) );
					$disabled = ( $disabled_cap ) ? 'disabled="disabled"' : '';

					$style = ( $is_default ) ? "style='font-weight: bold'" : '';

					$cap_safename = str_replace( ' ', '_', $cap_name );
					
					echo "<li><input type='checkbox' name='{$role_handle}_caps[]' id='{$role_handle}_{$cap_safename}' value='$cap_name' $checked $disabled />"
						. "<label for='{$role_handle}_{$cap_safename}' $style>" . str_replace( ' ', '&nbsp;', ucwords( str_replace('_', ' ', $cap_name) ) ) . '</label></li>';
				}

				echo '</ul></td></tr>';
			}

			echo '</tbody></table>';
			echo '<br /><br />';
		} // foreach otype_role (distinguish object roles from term roles)
	} // end foreach object_type
	
} // end foreach data source

// TODO: clean up formatting for this checkbox
echo '<span class="alignright">';
echo '<label for="rs_role_resync"><input name="rs_role_resync" type="checkbox" id="rs_role_resync" value="1" />';
echo '&nbsp;';
_e ( 'Re-sync with WordPress roles on next Update', 'scoper' );
echo '</label></span>';
echo '<br />'

?>
</div>