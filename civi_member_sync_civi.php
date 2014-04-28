<?php /* 
--------------------------------------------------------------------------------
Civi_Member_Sync_CiviCRM Class
--------------------------------------------------------------------------------
*/  

class Civi_Member_Sync_CiviCRM {
	
	
	
	/** 
	 * Properties
	 */
	
	// form error messages
	public $error_strings;
	
	// errors in current submission
	public $errors;
	
	
	
	/** 
	 * Initialise this object
	 * @return object
	 */
	function __construct() {
		
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
	
	
	
	/**
	 * Register hooks when CiviCRM initialises
	 * @return nothing
	 */
	public function initialise() {
	
		// add schedule, if not already present (to be removed)
		if ( !wp_next_scheduled( 'civi_member_sync_refresh' ) ) {  
		   wp_schedule_event( time(), 'daily', 'civi_member_sync_refresh' );
		}

		// add cron action (to be removed)
		add_action( 'civi_member_sync_refresh', array( $this, 'sync_daily' ) );
		
		// add login/logout check (to be removed)
		add_action( 'wp_login', array( $this, 'sync_check' ), 10, 2 );
		add_action( 'wp_logout', array( $this, 'sync_check' ), 10, 2 );
		//add_action( 'profile_update', array( $this, 'sync_check' ), 10, 2 );
		
		// add in CiviCRM hooks, if they exist...
		
	}
	
	
	
	/**
	* Schedule manual sync daily
	* @return nothing
	*/
	public function sync_daily() {
		
		// disable for now
		return;

		// kick out if no CiviCRM
		if ( ! civi_wp()->initialize() ) return;
		
		// make sure Civi file is included
		require_once( 'CRM/Core/BAO/UFMatch.php' );
		
		// get all WordPress users
		$users = get_users();
		
		// loop through all users (surely not!)
		foreach( $users AS $user ) {
			
			// sanity check
			$uid = $user->ID;
			if ( empty( $uid ) ) {
				continue;
			}
			
			// get Civi contact
			$sql = "SELECT * FROM civicrm_uf_match WHERE uf_id = '$uid'";
			$contact = CRM_Core_DAO::executeQuery( $sql );
			
			// did we get one?
			if ( $contact->fetch() ) {
				
				// get membership details for this contact
				$cid = $contact->contact_id;
				$memDetails = civicrm_api( 'Membership', 'get', array(
					'version' => '3',
					'page' => 'CiviCRM',
					'q' => 'civicrm/ajax/rest',
					'sequential' => '1',
					'contact_id' => $cid
				));
				
				// if we get membership details
				if ( !empty( $memDetails['values'] ) ) {
					foreach( $memDetails['values'] AS $key => $value ) {
						$memStatusID = $value['status_id']; 
						$membershipTypeID = $value['membership_type_id'];  
					}
				}
				
				// get WordPress role
				$userData = get_userdata( $uid );
				if ( !empty( $userData ) ) {
					$currentRole = $userData->roles[0];
				}
				
				// check membership status and assign role
				$check = $this->member_check( $cid, $uid, $currentRole );     

			}
			
		}
		
	}



	/**
	 * Check user's membership record during login and logout
	 * @return bool true if successful
	 */
	public function sync_check( $user_login, $user ) {    

		// disable for now
		return;

		// kick out if no CiviCRM
		if ( ! civi_wp()->initialize() ) return;
		
		// access globals
		global $wpdb, $current_user;
		
		// get username in post while login (not needed now we're using the hook properly)
		if ( !empty( $_POST['log'] ) ) {
			$username = $_POST['log'];
			$userDetails = $wpdb->get_results( "SELECT * FROM $wpdb->users WHERE user_login = '$username'" );
			$currentUserID = $userDetails[0]->ID;
		} else {
			$currentUserID = $current_user->ID;
		}
		
		// get current logged in user's role
		$current_user_role = new WP_User( $currentUserID );
		$current_user_role = $current_user_role->roles[0];
		//echo $current_user_role . "\n";
		
		// if not admin (better to use is_super_admin() function)
		if ( $current_user_role != 'administrator' ) {
		
			// get user's civi contact id and check membership details
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
		
		// --<
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
			$memDetails = civicrm_api( 'Membership', 'get', array(
				'version' => '3',
				'page' => 'CiviCRM',
				'q' => 'civicrm/ajax/rest',
				'sequential' => '1',
				'contact_id' => $contactID
			));
			//print_r($memDetails); echo "\n";
			
			if ( !empty( $memDetails['values'] ) ) {
				foreach( $memDetails['values'] AS $key => $value ) {
					$memStatusID = $value['status_id'];
					$membershipTypeID = $value['membership_type_id'];
				}
			}
			
			// kick out if no type found
			if ( ! isset($membershipTypeID) ) { return; }

			// fetching member sync association rule to the corsponding membership type 
			$table_name = $wpdb->prefix . 'civi_member_sync';
			$sql = $wpdb->prepare( "SELECT * FROM $table_name WHERE civi_mem_type = %d", $membershipTypeID );
			$memSyncRulesDetails = $wpdb->get_results( $sql ); 
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
						$wp_user_object->set_role( '' );
					}
					
				}
				
			}
			
		}
		
		// --<
		return true;
		
	}
	
	
	
	/**
	 * Update membership rules
	 * @return bool $success True if successful, false otherwise
	 */
	public function update_rules() {
		
		// check that we trust the source of the data
		check_admin_referer( 'civi_member_sync_rules_action', 'civi_member_sync_nonce' );
		
		// init errors
		$this->errors = array();
		
		// check and sanitise CiviCRM Membership Type
		if( 
			isset( $_POST['civi_member_type'] ) AND 
			!empty( $_POST['civi_member_type'] ) AND
			is_numeric( $_POST['civi_member_type'] )
		) {
			$civi_member_type = absint( $_POST['civi_member_type'] );
		} else {
			$this->errors[] = 1;
		}
		
		// check and sanitise WP Role
		if( 
			isset( $_POST['wp_role'] ) AND 
			!empty( $_POST['wp_role'] ) 
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
			!empty( $_POST['current'] )
		) {
			
			// first, check against 'expire' array
			if ( 
				isset( $_POST['expire'] ) AND 
				is_array( $_POST['expire'] ) AND 
				!empty( $_POST['expire'] ) ) 
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
			!empty( $_POST['expire'] )
		) {
			$expiry_rule = serialize( $_POST['expire'] ); 
		} else {
			$this->errors[] = 4;
		}
		
		// check and sanitise Expiry Role
		if ( 
			isset( $_POST['expire_assign_wp_role'] ) AND 
			!empty( $_POST['expire_assign_wp_role'] ) 
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
			
			// redirect to list page
			wp_redirect( menu_page_url( 'civi_member_sync_list', false ) . '&syncrule=' . $mode );
			die();
			
		} else {
			
			// in addition, are there type matches?
			if ( !empty( $sameType ) ) {  
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
			!isset( $_GET['civi_member_sync_delete_nonce'] ) OR 
			!wp_verify_nonce( $_GET['civi_member_sync_delete_nonce'], 'civi_member_sync_delete_link' )
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
			
			// redirect to list page with message
			wp_redirect( menu_page_url( 'civi_member_sync_list', false ) . '&syncrule=delete' );
			die();
			
		} else {
			
			// show error
			$this->errors[] = 7;
			
			// sad face
			return false;
			
		}
		
	}
	
	
	
	/**
	 * Do Manual Sync of membership rules
	 * @return bool $success True if successful, false otherwise
	 */
	public function do_manual_sync() {
	
		// check that we trust the source of the request
		check_admin_referer( 'civi_member_sync_manual_sync_action', 'civi_member_sync_nonce' );
		
		// trace
		print_r( $_POST ); die();
		
		// kick out if no CiviCRM
		if ( ! civi_wp()->initialize() ) return;
		
		// make sure Civi file is included
		require_once 'CRM/Core/BAO/UFMatch.php';

		// get all WordPress users
		$users = get_users();
		
		// loop through all users
		foreach( $users AS $user ) {
			
			// sanity check
			$uid = $user->ID;
			if ( empty( $uid ) ) {
				continue;
			}
			
			// get Civi contact
			$sql = "SELECT * FROM civicrm_uf_match WHERE uf_id = '$uid'";
			$contact = CRM_Core_DAO::executeQuery($sql); 
			
			// did we get one?
			if ( $contact->fetch() ) {
				
				// get membership details
				$cid = $contact->contact_id;
				$memDetails = civicrm_api( 'Membership', 'get', array(
					'version' => '3',
					'page' => 'CiviCRM',
					'q' => 'civicrm/ajax/rest',
					'sequential' => '1',
					'contact_id' => $cid
				));
			 	
			 	// did we get any?
				if ( !empty( $memDetails['values'] ) ) {
					foreach( $memDetails['values'] AS $key => $value ) {
						$memStatusID = $value['status_id']; 
						$membershipTypeID = $value['membership_type_id'];
					}         
				}
				
				// get WordPress role
				$userData = get_userdata( $uid );
				if ( !empty( $userData ) ) {
					foreach ( $userData->roles as $role ) {
						if ( $role ) {
							$currentRole = $role;
							break;
						}
					}
				}

				// check Civi membership status and assign WordPress role
				$check = $this->member_check( $cid, $uid, $currentRole );

			}

		}
		
	}
	
	
	
	/**
	 * Get membership types
	 * @return array $membership_type List of types, key is ID, value is name
	 */
	public function get_types() {
		
		// init return
		$membership_type = array();
		
		// return empty array if no CiviCRM
		if ( ! civi_wp()->initialize() ) return $membership_type;
		
		// get membership details
		$membership_type_details = civicrm_api( 'MembershipType', 'get', array(
			'version' => '3',
			'sequential' => '1',
		));
		
		// construct array of types
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
		
		// return empty array if no CiviCRM
		if ( ! civi_wp()->initialize() ) return $membership_status;
		
		// get membership details
		$membership_status_details = civicrm_api( 'MembershipStatus', 'get', array(
			'version' => '3',
			'sequential' => '1',
		));
		
		// construct array of statuses
		foreach( $membership_status_details['values'] AS $key => $values ) {
			$membership_status[$values['id']] = $values['name']; 
		}
		
		// --<
		return $membership_status;

	}  



	/**
	 * Get role/membership names
	 * @param string $values Serialised array
	 * @param array $memArray An array of memberships
	 * @return string $current_roles The list of membership names, one per line
	 */
	public function get_names( $values, $memArray ) {  
	 
		$memArray = array_flip( $memArray );
		
		// init current rule (look again at this - I don't like suppressing errors)
		$current_rule = @unserialize( $values );
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



