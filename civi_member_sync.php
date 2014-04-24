<?php /* 
--------------------------------------------------------------------------------
Plugin Name: CiviCRM Member Role Sync
Plugin URI: https://github.com/christianwach/civi_member_sync
Description: Synchronize CiviCRM memberships with WordPress user roles
Author: Christian Wach
Version: 1.0
Author URI: https://haystack.co.uk
Text Domain: civi_member_sync
Domain Path: /languages
--------------------------------------------------------------------------------

Based heavily on:
1. CiviMember Role Synchronize by Jag Kandasamy of http://www.orangecreative.net
2. Tadpole CiviMember Role Synchronize by https://tadpole.cc

Refactored, rewritten and extended by Christian Wach <needle@haystack.co.uk>

--------------------------------------------------------------------------------
*/  



// define version as constant so as not to clutter global namespace
define( 'CIVI_MEMBER_SYNC_DB_VERSION', '1.0' );

// store reference to this file
define( 'CIVI_MEMBER_SYNC_PLUGIN_FILE', __FILE__ );

// store URL to this plugin's directory
if ( !defined( 'CIVI_MEMBER_SYNC_PLUGIN_URL' ) ) {
	define( 'CIVI_MEMBER_SYNC_PLUGIN_URL', plugin_dir_url( CIVI_MEMBER_SYNC_PLUGIN_FILE ) );
}
// store PATH to this plugin's directory
if ( !defined( 'CIVI_MEMBER_SYNC_PLUGIN_PATH' ) ) {
	define( 'CIVI_MEMBER_SYNC_PLUGIN_PATH', plugin_dir_path( CIVI_MEMBER_SYNC_PLUGIN_FILE ) );
}



/**
 * Class for encapsulating plugin functionality
 */
class Tad_Civi_Member_Sync {

	/** 
	 * Properties
	 */
	
	// options page
	public $options_page;
	
	
	
	/** 
	 * Initialise this object
	 * @return object
	 */
	function __construct() {
	
		// use translation
		add_action( 'plugins_loaded', array( $this, 'translation' ) );
		
		// initialise plugin when CiviCRM initialises
		add_action( 'civicrm_instance_loaded', array( $this, 'initialise' ) );
		
		// --<
		return $this;

	}
	
	
	
	/** 
	 * Load translation if present
	 */
	public function translation() {
		
		// only use, if we have it...
		if( function_exists( 'load_plugin_textdomain' ) ) {
	
			// there are no translations as yet, but they can now be added
			load_plugin_textdomain(
			
				// unique name
				'civi_member_sync', 
				
				// deprecated argument
				false,
				
				// relative path to directory containing translation files
				dirname( plugin_basename( CIVI_MEMBER_SYNC_PLUGIN_FILE ) ) . '/languages/'
	
			);
			
		}
		
	}
	
	
	
	/**
	 * Create MySQL table to track membership sync
	 * @return nothing
	 */
	public function install_db() {

		// access database object
		global $wpdb;
		
		// contruct table name
		$table_name = $wpdb->prefix . 'civi_member_sync';
		
		// define table structure
		$create_ddl = "CREATE TABLE IF NOT EXISTS $table_name (
					  `id` int(11) NOT NULL AUTO_INCREMENT,
					  `wp_role` varchar(255) NOT NULL,
					  `civi_mem_type` int(11) NOT NULL,
					  `current_rule` varchar(255) NOT NULL,
					  `expiry_rule` varchar(255) NOT NULL,
					  `expire_wp_role` varchar(255) NOT NULL,
					  PRIMARY KEY (`id`), 
					  UNIQUE KEY `civi_mem_type` (`civi_mem_type`)
					  ) ENGINE=InnoDB  DEFAULT CHARSET=utf8 ;";
		
		// maybe_create_table requires install-helper.php
		require_once( ABSPATH . 'wp-admin/install-helper.php' );
		
		// create table if it doesn't already exist
		$success = maybe_create_table( $table_name, $create_ddl );
		// do we care whether we're successful?
		
