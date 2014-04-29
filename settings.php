<div id="icon-options-general" class="icon32"></div>

<div class="wrap">

	<h2 class="nav-tab-wrapper"><a href="<?php echo $urls['list']; ?>" class="nav-tab"><?php _e( 'Association Rules', 'civi_member_sync' ); ?></a> <a href="<?php echo $urls['manual_sync']; ?>" class="nav-tab"><?php _e( 'Manual Synchronize', 'civi_member_sync' ); ?></a> <a href="<?php echo $urls['settings']; ?>" class="nav-tab nav-tab-active"><?php _e( 'Settings', 'civi_member_sync' ); ?></a></h2>

	<?php 

	// if we've updated, show message...
	if ( isset( $_GET['updated'] ) ) {
		echo '<div id="message" class="updated"><p>'.__( 'Options updated.', 'civicrm_member_sync' ).'</p></div>';
	}

	?>

	<p><?php _e( 'Select which methods CiviCRM Member Role Sync will use to synchronize Memberships and Roles. If you choose user login/logout, you will have to run "Manual Synchronize" after you create a new rule for it to be applied to all users and contacts. Leave the default settings if you are unsure which methods to use.', 'civi_member_sync' ); ?></p>
	
	<form method="post" id="civi_member_sync_settings_form" action="<?php echo $this->get_form_url(); ?>">

		<?php wp_nonce_field( 'civi_member_sync_settings_action', 'civi_member_sync_nonce' ); ?>

		<h3><?php _e( 'Synchronize Individuals', 'civi_member_sync' ); ?></h3> 

		<table class="form-table">

			<tr>
				<th scope="row"><?php _e( 'Login and Logout', 'civi_member_sync' ); ?></th>
				<td>
					<?php
					
					// checked by default
					$checked = ' checked="checked"';
					if ( isset( $login ) AND $login === 0 ) {
						$checked = '';
					}
					
					?><input type="checkbox" class="settings-checkbox" name="civi_member_sync_settings_login" id="civi_member_sync_settings_login" value="1"<?php echo $checked; ?> />
					<label class="civi_member_sync_settings_label" for="civi_member_sync_settings_login"><?php _e( 'Synchronize whenever a user logs in or logs out. This action is performed only on the user logging in or out.', 'civi_member_sync' ); ?></label>
				</td>
			</tr>
			
			<tr>
				<th scope="row"><?php _e( 'CiviCRM Admin', 'civi_member_sync' ); ?></th>
				<td>
					<?php
					
					// checked by default
					$checked = ' checked="checked"';
					if ( isset( $civicrm ) AND $civicrm === 0 ) {
						$checked = '';
					}
					
					?><input type="checkbox" class="settings-checkbox" name="civi_member_sync_settings_civicrm" id="civi_member_sync_settings_civicrm" value="1"<?php echo $checked; ?> />
					<label class="civi_member_sync_settings_label" for="civi_member_sync_settings_civicrm"><?php _e( 'Synchronize when membership is updated in CiviCRM admin pages.', 'civi_member_sync' ); ?></label>
				</td>
			</tr>
			
		</table>

		<h3><?php _e( 'Scheduled Synchronization', 'civi_member_sync' ); ?></h3> 

		<table class="form-table">

			<tr>
				<th scope="row"><?php _e( 'Scheduled Events', 'civi_member_sync' ); ?></th>
				<td>
					<?php
					
					// checked by default
					$checked = ' checked="checked"';
					if ( isset( $schedule ) AND $schedule === 0 ) {
						$checked = '';
					}
					
					?><input type="checkbox" class="settings-checkbox" name="civi_member_sync_settings_schedule" id="civi_member_sync_settings_schedule" value="1"<?php echo $checked; ?> />
					<label class="civi_member_sync_settings_label" for="civi_member_sync_settings_schedule"><?php _e( 'Synchronize using a recurring schedule. This action is performed on all users and contacts.', 'civi_member_sync' ); ?></label>
				</td>
			</tr>
			
			<tr>
				<th scope="row"><label class="civi_member_sync_settings_label" for="civi_member_sync_settings_interval"><?php _e( 'Schedule Interval', 'civi_member_sync' ); ?></label></th>
				<td>
					<select class="settings-select" name="civi_member_sync_settings_interval" id ="civi_member_sync_settings_interval">
						<?php
						
						foreach( $schedules AS $key => $value ) {
						
							$selected = '';
							if( isset( $interval ) AND $key == $interval ) {
								$selected = ' selected="selected"';
							}
							
							?><option value="<?php echo $key; ?>"<?php echo $selected; ?>><?php echo $value['display']; ?></option><?php
							
						}
						
						?>
					</select>
				</td>
			</tr>
			
		</table>
		
		<?php if ( is_super_admin() ) { ?>
		
			<h3><?php _e( 'Debugging', 'civi_member_sync' ); ?></h3> 

			<table class="form-table">

				<tr>
					<th scope="row"><?php _e( 'Debug', 'civi_member_sync' ); ?></th>
					<td>
						<input type="checkbox" class="settings-checkbox" name="civi_member_sync_settings_debug" id="civi_member_sync_settings_debug" value="1" />
						<label class="civi_member_sync_settings_label" for="civi_member_sync_settings_debug"><?php _e( 'Check this to trigger do_debug().', 'civi_member_sync' ); ?></label>
					</td>
				</tr>
			
			</table>
		
		<?php } ?>

		<p class="submit">
			<input class="button-primary" type="submit" id="civi_member_sync_settings_submit" name="civi_member_sync_settings_submit" value="<?php _e( 'Save Changes', 'civi_member_sync' ); ?>" />
		</p>

	</form>

</div><!-- /.wrap -->



