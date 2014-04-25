<div id="icon-options-general" class="icon32"></div> 

<div class="wrap">

	<h2><?php 
	
	if( isset( $_GET['q'] ) AND $_GET['q'] == 'edit' ) {
		_e( 'Edit Association Rule', 'civi_member_sync' ); 
	} else {
		_e( 'Add Association Rule', 'civi_member_sync' );
	}
	
	?> <a class="add-new-h2" href="<?php echo $list_url; ?>"><?php _e( 'Association Rules', 'civi_member_sync' ); ?></a> <a class="add-new-h2" href="<?php echo $manual_sync_url; ?>"><?php _e( 'Manual Synchronize', 'civi_member_sync' ); ?></a></h2>
	
	<?php
	
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

	<p><?php _e( 'Choose a CiviMember Membership Type and a WordPress Role below. This will associate that Membership Type with the WordPress Role. If you would like the have the same Membership Type associated with more than one WordPress Role, you will need to add a second association rule after you have completed this one.', 'civi_member_sync' ); ?></p>
	
	<form method="post" id="civi_member_sync_rules_form" action="<?php echo $this->get_form_url(); ?>">
		
		<?php wp_nonce_field( 'civi_member_sync_rules_action', 'civi_member_sync_nonce' ); ?>
		
		<span class="error"><?php //echo $err; ?></span>
		
		<table class="form-table">

			<tr class="form-field form-required">
				<th scope="row"><label class="civi_member_type_label" for="civi_member_type"><?php _e( 'Select a CiviMember Membership Type', 'civi_member_sync' ); ?> *</label></th>
				<td>
					<select name="civi_member_type" id="civi_member_type" class ="required">
						<option value=""></option>
						<?php
						
						foreach( $membership_type AS $key => $value ) { 
							
							$selected = '';
							if( $key == $civi_member_type ) {
								$selected = ' selected="selected"';
							}
							
							?><option value="<?php echo $key;?>"<?php echo $selected; ?>><?php echo $value; ?></option><?php
						
						}
						
						?>
					</select>
				</td>  
			</tr>
			
			<tr class="form-field form-required">  
				<th scope="row"><label class="wp_role_label" for="wp_role"><?php _e( 'Select a WordPress Role', 'civi_member_sync' ); ?> *</label></th>
				<td>
					<select name="wp_role" id="wp_role" class="required">
						<option value=""></option>
						<?php
						
						global $wp_roles;
						$roles = $wp_roles->get_names();
						
						foreach( $roles as $key => $value ) {
						
							$selected = '';
							if( $value == $wp_role ) {
								$selected = ' selected="selected"';
							}
							
							?><option value="<?php echo $value; ?>"<?php echo $selected; ?>><?php echo $value; ?></option><?php
							
						}
					
						?>
					</select>
				</td>  
			</tr>                

			<tr>
				<th scope="row"><label class="current_label" for="current"><?php _e( 'Current Status', 'civi_member_sync' ); ?> *</label></th>
				<td>
				<?php
				
				foreach( $membership_status AS $key => $value ) {
					
					$checked = '';
					if ( !empty( $current_rule ) ) {
						if ( array_search( $key, $current_rule ) ) {
							$checked = ' checked="checked"';
						}
					}
					
					?><input type="checkbox" class="required-current" name="<?php echo 'current['.$key.']'; ?>" id="<?php echo 'current['.$key.']'; ?>" value="<?php echo $key; ?>"<?php echo $checked; ?> />
					<label for="<?php echo 'current['.$key.']'; ?>"><?php echo $value; ?></label><br />
					<?php
					
				}
				
				?> 
				
				</td>
			</tr>
			
			<tr>
				<th scope="row"><label class="expire_label" for="expire"><?php _e( 'Expire Status', 'civi_member_sync' ); ?> *</label></th>
				<td>
				<?php
				
				foreach( $membership_status AS $key => $value ) { 
					
					$checked = '';
					if ( !empty( $expiry_rule ) ) {
						if ( array_search( $key, $expiry_rule ) ) {
							$checked = ' checked="checked"';
						}
					}
					
					?><input type="checkbox" class="required-expire" name="<?php echo 'expire['.$key.']'; ?>" id="<?php echo 'expire['.$key.']'; ?>" value="<?php echo $key; ?>"<?php echo $checked; ?> />
					<label for="<?php echo 'expire['.$key.']';?>"><?php echo $value; ?></label><br />
					<?php
					
				}
				
				?>
				</td>
			</tr>
			
			<tr class="form-field form-required">
				<th scope="row"><label class="expire_assign_wp_role_label" for="expire_assign_wp_role"><?php _e( 'Select a WordPress Expiry Role', 'civi_member_sync' ); ?> *</label></th>
				<td>
					<select name="expire_assign_wp_role" id ="expire_assign_wp_role" class ="required">
						<option value=""></option>
						<?php
						
						global $wp_roles;
						$roles = $wp_roles->get_names();
						
						foreach( $roles AS $key => $value ) {
						
							$selected = '';
							if( $value == $expired_wp_role ) {
								$selected = ' selected="selected"';
							}
							
							?><option value="<?php echo $value; ?>"<?php echo $selected; ?>><?php echo $value; ?></option><?php
							
						}
						
						?>
					</select>
				</td>
			</tr>
			
		</table>  

		<?php
		
		// add a hidden element depending on mode
		if ( isset( $_GET['q'] ) AND $_GET['q'] == 'edit' ) {
			?><input type="hidden" id="civi_member_sync_rules_mode" name="civi_member_sync_rules_mode" value="edit" /><?php
		} else {
			?><input type="hidden" id="civi_member_sync_rules_mode" name="civi_member_sync_rules_mode" value="add" /><?php
		}
		
		// alter submit text depending on mode
		if ( isset( $_GET['q'] ) AND $_GET['q'] == 'edit' ) {
			$submit = __( 'Save Association Rule', 'civi_member_sync' );
		} else {
			$submit = __( 'Add Association Rule', 'civi_member_sync' );
		}
		
		?><input class="button-primary" type="submit" id="civi_member_sync_rules_submit" name="civi_member_sync_rules_submit" value="<?php echo $submit; ?>" />

	</form>

</div><!-- /.wrap -->



