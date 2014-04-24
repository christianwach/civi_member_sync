<?php /* 
--------------------------------------------------------------------------------
Plugin Name: CiviCRM Member Role Sync
Plugin URI: https://github.com/christianwach/civi_member_sync
Description: Synchronize CiviCRM memberships with WordPress user roles
Author: Christian Wach
Version: 1.1
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



// define plugin version (bump this to refresh CSS and JS)
define( 'CIVI_MEMBER_SYNC_VERSION', '1.1' );

// define DB table version
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
class Civi_Member_Sync {

	/** 
	 * Properties
	 */
	
	// admin pages
	public $list_page;
	public $rules_page;
	public $sync_page;
	
	
	
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
		add_option( 'civi_member_sync_db_version', CIVI_MEMBER_SYNC_DB_VERSION );
	
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
		
	}
	
	
	
	/**
	* Schedule manual sync daily
	* @return nothing
	*/
	public function sync_daily() {

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
	public function sync_check( $user_login, $user ) {    

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
	public function member_check( $contactID, $currentUserID, $current_user_role ) {
		
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
	public function admin_menu() {
		
		// check user permissions
		if ( current_user_can('manage_options') ) {

			// try and update options
			$saved = $this->options_update();
			
			// add options page
			$this->list_page = add_options_page(
				__( 'CiviCRM Member Role Sync', 'civi_member_sync' ), // page title
				__( 'CiviCRM Member Role Sync', 'civi_member_sync' ), // menu title
				'manage_options', // required caps
				'civi_member_sync_list', // slug name
				array( $this, 'admin_list' ) // callback
			);
		
			//  add first sub item
			$this->rules_page = add_submenu_page(
				'civi_member_sync_list', // parent slug
				__( 'CiviCRM Member Role Sync: Association Rules', 'civi_member_sync' ), // page title
				__( 'Association Rules', 'civi_member_sync' ), // menu title
				'manage_options', // required caps
				'civi_member_sync_rules', // slug name
				array( $this, 'admin_rules' ) // callback
			);
		
			//  add second sub item
			$this->sync_page = add_submenu_page(
				'civi_member_sync_list', // parent slug
				__( 'CiviCRM Member Role Sync: Manual Sync', 'civi_member_sync' ), // page title
				__( 'Manual Sync', 'civi_member_sync' ), // menu title
				'manage_options', // required caps
				'civi_member_sync_manual_sync', // slug name
				array( $this, 'admin_sync' ) // callback
			);
		
			// add scripts and styles
			add_action( 'admin_print_scripts-'.$this->list_page, array( $this, 'admin_js' ) );
			add_action( 'admin_print_styles-'.$this->list_page, array( $this, 'admin_css' ) );
			add_action( 'admin_head-'.$this->list_page, array( $this, 'admin_head' ), 50 );
			add_action( 'admin_print_scripts-'.$this->rules_page, array( $this, 'admin_js' ) );
			add_action( 'admin_print_styles-'.$this->rules_page, array( $this, 'admin_css' ) );
			add_action( 'admin_head-'.$this->rules_page, array( $this, 'admin_head' ), 50 );
			add_action( 'admin_print_scripts-'.$this->sync_page, array( $this, 'admin_js' ) );
			add_action( 'admin_print_styles-'.$this->sync_page, array( $this, 'admin_css' ) );
			add_action( 'admin_head-'.$this->sync_page, array( $this, 'admin_head' ), 50 );

		}
		
	}
	
	
	
	/** 
	 * Show civi_member_sync_list admin page
	 * @return nothing
	 */
	public function admin_list() {
		
		// include file
		include( 'list.php' );
		
	}
	
	
		
	/** 
	 * Show civi_member_sync_rules admin page
	 * @return nothing
	 */
	public function admin_rules() {
	
		// include file
		include( 'rules.php' );
		
	}
	
	
		
	/** 
	 * Show civi_member_sync_manual_sync admin page
	 * @return nothing
	 */
	public function admin_sync() {
		
		// include file
		include( 'manual_sync.php' );
		
	}
	
	
		
	/** 
	 * Initialise plugin help
	 * @return nothing
	 */
	public function admin_head() {
		
		// there's a new screen object for help in 3.3
		$screen = get_current_screen();
		//print_r( $screen ); die();
		
		// use method in this class
		$this->admin_help( $screen );
		
	}
	
	
		
	/** 
	 * Enqueue plugin options page css
	 */
	public function admin_css() {
		
		// add admin stylesheet
		wp_enqueue_style(
		
			'civi_member_sync_admin_css', 
			plugins_url( 'assets/css/civi_member_sync.css', CIVI_MEMBER_SYNC_PLUGIN_FILE ),
			false,
			CIVI_MEMBER_SYNC_VERSION, // version
			'all' // media
			
		);
		
	}
	
	
	
	/** 
	 * Ensure jQuery and jQuery Form are available in WP admin
	 * @return nothing
	 */
	public function admin_js() {
		
		// add javascript plus dependencies
		wp_enqueue_script(
		
			'civi_member_sync_admin_js', 
			plugins_url( 'assets/js/civi_member_sync.js', CIVI_MEMBER_SYNC_PLUGIN_FILE ),
			array( 'jquery', 'jquery-form' ),
			CIVI_MEMBER_SYNC_VERSION // version
		
		);
		
	}



	/** 
	 * @description: adds help copy to admin page in WP3.3+
	 * @todo: 
	 *
	 */
	public function admin_help( $screen ) {
	
		//print_r( $screen ); die();
		
		// kick out if not our screen
		if ( $screen->id != $this->list_page ) return;
		
		// add a tab - we can add more later
		$screen->add_help_tab( array(
		
			'id'      => 'civi_member_sync',
			'title'   => __( 'CiviCRM Member Role Sync', 'civi_member_sync' ),
			'content' => $this->get_help(),
			
		));
		
		// --<
		return $screen;

	}
	
	
	
	/** 
	 * Get help text
	 * @return string $help Help formatted as HTML
	 */
	public function get_help() {
		
		// stub help text, to be developed further...
		$help = '<p>' . __( 'For further information about using CiviCRM Member Role Sync, please refer to the README.md that comes with this plugin.', 'civi_member_sync' ) . '</p>';
		
		// --<
		return $help;

	}
	
	
	
	/** 
	 * Save the settings set by the administrator
	 * @return bool $result Success or failure
	 */
	public function options_update() {
	
		// init result
		$result = false;
		
	 	// was the form submitted?
		if( isset( $_POST[ 'civi_member_sync_submit' ] ) ) {
			
			// check that we trust the source of the data
			check_admin_referer( 'civi_member_sync_admin_action', 'civi_member_sync_nonce' );
			
			if( !empty( $_POST['wp_role'] ) ) {
				$wp_role = $_POST['wp_role'];
			}
			
			if ( !empty( $_POST['civi_member_type'] ) ) {
				$civi_member_type = $_POST['civi_member_type'];
			}
			
			if ( !empty( $_POST['expire_assign_wp_role'] ) ) {
				$expired_wp_role = $_POST['expire_assign_wp_role'];
			}
			
			if ( !empty( $_POST['current'] ) ) {
				$sameType = '';
				foreach( $_POST['current'] AS $key => $value ) {
					if ( !empty( $_POST['expire'] ) ) {
						$sameType .= array_search( $key, $_POST['expire'] );
					}
				}   
				$current_rule = serialize( $_POST['current'] );   
			} else {
				$errors[] = "Current Status field is required.";
			}
			
			if ( !empty( $_POST['expire'] ) ) {   
				$expiry_rule = serialize( $_POST['expire'] ); 
			} else {
				$errors[] = "Expiry Status field is required.";
			}
			
			if ( empty( $sameType ) AND empty( $errors ) ) {
				
				/*
				$table_name = $wpdb->prefix . "civi_member_sync";    
				$insert = $wpdb->get_results( 
					"REPLACE INTO $table_name ".
					"SET `wp_role` = '$wp_role', ".
					"`civi_mem_type` = '$civi_member_type', ".
					"`current_rule` = '$current_rule', ".
					"`expiry_rule` = '$expiry_rule', ".
					"`expire_wp_role` = '$expired_wp_role'"
				);
				
				$location = get_bloginfo('url')."/wp-admin/options-general.php?page=civi_member_sync/list.php";
				echo "<meta http-equiv='refresh' content='0;url=$location' />";
				exit;
				*/
				
			} else {
			
				if ( !empty( $sameType ) ) {  
					$errors[] = "You can not have the same Status Rule registered as both \"Current\" and \"Expired\".";
				}
				
				?><span class="error" style="color: #FF0000;"><?php 
					foreach ($errors AS $key => $values ) {
						echo $values."<br>";
					} 
				?></span><?php
				
			}

		}
		
			if ( isset( $_GET['q'] ) AND $_GET['q'] == 'delete' ) {
				if ( !empty( $_GET['id'] ) ) {

					// contruct table name
					$table_name = $wpdb->prefix . 'civi_member_sync';
					
					// noooo, redo this
					$delete = $wpdb->get_results( "DELETE FROM $table_name WHERE `id` = ".$_GET['id'] );        

				}
			}
			
		// --<
		return $result;
		
	}
	
	
	
	/**
	 * Get membership types
	 * @return array $membership_type List of types, key is ID, value is name
	 */
	public function get_types() {
		
		// init return
		$membership_type = array();

		// init CiviCRM
		civicrm_wp_initialize();

		$membership_type_details = civicrm_api( 'MembershipType', 'get', array(
			'version' => '3',
			'sequential' => '1',
		));

		foreach( $membership_type_details['values'] AS $key => $values ) {
			$membership_type[$values['id']] = $values['name']; 
		}
		
		// --<
		return $membership_type;

	}  



	/**
	 * Get membership statuses
	 * @return array $membership_status List of statuses, key is ID, value is name
	 */
	public function get_statuses() {
	
		// init return
		$membership_status = array();

		// init CiviCRM
		civicrm_wp_initialize();

		$membership_status_details = civicrm_api( 'MembershipStatus', 'get', array(
			'version' => '3',
			'sequential' => '1',
		));

		foreach( $membership_status_details['values'] AS $key => $values ) {
			$membership_status[$values['id']] = $values['name']; 
		}
		
		// --<
		return $membership_status;

	}  



	/**
	 * Get role/membership names
	 * @return string $current_roles The list of membership names, one per line
	 */
	public function get_names( $values, $memArray ) {  
	 
		$memArray = array_flip( $memArray );
		
		// init current rule
		$current_rule =  unserialize($values);
		if ( empty( $current_rule ) ) {
			$current_rule = $values; 
		}
		
		// init current roles
		$current_roles = ''; 
		if ( !empty( $current_rule ) ) { 
			if ( is_array( $current_rule ) ) {    
				foreach( $current_rule as $ckey => $cvalue ) {
					$current_roles .= array_search( $ckey, $memArray ) . '<br>';
				}
			}else{
				$current_roles = array_search( $current_rule, $memArray ) . '<br>';
			}    
		}
		
		// --<
		return $current_roles;
		
	}  



} // class ends



 


// declare as global for external reference
global $civi_member_sync;

// init plugin
$civi_member_sync = new Civi_Member_Sync;

// plugin activation
register_activation_hook( __FILE__, array( $civi_member_sync, 'install_db' ) );    

// uninstall uses the 'uninstall.php' method
// see: http://codex.wordpress.org/Function_Reference/register_uninstall_hook





/**
 * Add utility links to WordPress Plugin Listings Page
 * @return array $links The list of plugin links
 */
function civi_member_sync_plugin_add_settings_link( $links ) {
	$settings_link = '<a href="admin.php?page=civi_member_sync/list.php">'.__( 'Settings', 'civi_member_sync' ).'</a>';
  	array_push( $links, $settings_link );
  	return $links;
}

// contstriuct filter
$plugin = plugin_basename( __FILE__ );
add_filter( "plugin_action_links_$plugin", 'civi_member_sync_plugin_add_settings_link' );



