<?php
if( basename(__FILE__) == basename($_SERVER['SCRIPT_FILENAME']) )
	die();

	function determine_role_usage_rs( $src_name = 'post', $listed_ids = '' ) {
		global $scoper, $wpdb;
		static $checked_ids;
		
		if ( 'post' != $src_name )
			return;
		
		if ( ! is_array($checked_ids) )
			$checked_ids = array();
		
		if ( empty($listed_ids) ) {
			if ( ! empty($scoper->listed_ids[$src_name]) )
				$listed_ids = $scoper->listed_ids[$src_name];
			else
				return;
		}

		if ( empty($checked_ids[$src_name]) )
			$checked_ids[$src_name] = array();
		else {
			if ( ! array_diff( $checked_ids[$src_name], $listed_ids ) )
				return;
		}
		
		$checked_ids[$src_name] = array_merge($checked_ids[$src_name], $listed_ids);
		
		$src = $scoper->data_sources->get($src_name);
		$col_id = $src->cols->id;
		$col_type = ( isset($src->cols->type) ) ? $src->cols->type : '';
		
		$role_type = SCOPER_ROLE_TYPE;
		
		// temp hardcode
		if ( is_admin() ) {
			if ( strpos( $_SERVER['SCRIPT_NAME'], 'p-admin/edit-pages.php' ) )
				$object_types = array( 'page' );
			else
				$object_types = array( 'post' );
		
		} else
			$object_types = array('post', 'page');
	
		// For now, only determine restricted posts if using rs role type.  
		// Backing this out will be more convoluted for WP role type; may need to just list which roles are restricted rather than trying to give an Restricted Read/Edit summary
		if ( 'rs' == SCOPER_ROLE_TYPE) {
			$roles = array();
			if ( is_admin() ) {
				$roles['edit']['post'] = array( 'publish' => 'rs_post_editor', 'private' => 'rs_post_editor', 'draft' => 'rs_post_contributor', 'pending' => 'rs_post_contributor' );
				$roles['edit']['page'] = array( 'publish' => 'rs_page_editor', 'private' => 'rs_page_editor', 'draft' => 'rs_page_contributor', 'pending' => 'rs_page_contributor' );
			
				$roles['read']['post'] = array( 'publish' => 'rs_post_reader', 'private' => 'rs_private_post_reader', 'draft' => 'rs_post_reader', 'pending' => 'rs_post_reader', 'future' => 'rs_post_reader' );
				$roles['read']['page'] = array( 'publish' => 'rs_page_reader', 'private' => 'rs_private_page_reader', 'draft' => 'rs_page_reader', 'pending' => 'rs_page_reader', 'future' => 'rs_page_reader' );
			} else {
				$roles['read']['post'] = array( 'publish' => 'rs_post_reader', 'private' => 'rs_private_post_reader' );
				$roles['read']['page'] = array( 'publish' => 'rs_page_reader', 'private' => 'rs_private_page_reader' );
			}
		}
		
		// which of these results ignore blog role assignments?
		if ( ! empty( $src->uses_taxonomies ) ) {
			$strict_terms = array();
			foreach ($src->uses_taxonomies as $taxonomy) {
				if ( ! $scoper->taxonomies->member_property($taxonomy, 'requires_term') )
					continue;
				
				if ( $strict_terms[$taxonomy] = $scoper->get_restrictions(TERM_SCOPE_RS, $taxonomy ) )
					$scoper->any_restricted_terms = true;
			}

			$join = '';
			$use_term_roles = false;
			foreach ( $object_types as $object_type ) // do the join if any of the object types consider term roles
				if( scoper_get_otype_option( 'use_term_roles', $src_name, $object_type ) ) {
					$use_term_roles = true;
					break;
				}
			
			if ( $use_term_roles ) {
				foreach ($src->uses_taxonomies as $taxonomy) {
					$qvars = $scoper->taxonomies->get_terms_query_vars($taxonomy);
	
					$new_join = " {$qvars->term->table} {$qvars->term->as} ";
					
					if ( ! strpos( $join, $new_join ) )
						$join .= " LEFT JOIN{$new_join}ON {$src->table}.{$src->cols->id} = {$qvars->term->alias}.{$qvars->term->col_obj_id} ";
				}
			}

			if ( $join ) {
				if ( 'rs' == SCOPER_ROLE_TYPE ) {
					foreach ( array_keys($roles) as $op_type ) {
						$status_where = array();
						foreach ( $object_types as $object_type) {
							if ( ! scoper_get_otype_option('use_term_roles', $src_name, $object_type) ) {
								$status_where[$object_type] = "{$src->cols->type} = '$object_type' AND 1=1";
								continue;
							}

							$term_clauses = array();
							foreach ( $roles[$op_type][$object_type] as $status => $check_role ) {
								// generate clause for restricted terms query
								foreach ($src->uses_taxonomies as $taxonomy) {
									if ( ! $scoper->taxonomies->member_property($taxonomy, 'requires_term') )
										continue;

									$qvars = $scoper->taxonomies->get_terms_query_vars($taxonomy);
									$all_terms = $scoper->get_terms($taxonomy, UNFILTERED_RS, COL_ID_RS);

									if ( isset($strict_terms[$taxonomy]['restrictions'][$check_role]) && is_array($strict_terms[$taxonomy]['restrictions'][$check_role]) )
										$loose_terms = array_diff($all_terms, array_keys($strict_terms[$taxonomy]['restrictions'][$check_role]) );

									elseif ( isset($strict_terms[$taxonomy]['unrestrictions'][$check_role]) && is_array($strict_terms[$taxonomy]['unrestrictions'][$check_role]) )
										$loose_terms = array_intersect($all_terms, array_keys( $strict_terms[$taxonomy]['unrestrictions'][$check_role] ) );
									else
										$loose_terms = $all_terms;

									if ( ! $loose_terms )  // no terms in this taxonomy honor blog-wide assignment of the pertinent role
										continue; 	
									elseif ( $loose_terms == $all_terms ) {  // all terms in this taxonomy honor blog-wide assignment of the pertinent role
										$term_clauses[$status] = '1=1';
										break;
									} else
										$term_clauses[$status] []= " {$qvars->term->alias}.{$qvars->term->col_id} IN ('" . implode("', '", $loose_terms) . "')";
								}

								if ( isset($term_clauses[$status]) )	// status='status_val' AND ( (taxonomy 1 loose terms clause) OR (taxonomy 2 loose terms clause) ...
									$status_where[$object_type][$status] = " {$src->cols->status} = '$status' AND ( " . agp_implode(' ) OR ( ', $term_clauses[$status], ' ( ', ' ) ') . " )";

							} // end foreach statuses

							if ( isset($status_where[$object_type]) ) // object_type='type_val' AND ( (status 1 clause) OR (status 2 clause) ...
								$status_where[$object_type] = " {$src->cols->type} = '$object_type' AND ( " . agp_implode(' ) OR ( ', $status_where[$object_type], ' ( ', ' ) ') . " )";
						} // end foreach object_types

						if ( $status_where ) {
							$where = ' AND (' . agp_implode(' ) OR ( ', $status_where, ' ( ', ' ) ') . ' )';
							$where .= " AND {$src->table}.$col_id IN ('" . implode("', '", array_keys($listed_ids)) . "')";
							
							$query = "SELECT DISTINCT $col_id FROM $src->table $join WHERE 1=1 $where";
							
							if ( $unrestricted_ids = scoper_get_col($query) )
								$restricted_ids = array_diff_key( $listed_ids, array_flip($unrestricted_ids) );
							else
								$restricted_ids = $listed_ids;
								
							foreach ( array_keys($restricted_ids) as $id ) {
								$scoper->termscoped_ids[$src_name][$id][$op_type] = true;
								$scoper->restricted_ids[$src_name][$id][$op_type] = true;
							}
						}
					} // end foreach op_type (read/edit)
				} else // endif rs role type
					$qvars = $scoper->taxonomies->get_terms_query_vars($taxonomy);

				// query for term role assignments
				if ( is_admin() && ! empty($qvars) ) {
					// this assumes shared taxonomy schema for all data source's taxonomies; will need to change to support non-post data sources
					$tx_in = "'" . implode("', '", $src->uses_taxonomies) . "'";
				
					if ( $src_roles = $scoper->role_defs->get_matching($role_type, 'post', $object_types) ) {
						$otype_role_names = array();
						foreach ( array_keys($src_roles) as $role_handle )
							$otype_role_names []= $src_roles[$role_handle]->name;

						$role_clause = "AND uro.role_name IN ('" . implode("', '", $otype_role_names) . "')";

						$join_assigned = $join . " INNER JOIN $wpdb->user2role2object_rs AS uro ON uro.obj_or_term_id = {$qvars->term->alias}.{$qvars->term->col_id}"
												. " AND uro.scope = 'term' AND uro.role_type = '$role_type' $role_clause AND uro.src_or_tx_name IN ($tx_in)";

						$where = " AND {$src->table}.$col_id IN ('" . implode("', '", array_keys($listed_ids)) . "')";
						$select_type = ( $col_type ) ? "$col_type," : '';

						$query = "SELECT DISTINCT $col_id, $select_type uro.role_name FROM $src->table $join_assigned WHERE 1=1 $where";

						$role_results = scoper_get_results($query);

						$otype_roles = array();
						foreach ( $role_results as $row ) {
							$role_handle = scoper_get_role_handle($row->role_name, $role_type);

							// filter role handles by object type in case posts and pages are sharing categories
							if ( $col_type ) {
								$object_type = $row->$col_type;

								if ( ! isset($otype_roles[$row->$col_type]) )
									$otype_roles[$object_type] = $scoper->role_defs->get_matching($role_type, 'post', $object_type);

								if ( ! isset($otype_roles[$object_type][$role_handle]) )
									continue;
							}

							$scoper->have_termrole_ids[$src_name][$row->$col_id][$role_handle] = true;
						}
					}
				}
				
			} // endif join clause was constructed
		} // endif this data source uses taxonomies
		

		if ( 'rs' == SCOPER_ROLE_TYPE ) {
			// which of these results ignore blog AND term role assignments?
			if ( $objscope_objects = $scoper->get_restrictions(OBJECT_SCOPE_RS, $src_name) )
				$scoper->any_restricted_objects = true;
				
			foreach ( array_keys($roles) as $op_type ) {
			
				foreach ( $object_types as $object_type) {
					if ( ! scoper_get_otype_option('use_object_roles', $src_name, $object_type) )
						continue;
	
					if ( is_array($roles[$op_type][$object_type]) ) {
						foreach ( array_keys($listed_ids) as $id ) {
							foreach ( $roles[$op_type][$object_type] as $check_role ) {
								// If a restriction is set for this object and role, 
								// OR if the role is default-restricted with no unrestriction for this object...
								if ( isset($objscope_objects['restrictions'][$check_role][$id])
								|| ( isset($objscope_objects['unrestrictions'][$check_role]) && is_array($objscope_objects['unrestrictions'][$check_role]) && ! isset($objscope_objects['unrestrictions'][$check_role][$id]) ) ) {
									$scoper->objscoped_ids[$src_name][$id][$op_type] = true;
									$scoper->restricted_ids[$src_name][$id][$op_type] = true;
								}
							}
						} //end foreach listed ids
					} // endif any applicable roles defined
					
				} // end forach object type
			} // end foreach op_type (read/edit)
		}
		
		// query for object role assignments
		if ( is_admin() ) {
			if ( $scoper->role_defs->get_applied_object_roles() ) {
				$scoper->any_object_roles = true;
				
				$join_assigned = " INNER JOIN $wpdb->user2role2object_rs AS uro ON uro.obj_or_term_id = {$src->table}.$col_id"
										. " AND uro.src_or_tx_name = '$src_name' AND uro.scope = 'object' AND uro.role_type = '$role_type'";
				
				$where = " AND {$src->table}.$col_id IN ('" . implode("', '", array_keys($listed_ids)) . "')";
				
				$query = "SELECT DISTINCT $col_id, uro.role_name FROM $src->table $join_assigned WHERE 1=1 $where";
				
				$role_results = scoper_get_results($query);

				foreach ( $role_results as $row ) {
					$role_handle = scoper_get_role_handle($row->role_name, $role_type);
					$scoper->have_objrole_ids[$src_name][$row->$col_id][$role_handle] = true;
				}
			}
		}
	}

?>