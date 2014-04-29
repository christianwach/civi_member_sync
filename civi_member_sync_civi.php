<?php /*
--------------------------------------------------------------------------------
Civi_Member_Sync_CiviCRM Class
--------------------------------------------------------------------------------
*/

class Civi_Member_Sync_CiviCRM {
	
	
	
	/**
	 * Properties
	 */
	
	// parent object
	public $parent_obj;
	
	// form error messages
	public $error_strings;
	
	// errors in current submission
	public $errors;
	
	
	
	/** 
	 * Initialise this object
	 * @param object $parent_obj The parent object
	 * @return object
	 */
	function __construct( $parent_obj ) {
		
		// store reference to parent
		$this->parent_obj = $parent_obj;
	
		// define errors
		$this->error_strings = array(
			
			// update rules error strings
			1 => __( 'Please select a CiviCRM Membership Type', 'civi_member_sync' ),
			2 => __( 'Please select a WordPress Role.', 'civi_member_sync' ),
			3 => __( 'Please select a Current Status', 'civi_member_sync' ),
			4 => __( 'Please select an Expire Status', 'civi_member_sync' ),
			5 => __( 'Please select a WordPress Expiry Role', 'civi_member_sync' ),
			6 => __( 'You can not have the same Status Rule registered as both "Current" and "Expired"', 'civi_member_sync' ),
			
			// delete rule error strings
			7 => __( 'Could not delete Association Rule', 'civi_member_sync' ),
			
		);
	
		// --<
		return $this;
		
	}
	
	
	
	//##########################################################################
	
	
	
	/**
	 * Register hooks when CiviCRM initialises
	 * @return nothing
	 */
	public function initialise() {
	
		// get our schedule sync setting
		$schedule = absint( $this->parent_obj->setting_get( 'schedule' ) );
		
		// add hooks if set
		if ( $schedule === 1 ) {
		
			// get our interval setting
			$interval = $this->parent_obj->setting_get( 'interval' );
		
			// sanity check
			if ( ! empty( $interval ) AND $interval == 1 ) {
		
				// add schedule, if not already present
				if ( ! wp_next_scheduled( 'civi_member_sync_refresh' ) ) {
					wp_schedule_event( time(), $interval, 'civi_member_sync_refresh' );
				}
		
			}
		
			// add cron callback action
			add_action( 'civi_member_sync_refresh', array( $this, 'sync_interval' ) );
		
		}
		
		// get our login/logout sync setting
		$login = absint( $this->parent_obj->setting_get( 'login' ) );
		
		// add hooks if set
		if ( $login === 1 ) {
		
			// add login check
			add_action( 'wp_login', array( $this, 'sync_user' ), 10, 2 );
		
			// add logout check (can't use 'wp_logout' action, as user no longer exists)
			add_action( 'clear_auth_cookie', array( $this, 'sync_on_logout' ) );
		
		}
		
		// get our civicrm sync setting
		$civicrm = absint( $this->parent_obj->setting_get( 'civicrm' ) );
		
		// add hooks if set
		if ( $civicrm === 1 ) {
		
			// intercept CiviCRM membership add/edit form submission
			add_action( 'civicrm_postProcess', array( $this, 'form_process' ), 10, 2 );
		
			//intercept a CiviCRM membership update
			add_action( 'civicrm_post', array( $this, 'civi_membership_updated' ), 10, 4 );
		
		}
		
	}
	
	
	
	//##########################################################################
	
	
	
	/**
	 * @description: update a WP user role when a CiviCRM membership is updated
	 * @param string $op the type of database operation
	 * @param string $objectName the type of object
	 * @param integer $objectId the ID of the object
	 * @param object $objectRef the object
	 * @return nothing
	 */
	public function civi_membership_updated( $op, $objectName, $objectId, $objectRef ) {
		
		// target our object type
		if ( $objectName != 'Membership' ) { return; }
		
		/*
		print_r( array( 
			'op' => $op,
			'objectName' => $objectName,
			'objectId' => $objectId,
			'objectRef' => $objectRef,
		)); die();
		*/
		
		// catch create and edit operations
		if ( $op == 'edit' OR $op == 'create' ) {
		
			// kick out if not membership object
			if ( ! is_a( $objectRef, 'CRM_Member_BAO_Membership' ) ) { return; }
		
			// kick out if we don't have a contact ID
			if ( ! isset( $objectRef->contact_id ) ) { return; }
		
			// get WordPress user for this contact ID
			$user = $this->parent_obj->get_wp_user( $objectRef->contact_id );
		
			// kick out if we don't receive a valid user
			if ( ! is_a( $user, 'WP_User' ) ) { return; }
			if ( ! $user->exists() ) { return; }
		
			// exclude admins
			if ( is_super_admin( $user->ID ) OR $user->has_cap( 'delete_users' ) ) { return; }
		
			// get primary WP role
			$user_role = $this->parent_obj->get_wp_role( $user );
		
			// reformat $objectRef as if it was an API return
			$membership = array( 
				'is_error' => 0,
				'values' => array( (array) $objectRef ),
			);
		
			// update WP role by membership
			$success = $this->member_check( $objectRef->contact_id, $user, $user_role, $membership );
			// do we care about success?
		
		}
		
		// catch delete operation
		if ( $op == 'delete' ) {
		
			// do we assign the expired role?
		
		}
		
	}
	
	
	
