<div id="icon-options-general" class="icon32"></div>

<div class="wrap">

<h2 class="nav-tab-wrapper"><a href="<?php echo $urls['list']; ?>" class="nav-tab nav-tab-active"><?php _e( 'Association Rules', 'civi_member_sync' ); ?></a> <a href="<?php echo $urls['manual_sync']; ?>" class="nav-tab"><?php _e( 'Manual Synchronize', 'civi_member_sync' ); ?></a> <a href="<?php echo $urls['settings']; ?>" class="nav-tab"><?php _e( 'Settings', 'civi_member_sync' ); ?></a></h2>

<h3><?php _e( 'All Association Rules', 'civi_member_sync' ); ?> <a class="add-new-h2" href="<?php echo $urls['rules']; ?>"><?php _e( 'Add New', 'civi_member_sync' ); ?></a></h3> 

<?php

// if we've updated, show message...
if ( isset( $_GET['syncrule'] ) ) {
	echo '<div id="message" class="updated"><p>';
	
	// switch message based on result
	switch( $_GET['syncrule'] ) {
		case 'edit':
			_e( 'Association Rule updated.', 'civicrm_member_sync' );
			break;
		case 'add':
			_e( 'Association Rule added.', 'civicrm_member_sync' );
			break;
		case 'delete':
			_e( 'Association Rule deleted.', 'civicrm_member_sync' );
			break;
	}

	echo '</p></div>';
}

// if we've updated, show message (note that this will only display if we have JS turned off)
if ( isset( $this->civi->errors ) AND is_array( $this->civi->errors ) ) {
	
	// init messages
	$error_messages = array();
	
	// construct array of messages based on error code
	foreach( $this->civi->errors AS $error_code ) {
		$error_messages[] = $this->civi->error_strings[$error_code];
	}
	
	// show them
	echo '<div id="message" class="error"><p>' . implode( '<br>', $error_messages ) . '</p></div>';
	
}

?>

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
			$edit_url = $urls['rules'] . '&q=edit&id='.$value->id;
			$delete_url = wp_nonce_url( 
				$urls['list'] . '&syncrule=delete&id='.$value->id,
				'civi_member_sync_delete_link',
				'civi_member_sync_delete_nonce'
			);
			
			?>
			<tr> 
				<td>
					<?php echo $this->civi->get_membership_name_by_id( $value->civi_mem_type ); ?><br />
					<div class="row-actions">
						<span class="edit"><a href="<?php echo $edit_url; ?>"><?php _e( 'Edit', 'civi_member_sync' ); ?></a> | </span>
						<span class="delete"><a href="<?php echo $delete_url; ?>" class="submitdelete"><?php _e( 'Delete', 'civi_member_sync' );?></a></span>
					</div>
				</td>
				<td><?php echo $this->get_wp_role_name( $value->wp_role ); ?></td>
				<td><?php echo $this->civi->get_current_status_rules( $value->current_rule ); ?></td>
				<td><?php echo $this->civi->get_current_status_rules( $value->expiry_rule );?></td>
				<td><?php echo $this->get_wp_role_name( $value->expire_wp_role ); ?></td>
			</tr>
			<?php
		
		}
		
		?>
	</tbody>

</table>


