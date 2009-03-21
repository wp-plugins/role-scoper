<?php

if( basename(__FILE__) == basename($_SERVER['SCRIPT_FILENAME']) )
	die( 'This page cannot be called directly.' );

global $scoper, $wpdb, $current_user;

$role_assigner = init_role_assigner();

require_once('admin_ui_lib_rs.php');
require_once('role_assignment_lib_rs.php');

$role_bases = array();
$agents = array();

$is_administrator = is_administrator_rs();

if ( USER_ROLES_RS && ( $is_administrator || current_user_can('edit_users') ) ) {
	$role_bases []= ROLE_BASIS_USER;
	$agents[ROLE_BASIS_USER] = $scoper->users_who_can('', COLS_ID_DISPLAYNAME_RS);
	
	$agent_list_prefix[ROLE_BASIS_USER] = '';
}

if ( GROUP_ROLES_RS && ( $is_administrator || current_user_can('edit_users') ) ) {
	$groups_url = SCOPER_ADMIN_URL . '/groups.php';

	if ( $agents[ROLE_BASIS_GROUPS] = ScoperAdminLib::get_all_groups(UNFILTERED_RS) ) {
		$role_bases []= ROLE_BASIS_GROUPS;
		$agent_list_prefix[ROLE_BASIS_GROUPS] = __('Groups') . ': ';
		$edit_groups_link = sprintf(_c('%1$s define user groups%2$s|Args are link open, close tags', 'scoper'), "<a href='$groups_url'>", '</a>');
	} else
		$edit_groups_link = sprintf(_c('<b>Note:</b> To assign roles to user groups, first %1$s define the group(s)%2$s.|Args are link open, close tags', 'scoper'), "<a href='$groups_url'>", '</a>');
}

if ( empty($role_bases) )
	wp_die(__('Cheatin&#8217; uh?'));

$agent_names = array();
foreach ( $role_bases as $role_basis )
	foreach( $agents[$role_basis] as $agent )
		$agent_names[$role_basis][$agent->ID] = str_replace(' ', '&nbsp;', $agent->display_name);
		
if ( count($role_bases) > 1 ) {
	$agent_caption =  __('User / Group', 'scoper');
	$agent_caption_plural = __('Users or Groups', 'scoper');
} elseif ( isset($agents[ROLE_BASIS_USER]) ) {
	$agent_caption =  __('User', 'scoper');
	$agent_caption_plural = __('Users', 'scoper');
} elseif ( isset($agents[ROLE_BASIS_GROUPS]) ) {
	$agent_caption =  __('Group', 'scoper');
	$agent_caption_plural = __('Groups', 'scoper');
}

echo '<a name="scoper_top"></a>';

$err = 0;

$role_codes = array(); //generate temporary numeric id for each defined role, to reduce html bulk
$i = 0;

$role_defs = array();

if ( 'rs' == SCOPER_ROLE_TYPE )

foreach( $scoper->role_defs->get_matching(SCOPER_ROLE_TYPE) as $role_handle => $role_def) {  // user can only view/assign roles they have
	// instead of hiding unowned roles, just make them uneditable
	//if ( $is_administrator || ! array_diff(array_keys($scoper->role_defs->role_caps[$role_handle]), $current_user->allcaps) ) {
		$role_defs[$role_handle] = $role_def;
		$role_codes[$role_handle] = $i; 
		$i++;
	//}
}
	