	/**
	 * Update a WordPress user role when a Civi membership is added
	 * @param string $formName the CiviCRM form name
	 * @param object $form the CiviCRM form object
	 * @return nothing
	 */
	public function form_process( $formName, &$form ) {
		
		/*
		print_r( array(
			'formName' => $formName,
			'form' => $form,
		) ); die();
		*/
		
		// kick out if not membership form
		if ( ! is_a( $form, 'CRM_Member_Form_Membership' ) ) { return; }
		
	}
	
	
	
	/**
	 * Sync membership rules for all users when scheduled event is triggered
	 * @return nothing
	 */
	public function sync_interval() {
		
		// disable for now
		return;
		
		// call sync all method
		$this->sync_all();
	
	}
	
	
	
	/**
	 * Do Manual Sync of membership rules
	 * @return bool $success True if successful, false otherwise
	 */
	public function do_manual_sync() {
	
		// check that we trust the source of the request
		check_admin_referer( 'civi_member_sync_manual_sync_action', 'civi_member_sync_nonce' );
		
		// trace
		//print_r( $_POST ); die();
		
		// call sync all method
		$this->sync_all();
	
	}
	
	
	
	/**
	 * Sync all membership rules
	 * @return bool $success True if successful, false otherwise
	 */
	public function sync_all() {
	
		// kick out if no CiviCRM
		if ( ! civi_wp()->initialize() ) { return; }
		
		// make sure Civi file is included
		require_once 'CRM/Core/BAO/UFMatch.php';
		
		// get all WordPress users
		$users = get_users( array( 'all_with_meta' => true ) );
		//print_r( $users ); die();
		
		// loop through all users
		foreach( $users AS $user ) {
			
			// skip if we don't have a valid user
			if ( ! is_a( $user, 'WP_User' ) ) { continue; }
			if ( ! $user->exists() ) { continue; }
			
			// call login method
			$this->sync_user( $user->user_login, $user );
		
		}
		
	}
	
	
	
	/**
	 * Check user's membership record during logout
	 * @return nothing
	 */
	public function sync_on_logout() {
		
		// get user
		$user = wp_get_current_user();
		$user_login = $user->user_login;
		
		// call login method
		$this->sync_user( $user_login, $user );
		
	}
	
	
	
	/**
	 * Sync a user's role based on their membership record
	 * @param string $user_login Logged in user's username
	 * @param WP_User $user WP_User object of the logged-in user.
	 * @return nothing
	 */
	public function sync_user( $user_login, $user ) {
	
		// kick out if we don't receive a valid user
		if ( ! is_a( $user, 'WP_User' ) ) { return; }
		if ( ! $user->exists() ) { return; }
		//print_r( array( $user_login, $user ) ); //die();
		
		// exclude admins
		if ( is_super_admin( $user->ID ) OR $user->has_cap( 'delete_users' ) ) { return; }
		
		// get Civi contact ID
		$civi_contact_id = $this->get_civi_contact_id( $user );
		
		// bail if we don't have one
		if ( $civi_contact_id === false ) { return; }
		
		// get primary WP role
		$user_role = $this->parent_obj->get_wp_role( $user );
		
		// we *must* have that ID now...
		$success = $this->member_check( $civi_contact_id, $user, $user_role );
		// do we care about success?

	}
	
	
	
