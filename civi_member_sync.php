<?php /* 
--------------------------------------------------------------------------------
Plugin Name: CiviCRM Member Role Sync
Plugin URI: https://github.com/christianwach/civi_member_sync
Description: Synchronize CiviCRM memberships with WordPress user roles
Author: Christian Wach
Version: 2.0
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
define( 'CIVI_MEMBER_SYNC_VERSION', '2.0' );

// define DB table version
define( 'CIVI_MEMBER_SYNC_DB_VERSION', '1.0' );

// store reference to this file
define( 'CIVI_MEMBER_SYNC_PLUGIN_FILE', __FILE__ );

// store URL to this plugin's directory
if ( ! defined( 'CIVI_MEMBER_SYNC_PLUGIN_URL' ) ) {
	define( 'CIVI_MEMBER_SYNC_PLUGIN_URL', plugin_dir_url( CIVI_MEMBER_SYNC_PLUGIN_FILE ) );
}
// store PATH to this plugin's directory
if ( ! defined( 'CIVI_MEMBER_SYNC_PLUGIN_PATH' ) ) {
	define( 'CIVI_MEMBER_SYNC_PLUGIN_PATH', plugin_dir_path( CIVI_MEMBER_SYNC_PLUGIN_FILE ) );
}



/**
 * Class for encapsulating plugin functionality
 */
class Civi_Member_Sync {

	/** 
	 * Properties
	 */
	
	// CiviCRM utilities class
	public $civi;
	
	// admin pages
	public $list_page;
	public $rules_page;
	public $sync_page;
	public $settings_page;
	
	// settings
	public $settings = array();
	
	
	
	/** 
	 * Initialise this object
	 * @return object
	 */
	function __construct() {
	
		// use translation
		add_action( 'plugins_loaded', array( $this, 'translation' ) );
		
		// initialise plugin when CiviCRM initialises
		add_action( 'civicrm_instance_loaded', array( $this, 'initialise' ) );
		
		// load our CiviCRM utility class
		require( CIVI_MEMBER_SYNC_PLUGIN_PATH . 'civi_member_sync_civi.php' );
		
		// initialise
		$this->civi = new Civi_Member_Sync_CiviCRM( $this );
	
		// --<
		return $this;
		
	}
	
	
	
	//##########################################################################
	
	
	
	/**
	 * Perform plugin activation tasks
	 * @return nothing
	 */
	public function activate() {
		
		// create MySQL table to track membership sync
	
		// access database object
		global $wpdb;
		
		// construct table name
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
		
		// create plugin options
		
		// store version for later reference
		add_option( 'civi_member_sync_db_version', CIVI_MEMBER_SYNC_DB_VERSION );
		
		// store default settings
		add_option( 'civi_member_sync_settings', $this->settings_get_default() );
	
	}
	
	
	
	/**
	 * Perform plugin deactivation tasks
	 * @return nothing
	 */
	public function deactivate() {
		
		// remove scheduled hook
		wp_clear_scheduled_hook( 'civi_member_sync_refresh' );
		
	}
	
	
	
	/**
	 * Register hooks when CiviCRM initialises
	 * @return nothing
	 */
	public function initialise() {
	
		// load settings array
		$this->settings = get_option( 'civi_member_sync_settings', $this->settings );
		
		// is this the back end?
		if ( is_admin() ) {
		
			// multisite?
			if ( is_multisite() ) {
	
				// add admin page to Network menu
				add_action( 'network_admin_menu', array( $this, 'admin_menu' ), 30 );
			
			} else {
			
				// add admin page to menu
				add_action( 'admin_menu', array( $this, 'admin_menu' ) ); 
			
			}
			
		}
		
		// initialise CiviCRM object
		$this->civi->initialise();
		
		// broadcast that we're up and running
		do_action( 'civi_member_sync_initialised' );
		
	}
	
	
	
