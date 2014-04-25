<?php

// sanitise admin page url
$target_url = $_SERVER['REQUEST_URI'];
$url_array = explode( '&', $target_url );
if ( $url_array ) { $target_url = htmlentities( $url_array[0].'&updated=true' ); }

?><div id="icon-options-general" class="icon32"><br/></div>

<div class="wrap">

<h2>Manual Synchronize</h2>

<?php 

// if we've updated, show message...
if ( isset( $_GET['updated'] ) ) {
	echo '<div id="message" class="updated"><p>'.__( 'Sync completed.', 'civicrm_member_sync' ).'</p></div>';
}

?>

<p>Synchronize CiviMember Memberships and WordPress Roles using the available rules.<br>
<em>Note:</em> if no association rules exist then no synchronization will take place.</p>
	
<form method="post" id="civi_member_sync_manual_sync_form" action="<?php echo $target_url; ?>">

	<?php wp_nonce_field( 'civi_member_sync_manual_sync', 'civi_member_sync_nonce' ); ?>

	<input class="button-primary" type="submit"  id="civi_member_sync_manual_sync_submit" name="civi_member_sync_manual_sync_submit" value="Synchronize now" />

</form>

</div><!-- /.wrap -->



