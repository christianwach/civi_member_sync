<?php /* 
--------------------------------------------------------------------------------
Civi_Member_Sync_CiviCRM Class
--------------------------------------------------------------------------------
*/  

class Civi_Member_Sync_CiviCRM {

	/** 
	 * Initialise this object
	 * @return object
	 */
	function __construct() {
	
		// --<
		return $this;

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

		require_once( 'civi.php' );
		require_once( 'CRM/Core/BAO/UFMatch.php' );

		$users = get_users();

		foreach( $users AS $user ) {
		
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

		// disable for now
		return;

		// kick out if no CiviCRM
		if ( ! civi_wp()->initialize() ) return;

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
	 * Update membership rules
	 * @return bool $success True if successful, false otherwise
	 */
	public function update_rules() {
		
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
		
			wp_die( 'Cheating, eh?' );
			exit();
			
		}
		
		/*
		// contruct table name
		$table_name = $wpdb->prefix . 'civi_member_sync';
		
		// noooo, redo this
		$delete = $wpdb->get_results( "DELETE FROM $table_name WHERE `id` = " . $_GET['id'] );        
		*/
		
	}
	
	
	
	/**
	 * Do Manual Sync of membership rules
	 * @return bool $success True if successful, false otherwise
	 */
	public function do_manual_sync() {
	
		// check that we trust the source of the data
		check_admin_referer( 'civi_member_sync_manual_sync', 'civi_member_sync_nonce' );
		
		$users = get_users();

		require_once('civi.php');
		require_once 'CRM/Core/BAO/UFMatch.php';

		foreach( $users AS $user ) {

			$uid = $user->ID;
			if ( empty( $uid ) ) {
				continue;
			}
	
			$sql = "SELECT * FROM civicrm_uf_match WHERE uf_id = $uid";
			$contact = CRM_Core_DAO::executeQuery($sql); 
	
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
					foreach( $memDetails['values'] AS $key => $value ) {
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
	 * Get membership types
	 * @return array $membership_type List of types, key is ID, value is name
	 */
	public function get_types() {
		
		// init return
		$membership_type = array();

		// return empty array if no CiviCRM
		if ( ! civi_wp()->initialize() ) return $membership_type;

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

		// return empty array if no CiviCRM
		if ( ! civi_wp()->initialize() ) return $membership_status;

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



