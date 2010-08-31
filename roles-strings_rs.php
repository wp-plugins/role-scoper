<?php

class ScoperRoleStrings {

	function get_display_name( $role_handle, $context = '' ) {
		switch( $role_handle ) {
			case 'rs_post_reader' :
				$str = __('Post Reader', 'scoper');
				break;
			case 'rs_private_post_reader' :
				// We want the object-assigned reading role to enable the user/group regardless of post status setting.
				// But we don't want the caption to imply that assigning this object role MAKES the post_status private
				// Also want the "role from other scope" indication in post edit UI to reflect the post's current status
				$str = ( ( OBJECT_UI_RS == $context ) && ! defined( 'DISABLE_OBJSCOPE_EQUIV_' . $role_handle ) ) ? __('Post Reader', 'scoper') : __('Private Post Reader', 'scoper');
				break;
			case 'rs_post_contributor' :
				$str = __('Post Contributor', 'scoper');
				break;
			case 'rs_post_author' :
				$str = __('Post Author', 'scoper');
				break;
			case 'rs_post_revisor' :
				$str = __('Post Revisor', 'scoper');
				break;
			case 'rs_post_editor' :
				if ( defined( 'SCOPER_PUBLISHER_CAPTION' ) )
					$str = __('Post Publisher', 'scoper');
				else
					$str = __('Post Editor', 'scoper');
				break;
			case 'rs_page_reader' :
				$str = __('Page Reader', 'scoper');
				break;
			case 'rs_private_page_reader' :
				$str = ( OBJECT_UI_RS == $context ) && ! defined( 'DISABLE_OBJSCOPE_EQUIV_' . $role_handle ) ? __('Page Reader', 'scoper') : __('Private Page Reader', 'scoper');
				break;
			case 'rs_page_associate' :
				$str = __('Page Associate', 'scoper');
				break;
			case 'rs_page_contributor' :
				$str = __('Page Contributor', 'scoper');
				break;
			case 'rs_page_author' :
				$str = __('Page Author', 'scoper');
				break;
			case 'rs_page_revisor' :
				$str = __('Page Revisor', 'scoper');
				break;
			case 'rs_page_editor' :
				if ( defined( 'SCOPER_PUBLISHER_CAPTION' ) )
					$str = __('Page Publisher', 'scoper');
				else
					$str = __('Page Editor', 'scoper');
				break;
			case 'rs_link_editor' :
				$str = __('Link Admin', 'scoper');
				break;
			case 'rs_category_manager' :
				$str = __('Category Manager', 'scoper');
				break;
			case 'rs_group_manager' :
				$str = __('Group Manager', 'scoper');
				break;
			case 'rs_group_moderator' :
				$str = __('Group Moderator', 'scoper');
				break;
			case 'rs_group_applicant' :
				$str = __('Group Applicant', 'scoper');
				break;
			default :
				$str = '';

				$custom_types = get_post_types( array( '_builtin' => false, 'public' => true ), 'object' );
				
				foreach( $custom_types as $custype ) {
					if ( strpos( $role_handle, "_{$custype->name}_" ) ) {
						$label = $custype->labels->singular_name;
						
						if ( strpos( $role_handle, '_editor' ) )
							$str = ( defined( 'SCOPER_PUBLISHER_CAPTION' ) ) ? sprintf( __( '%s Publisher', 'scoper' ), $label ) : sprintf( __( '%s Editor', 'scoper' ), $label );
						elseif ( strpos( $role_handle, '_revisor' ) )
							$str = sprintf( __( '%s Revisor', 'scoper' ), $label );
						elseif ( strpos( $role_handle, '_author' ) )
							$str = sprintf( __( '%s Author', 'scoper' ), $label );
						elseif ( strpos( $role_handle, '_contributor' ) )
							$str = sprintf( __( '%s Contributor', 'scoper' ), $label );
						elseif ( false !== strpos( $role_handle, 'private_' ) && strpos( $role_handle, '_reader' ) )
							$str = ( ( OBJECT_UI_RS == $context ) && ! defined( 'DISABLE_OBJSCOPE_EQUIV_' . $role_handle ) ) ? sprintf( __( '%s Reader', 'scoper' ), $label ) : sprintf( __( 'Private %s Reader', 'scoper' ), $label );
						elseif ( strpos( $role_handle, '_reader' ) )
							$str = sprintf( __( '%s Reader', 'scoper' ), $label );
					}
				}
				
				$taxonomies = get_taxonomies( array( '_builtin' => false, 'public' => true ), 'object' );
				
				foreach( $taxonomies as $name => $tx_obj ) {
					if ( strpos( $role_handle, "_{$name}_" ) ) {
						if ( strpos( $role_handle, '_manager' ) )
							$str = sprintf( __( '%s Manager', 'scoper' ), $tx_obj->labels->singular_name );
					}
				}
				
		} // end switch
		
		return apply_filters( 'role_display_name_rs', $str, $role_handle );			
	}
	
