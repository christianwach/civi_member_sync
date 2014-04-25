<div id="icon-options-general" class="icon32"></div>

<div class="wrap">

<h2><?php _e( 'Manual Synchronize', 'civi_member_sync' ); ?> <a class="add-new-h2" href="<?php echo $list_url; ?>"><?php _e( 'Association Rules', 'civi_member_sync' ); ?></a> <a class="add-new-h2" href="<?php echo $rules_url; ?>"><?php _e( 'Add Association Rule', 'civi_member_sync' ); ?></a></h2>

<?php 

// if we've updated, show message...
if ( isset( $_GET['updated'] ) ) {
	echo '<div id="message" class="updated"><p>'.__( 'Sync completed.', 'civicrm_member_sync' ).'</p></div>';
}

?>

<p><?php _e( 'Synchronize CiviMember Memberships and WordPress Roles using the available rules.<br> <em>Note:</em> if no association rules exist then no synchronization will take place.', 'civi_member_sync' ); ?></p>
	
<form method="post" id="civi_member_sync_manual_sync_form" action="<?php echo $this->get_form_url(); ?>">

	<?php wp_nonce_field( 'civi_member_sync_manual_sync_action', 'civi_member_sync_nonce' ); ?>

	<input class="button-primary" type="submit"  id="civi_member_sync_manual_sync_submit" name="civi_member_sync_manual_sync_submit" value="<?php _e( 'Synchronize Now', 'civi_member_sync' ); ?>" />

</form>

</div><!-- /.wrap -->



