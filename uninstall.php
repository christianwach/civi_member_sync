<?php /* 
--------------------------------------------------------------------------------
Tadpole CiviMember Role Synchronize Uninstaller
--------------------------------------------------------------------------------
*/



// kick out if uninstall not called from WordPress
if ( !defined( 'WP_UNINSTALL_PLUGIN' ) ) { exit(); }



/** 
 * Restore Wordpress database schema
 * @return boolean $result
 */
function tadms_delete_table() {
	
	// access database object
	global $wpdb;
	
	// our custom table name
	$table_name = $wpdb->prefix . 'civi_member_sync';
	
	// drop our custom table
	$wpdb->query( "DROP TABLE IF EXISTS $table_name" );
	
	// check if we were successful
	if ( $wpdb->get_var( "SHOW TABLES LIKE '$table_name'" ) == $table_name ) {
		return false;
	}
	
	// --<
	return true;

}



// delete standalone options
delete_option( 'tadms_db_version' );

// remove database table
$success = tadms_delete_table();
// do we care about the result?

// are we deleting in multisite?
if ( is_multisite() ) {

	// delete multisite options
	//delete_site_option( 'tadms_db_version );
	
}