if ( isset($_POST['rs_submit']) ) :	?>
	<?php
	// =========================== Process Submission ===============================
		check_admin_referer( "scoper-assign-blogrole" );
		
		$blog_roles = array();
		$selected_agents = array();
		foreach ( $role_bases as $role_basis )
			if ( ! empty($_POST[$role_basis]) )
				$selected_agents[$role_basis] = $_POST[$role_basis];
		
		$selected_roles = $_POST['roles'];
		$assign_for = $_POST['assign_for'];
		$modcount = 0;
		$agents_msg = array();
	
		if ( ! $selected_agents ) :?>
			<div id="message" class="error"><p><b>
			<?php 
			$_POST['scoper_error'] = 1;
			printf( __('Error: no %s were selected!', 'scoper'), $agent_caption_plural);
			echo $msg;
			?>
			</b></p></div>
			<?php $err = 1;?>
		<?php elseif ( ! $selected_roles ) :?>
			<?php $_POST['scoper_error'] = 1; ?>
			<div id="message" class="error"><p><b><?php _e('Error: no roles were selected!', 'scoper'); ?></b></p></div>
			<?php $err = 2;?>
		<?php else : ?>
		
			<?php 
			foreach ( array_keys($selected_agents) as $role_basis ) {
				foreach($selected_agents[$role_basis] as $agent_id)
					foreach($selected_roles as $role_code)
						if ( $role_handle = array_search($role_code, $role_codes) )
							$blog_roles[ $role_basis ][ $role_handle ][ $agent_id ] = $assign_for;
				
				if ( ROLE_BASIS_USER == $role_basis )
					$agents_msg []= sprintf(__ngettext("%d user", "%d users", count($selected_agents[$role_basis]), 'scoper'), count($selected_agents[$role_basis]) );
				else
					$agents_msg []= sprintf(__ngettext("%d group", "%d groups", count($selected_agents[$role_basis]), 'scoper'), count($selected_agents[$role_basis]) );

				$role_assigner->assign_blog_roles($blog_roles[$role_basis], $role_basis);
			} // end foreach role basis
			?>
			
			<div id="message" class="updated fade"><p>
			<?php
			$agents_msg = implode( ", ", $agents_msg );
			$roles_msg = sprintf(__ngettext("%d role selection", "%d role selections", count($selected_roles), 'scoper'), count($selected_roles) );
			
			printf(_c('Role Assignments Updated: %1$s for %2$s|%d selections for %d', 'scoper'), $roles_msg, $agents_msg );
			?>
			</p></div>
		<?php endif; ?> 
<?php endif; // end submission response block
	

// =========================== Display UI ===============================
		
//$blog_roles[role_basis] [role_handle] [agent_id] = 1
$blog_roles = array();
foreach ( $role_bases as $role_basis )
	$blog_roles[$role_basis] = ScoperRoleAssignments::get_assigned_blog_roles($role_basis);

$assignment_modes = array( ASSIGN_FOR_ENTITY_RS => __('Assign', 'scoper'), REMOVE_ASSIGNMENT_RS =>__('Remove', 'scoper') );	   
?>
<div class="wrap agp-width97">
<h2><?php _e('Assign General Roles', 'scoper');?></h2>
<?php
if ( scoper_get_option('display_hints') ) {
	echo '<div class="rs-hint">';
	_e("Supplement any user's blog-wide WordPress Role with additional, type-specific role(s). This does not alter the WordPress role.", 'scoper');
	echo '</div>';
}
?>
<form action="" method="post" name="role_assign" id="role_assign">
<?php

wp_nonce_field( "scoper-assign-blogrole" );


//echo $scoper->admin->blogrole_scroll_links();
//echo '<hr />';

// ============ Users / Groups Selection Display ================
echo "<h3><a name='scoper_submit'></a><b>";
?>
</b></h3>

<?php
echo '<ul class="rs-list_horiz"><li style="float:left;"><h3>1.&nbsp;';
_e('Select Assignment Mode', 'scoper');
echo '</h3></li>';
echo '<li style="float:right;"><h3>4.&nbsp;';
_e('Confirm and Submit', 'scoper');
echo '</h3></li>';
echo '</ul>';
?>

<ul class="rs-list_horiz"><li>
<select id="assign_for" name="assign_for"><?php 
	$retain_value = ( isset($_POST["assign_for"]) ) ? $_POST["assign_for"] : 0;

	foreach($assignment_modes as $status_id => $caption) {
		$selected = ( $status_id === $retain_value ) ? 'selected="selected"' : '';
		echo "<option value='$status_id' $selected>$caption</option>";
	} 
	?>
