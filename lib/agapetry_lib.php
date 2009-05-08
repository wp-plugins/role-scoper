<?php
if( basename(__FILE__) == basename($_SERVER['SCRIPT_FILENAME']) )
	die();
	
// derived from http://us3.php.net/manual/en/ref.array.php#80631
function agp_array_flatten($arr_md, $go_deep = true) { //flattens multi-dim arrays (destroys keys)
    $arr_flat = array(); 
    if ( ! is_array($arr_md) ) return $arr_flat;
    
    foreach ($arr_md as $element) {
       	if ( is_array($element) ) {
       		if ( $go_deep )
           		$arr_flat = array_merge($arr_flat, agp_array_flatten($element));
           	else
           		$arr_flat = array_merge($arr_flat, $element);
        } else
            array_push($arr_flat, $element);
    }
 
    return $arr_flat;
}

function agp_implode( $delim, $arr, $wrap_open, $wrap_close, $array_unique = true, $wrap_single_item = false ) {
	if ( ! is_array($arr) )
		return $arr;

	if ( count($arr) ) {
		if ( $array_unique )
			$arr = array_unique($arr);

		return $wrap_open . implode($delim, $arr) . $wrap_close;
	} else {
		if ( $wrap_single_item )
			return $wrap_open . reset($arr) . $wrap_close;
		else
			return reset($arr);
	}
}

// recursive function to merge two arrays with a specified number of key dimension
// supports absent keys in either array, with arr_custom values taking precedence
function agp_merge_md_array($arr_default, $arr_custom, $key_dimensions = 1, $current_dimension = 1 ) {
	if ( $current_dimension == $key_dimensions )
		return array_merge($arr_default, $arr_custom);
	else {
		$opt_keys = array_merge( array_keys($arr_default), array_keys($arr_custom) );
		foreach ($opt_keys as $key_name) {
			if ( ! isset($arr_custom[$key_name]) ) $arr_custom[$key_name] = array();
			if ( ! isset($arr_default[$key_name]) ) $arr_default[$key_name] = array();
			$arr_custom[$key_name] = agp_merge_md_array($arr_default[$key_name], $arr_custom[$key_name], $key_dimensions, $current_dimension + 1);
		}
		
		return $arr_custom;
	}
}

// adapted from http://us.php.net/manual/en/function.array-unique.php#86210
function agp_array_unique_md($arr) {
	array_walk($arr, create_function('&$value', '$value = serialize($value);'));
	$arr = array_unique($arr);
	array_walk($arr, create_function('&$value', '$value = unserialize($value);'));
	return $arr;
}

function agp_get_lambda_argstring($num_args) {
	if ( $num_args ) {
		$args = array();
		for ( $i = 97; $i < 97 + $num_args; $i++)
			$args[] = '$' . chr($i);
		$arg_str = implode( ', ', $args);
	} else
		$arg_str = '';
		
	return $arg_str;
}

function agp_parse_after_WHERE_11( $request, &$pos_where, &$pos_suffix ) {
	$request_u = strtoupper($request);
	$pos_where = strpos( $request_u, ' WHERE 1=1 ');
	
	if ( ! $pos_where ) {
		$suffix_terms = array(' ORDER BY ', ' GROUP BY ', ' LIMIT ');
		$pos_suffix = strlen($request) + 1;
		foreach ( $suffix_terms as $suffix_term )
			if ( $pos = strrpos($request_u, $suffix_term) )
				if ( $pos < $pos_suffix )
					$pos_suffix = $pos;
		
		if ( $pos_suffix )
			$where =  substr($request, $pos_suffix); 

	} else {
		// note: this will still also contain any orderby/limit/groupby clauses ( okay since we won't append anything to the end )
		$where = substr($request, $pos_where + strlen(' WHERE 1=1 ')); 
	}
	
	return $where;	
}

function agp_string_ends_in($haystack, $needles, $any_substr_pos = false) {
	if ( ! $needles )
		return false;

	if ( ! is_array($needles) )
		$needle = array($needles);
	
	foreach($needles as $needle) {
		$pos = strpos($haystack, $needle);
		if ( is_numeric($pos) && ($any_substr_pos || ( $pos == strlen($haystack) - strlen($needle) ) ) )
			return true;
	}
}

function agp_force_distinct($query) {
	if ( ! $pos_from = strpos($query, ' from ') )
		return $query;
		
	$pos_distinct = strpos($query, ' distinct ');
	if ( $pos_distinct && ( $pos_distinct < $pos_from ) )
		return $query;

	$sel_clause = 'select ';
	$pos_select = strpos($query, $sel_clause);
	$query = substr( $query, 0, $pos_select + strlen($sel_clause) ) . 'DISTINCT ' . substr($query, $pos_select + strlen($sel_clause) );
	return $query;
}

function agp_is_ie6() {
	$agent = strtolower($_SERVER['HTTP_USER_AGENT']);
	
	if ( (false !== strpos($agent, 'msie 6.0') || false !== strpos($agent, 'msie 5.5')) 
	&& ( false === strpos($agent, 'opera') &&  false === strpos($agent, 'firefox') && false === strpos($agent, 'safari') ) )
		return true;
}

?>