		// store version for later reference
		add_option( 'civimembersync_db_version', CIVI_MEMBER_SYNC_DB_VERSION );
	
	}
	
	
	

	/**
	 * Register hooks when CiviCRM initialises
	 * @return nothing
	 */
	public function initialise() {
	
		// add schedule, if not already present
		if ( !wp_next_scheduled( 'civi_member_sync_refresh' ) ) {  
		   wp_schedule_event( time(), 'daily', 'civi_member_sync_refresh' );
		}

		// add cron action
		add_action( 'civi_member_sync_refresh', array( $this, 'sync_daily' ) );
		
		// add login/logout check
		add_action( 'wp_login', array( $this, 'sync_check' ), 10, 2 );
		add_action( 'wp_logout', array( $this, 'sync_check' ), 10, 2 );
		//add_action( 'profile_update', array( $this, 'sync_check' ), 10, 2 );
		
		// add menu items
		add_action( 'admin_menu', array( $this, 'admin_menu' ) ); 
		
		// add jQuery to WP admin (almost certainly not necessary)
		add_action( 'admin_init', array( $this, 'admin_init' ) );
		
	}
	
	
	
	/**
	* Schedule manual sync daily
	* @return nothing
	*/
	function sync_daily() {

		$users = get_users();

		require_once( 'civi.php' );
		require_once( 'CRM/Core/BAO/UFMatch.php' );

		foreach( $users as $user ) {
		
			$uid = $user->ID;
			if ( empty( $uid ) ) {
				continue;
			}
			        
			$sql = "SELECT * FROM civicrm_uf_match WHERE uf_id = '$uid'";
			$contact = CRM_Core_DAO::executeQuery( $sql );

			if ( $contact->fetch() ) {
			
				$cid = $contact->contact_id;
				$memDetails = civicrm_api( 'Membership', 'get', array(
					'version' => '3',
					'page' => 'CiviCRM',
					'q' => 'civicrm/ajax/rest',
					'sequential' => '1',
					'contact_id' => $cid
				));
				
				if ( !empty( $memDetails['values'] ) ) {
					foreach( $memDetails['values'] as $key => $value ) {
						$memStatusID = $value['status_id']; 
						$membershipTypeID = $value['membership_type_id'];  
					}
				}

				$userData = get_userdata( $uid );
				if ( !empty( $userData ) ) {
					$currentRole = $userData->roles[0];
				}
				
				// checking membership status and assign role
				$check = $this->member_check( $cid, $uid, $currentRole );     

			}
			
		}
		
	}



	/**
	 * Check user's membership record during login and logout
	 * @return bool true if successful
	 */
	function sync_check( $user_login, $user ) {    

		global $wpdb, $current_user;
		
		// get username in post while login  
		if ( !empty( $_POST['log'] ) ) {
			$username = $_POST['log']; 
			$userDetails = $wpdb->get_results( "SELECT * FROM $wpdb->users WHERE user_login = '$username'" );
			$currentUserID = $userDetails[0]->ID;
		} else {
			$currentUserID = $current_user->ID;
		}
		
		//getting current logged in user's role
		$current_user_role = new WP_User( $currentUserID );
		$current_user_role = $current_user_role->roles[0];
		//echo $current_user_role . "\n";

		civicrm_wp_initialize();
		
		//getting user's civi contact id and checkmembership details
		if ( $current_user_role != 'administrator' ) {
		
			require_once 'CRM/Core/Config.php';
			$config = CRM_Core_Config::singleton();
			
			require_once 'api/api.php';
			$params = array(
				'version' => '3',
				'page' => 'CiviCRM', 
				'q' => 'civicrm/ajax/rest', 
				'sequential' => '1', 
				'uf_id' => $currentUserID
			);
			
			$contactDetails = civicrm_api( 'UFMatch', 'get', $params );
			
			$contactID = $contactDetails['values'][0]['contact_id'];
			if ( !empty( $contactID ) ) {
				$member = $this->member_check( $contactID, $currentUserID, $current_user_role );
			}
			
		}
		
		return true;
		
	}
	
	
	
	/**
	 * Check membership record and assign WordPress role based on membership status
	 * @param int $contactID The numerical CiviCRM contact ID
	 * @param int $currentUserID The numerical ID of the current user
	 * @param string $current_user_role The role of the current user
	 * @return bool true if successful
	 */
	function member_check( $contactID, $currentUserID, $current_user_role ) {
		
		// access globals
		global $wpdb, $user, $current_user;
		
		if ( $current_user_role != 'administrator' ) {
		
			// fetching membership details
			$memDetails=civicrm_api( 'Membership', 'get', array(
				'version' => '3',
				'page' => 'CiviCRM',
				'q' => 'civicrm/ajax/rest',
				'sequential' => '1',
				'contact_id' => $contactID
			));
			//print_r($memDetails); echo "\n";
			
			if ( !empty( $memDetails['values'] ) ) {
				foreach( $memDetails['values'] as $key => $value ) {
					$memStatusID = $value['status_id'];
					$membershipTypeID = $value['membership_type_id'];
				}
			}

			// fetching member sync association rule to the corsponding membership type 
			$table_name = $wpdb->prefix . "civi_member_sync";
			$memSyncRulesDetails = $wpdb->get_results( "SELECT * FROM $table_name WHERE civi_mem_type = '$membershipTypeID'" ); 
			//print_r($memSyncRulesDetails);
			
			if ( !empty( $memSyncRulesDetails ) ) {
			
				$current_rule = unserialize( $memSyncRulesDetails[0]->current_rule );
				//print_r($current_rule); echo "\n";
				
				$expiry_rule = unserialize( $memSyncRulesDetails[0]->expiry_rule );
				//print_r($expiry_rule); echo "\n";
				
				//checking membership status
				if ( isset( $memStatusID ) && array_search( $memStatusID, $current_rule ) ) {
				
					$wp_role = strtolower( $memSyncRulesDetails[0]->wp_role );
					//print $wp_role;
					
					if ( $wp_role == $current_user_role ) {
						//print 'current member, up to date';      
						return;
					} else {
						//print 'current member, update';
						$wp_user_object = new WP_User( $currentUserID );
						$wp_user_object->set_role( "$wp_role" ); 
					}
					
				} else {
				
					$wp_user_object = new WP_User( $currentUserID );
					$expired_wp_role = strtolower( $memSyncRulesDetails[0]->expire_wp_role );
					//print $expired_wp_role;
					
					if ( !empty( $expired_wp_role ) ) {
						//print 'expired member, update';
						$wp_user_object->set_role( "$expired_wp_role" ); 
					} else {
						//print 'expired member, up to date';
						$wp_user_object->set_role( "" );
					}
					
				}
				
			}
			
		}
		
		return true;
		
	}
	
	
	
	/**
	 * Add this plugin's Settings Page to the WordPress admin menu
	 * @return nothing
	 */
	function admin_menu() {
		
		// add options page
		$this->options_page = add_options_page(
			__( 'CiviMember Role Sync', 'civi_member_sync' ), // page title
			__( 'CiviMember Role Sync', 'civi_member_sync' ), // menu title
			'manage_options', // required caps
			'civi_member_sync/list.php', // slug name
			null // callback
		);
		
		//  add first sub item
		add_submenu_page(
			'civi_member_sync/list.php', // parent slug
			__( 'CiviMember Role Sync', 'civi_member_sync' ), // page title
			__( 'List of Rules', 'civi_member_sync' ), // menu title
			'add_users', // required caps
			'civi_member_sync/settings.php' // slug name
		);
		
		//  add second sub item
		add_submenu_page(
			'civi_member_sync/list.php', // parent slug
			__( 'CiviMember Role Manual Sync', 'civi_member_sync' ), // page title
			__( 'List of Rules', 'civi_member_sync' ), // menu title
			'add_users', // required caps
			'civi_member_sync/manual_sync.php' // slug name
		);
		
	}
	
	
	
	/**
	 * Ensure jQuery and jQuery Form are available in WP admin
	 * @return nothing
	 */
	function admin_init() {
		
		// this can't be necessary!
		wp_enqueue_script('jquery');
		wp_enqueue_script('jquery-form');
		
	}



} // class ends



 


// declare as global for external reference
global $tad_civi_member_sync;

// init plugin
$tad_civi_member_sync = new Tad_Civi_Member_Sync;

// plugin activation
register_activation_hook( __FILE__, array( $tad_civi_member_sync, 'install_db' ) );    

// uninstall uses the 'uninstall.php' method
// see: http://codex.wordpress.org/Function_Reference/register_uninstall_hook





/**
 * Add utility links to WordPress Plugin Listings Page
 * @return array $links The list of plugin links
 */
function civimembersync_plugin_add_settings_link( $links ) {
	$settings_link = '<a href="admin.php?page=civi_member_sync/list.php">'.__( 'Settings', 'civi_member_sync' ).'</a>';
  	array_push( $links, $settings_link );
  	return $links;
}

// contstriuct filter
$plugin = plugin_basename( __FILE__ );
add_filter( "plugin_action_links_$plugin", 'civimembersync_plugin_add_settings_link' );



