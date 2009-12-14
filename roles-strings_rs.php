<?php

class ScoperRoleStrings {

	function get_display_name( $role_handle, $context = '' ) {
		switch( $role_handle ) {
			case 'rs_post_reader' :
				$str = _x('Post Reader', 'role', 'scoper');
				break;
			case 'rs_private_post_reader' :
				// We want the object-assigned reading role to enable the user/group regardless of post status setting.
				// But we don't want the caption to imply that assigning this object role MAKES the post_status private
				// Also want the "role from other scope" indication in post edit UI to reflect the post's current status
				$str = ( ( OBJECT_UI_RS == $context ) && ! defined( 'DISABLE_OBJSCOPE_EQUIV_' . $role_handle ) ) ? _x('Post Reader', 'role', 'scoper') : _x('Private Post Reader', 'role', 'scoper');
				break;
			case 'rs_post_contributor' :
				$str = _x('Post Contributor', 'role', 'scoper');
				break;
			case 'rs_post_author' :
				$str = _x('Post Author', 'role', 'scoper');
				break;
			case 'rs_post_revisor' :
				$str = _x('Post Revisor', 'role', 'scoper');
				break;
			case 'rs_post_editor' :
				if ( defined( 'SCOPER_PUBLISHER_CAPTION' ) )
					$str = _x('Post Publisher', 'role', 'scoper');
				else
					$str = _x('Post Editor', 'role', 'scoper');
				break;
			case 'rs_page_reader' :
				$str = _x('Page Reader', 'role', 'scoper');
				break;
			case 'rs_private_page_reader' :
				$str = ( OBJECT_UI_RS == $context ) ? _x('Page Reader', 'role', 'scoper') : _x('Private Page Reader', 'role', 'scoper');
				break;
			case 'rs_page_associate' :
				$str = _x('Page Associate', 'role', 'scoper');
				break;
			case 'rs_page_contributor' :
				$str = _x('Page Contributor', 'role', 'scoper');
				break;
			case 'rs_page_author' :
				$str = _x('Page Author', 'role', 'scoper');
				break;
			case 'rs_page_revisor' :
				$str = _x('Page Revisor', 'role', 'scoper');
				break;
			case 'rs_page_editor' :
				if ( defined( 'SCOPER_PUBLISHER_CAPTION' ) )
					$str = _x('Page Publisher', 'role', 'scoper');
				else
					$str = _x('Page Editor', 'role', 'scoper');
				break;
			case 'rs_link_editor' :
				$str = _x('Link Admin', 'role', 'scoper');
				break;
			case 'rs_category_manager' :
				$str = _x('Category Manager', 'role', 'scoper');
				break;
			case 'rs_group_manager' :
				$str = _x('Group Manager', 'role', 'scoper');
				break;
			default :
				$str = '';
		} // end switch
		
		return apply_filters( 'role_display_name_rs', $str, $role_handle );			
	}
	
	function get_abbrev( $role_handle, $context = '' ) {
		switch( $role_handle ) {
			case 'rs_post_reader' :
			case 'rs_page_reader' :
				$str = _x('Readers', 'role', 'scoper');
				break;
			case 'rs_private_post_reader' :
			case 'rs_private_page_reader' :
				$str = ( ( OBJECT_UI_RS == $context ) && ! defined( 'DISABLE_OBJSCOPE_EQUIV_' . $role_handle ) ) ? _x('Readers', 'role', 'scoper') : _x('Private Readers', 'role', 'scoper');
				break;
			case 'rs_post_contributor' :
			case 'rs_page_contributor' :
				$str = _x('Contributors', 'role', 'scoper');
				break;
			case 'rs_post_author' :
			case 'rs_page_author' :
				$str = _x('Authors', 'role', 'scoper');
				break;
			case 'rs_post_revisor' :
			case 'rs_page_revisor' :
				$str = _x('Revisors', 'role', 'scoper');
				break;
			case 'rs_post_editor' :
			case 'rs_page_editor' :
				if ( defined( 'SCOPER_PUBLISHER_CAPTION' ) )
					$str = _x('Publishers', 'role', 'scoper');
				else
					$str = _x('Editors', 'role', 'scoper');
				break;
			case 'rs_page_associate' :
				$str = _x('Associates', 'role', 'scoper');
				break;
			
			case 'rs_link_editor' :
				$str = _x('Admins', 'role', 'scoper');
				break;
			case 'rs_category_manager' :
			case 'rs_group_manager' :
				$str = _x('Managers', 'role', 'scoper');
				break;
			default :
				$str = '';
		} // end switch
		
		return apply_filters( 'role_abbrev_rs', $str, $role_handle );
	}
	
	function get_micro_abbrev( $role_handle, $context = '' ) {
		switch( $role_handle ) {
			case 'rs_post_reader' :
			case 'rs_page_reader' :
				$str = _x('Reader', 'role', 'scoper');
				break;
			case 'rs_private_post_reader' :
			case 'rs_private_page_reader' :
				$str = ( ( OBJECT_UI_RS == $context ) && ! defined( 'DISABLE_OBJSCOPE_EQUIV_' . $role_handle ) ) ? _x('Reader', 'role', 'scoper') : _x('Pvt Reader', 'role', 'scoper');
				break;
			case 'rs_post_contributor' :
			case 'rs_page_contributor' :
				$str = _x('Contrib', 'role', 'scoper');
				break;
			case 'rs_post_author' :
			case 'rs_page_author' :
				$str = _x('Author', 'role', 'scoper');
				break;
			case 'rs_post_revisor' :
			case 'rs_page_revisor' :
				$str = _x('Revisor', 'role', 'scoper');
				break;
			case 'rs_post_editor' :
			case 'rs_page_editor' :
				if ( defined( 'SCOPER_PUBLISHER_CAPTION' ) )
					$str = _x('Publisher', 'role', 'scoper');
				else
					$str = _x('Editor', 'role', 'scoper');
				break;
			case 'rs_page_associate' :
				$str = _x('Assoc', 'role', 'scoper');
				break;
			
			case 'rs_link_editor' :
				$str = _x('Admin', 'role', 'scoper');
				break;
			case 'rs_category_manager' :
			case 'rs_group_manager' :
				$str = _x('Manager', 'role', 'scoper');
				break;
			default :
				$str = '';
		} // end switch
		
		return apply_filters( 'role_micro_abbrev_rs', $str, $role_handle );
	}
} // end class
?>