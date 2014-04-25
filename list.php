<div id="icon-options-general" class="icon32"><br/></div>

<div class="wrap">

<h2><?php _e( 'Association Rules', 'civi_member_sync' ); ?> <a class="add-new-h2" href="<?php echo $rules_url; ?>"><?php _e( 'Add Association Rule', 'civi_member_sync' ); ?></a> <a class="add-new-h2" href="<?php echo $manual_sync_url; ?>"><?php _e( 'Manual Synchronize', 'civi_member_sync' ); ?></a></h2> 

</div>

<table cellspacing="0" class="wp-list-table widefat fixed users">

	<thead>
		<tr>
			<th class="manage-column column-role" id="role" scope="col"><?php _e( 'Civi Membership Type', 'civi_member_sync' ); ?></th>
			<th class="manage-column column-role" id="role" scope="col"><?php _e( 'WordPress Role', 'civi_member_sync' ); ?></th>
			<th class="manage-column column-role" id="role" scope="col"><?php _e( 'Current Codes', 'civi_member_sync' ); ?></th>
			<th class="manage-column column-role" id="role" scope="col"><?php _e( 'Expired Codes', 'civi_member_sync' ); ?></th>
			<th class="manage-column column-role" id="role" scope="col"><?php _e( 'Expiry Assign Role', 'civi_member_sync' ); ?></th>
		</tr>
	</thead>    

	<tbody class="civi_member_sync_table" id="civi_member_sync_list">
		<?php
		
		foreach( $select AS $key => $value ) { 
			
			// construct URLs for this item
			$edit_url = $rules_url . '&q=edit&id='.$value->id;
			$delete_url = wp_nonce_url( 
				$list_url . '&q=delete&id='.$value->id,
				'civi_member_sync_delete_link',
				'civi_member_sync_delete_nonce'
			);
			
			?>
			<tr>   
				<td>
					<?php echo $this->civi->get_names( $value->civi_mem_type, $membership_type ); ?><br />
					<div class="row-actions">
						<span class="edit"><a href="<?php echo $edit_url; ?>"><?php _e( 'Edit', 'civi_member_sync' ); ?></a> | </span>
						<span class="delete"><a href="<?php echo $delete_url; ?>" class="submitdelete"><?php _e( 'Delete', 'civi_member_sync' ); ?></a></span>
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


