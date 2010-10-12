<?php
// include this (from WP 3.0 code) so hardway terms/pages filters can use it on older WP
if ( ! function_exists( 'wp_parse_id_list' ) ) :
function wp_parse_id_list( $list ) {
	if ( !is_array($list) )
		$list = preg_split('/[\s,]+/', $list);

	return array_unique(array_map('absint', $list));
}
endif;


if ( ! function_exists( 'taxonomy_exists' ) ) :
function taxonomy_exists( $taxonomy ) {
	return is_taxonomy( $taxonomy );
}
endif;


// back compat for older MU versions
if ( IS_MU_RS && ! function_exists( 'is_super_admin' ) ) :
function is_super_admin() {
	return is_site_admin();
}
endif;

if ( ! function_exists('esc_attr') ) :
function esc_attr( $text ) {
	$safe_text = wp_check_invalid_utf8( $text );
	$safe_text = wp_specialchars( $safe_text, ENT_QUOTES );
	return apply_filters( 'attribute_escape', $safe_text, $text );
}
endif;

if ( ! function_exists('esc_html') ) :
function esc_html( $text ) {
	$safe_text = wp_check_invalid_utf8( $text );
	$safe_text = wp_specialchars( $safe_text, ENT_QUOTES );
	return apply_filters( 'esc_html', $safe_text, $text );
	return $text;
}
endif;


if ( awp_ver( '2.9' ) && ! awp_ver( '3.0' ) ) :
function get_custom_taxonomies_rs() {
	$arr = array();
	global $wp_taxonomies;
	
	foreach( array_keys($wp_taxonomies) as $taxonomy ) {
		if ( ! empty( $wp_taxonomies[$taxonomy]->public ) && empty( $wp_taxonomies[$taxonomy]->_builtin ) )
			$arr[$taxonomy] = $wp_taxonomies[$taxonomy];
	}
		
	return $arr;
}
endif;
?>