	/**
	 * Check membership record and assign WordPress role based on membership status
	 * @param int $civi_contact_id The numerical CiviCRM contact ID
	 * @param WP_User $user WP_User object of the logged-in user.
	 * @param string $user_role The primary role of the current WordPress user
	 * @param array $membership_details The membership details of the current WordPress user
	 * @return bool True if successful, false otherwise
	 */
	public function member_check( $civi_contact_id, $user, $user_role, $membership_details = false ) {
		
		// removed check for admin user - DO NOT call this for admins UNLESS 
		// you're using a plugin that enables multiple roles
		
		// kick out if no CiviCRM
		if ( ! civi_wp()->initialize() ) { return false; }
		
		// if we didn't get details passed, get them
		if ( $membership_details === false ) {
		
			// get Civi membership details
			$membership_details = civicrm_api( 'Membership', 'get', array(
				'version' => '3',
				'page' => 'CiviCRM',
				'q' => 'civicrm/ajax/rest',
				'sequential' => '1',
				'contact_id' => $civi_contact_id,
			));
		
		}
		
		// trace
		//print_r( $membership_details ); die();
		
		// if we have membership details
		if (
		
			$membership_details['is_error'] == 0 AND 
			isset( $membership_details['values'] ) AND 
			count( $membership_details['values'] ) > 0 
			
		) {
			
			// Civi should return a 'values' array with just one element
		
			// get membership type and status rule
			foreach( $membership_details['values'] AS $value ) {
				$membership_type_id = $value['membership_type_id'];
				$status_id = $value['status_id'];
			}
			//print_r( array( $membership_type_id, membership$status_id ) ); die();
		
			// kick out if something went wrong
			if ( ! isset( $membership_type_id ) ) { return false; }
			if ( ! isset( $status_id ) ) { return false; }
		
			// get association rule for this membership type
			$association_rule = $this->get_rule_by_type( $membership_type_id );
			//print_r( $association_rule ); die();
		
			// kick out if we have an error of some kind
			if ( $association_rule === false ) { return false; }
		
			// get status rules
			$current_rule = maybe_unserialize( $association_rule->current_rule );
			$expiry_rule = maybe_unserialize( $association_rule->expiry_rule );
			
			/*
			print_r( array(
				'status_id' => $status_id,
				'current_rule' => $current_rule,
				'expiry_rule' => $expiry_rule,
				'user_role' => $user_role,
				'wp_role' => $association_rule->wp_role,
			) ); die();
			*/
			
			// does the user's membership status match a current status rule?
			if ( isset( $status_id ) && array_search( $status_id, $current_rule ) ) {
				
				// yes - get role for current status rule
				$wp_role = $association_rule->wp_role;
				
				// if we have one (we should) and the user has a different role...
				if (  ! empty( $wp_role ) AND $wp_role != $user_role ) {
					
					// no - set new role
					$this->parent_obj->set_wp_role( $user, $user_role, $wp_role );
					 
				}
			
			} else {
		
				// no - get role for expired status rule
				$expired_wp_role = $association_rule->expire_wp_role;
				
				// if we have one (we should) and the user has a different role...
				if ( ! empty( $expired_wp_role ) AND $expired_wp_role != $user_role ) {
				
					// switch user's role to the expired role
					$this->parent_obj->set_wp_role( $user, $user_role, $expired_wp_role );
					
				}
			
			}
		
			// --<
			return true;
		
		}
		
		// --<
		return false;
		
	}
	
	
	
	//##########################################################################
	
	
	
	/**
	 * Get a membership rule
	 * @return bool $success True if successful, false otherwise
	 */
	public function get_rule_by_type( $type ) {
		
		// access database object
		global $wpdb;
		
		// construct table name
		$table_name = $wpdb->prefix . 'civi_member_sync';
		
		// construct query
		$sql = $wpdb->prepare( "SELECT * FROM $table_name WHERE civi_mem_type = %d", $type );
		
		// do query and return result if successful
		if ( $row = $wpdb->get_row( $sql ) ) { return $row; }
		
		// return error
		return false;
		
	}
	
	
	
