<?php
if( basename(__FILE__) == basename($_SERVER['SCRIPT_FILENAME']) )
	die();

class Scoper_Submittee {

	function handle_submission($action) {
		if ( 'flush' == $action ) {
			wpp_cache_flush();
			return;	
		}
		
		if ( false === strpos($_GET["page"], SCOPER_FOLDER) )
			return;
		
		if ( empty($_POST['rs_submission_topic']) )
			return;

		if ( 'options' == $_POST['rs_submission_topic'] ) {
			$method = "{$action}_options";
			if ( method_exists( $this, $method ) )
				call_user_func( array($this, $method) );
			
			$method = "{$action}_realm";
			if ( method_exists( $this, $method ) )
				call_user_func( array($this, $method) );
			
			if ( isset($_POST['rs_role_defs']) && empty($_POST['rs_defaults']) )
				add_action( 'init', array(&$this, 'update_rs_role_defs'), 20 );	// this must execute after other plugins have added rs config filters
		}

		scoper_refresh_options();
	}
	
	function update_options() {
		check_admin_referer( 'scoper-update-options' );
	
		$this->update_page_options();
		$this->update_page_otype_options();
	}
	
	function default_options() {
		check_admin_referer( 'scoper-update-options' );
		
		$reviewed_options = explode(',', $_POST['all_options']);
		foreach ( $reviewed_options as $option_name )
			delete_option("scoper_{$option_name}");
			
		$reviewed_otype_options = explode(',', $_POST['all_otype_options']);
		foreach ( $reviewed_otype_options as $option_name )
			delete_option("scoper_{$option_name}");

		delete_option('scoper_disabled_role_caps');
		delete_option('scoper_user_role_caps');
	}
	
	function update_realm() {
		check_admin_referer( 'scoper-update-options' );
	
		$disabled = array();
		$access_names = explode(',', $_POST['all_access_types'] );
		foreach ( $access_names as $access_name )
			$disabled[$access_name] = empty( $_POST['access_types-' . $access_name ] );	
		update_option('scoper_disabled_access_types', $disabled);
		
		$enable_taxonomies = array();
		
		//$reviewed_wp_taxonomies = explode( ',', $_POST['all_wp_taxonomies'] );
		$selected_wp_taxonomies = isset($_POST['enable_wp_taxonomies']) ? $_POST['enable_wp_taxonomies'] : array();
		
		if ( isset($_POST['locked_wp_taxonomies']) ) {
			$locked_wp_taxonomies  = explode( ',', $_POST['locked_wp_taxonomies'] );
			$selected_wp_taxonomies = array_merge( $selected_wp_taxonomies, $locked_wp_taxonomies);
		}
		
		$selected_wp_taxonomies = array_fill_keys($selected_wp_taxonomies, 1);
		update_option('scoper_enable_wp_taxonomies', $selected_wp_taxonomies);
		
		$this->update_page_otype_options();
	}
	
	// todo: hidden $_POST['all_options']
	function default_realm() {
		check_admin_referer( 'scoper-update-options' );
		
		delete_option( 'scoper_enable_wp_taxonomies' );
		delete_option( 'scoper_disabled_access_types' );

		$reviewed_otype_options = explode(',', $_POST['all_otype_options']);
		foreach ( $reviewed_otype_options as $option_name )
			delete_option("scoper_{$option_name}");
	}
	
	function update_page_options() {
		global $scoper_role_types;
		
		$reviewed_options = explode(',', $_POST['all_options']);
		foreach ( $reviewed_options as $option_basename ) {
			$value = isset($_POST[$option_basename]) ? $_POST[$option_basename] : '';
			
			if ( 'role_type' == $option_basename )
				$value = $scoper_role_types[$value];
			
			$option_name = 'scoper_' . $option_basename;
			if ( ! is_array($value) )
				$value = trim($value);
			$value = stripslashes_deep($value);
			
			update_option($option_name, $value);
		}
	}
	
	function update_page_otype_options() {
		global $scoper_default_otype_options;
		
		$reviewed_otype_options = explode(',', $_POST['all_otype_options']);
		$otype_option_vals = array();
		foreach ( $reviewed_otype_options as $option_basename ) {
			if ( isset( $scoper_default_otype_options[$option_basename] ) ) {
				if ( $opt = $scoper_default_otype_options[$option_basename] ) {
					foreach ( array_keys($opt) as $src_otype ) {
						$postvar = $option_basename . '-' . str_replace(':', '_', $src_otype);
						$value = isset($_POST[$postvar]) ? $_POST[$postvar] : '';
						if ( ! is_array($value) ) 
							$value = trim($value);
						
						$otype_option_vals[ $option_basename ] [ $src_otype ] = stripslashes_deep($value);
					}
				}
			}
		}
		
		foreach ( $otype_option_vals as $option_basename => $value )
			update_option('scoper_' . $option_basename , $value);
	}

	function update_rs_role_defs() {
		$default_role_caps = apply_filters('define_role_caps_rs', scoper_core_role_caps() );

		$cap_defs = new WP_Scoped_Capabilities();
		$cap_defs = apply_filters('define_capabilities_rs', $cap_defs);
		$cap_defs->add_member_objects( scoper_core_cap_defs() );

		global $scoper, $scoper_role_types;
		$role_defs = new WP_Scoped_Roles($cap_defs, $scoper_role_types);
		$role_defs->add_member_objects( scoper_core_role_defs() );
		$role_defs = apply_filters('define_roles_rs', $role_defs);

		$disable_caps = array();
		$add_caps = array();
		
		foreach ( $default_role_caps as $role_handle => $default_caps ) {
			if ( $role_defs->member_property($role_handle, 'no_custom_caps') || $role_defs->member_property($role_handle, 'anon_user_blogrole') )
				continue;

			$posted_set_caps = ( empty($_POST["{$role_handle}_caps"]) ) ? array() : $_POST["{$role_handle}_caps"];

			// html IDs have any spaces stripped out of cap names.  Replace them for processing.
			$set_caps = array();
			foreach ( $posted_set_caps as $cap_name ) {
				if ( strpos( $cap_name, ' ' ) )
					$set_caps []= str_replace( '_', ' ', $cap_name );
				else
					$set_caps []= $cap_name;
			}
			
			// deal with caps which are locked into role, therefore displayed as a disabled checkbox and not included in $_POST
			foreach ( array_keys($default_caps) as $cap_name ) {
				if ( ! in_array($cap_name, $set_caps) && $cap_defs->member_property($cap_name, 'no_custom_remove') )
					$set_caps []= $cap_name;
			}

			$disable_caps[$role_handle] = array_fill_keys( array_diff( array_keys($default_caps), $set_caps ), true);
			$add_caps[$role_handle] = array_fill_keys( array_diff( $set_caps, array_keys($default_caps) ), true);
		}

		update_option('scoper_disabled_role_caps', $disable_caps);
		update_option('scoper_user_role_caps', $add_caps);
		
		$scoper->load_role_caps();
	}
}
	
	
?>