</select>
</li><li style='margin: 0 0.25em 0.25em 0.5em;padding-top:0.35em;'>

</li>
<li style='float:right;margin: 0 0.25em 0.25em 0.25em;'><span class="submit" style="border:none;">
<input type="submit" name="rs_submit" value="<?php _e('Update &raquo;', 'scoper');?>" />
</span></li>
</ul>
<p style="clear:both"></p>
<?php
echo '<br /><h3>2.&nbsp;';
printf( _c('Select %s to Modify|Users or Groups', 'scoper'), $agent_caption_plural );
echo '</h3>';

$args = array( 'suppress_extra_prefix' => true, 'filter_threshold' => 20, 'default_hide_threshold' => 20, 'check_for_incomplete_submission' => true );
require_once('agents_checklist_rs.php');
ScoperAgentsChecklist::all_agents_checklist($role_bases, $agents, $args);

echo '<p style="clear:both"></p>';
//=================== end users/groups selection display ====================

echo '<hr /><br />';

echo '<br /><h3>3.&nbsp;';
_e('Select Roles to Assign / Remove', 'scoper');
echo '</h3>';

echo "<a href='javascript:void(0);' onclick=\"agp_check_by_name('roles[]', true, false, true);\">";
_e('select all roles', 'scoper');
echo "</a> | ";

echo "<a href='javascript:void(0);' onclick=\"agp_check_by_name('roles[]', '', false, true);\">";
_e('unselect all roles', 'scoper');
echo '</a> | ';

if ( in_array(ROLE_BASIS_USER, $role_bases) ) {
	echo "<a href='javascript:void(0);' onclick=\"agp_setcss('.user-csv','display','none');\">";
	_e('hide users', 'scoper');
	echo '</a> | ';
}

if ( in_array(ROLE_BASIS_GROUPS, $role_bases) ) {
	echo "<a href='javascript:void(0);' onclick=\"agp_setcss('.groups-csv','display','none');\">";
	_e('hide groups', 'scoper');
	echo '</a> | ';
}

// Hide Empty
$js_call = "
agp_display_marked_elements('li','no-role-li','none');
agp_setcss('.no-role','display','none');
agp_setcss('.user-csv','display','inline');
agp_setcss('.groups-csv','display','inline');
";
echo "<a href='javascript:void(0);' onclick=\"$js_call\">";
_e('hide empty', 'scoper');
echo '</a> | ';

// Show All
$tr_display = (strpos($_SERVER['HTTP_USER_AGENT'], 'MSIE') !== false) ? 'block' : 'table-row';
$js_call = "
agp_display_marked_elements('li','role-li','block');
agp_setcss('.no-role','display','$tr_display');
agp_setcss('.user-csv','display','inline');
agp_setcss('.groups-csv','display','inline');
";
echo "<a href='javascript:void(0);' onclick=\"$js_call\">";
_e('show all', 'scoper');
echo '</a>';

$all_roles = array();
$otype_source = array();