	/**
	 * Update a membership rule
	 * @return bool $success True if successful, false otherwise
	 */
	public function update_rule() {
		
		// check that we trust the source of the data
		check_admin_referer( 'civi_member_sync_rules_action', 'civi_member_sync_nonce' );
		
		// init errors
		$this->errors = array();
		
		// check and sanitise CiviCRM Membership Type
		if( 
			isset( $_POST['civi_member_type'] ) AND 
			! empty( $_POST['civi_member_type'] ) AND
			is_numeric( $_POST['civi_member_type'] )
		) {
			$civi_member_type = absint( $_POST['civi_member_type'] );
		} else {
			$this->errors[] = 1;
		}
		
		// check and sanitise WP Role
		if( 
			isset( $_POST['wp_role'] ) AND 
			! empty( $_POST['wp_role'] ) 
		) {
			$wp_role = esc_sql( trim( $_POST['wp_role'] ) );
		} else {
			$this->errors[] = 2;
		}
		
		// init current-expire checking
		$sameType = '';
		
		// check and sanitise Current Status
		if ( 
			isset( $_POST['current'] ) AND 
			is_array( $_POST['current'] ) AND
			! empty( $_POST['current'] )
		) {
			
			// first, check against 'expire' array
			if ( 
				isset( $_POST['expire'] ) AND 
				is_array( $_POST['expire'] ) AND 
				! empty( $_POST['expire'] ) ) 
			{
				foreach( $_POST['current'] AS $key => $value ) {
					$sameType .= array_search( $key, $_POST['expire'] );
				}
			}
			
			// serialize
			$current_rule = serialize( $_POST['current'] );
			
		} else {
			$this->errors[] = 3;
		}
		
		// check and sanitise Expire Status
		if ( 
			isset( $_POST['expire'] ) AND 
			is_array( $_POST['expire'] ) AND
			! empty( $_POST['expire'] )
		) {
			$expiry_rule = serialize( $_POST['expire'] ); 
		} else {
			$this->errors[] = 4;
		}
		
		// check and sanitise Expiry Role
		if ( 
			isset( $_POST['expire_assign_wp_role'] ) AND 
			! empty( $_POST['expire_assign_wp_role'] ) 
		) {
			$expired_wp_role = esc_sql( trim( $_POST['expire_assign_wp_role'] ) );
		} else {
			$this->errors[] = 5;
		}
		
		// how did we do?
		if ( $sameType === '' AND empty( $this->errors ) ) {
		
			// we're good - let's add/update this rule
			
			// access db object
			global $wpdb;
			
			$table_name = $wpdb->prefix . 'civi_member_sync';
			
			// construct sql
			$sql = $wpdb->prepare(
				"REPLACE INTO $table_name SET 
				`wp_role` = %s, 
				`civi_mem_type` = %s, 
				`current_rule` = %s, 
				`expiry_rule` = %s, 
				`expire_wp_role` = %s",
				$wp_role,
				$civi_member_type,
				$current_rule,
				$expiry_rule,
				$expired_wp_role
			);
			
			// do query
			$wpdb->query( $sql );
			
			// default save mode to 'add'
			$mode = 'add';
			
			// test our hidden element
			if ( 
				isset( $_POST['civi_member_sync_rules_mode'] ) AND
				$_POST['civi_member_sync_rules_mode'] == 'edit'
			) {
				$mode = 'edit';
			}
			
			// get admin URLs
			$urls = $this->parent_obj->get_admin_urls();
			
			// redirect to list page
			wp_redirect( $urls['list'] . '&syncrule=' . $mode );
			die();
			
		} else {
			
			// in addition, are there type matches?
			if ( ! empty( $sameType ) ) {
				$this->errors[] = 6;
			}
			
			// sad face
			return false;
			
		}

	}
	
	
	
	/**
	 * Delete a membership rule
	 * @return bool $success True if successful, false otherwise
	 */
	public function delete_rule() {
		
		// check nonce
		if ( 
			! isset( $_GET['civi_member_sync_delete_nonce'] ) OR 
			! wp_verify_nonce( $_GET['civi_member_sync_delete_nonce'], 'civi_member_sync_delete_link' )
		) {
		
			wp_die( __( 'Cheating, eh?', 'civi_member_sync' ) );
			exit();
			
		}
		
		// access db object
		global $wpdb;
		
		// construct table name
		$table_name = $wpdb->prefix . 'civi_member_sync';
		
		// construct query
		$sql = $wpdb->prepare( "DELETE FROM $table_name WHERE `id` = %d", absint( $_GET['id'] ) );
		
		// do query
		if ( $wpdb->query( $sql ) ) {
			
			// get admin URLs
			$urls = $this->parent_obj->get_admin_urls();
			
			// redirect to list page with message
			wp_redirect( $urls['list'] . '&syncrule=delete' );
			die();
			
		} else {
			
			// show error
			$this->errors[] = 7;
			
			// sad face
			return false;
			
		}
		
	}
	
	
	
	//##########################################################################
	
	
	
	/**
	 * Get membership types
	 * @return array $membership_type List of types, key is ID, value is name
	 */
	public function get_types() {
		
		// only calculate once
		if ( isset( $this->membership_types ) ) { return $this->membership_types; }
		
		// init return
		$this->membership_types = array();
		
		// return empty array if no CiviCRM
		if ( ! civi_wp()->initialize() ) return array();
		
		// get membership details
		$membership_type_details = civicrm_api( 'MembershipType', 'get', array(
			'version' => '3',
			'sequential' => '1',
		));
		
		// construct array of types
		foreach( $membership_type_details['values'] AS $key => $values ) {
			$this->membership_types[$values['id']] = $values['name']; 
		}
		
		// --<
		return $this->membership_types;
		
	}
	
	
	
