<?php 

// get membership data
$membership_type = $this->civi->get_types();
$membership_status = $this->civi->get_statuses();

// original logic...
if ( isset( $_GET['q'] ) AND $_GET['q'] == 'edit' ) {
	if ( !empty( $_GET['id'] ) ) {
		$table_name = $wpdb->prefix . 'civi_member_sync';
		$select = $wpdb->get_row( "SELECT * FROM $table_name WHERE `id` = ".$_GET['id'] );
		$wp_role = $select->wp_role; 
		$expired_wp_role = $select->expire_wp_role; 
		$civi_member_type = $select->civi_mem_type;  
		$current_rule = unserialize( $select->current_rule );
		$expiry_rule = unserialize( $select->expiry_rule );
	}      
}

// sanitise admin page url
$target_url = $_SERVER['REQUEST_URI'];
$url_array = explode( '&', $target_url );
if ( $url_array ) { $target_url = htmlentities( $url_array[0].'&updated=true' ); }

?>
<div id="icon-options-general" class="icon32"></div> 

<div class="wrap">

	<h2 id="add-new-user"><?php 
	
	if( isset( $_GET['q'] ) ) {
		echo 'Edit Association Rule'; 
	} else {
		echo 'Add Association Rule';
	}
	
	?></h2>
	
	<p>Choose a CiviMember Membership Type and a WordPress Role below. This will associate that Membership Type with the WordPress Role. If you would like the have the same Membership Type associated with more than one WordPress Role, you will need to add a second association rule after you have completed this one.</p>
	
	<form method="post" id="civi_member_sync_rules_form" action="<?php echo $target_url; ?>">
		
		<?php wp_nonce_field( 'civi_member_sync_admin_action', 'civi_member_sync_nonce' ); ?>
		
		<span class="error"><?php echo $nameErr; ?></span>
		
		<table class="form-table">

			<tr class="form-field form-required">
				<th scope="row"><label for="user_login">Select a CiviMember Membership Type *</label></th>
				<td>
					<select name="civi_member_type" id= "civi_member_type" class ="required">
						<option value=""></option>
						<?php
						
						foreach( $membership_type AS $key => $value ) { 
							
							$selected = '';
							if( $key == $civi_member_type) {
								$selected = ' selected="selected"';
							}
							
							?><option value="<?php echo $key;?>"<?php echo $selected; ?>><?php echo $value; ?></option><?php
						
						}
						
						?>
					</select>
				</td>  
			</tr>
			
			<tr class="form-field form-required">  
				<th scope="row"><label for="user_login">Select a WordPress Role *</label></th>
				<td>
					<select name="wp_role" id="wp_role" class="required">
						<option value=""></option>
						<?php
						
						global $wp_roles;
						$roles = $wp_roles->get_names();
						
						foreach( $roles as $key => $value) {
						
							$selected = '';
							if( $key == $civi_member_type) {
								$selected = ' selected="selected"';
							}
							
							?><option value="<?php echo $value; ?>"<?php echo $selected; ?>><?php echo $value; ?></option><?php
							
						}
					
						?>
					</select>
				</td>  
			</tr>                

			<tr>
				<th scope="row"><label for="user_login">Current Status *</label></th>
				<td>
				<?php
				
				foreach( $membership_status AS $key => $value) {
					
					$checked = '';
					if ( !empty( $current_rule ) ) {
						if ( array_search( $key, $current_rule ) ) {
							$checked = ' checked="checked"';
						}
					}
					
					?><input type="checkbox" class="requiredCheckbox" name="<?php echo 'current['.$key.']'; ?>" id="<?php echo 'current['.$key.']'; ?>" value="<?php echo $key; ?>"<?php echo $checked; ?> />
					<label for="<?php echo 'current['.$key.']'; ?>"><?php echo $value; ?></label><br />
					<?php
					
				}
				
				?> 
				
				</td>
			</tr>
			
			<tr>
				<th scope="row"><label for="user_login">Expire Status *</label></th>
				<td>
				<?php
				
				foreach( $membership_status AS $key => $value) { 
					
					$checked = '';
					if ( !empty( $expiry_rule ) ) {
						if ( array_search( $key, $expiry_rule ) ) {
							$checked = ' checked="checked"';
						}
					}
					
					?><input type="checkbox" class="requiredCheckbox" name="<?php echo 'expire['.$key.']'; ?>" id="<?php echo 'expire['.$key.']'; ?>" value="<?php echo $key; ?>"<?php echo $checked; ?> />
					<label for="<?php echo 'expire['.$key.']';?>"><?php echo $value; ?></label><br />
					<?php
					
				}
				
				?>
				</td>
			</tr>
			
			<tr class="form-field form-required">
				<th scope="row"><label for="user_login">Select a WordPress Expiry Role *</label></th>
				<td>
					<select name="expire_assign_wp_role" id ="expire_assign_wp_role" class ="required">
						<option value=""></option>
						<?php
						
						global $wp_roles;
						$roles = $wp_roles->get_names();
						
						foreach( $roles AS $key => $value ) {
						
							$selected = '';
							if( $key == $civi_member_type) {
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
		
		if ( isset( $_GET['q'] ) ) {
			$submit = "Save Association Rule";
		} else {
			$submit = "Add Association Rule";
		}
		
		?><input class="button-primary" type="submit" id="civi_member_sync_rules_submit" name="civi_member_sync_rules_submit" value="<?php echo $submit; ?>" />

	</form>

</div><!-- /.wrap -->



