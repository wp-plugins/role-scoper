<?php
if( basename(__FILE__) == basename($_SERVER['SCRIPT_FILENAME']) )
	die();

global $wpdb;
require_once(ABSPATH . 'wp-admin/upgrade-functions.php');


function scoper_db_setup($last_db_ver) {
	scoper_update_schema($last_db_ver);
	
	global $scoper_db_setup_done;
	$scoper_db_setup_done = 1;
}

function scoper_update_schema($last_db_ver) {
	global $wpdb;
	
	/*--- Groups table def 
		(complicated because (a) we support usage of pre-existing group table from other app
		 					 (b) existing group table may include a subset of our required columns
		 					 (c) existing group table may require authenticated/unauthenticated default group flag to be stored in two different columns 
	*/
	
	//first define column(s) to create for default groups
	$cols = array();
	$cols[$wpdb->groups_id_col] = "bigint(20) NOT NULL auto_increment";
	$cols[$wpdb->groups_name_col] = "text NOT NULL";
	$cols[$wpdb->groups_descript_col] = "text NOT NULL";
	$cols[$wpdb->groups_homepage_col] = "varchar(128) NOT NULL default ''";
	$cols[$wpdb->groups_meta_id_col] = "varchar(32) NOT NULL default ''";
	
	if($tables = $wpdb->get_col('SHOW TABLES;'))
		foreach($tables as $table)
			if ($table == $wpdb->groups_rs)
				break;	
				
	if ( $table != $wpdb->groups_rs ) { //group table doesn't already exist
		 $query = "CREATE TABLE IF NOT EXISTS " . $wpdb->groups_rs . ' (';
		 
		 foreach ($cols as $colname => $typedef)
		 	$query .= $colname . ' ' . $typedef . ',';

		$query .= " PRIMARY KEY ($wpdb->groups_id_col),"
				. " KEY meta_id ($wpdb->groups_meta_id_col, $wpdb->groups_id_col) );";
	
		$wpdb->query($query);
		
	} else {
		//specified group table exists already, so do not alter any existing columns
		// (we're not fussy about data types since the joins to these tables are infrequent and/or buffered, 
		// but other app(s) might be fussy).
		
		$tablefields = $wpdb->get_col("DESC $wpdb->groups_rs", 0);
		
		if ( $add_cols = array_diff_key( $cols, array_flip($tablefields) ) ) {
			foreach ( $add_cols as $requiredcol_name => $requiredcol_typedef ) {
				if ( $requiredcol_name == $wpdb->groups_id_col ) // don't try to add id column
					continue;

				$wpdb->query("ALTER TABLE $wpdb->groups_rs ADD COLUMN $requiredcol_name $requiredcol_typedef");	
				
				if ( $wpdb->groups_meta_id_col == $requiredcol_name )
					$wpdb->query( "CREATE INDEX meta_id ON $wpdb->groups_rs ($wpdb->groups_meta_id_col, $wpdb->groups_id_col)" );
			}
		}
		
		if ( ! version_compare( $last_db_ver, '1.0.2', '>=') ) {
			// DB version < 1.0.2 used varchar columns, which don't support unicode
			$wpdb->query("ALTER TABLE $wpdb->groups_rs MODIFY COLUMN $wpdb->groups_name_col text NOT NULL");
			$wpdb->query("ALTER TABLE $wpdb->groups_rs MODIFY COLUMN $wpdb->groups_descript_col text NOT NULL");	
		}
	}

	
	// User2Group table def (use existing table from other app if so defined in grp-config.php)
	$cols = array();
	$cols[$wpdb->user2group_gid_col] = "bigint(20) unsigned NOT NULL default '0'";
	$cols[$wpdb->user2group_uid_col] = "bigint(20) unsigned NOT NULL default '0'";
	$cols[$wpdb->user2group_assigner_id_col] = "bigint(20) unsigned NOT NULL default '0'";
				 
	if($tables = $wpdb->get_col('SHOW TABLES;'))
		foreach($tables as $table)
			if ($table == $wpdb->user2group_rs)
				break;	
	
	if ( $table != $wpdb->user2group_rs ) { // table doesn't already exist

		$query = "CREATE TABLE IF NOT EXISTS " . $wpdb->user2group_rs . ' (';
		 
		foreach ($cols as $colname => $typedef)
			$query .= $colname . ' ' . $typedef . ',';
		 
		$query .= "PRIMARY KEY user2group ($wpdb->user2group_uid_col, $wpdb->user2group_gid_col));";
		
		$wpdb->query($query);
		
	} else {

		// if existing table was found but specified groupid and userid columns are invalid, bail
		$tablefields = $wpdb->get_col("DESC $wpdb->user2group_rs", 0);
		
		foreach ($tablefields as $column) if ($column == $wpdb->user2group_gid_col) break;	
		if ($column != $wpdb->user2group_gid_col)
			wp_die ( sprintf( 'Database config error: specified ID column (%1$s) not found in table %2$s', $wpdb->user2group_gid_col, $wpdb->user2group_rs ) );
		
		foreach ($tablefields as $column) if ($column == $wpdb->user2group_uid_col) break;	
		if ($column != $wpdb->user2group_uid_col)
			wp_die ( sprintf( 'Database config error: specified ID column (%1$s) not found in table %2$s', $wpdb->user2group_uid_col, $wpdb->user2group_rs ) );

		foreach ($cols as $requiredcol_name => $requiredcol_typedef) {
			foreach ($tablefields as $col_name)
				if ($requiredcol_name == $col_name) break;
				
			if ($requiredcol_name != $col_name)
				//column was not found, so create it
				$wpdb->query("ALTER TABLE $wpdb->user2group_rs ADD COLUMN $requiredcol_name $requiredcol_typedef");
		}	
	}
	
	$tabledefs='';

	/*  user2role2object_rs: 
		
		(scope == 'object' ) => for the specified object, all users in specified group have all caps in specified role
					- OR -
		(scope == 'term' ) => for all entities for which the specified object is a category, all users in specified group have all caps in specified role
	
		(assign_for = 'children' or 'both' ) => new children of the specified object inherit this object_role_name
	
	Abstract object type to support group control of new content-specific roles without revising db schema
	
	note: Term roles are retrieved and buffered into memory for the current user at login.
	*/
	
	// note: dbDelta requires two spaces after PRIMARY KEY, no spaces between KEY columns
	$tabledefs .= "CREATE TABLE $wpdb->user2role2object_rs (
	 assignment_id bigint(20) unsigned NOT NULL auto_increment,
	 user_id bigint(20) unsigned NULL,
	 group_id bigint(20) unsigned NULL,
	 role_name varchar(32) NOT NULL default '',
	 role_type enum('rs', 'wp', 'wp_cap') NOT NULL default 'rs',
	 scope enum('blog', 'term', 'object') NOT NULL,
	 src_or_tx_name varchar(32) NOT NULL default '',
	 obj_or_term_id bigint(20) unsigned NOT NULL default '0',
	 assign_for enum('entity', 'children', 'both') NOT NULL default 'entity',
	 inherited_from bigint(20) unsigned NOT NULL default '0',
	 assigner_id bigint(20) unsigned NOT NULL default '0',
	 	PRIMARY KEY  (assignment_id),
	 	KEY role2obj (scope,assign_for,role_type,role_name,src_or_tx_name,obj_or_term_id),
	 	KEY role2agent (assign_for,scope,role_type,role_name,group_id,user_id),
	 	KEY role (role_type,role_name,scope,assign_for,src_or_tx_name,group_id,user_id,obj_or_term_id),
	 	KEY role_assignments (role_name,role_type,scope,assign_for,src_or_tx_name,group_id,user_id,obj_or_term_id,inherited_from,assignment_id)
	);
	";
	
	$tabledefs .= "CREATE TABLE $wpdb->role_scope_rs (
	 requirement_id bigint(20) NOT NULL auto_increment,
	 role_name varchar(32) NOT NULL default '',
	 role_type enum('rs', 'wp', 'wp_cap') NOT NULL default 'rs',
	 topic enum('blog', 'term', 'object') NOT NULL,
	 src_or_tx_name varchar(32) NOT NULL default '',
	 obj_or_term_id bigint(20) NOT NULL default '0',
	 max_scope enum('blog', 'term', 'object') NOT NULL,
	 require_for enum('entity', 'children', 'both') NOT NULL default 'entity',
	 inherited_from bigint(20) NOT NULL default '0',
	 	PRIMARY KEY  (requirement_id),
	 	KEY role_scope (max_scope,topic,require_for,role_type,role_name,src_or_tx_name,obj_or_term_id),
	 	KEY role_scope_assignments (max_scope,topic,require_for,role_type,role_name,src_or_tx_name,obj_or_term_id,inherited_from,requirement_id)
	);
	";
	
	// apply all table definitions
	dbDelta($tabledefs);
	
} //end update_schema function


function scoper_update_supplemental_schema($table_name) {
	global $wpdb;

	require_once(ABSPATH . 'wp-admin/upgrade-functions.php');

	if ( 'data_rs' == $table_name ) {
		$tabledefs .= "CREATE TABLE {$wpdb->prefix}{$table_name} (
		 rs_id bigint(20) NOT NULL auto_increment,
		 topic enum('term', 'object') default 'object',
		 src_or_tx_name varchar(32) NOT NULL default '',
		 object_type varchar(32) NOT NULL default '',
		 actual_id bigint(20) NOT NULL default '0',
		 name text NOT NULL,
		 parent bigint(20) NOT NULL default '0',
		 owner bigint(20) NOT NULL default '0',
		 status varchar(20) NOT NULL default '',
		 	PRIMARY KEY  (rs_id),
		 	KEY actual_id (actual_id,src_or_tx_name,object_type,topic)
		);
		";
		
		// apply all table definitions
		dbDelta($tabledefs);
		
		return true;
	}
}
?>