	function get_abbrev( $role_handle, $context = '' ) {
		if ( strpos( $role_handle, '_reader' ) ) {
			// TODO: support distinct captioning of status-specific object role for other custom statuses
			//if ( ( false !== strpos( $role_handle, 'private_' ) ) && ( OBJECT_UI_RS == $context ) && ! defined( 'DISABLE_OBJSCOPE_EQUIV_' . $role_handle ) )
			//	$str = __('Private Readers', 'scoper');
			//else
				$str = __('Readers', 'scoper');

		} elseif ( strpos( $role_handle, '_contributor' ) )
			$str = __('Contributors', 'scoper');
			
		elseif ( strpos( $role_handle, '_author' ) )
			$str = __('Authors', 'scoper');
			
		elseif ( strpos( $role_handle, '_revisor' ) )
			$str = __('Revisors', 'scoper');
			
		elseif ( strpos( $role_handle, '_editor' ) ) {
			if ( defined( 'SCOPER_PUBLISHER_CAPTION' ) )
				$str = __('Publishers', 'scoper');
			else
				$str = __('Editors', 'scoper');
				
		} elseif ( strpos( $role_handle, '_associate' ) )
			$str = __('Readers', 'scoper');
			
		elseif ( strpos( $role_handle, '_manager' ) )
			$str = __('Managers', 'scoper');
		
		return apply_filters( 'role_abbrev_rs', $str, $role_handle );
	}
	
	function get_micro_abbrev( $role_handle, $context = '' ) {
		switch( $role_handle ) {
			case 'rs_post_reader' :
			case 'rs_page_reader' :
				$str = __('Reader', 'scoper');
				break;
			case 'rs_private_post_reader' :
			case 'rs_private_page_reader' :
				$str = ( ( OBJECT_UI_RS == $context ) && ! defined( 'DISABLE_OBJSCOPE_EQUIV_' . $role_handle ) ) ? __('Reader', 'scoper') : __('Pvt Reader', 'scoper');
				break;
			case 'rs_post_contributor' :
			case 'rs_page_contributor' :
				$str = __('Contrib', 'scoper');
				break;
			case 'rs_post_author' :
			case 'rs_page_author' :
				$str = __('Author', 'scoper');
				break;
			case 'rs_post_revisor' :
			case 'rs_page_revisor' :
				$str = __('Revisor', 'scoper');
				break;
			case 'rs_post_editor' :
			case 'rs_page_editor' :
				if ( defined( 'SCOPER_PUBLISHER_CAPTION' ) )
					$str = __('Publisher', 'scoper');
				else
					$str = __('Editor', 'scoper');
				break;
			case 'rs_page_associate' :
				$str = __('Assoc', 'scoper');
				break;
			
			case 'rs_link_editor' :
				$str = __('Admin', 'scoper');
				break;
			case 'rs_category_manager' :
			case 'rs_group_manager' :
				$str = __('Manager', 'scoper');
				break;
			default :
				$str = '';
		} // end switch
		
		return apply_filters( 'role_micro_abbrev_rs', $str, $role_handle );
	}
} // end class
?>