	//##########################################################################
	
	
	
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
	 * Add this plugin's Settings Page to the WordPress admin menu
	 * @return nothing
	 */
	public function admin_menu() {
		
		// we must be network admin in multisite
		if ( is_multisite() AND !is_super_admin() ) { return false; }
		
		// check user permissions
		if ( !current_user_can('manage_options') ) { return false; }
		
		// multisite?
		if ( is_multisite() ) {
			
			// add the admin page to the Network Settings menu
			$this->list_page = add_submenu_page(
				'settings.php', 
				__( 'CiviCRM Member Role Sync', 'civi_member_sync' ), // page title
				__( 'CiviCRM Member Role Sync', 'civi_member_sync' ), // menu title
				'manage_options', // required caps
				'civi_member_sync_list', // slug name
				array( $this, 'rules_list' ) // callback
			);
		
		} else {
		
			// add the admin page to the Settings menu
			$this->list_page = add_options_page(
				__( 'CiviCRM Member Role Sync', 'civi_member_sync' ), // page title
				__( 'CiviCRM Member Role Sync', 'civi_member_sync' ), // menu title
				'manage_options', // required caps
				'civi_member_sync_list', // slug name
				array( $this, 'rules_list' ) // callback
			);
		
		}
		
		// add scripts and styles
		add_action( 'admin_print_styles-'.$this->list_page, array( $this, 'admin_css' ) );
		add_action( 'admin_head-'.$this->list_page, array( $this, 'admin_head' ), 50 );
		
		// add list page
		$this->rules_page = add_submenu_page(
			'civi_member_sync_list', // parent slug
			__( 'CiviCRM Member Role Sync: Association Rules', 'civi_member_sync' ), // page title
			__( 'Association Rules', 'civi_member_sync' ), // menu title
			'manage_options', // required caps
			'civi_member_sync_rules', // slug name
			array( $this, 'rules_add_edit' ) // callback
		);
		
		// add scripts and styles
		add_action( 'admin_print_scripts-'.$this->rules_page, array( $this, 'admin_js' ) );
		add_action( 'admin_print_styles-'.$this->rules_page, array( $this, 'admin_css' ) );
		add_action( 'admin_head-'.$this->rules_page, array( $this, 'admin_head' ), 50 );
		
		// add manual sync page
		$this->sync_page = add_submenu_page(
			'civi_member_sync_list', // parent slug
			__( 'CiviCRM Member Role Sync: Manual Sync', 'civi_member_sync' ), // page title
			__( 'Manual Sync', 'civi_member_sync' ), // menu title
			'manage_options', // required caps
			'civi_member_sync_manual_sync', // slug name
			array( $this, 'rules_sync' ) // callback
		);
		
		// add scripts and styles
		add_action( 'admin_print_styles-'.$this->sync_page, array( $this, 'admin_css' ) );
		add_action( 'admin_head-'.$this->sync_page, array( $this, 'admin_head' ), 50 );
		
		// add settings page
		$this->settings_page = add_submenu_page(
			'civi_member_sync_list', // parent slug
			__( 'CiviCRM Member Role Sync: Settings', 'civi_member_sync' ), // page title
			__( 'Settings', 'civi_member_sync' ), // menu title
			'manage_options', // required caps
			'civi_member_sync_settings', // slug name
			array( $this, 'rules_settings' ) // callback
		);
		
		// add scripts and styles
		add_action( 'admin_print_styles-'.$this->settings_page, array( $this, 'admin_css' ) );
		add_action( 'admin_head-'.$this->settings_page, array( $this, 'admin_head' ), 50 );
		
		// try and update options
		$saved = $this->admin_update();
		
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
		if ( $screen->id != $this->list_page ) { return; }
		
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
	
	
	
	//##########################################################################
	
	
	
	/** 
	 * Show civi_member_sync_list admin page
	 * @return nothing
	 */
	public function rules_list() {
		
		// check user permissions
		if ( current_user_can('manage_options') ) {
		
			// get admin page URLs
			$urls = $this->get_admin_urls(); 

			// access database object
			global $wpdb;
			
			// get tabular data
			$table_name = $wpdb->prefix . 'civi_member_sync';
			$select = $wpdb->get_results( "SELECT * FROM $table_name" );
			
			// include template file
			include( CIVI_MEMBER_SYNC_PLUGIN_PATH . 'list.php' );
		
		}
		
	}
	
	
	
	/** 
	 * Show civi_member_sync_rules admin page
	 * @return nothing
	 */
	public function rules_add_edit() {
	
		// check user permissions
		if ( current_user_can('manage_options') ) {
			
			// get admin page URLs
			$urls = $this->get_admin_urls(); 
			
			// get all membership types
			$membership_types = $this->civi->get_types();
			
			// get all membership status rules
			$status_rules = $this->civi->get_status_rules();
			
			// get filtered roles
			$roles = $this->get_wp_role_names();
			//print_r( $roles ); die();
			
			// do we want to populate the form?
			if ( isset( $_GET['q'] ) AND $_GET['q'] == 'edit' ) {
				if ( isset( $_GET['id'] ) AND is_numeric( $_GET['id'] ) ) {
					
					// access db object
					global $wpdb;
					
					// get rule
					$table_name = $wpdb->prefix . 'civi_member_sync';
					$sql = $wpdb->prepare( "SELECT * FROM $table_name WHERE `id` = %d", absint( $_GET['id'] ) );
					$select = $wpdb->get_row( $sql );
					//print_r( array( $sql, $select ) ); die();
					
					// set vars for populating form
					$wp_role = $select->wp_role; 
					$civi_member_type = $select->civi_mem_type;
					$current_rule = maybe_unserialize( $select->current_rule );
					$expiry_rule = maybe_unserialize( $select->expiry_rule );
					$expired_wp_role = $select->expire_wp_role; 
				
				}
			}
			
			// include template file
			include( CIVI_MEMBER_SYNC_PLUGIN_PATH . 'rules.php' );
		
		}
		
	}
	
	
	
	/** 
	 * Show civi_member_sync_manual_sync admin page
	 * @return nothing
	 */
	public function rules_sync() {
		
		// check user permissions
		if ( current_user_can('manage_options') ) {

			// get admin page URLs
			$urls = $this->get_admin_urls(); 

			// include template file
			include( CIVI_MEMBER_SYNC_PLUGIN_PATH . 'manual_sync.php' );
		
		}
		
	}
	
	
		
	/** 
	 * Show civi_member_sync_settings admin page
	 * @return nothing
	 */
	public function rules_settings() {
		
		// check user permissions
		if ( current_user_can('manage_options') ) {

			// get admin page URLs
			$urls = $this->get_admin_urls(); 

			// get all schedules
			$schedules = wp_get_schedules();
			//print_r( $schedules ); die();
			
			// get our sync settings
			$login = absint( $this->setting_get( 'login' ) );
			$civicrm = absint( $this->setting_get( 'civicrm' ) );
			$schedule = absint( $this->setting_get( 'schedule' ) );
			
			// get our interval setting
			$interval = $this->setting_get( 'interval' );
			
			// include template file
			include( CIVI_MEMBER_SYNC_PLUGIN_PATH . 'settings.php' );
		
		}
		
	}
	
	
		
	//##########################################################################
	
	
	
	/** 
	 * Save the settings set by the administrator
	 * @return bool $result Success or failure
	 */
	public function admin_update() {
	
		// init result
		$result = false;
		
		// was the rules form submitted?
		if( isset( $_POST[ 'civi_member_sync_rules_submit' ] ) ) {
			$result = $this->civi->update_rule();
		}
		
		// was a delete link clicked?
		if ( isset( $_GET['syncrule'] ) AND $_GET['syncrule'] == 'delete' ) {
			if ( ! empty( $_GET['id'] ) AND is_numeric( $_GET['id'] ) ) {
				$result = $this->civi->delete_rule();
			}
		}
		
		// was the Manual Sync form submitted?
		if( isset( $_POST[ 'civi_member_sync_manual_sync_submit' ] ) ) {
			$result = $this->civi->do_manual_sync();
		}
		
		// was the Settings form submitted?
		if( isset( $_POST[ 'civi_member_sync_settings_submit' ] ) ) {
			$result = $this->settings_update();
		}
		
		// --<
		return $result;
		
	}
	
	
	
	/**
	 * Get default plugin settings
	 * @return array $settings The array of settings, keyed by setting name
	 */
	public function settings_get_default() {
	
		// init return
		$settings = array();
		
		// switch all sync settings on by default
		$settings['login'] = 1;
		$settings['civicrm'] = 1;
		$settings['schedule'] = 1;
		
		// set default schedule interval
		$settings['interval'] = 'daily';
		
		// allow filtering
		return apply_filters( 'civi_member_sync_default_settings', $settings );
	
	}
	
	
	
	/**
	 * Update plugin settings
	 * @return nothing
	 */
	public function settings_update() {
	
		// check that we trust the source of the request
		check_admin_referer( 'civi_member_sync_settings_action', 'civi_member_sync_nonce' );
		//print_r( $_POST ); die();
		
		
		
		// debugging switch for admins and network admins - if set, triggers do_debug() below
		if ( is_super_admin() AND isset( $_POST['civi_member_sync_settings_debug'] ) ) {
			$settings_debug = absint( $_POST['civi_member_sync_settings_debug'] );
			$debug = $settings_debug ? 1 : 0;
			if ( $debug ) { $this->do_debug(); }
			return;
		}
		
		
		
		// login/logout sync enabled
		if ( isset( $_POST['civi_member_sync_settings_login'] ) ) {
			$settings_login = absint( $_POST['civi_member_sync_settings_login'] );
		} else {
			$settings_login = 0;
		}
		$this->setting_set( 'login', ( $settings_login ? 1 : 0 ) );
		
		
		
		// civicrm sync enabled
		if ( isset( $_POST['civi_member_sync_settings_civicrm'] ) ) {
			$settings_civicrm = absint( $_POST['civi_member_sync_settings_civicrm'] );
		} else {
			$settings_civicrm = 0;
		}
		$this->setting_set( 'civicrm', ( $settings_civicrm ? 1 : 0 ) );
		
		
		
		// get existing schedule
		$existing_schedule = $this->setting_get( 'schedule' );
		
		// schedule sync enabled
		if ( isset( $_POST['civi_member_sync_settings_schedule'] ) ) {
			$settings_schedule = absint( $_POST['civi_member_sync_settings_schedule'] );
		} else {
			$settings_schedule = 0;
		}
		$this->setting_set( 'schedule', ( $settings_schedule ? 1 : 0 ) );
		
		// is the schedule being deactivated?
		if ( $existing_schedule == 1 AND $settings_schedule === 0 ) {

			// clear current scheduled event
			$this->clear_schedule();
			
		}
		
		
		
		// schedule interval
		if ( isset( $_POST['civi_member_sync_settings_interval'] ) ) {
		
			// get existing interval
			$existing_interval = $this->setting_get( 'interval' );
			
			// get value passed in
			$settings_interval = esc_sql( trim( $_POST['civi_member_sync_settings_interval'] ) );
			
			// is the schedule active and has the interval changed?
			if ( $settings_schedule AND $settings_interval != $existing_interval ) {
			
				// clear current scheduled event
				$this->clear_schedule();
				
				// now add new scheduled event
				wp_schedule_event( time(), $settings_interval, 'civi_member_sync_refresh' );
			
			}
			
			// set new value whatever (for now)
			$this->setting_set( 'interval', $settings_interval );
			
		}
		
		// save settings
		$this->settings_save();
		
		// get admin URLs
		$urls = $this->get_admin_urls();
		
		// redirect to settings page with message
		wp_redirect( $urls['settings'] . '&updated=true' );
		die();
		
	}
	
	
	
	/** 
	 * Save the plugin's settings array
	 * @return bool $result True if setting value has changed, false if not or if update failed
	 */
	public function settings_save() {
		
		// update WordPress option and return result
		return update_option( 'civi_member_sync_settings', $this->settings );
		
	}
	
	
	
	/** 
	 * Return a value for a specified setting
	 * @return mixed $setting The value of the setting
	 */
	public function setting_get( $setting_name = '', $default = false ) {
	
		// sanity check
		if ( $setting_name == '' ) {
			die( __( 'You must supply a setting to setting_get()', 'civi_member_sync' ) );
		}
	
		// get setting
		return ( array_key_exists( $setting_name, $this->settings ) ) ? $this->settings[ $setting_name ] : $default;
		
	}
	
	
	
	/** 
	 * Set a value for a specified setting
	 * @return nothing
	 */
	public function setting_set( $setting_name = '', $value = '' ) {
	
		// sanity check
		if ( $setting_name == '' ) {
			die( __( 'You must supply a setting to setting_set()', 'civi_member_sync' ) );
		}
	
		// set setting
		$this->settings[ $setting_name ] = $value;
		
	}
	
	
	
	//##########################################################################
	
	
	
	/** 
	 * Clear our scheduled event
	 * @return nothing
	 */
	public function clear_schedule() {
		
		// get next scheduled event
		$timestamp = wp_next_scheduled( 'civi_member_sync_refresh' );
		
		// unschedule it if we get one
		if ( $timestamp !== false ) {
			wp_unschedule_event( $timestamp, 'civi_member_sync_refresh' );
		}
		
		// it's not clear whether wp_unschedule_event() clears everything,
		// so let's remove existing scheduled hook as well
		wp_clear_scheduled_hook( 'civi_member_sync_refresh' );
		
	}
	
	
	
	//##########################################################################
	
	
	
	/**
	 * Get a WordPress user for a Civi contact ID
	 * @param int $contact_id The numeric CiviCRM contact ID
	 * @return WP_User $user WP_User object for the WordPress user
	 */
	public function get_wp_user( $contact_id ) {
		
		// kick out if no CiviCRM
		if ( ! civi_wp()->initialize() ) return false;
		
		// make sure Civi file is included
		require_once 'CRM/Core/BAO/UFMatch.php';
			
		// search using Civi's logic
		$user_id = CRM_Core_BAO_UFMatch::getUFId( $contact_id );
		
		// get user object
		$user = new WP_User( $user_id );
		
		// --<
		return $user;
		
	}
	
	
	
	/**
	 * Get WordPress user role
	 * @param WP_User $user WP_User object
	 * @return string $role Primary WordPress role for this user
	 */
	public function get_wp_role( $user ) {
	
		// kick out if we don't receive a valid user
		if ( ! is_a( $user, 'WP_User' ) ) return false;
		
		// only build role names array once, since this is called by the sync routine
		if ( ! isset( $this->role_names ) ) {
		
			// get role names array
			$this->role_names = $this->get_wp_role_names();
		
		}
		
		// init filtered as empty
		$filtered_roles = array_keys( $this->role_names );
		
		// roles is still an array
		foreach ( $user->roles AS $role ) {
		
			// return the first valid one
			if ( $role AND in_array( $role, $filtered_roles ) ) { return $role; }
		
		}
	
		// fallback
		return false;
		
	}
	
	
		
	/**
	 * Set WordPress user role
	 * @param WP_User $user WP_User object of the logged-in user.
	 * @param string $old_role Old WordPress role key
	 * @param string $new_role New WordPress role key
	 * @return nothing
	 */
	public function set_wp_role( $user, $old_role, $new_role ) {
		
		// kick out if we don't receive a valid user
		if ( ! is_a( $user, 'WP_User' ) ) return;
		
		// sanity check params
		if ( empty( $old_role ) ) return;
		if ( empty( $new_role ) ) return;
		
		// Remove old role then add new role, so that we don't inadventently 
		// overwrite multiple roles, for example when BBPress is active
		
		// remove user's existing role
		$user->remove_role( $old_role );
		 
		// add new role
		$user->add_role( $new_role );
		 
	}
	
	
		
	/**
	 * Get a WordPress role name by role key
	 * @param string $key The machine-readable name of the WP_Role
	 * @return string $role_name The human-readable name of the WP_Role
	 */
	public function get_wp_role_name( $key ) {
		
		// only build role names array once, since this is called by the list page
		if ( ! isset( $this->role_names ) ) {
		
			// get role names array
			$this->role_names = $this->get_wp_role_names();
		
		}
		
		// get value by key
		$role_name = isset( $this->role_names[$key] ) ? $this->role_names[$key] : false;
		
		// --<
		return $role_name;
		
	}
	
	
		
	/**
	 * Get all WordPress role names
	 * @return array $role_names An array of role names, keyed by role key
	 */
	public function get_wp_role_names() {
		
		// access roles global
		global $wp_roles;

		// load roles if not set
		if ( ! isset( $wp_roles ) ) {
			$wp_roles = new WP_Roles();
		}
		
		// get names
		$role_names = $wp_roles->get_names();
		
		// if we have BBPress active, filter out its custom roles
		if ( function_exists( 'bbp_get_blog_roles' ) ) {
		
			// get BBPress-filtered roles
			$bbp_roles = bbp_get_blog_roles();
			
			// init roles
			$role_names = array();
			
			// sanity check
			if ( ! empty( $bbp_roles ) ) {
				foreach( $bbp_roles AS $bbp_role => $bbp_role_data ) {
					
					// add to roles array
					$role_names[$bbp_role] = $bbp_role_data['name'];
					
				}
			}
			
		}
		
		//print_r( $role_names ); die();
		
		// --<
		return $role_names;
		
	}
	
	
	
	//##########################################################################
	
	
	
	/** 
	 * Get the URL for the form action
	 * @return string $target_url The URL for the admin form action
	 */
	public function get_form_url() {
	
		// sanitise admin page url
		$target_url = $_SERVER['REQUEST_URI'];
		$url_array = explode( '&', $target_url );
		if ( $url_array ) { $target_url = htmlentities( $url_array[0].'&updated=true' ); }
		
		// --<
		return $target_url;
		
	}
	
	
	
	/** 
	 * Get admin page URLs
	 * @return array $admin_urls The array of admin page URLs
	 */
	public function get_admin_urls() {
		
		// only calculate once
		if ( isset( $this->urls ) ) { return $this->urls; }
		
		// init return
		$this->urls = array();
		
		// multisite?
		if ( is_multisite() ) {
		
			// get admin page URLs via our adapted method
			$this->urls['list'] = $this->network_menu_page_url( 'civi_member_sync_list', false );
			$this->urls['rules'] = $this->network_menu_page_url( 'civi_member_sync_rules', false ); 
			$this->urls['manual_sync'] = $this->network_menu_page_url( 'civi_member_sync_manual_sync', false ); 
			$this->urls['settings'] = $this->network_menu_page_url( 'civi_member_sync_settings', false ); 
		
		} else {
		
			// get admin page URLs
			$this->urls['list'] = menu_page_url( 'civi_member_sync_list', false );
			$this->urls['rules'] = menu_page_url( 'civi_member_sync_rules', false ); 
			$this->urls['manual_sync'] = menu_page_url( 'civi_member_sync_manual_sync', false ); 
			$this->urls['settings'] = menu_page_url( 'civi_member_sync_settings', false ); 
		
		}
		
		// --<
		return $this->urls;
		
	}
	
	
	
	/**
	 * Get the url to access a particular menu page based on the slug it was registered with.
	 * If the slug hasn't been registered properly no url will be returned
	 * @param string $menu_slug The slug name to refer to this menu by (should be unique for this menu)
	 * @param bool $echo Whether or not to echo the url - default is true
	 * @return string the url
	 */
	public function network_menu_page_url($menu_slug, $echo = true) {
		global $_parent_pages;
		
		if ( isset( $_parent_pages[$menu_slug] ) ) {
			$parent_slug = $_parent_pages[$menu_slug];
			if ( $parent_slug && ! isset( $_parent_pages[$parent_slug] ) ) {
				$url = network_admin_url( add_query_arg( 'page', $menu_slug, $parent_slug ) );
			} else {
				$url = network_admin_url( 'admin.php?page=' . $menu_slug );
			}
		} else {
			$url = '';
		}
		
		$url = esc_url($url);
		
		if ( $echo ) echo $url;
		
		return $url;
	}
	
	
	
	//##########################################################################
	
	
	
	/** 
	 * General debugging utility
	 * @return nothing
	 */
	public function do_debug() {
		
		global $wp_roles;
		$roles = $wp_roles->get_names();
		
		// get all role names
		$role_names = $this->get_wp_role_names();
		
		print_r( array( 
			'WP Roles' => $roles,
			'WP Role Names' => $role_names, 
		) ); die();
		
		if ( function_exists( 'bbp_get_blog_roles' ) ) {
			$bbpress_roles = bbp_get_blog_roles();
		}
		
	}
	
	
	
} // class ends






// declare as global for external reference
global $civi_member_sync;

// init plugin
$civi_member_sync = new Civi_Member_Sync;

// plugin activation
register_activation_hook( __FILE__, array( $civi_member_sync, 'activate' ) );

// plugin deactivation
register_deactivation_hook( __FILE__, array( $civi_member_sync, 'deactivate' ) );

// uninstall uses the 'uninstall.php' method
// see: http://codex.wordpress.org/Function_Reference/register_uninstall_hook





/**
 * Add utility links to WordPress Plugin Listings Page
 * @return array $links The list of plugin links
 */
function civi_member_sync_plugin_add_settings_link( $links ) {
	
	// access plugin
	global $civi_member_sync;
	
	// get admin URLs
	$urls = $civi_member_sync->get_admin_urls();
	
	// add courtesy link
	$links[] = '<a href="' . $urls['settings'] . '">' . __( 'Settings', 'civi_member_sync' ) . '</a>';
	
	// --<
	return $links;
	
}

// contstriuct filter
$plugin = plugin_basename( __FILE__ );
add_filter( "plugin_action_links_$plugin", 'civi_member_sync_plugin_add_settings_link' );