foreach ( $scoper->data_sources->get_all() as $src_name => $src) {
	if ( ! empty($src->taxonomy_only) )
		continue;

	$include_taxonomy_otypes = true;
	foreach ( $src->object_types as $object_type => $otype ) {
		$otype_roles = array();
		$otype_roles[$object_type] = $scoper->role_defs->get_matching( SCOPER_ROLE_TYPE, $src_name, $object_type );
		$otype_source[$object_type] = $src_name;
		
		if ( $include_taxonomy_otypes ) {
			if ( scoper_get_otype_option('use_term_roles', $src_name, $object_type) ) {
				foreach ( $src->uses_taxonomies as $taxonomy)
					if ( $tx_roles = $scoper->role_defs->get_matching( SCOPER_ROLE_TYPE, $src_name, $taxonomy ) )
						$otype_roles[$taxonomy] = $tx_roles;
					
				$include_taxonomy_otypes = false;
			}	
		}
		
		if ( ! $otype_roles )
			continue;

		echo "<br /><h4><a name='$object_type'></a><b>";
		printf( __('Modify role assignments for %s', 'scoper'), strtolower($otype->display_name_plural) );
		echo '</b></h4>';

		//display each role eligible for group/user assignment
		$row_class = 'rs-backwhite';

?>
<ul class="rs-termlist" style="padding-left:0.1em;"><li>
<table class='widefat'>
<thead>
<tr class="thead">
	<th class="rs-tightcol"></th>
	<th class="rs-tightcol"><?php _e('Role', 'scoper') ?></th>
	<th><?php echo($agent_caption_plural) ?></th>
</tr>
</thead>
<tbody>
<?php	
		foreach ( $otype_roles as $object_type => $roles ) {
			foreach ( $roles as $role_handle => $role) {
				if ( ! empty($role->anon_only) )
					continue;
			
				$assignment_list = array();
				foreach ( $role_bases as $role_basis ) {
					if ( is_array($blog_roles[$role_basis]) && isset($blog_roles[$role_basis][$role_handle]) ) {
						$assignment_names = array_intersect_key($agent_names[$role_basis], $blog_roles[$role_basis][$role_handle]);
						$assignment_list[$role_basis] = "<span class='$role_basis-csv'>" . $agent_list_prefix[$role_basis]
						. ScoperAdminUI::role_assignment_list($blog_roles[$role_basis][$role_handle], $assignment_names, $role_basis)
						. '</span>';
					}
				}
				
				$assignment_list = implode( '&nbsp;&nbsp;', $assignment_list);
				
				// retain previous selections in case of error ( user forgets to select groups/users )
				$val = $role_codes[$role_handle];
				$id = "$role_handle";
				$checked = ( $err && isset( $_POST['roles'] ) && in_array( $val, $_POST['roles'] ) ) ? 'checked="checked"' : '';
	
				$skip_if_val = REMOVE_ASSIGNMENT_RS;

				// Does current user have this role blog-wide?
				$is_admin_module = isset($otype_source[$object_type]) ? $otype_source[$object_type] : '';
				if ( is_administrator_rs($is_admin_module) || array_intersect_key( array($role_handle=>1), $current_user->blog_roles) ) {
					$checked = ( $err && isset($_POST['roles']) && in_array($vals[$role_handle], $_POST['roles']) ) ? 'checked="checked"' : '';
					$skip_if_val = REMOVE_ASSIGNMENT_RS;
					$js_call = "agp_uncheck('" . implode(',', array_keys($roles)) . "',this.id,'assign_for','$skip_if_val');";
					$checkbox = "<input type='checkbox' name='roles[]' id='$id' value='$val' $checked onclick=\"$js_call\" />";
					$label = "<label for='$id'>" . str_replace(' ', '&nbsp;', $role->display_name) . "</label>";
				} else {
					$checkbox = '';
					$label = str_replace(' ', '&nbsp;', $role->display_name);
				}
				
				if ( ! isset($blog_roles[ROLE_BASIS_USER][$role_handle]) && ! isset($blog_roles[ROLE_BASIS_GROUPS][$role_handle]) )
					$role_class = " no-role";
				elseif ( ! isset($blog_roles[ROLE_BASIS_USER][$role_handle]) )
					$role_class = " no-user";
				elseif ( ! isset($blog_roles[ROLE_BASIS_GROUPS][$role_handle]) )
					$role_class = " no-groups";
				else
					$role_class = '';

				echo "\r\n"
					. "<tr class='{$row_class}{$role_class}'>"
					. "<td>$checkbox</td>"
					. "<td>$label</td>"
					. "<td>$assignment_list</td>"
					. "</tr>";
				
				$row_class = ( 'alternate' == $row_class ) ? 'rs-backwhite' : 'alternate';
			
			} // end foreach role
		} // foreach otype_role (distinguish object roles from term roles)
		
		echo '</tbody></table>';
		echo '</li></ul>';
		echo '<br />';
	} // end foreach object_type
} // end foreach data source

echo '<a href="#scoper_submit">' . __('top', 'scoper') . '</a>';

?>
</form>
</div>