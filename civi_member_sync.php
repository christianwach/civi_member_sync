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
	
	// CiviCRM utilities class
	public $civi;
	
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
		
		// load our CiviCRM utility class
		require( CIVI_MEMBER_SYNC_PLUGIN_PATH . 'civi_member_sync_civi.php' );
		
		// initialise
		$this->civi = new Civi_Member_Sync_CiviCRM;
	
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
		
		// store version for later reference
		add_option( 'civi_member_sync_db_version', CIVI_MEMBER_SYNC_DB_VERSION );
	
	}
	
	
	

	/**
	 * Register hooks when CiviCRM initialises
	 * @return nothing
	 */
	public function initialise() {
	
		// add menu items
		add_action( 'admin_menu', array( $this, 'admin_menu' ) ); 
		
		// initialise CiviCRM object
		$this->civi->initialise();
		
		// broadcast that we're up and running
		do_action( 'civi_member_sync_initialised' );
	}
	
	
	
	/**
	 * Add this plugin's Settings Page to the WordPress admin menu
	 * @return nothing
	 */
	public function admin_menu() {
		
		// check user permissions
		if ( current_user_can('manage_options') ) {

			// add options page
			$this->list_page = add_options_page(
				__( 'CiviCRM Member Role Sync', 'civi_member_sync' ), // page title
				__( 'CiviCRM Member Role Sync', 'civi_member_sync' ), // menu title
				'manage_options', // required caps
				'civi_member_sync_list', // slug name
				array( $this, 'rules_list' ) // callback
			);
		
			// add scripts and styles
			add_action( 'admin_print_styles-'.$this->list_page, array( $this, 'admin_css' ) );
			add_action( 'admin_head-'.$this->list_page, array( $this, 'admin_head' ), 50 );
			
			//  add first sub item
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
			
			//  add second sub item
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

			// try and update options
			$saved = $this->admin_update();
			
		}
		
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
	public function admin_update() {
	
		// init result
		$result = false;
		
	 	// was the rules form submitted?
		if( isset( $_POST[ 'civi_member_sync_rules_submit' ] ) ) {
			$result = $this->civi->update_rules();
		}
		
		// was the Manual Sync form submitted?
		if( isset( $_POST[ 'civi_member_sync_manual_sync_submit' ] ) ) {
			$result = $this->civi->do_manual_sync();
		}

		// was a delete link clicked?
		if ( isset( $_GET['syncrule'] ) AND $_GET['syncrule'] == 'delete' ) {
			if ( !empty( $_GET['id'] ) AND is_numeric( $_GET['id'] ) ) {
				$result = $this->civi->delete_rule();
			}
		}
		
		// --<
		return $result;
		
	}
	
	
	
	/** 
	 * Show civi_member_sync_list admin page
	 * @return nothing
	 */
	public function rules_list() {
		
		// check user permissions
		if ( current_user_can('manage_options') ) {

			// access database object
			global $wpdb;

			// get membership data
			$membership_type = $this->civi->get_types();
			$membership_status = $this->civi->get_statuses();

			// get admin page URLs
			$list_url = menu_page_url( 'civi_member_sync_list', false );
			$rules_url = menu_page_url( 'civi_member_sync_rules', false ); 
			$manual_sync_url = menu_page_url( 'civi_member_sync_manual_sync', false ); 

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
			
			// get membership data
			$membership_type = $this->civi->get_types();
			$membership_status = $this->civi->get_statuses();
			
			// get admin page URLs
			$list_url = menu_page_url( 'civi_member_sync_list', false );
			$manual_sync_url = menu_page_url( 'civi_member_sync_manual_sync', false ); 

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
					$current_rule = unserialize( $select->current_rule );
					$expiry_rule = unserialize( $select->expiry_rule );
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
			$list_url = menu_page_url( 'civi_member_sync_list', false );
			$rules_url = menu_page_url( 'civi_member_sync_rules', false ); 

			// include template file
			include( CIVI_MEMBER_SYNC_PLUGIN_PATH . 'manual_sync.php' );
		
		}
		
	}
	
	
		
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
  	$links[] = '<a href="admin.php?page=civi_member_sync_list">' . __( 'Settings', 'civi_member_sync' ) . '</a>';
  	return $links;
}

// contstriuct filter
$plugin = plugin_basename( __FILE__ );
add_filter( "plugin_action_links_$plugin", 'civi_member_sync_plugin_add_settings_link' );