	/**
	 * Get membership status rules
	 * @return array $membership_status List of status rules, key is ID, value is name
	 */
	public function get_status_rules() {
	
		// only calculate once
		if ( isset( $this->membership_status_rules ) ) { return $this->membership_status_rules; }
		
		// init return
		$this->membership_status_rules = array();
		
		// return empty array if no CiviCRM
		if ( ! civi_wp()->initialize() ) return array();
		
		// get membership details
		$membership_status_details = civicrm_api( 'MembershipStatus', 'get', array(
			'version' => '3',
			'sequential' => '1',
		));
		
		// construct array of status rules
		foreach( $membership_status_details['values'] AS $key => $values ) {
			$this->membership_status_rules[$values['id']] = $values['name']; 
		}
		
		// --<
		return $this->membership_status_rules;

	}
	
	
	
	/**
	 * Get name of CiviCRM membership type by ID
	 * @param int $type_id the numeric ID of the membership type
	 * @return string $name The name of the membership type
	 */
	public function get_membership_name_by_id( $type_id = 0 ) {
		
		// sanity checks
		if ( ! is_numeric( $type_id ) ) { return false; }
		if ( $type_id === 0 ) { return false; }
		
		// init return
		$name = '';
		
		// get membership types
		$membership_types = $this->get_types();
		
		// sanity checks
		if ( ! is_array( $membership_types ) ) { return false; }
		if ( count( $membership_types ) == 0 ) { return false; }
		
		// flip for easier searching
		$membership_types = array_flip( $membership_types );
		
		// init current roles
		$name = array_search( $type_id, $membership_types );
		
		// --<
		return $name;
		
	}
	
	
	
	/**
	 * Get role/membership names
	 * @param string $values Serialised array of status rule IDs
	 * @return string $status_rules The list of status rules, one per line
	 */
	public function get_current_status_rules( $values ) {
		
		// init return
		$status_rules = '';
		
		// get current rules for this item
		$current_rules = $this->get_current_status_rules_array( $values );
		
		// if there are some...
		if ( $current_rules !== false AND is_array( $current_rules ) ) {
			
			// separate with line break
			$status_rules = implode( '<br>', $current_rules );
			
		}
	 
		// --<
		return $status_rules;
		
	}
	
	
	
	/**
	 * Get membership status rules for a particular item
	 * @param string $values Serialised array of status rule IDs
	 * @return array $rules_array The list of membership status rules for this item
	 */
	public function get_current_status_rules_array( $values ) {
	
		// get membership status rules
		$status_rules = $this->get_status_rules();
		
		// sanity checks
		if ( ! is_array( $status_rules ) ) { return false; }
		if ( count( $status_rules ) == 0 ) { return false; }
		
		// flip for easier searching
		$status_rules = array_flip( $status_rules );
		
		// init return
		$rules_array = array();
		
		// init current rule
		$current_rule = maybe_unserialize( $values );
		
		// build rules array for this item
		if ( ! empty( $current_rule ) ) {
			if ( is_array( $current_rule ) ) {
				foreach( $current_rule as $key => $value ) {
					$rules_array[] = array_search( $key, $status_rules );
				}
			}
		}
		
		// --<
		return $rules_array;
		
	}



	/**
	 * Get a Civi contact ID by WordPress user object
	 * @param WP_User $user WP_User object of the logged-in user.
	 * @return int $civi_contact_id The numerical CiviCRM contact ID
	 */
	public function get_civi_contact_id( $user ) {
	
		// kick out if no CiviCRM
		if ( ! civi_wp()->initialize() ) return false;
		
		// make sure Civi file is included
		require_once 'CRM/Core/BAO/UFMatch.php';
			
		// do initial search
		$civi_contact_id = CRM_Core_BAO_UFMatch::getContactId( $user->ID );
		if ( ! $civi_contact_id ) {
			
			// sync this user
			CRM_Core_BAO_UFMatch::synchronizeUFMatch(
				$user, // user object
				$user->ID, // ID
				$user->user_mail, // unique identifier
				'WordPress', // CMS
				null, // status
				'Individual', // contact type
				null // is_login
			);
			
			// get the Civi contact ID
			$civi_contact_id = CRM_Core_BAO_UFMatch::getContactId( $user->id );
			
			// sanity check
			if ( ! $civi_contact_id ) {
				return false;
			}
		
		}
		
		// --<
		return $civi_contact_id;
		
	}
	
	
	
} // class ends



