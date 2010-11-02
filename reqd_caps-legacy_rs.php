<?php

class ScoperReqdCapsLegacy {

	// account for different contexts of get_terms calls 
	// (Scoped roles can dictate different results for front end, edit page/post, manage categories)
	function get_terms_reqd_caps($src_name, $access_name = '') {
		global $current_user;	
	
		if ( ! $this->data_sources->is_member($src_name) )
			return array();
		
		if ( empty($access_name) )
			$access_name = ( is_admin() && strpos($_SERVER['SCRIPT_NAME'], 'p-admin/profile.php') ) ? 'front' : CURRENT_ACCESS_NAME_RS; // hack to support subscribe2 categories checklist
			
		if ( ! $arr = $this->data_sources->member_property($src_name, 'terms_where_reqd_caps', $access_name ) )
			return array();

		if ( ! is_array($arr) )
			$arr = array($arr);
		
		$full_uri = urldecode($_SERVER['REQUEST_URI']);
		
		$matched = array();
		foreach ( $arr as $uri_sub => $reqd_caps )	// if no uri substrings match, use default (nullstring key)
			if ( ( $uri_sub && strpos($full_uri, $uri_sub) ) || ( ! $uri_sub && ! $matched ) )
				$matched = $reqd_caps;
		
		// replace matched caps with status-specific equivalent if applicable
		if ( $matched ) {
			if ( $object_id = $this->data_sources->detect('id', $src_name) ) {
				$owner_id = $this->data_sources->get_from_db('owner', $src_name, $object_id);
				$cap_attribs = ( $owner_id == $current_user->ID ) ? '' : array('others'); 
				
				$status = $this->data_sources->detect('status', $src_name, $object_id);

				if ( $status || $cap_attribs )
					foreach ( $matched as $object_type => $otype_caps )
						foreach ( $otype_caps as $cap_name )
							if ( $cap_def = $this->cap_defs->get($cap_name) )
								if ( $other_defs = $this->cap_defs->get_matching($src_name, $cap_def->object_type, $cap_def->op_type, STATUS_ANY_RS) )
									
									foreach ( $other_defs as $other_cap_name => $other_def )
										if ( $other_cap_name != $cap_name )
										
											if ( ( ! $other_def->status || ( $other_def->status == $status ) )
											&& ( empty($other_def->attributes) || ( $other_def->attributes == $cap_attribs ) ) )
												$matched[] = $other_cap_name;
			}
		}
		
		return $matched;
	}

}

?>