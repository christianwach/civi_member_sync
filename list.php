<?php 

// access database object
global $wpdb;

// get membership data
$membership_type = $this->civi->get_types();
$membership_status = $this->civi->get_statuses();

// define admin URLs
$rules_url = menu_page_url( 'civi_member_sync_rules', false ); 
$manual_sync_url = menu_page_url( 'civi_member_sync_manual_sync', false ); 

// get tabular data
$table_name = $wpdb->prefix . 'civi_member_sync';
$select = $wpdb->get_results( "SELECT * FROM $table_name" );

?>
<div id="icon-options-general" class="icon32"><br/></div>

<div class="wrap">
    
<h2>Association Rules <a class="add-new-h2" href="<?php echo $rules_url; ?>">Add Association Rule</a> <a class="add-new-h2" href="<?php echo $manual_sync_url; ?>">Manual Synchronize</a></h2> 

</div>

<table cellspacing="0" class="wp-list-table widefat fixed users">

	<thead>
		<tr>
			<th class="manage-column column-role" id="role" scope="col">Civi Membership Type</th>
			<th class="manage-column column-role" id="role" scope="col">WordPress Role</th>
			<th class="manage-column column-role" id="role" scope="col">Current Codes</th>
			<th class="manage-column column-role" id="role" scope="col">Expired Codes</th>
			<th class="manage-column column-role" id="role" scope="col">Expiry Assign Role</th>
		</tr>
	</thead>    

	<tbody class="civi_member_sync_table" id="civi_member_sync_list">
		<?php 
		
		foreach( $select AS $key => $value ) { 
			
			// construct URLs for this item
			$edit_url = $rules_url.'&q=edit&id='.$value->id;
			$delete_url = menu_page_url( 'civi_member_sync_list' ) . '&q=delete&id='.$value->id;
			$safe_delete_url = wp_nonce_url( $delete_url, 'civi_member_sync_delete_link', 'civi_member_sync_delete_nonce' )
			
			?>
			<tr>   
				<td>
					<?php echo $this->civi->get_names( $value->civi_mem_type, $membership_type ); ?><br />
					<div class="row-actions">
						<span class="edit"><a href="<?php echo $edit_url; ?>">Edit</a> | </span>
						<span class="delete"><a href="<?php echo $safe_delete_url; ?>" class="submitdelete">Delete</a></span>
					</div>
				</td>
				<td><?php echo $value->wp_role; ?></td>   
				<td><?php echo $civi_member_sync->get_names( $value->current_rule, $membership_status ); ?></td>
				<td><?php echo $civi_member_sync->get_names( $value->expiry_rule, $membership_status );?></td>
				<td><?php echo $value->expire_wp_role; ?></td>
			</tr> 
			<?php 
		
		} 
		
		?>
	</tbody>

</table>


