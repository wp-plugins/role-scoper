<?php
class Relevanssi_Search_Filter_RS {
	var $valid_stati = array();

	function Relevanssi_Search_Filter_RS() {
		$this->valid_stati = array_merge( get_post_stati( array( 'public' => true ) ), get_post_stati( array( 'private' => true ) ) );

		remove_filter( 'relevanssi_post_ok', 'relevanssi_default_post_ok' );
		add_filter( 'relevanssi_post_ok', array( &$this, 'relevanssi_post_ok' ) );
	}

	function relevanssi_post_ok($doc) {
		if ( function_exists('relevanssi_s2member_level') ) {
			if ( relevanssi_s2member_level($doc) == 0 ) return false; // back compat with relevanssi_default_post_ok, in case somebody is also running s2member
		}
		
		$status = relevanssi_get_post_status($doc);

		if ( in_array( $status, $this->valid_stati ) )
			$post_ok = current_user_can( 'read_post', $doc );
		else
			$post_ok = false;

		return $post_ok;
	}
}
?>