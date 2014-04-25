/* 
--------------------------------------------------------------------------------
CiviCRM Member Role Sync Javascript
--------------------------------------------------------------------------------
*/

/** 
 * When the page is ready...
 */
jQuery(document).ready( function($) {
	
	// cursory error checking
	$(':submit').click( function(e) {
		
		// init vars
		var passed = true,
			current_checked = false,
			expire_checked = false;

		// check every required element...
		$('.required').each( function() {
			
			// if it's empty
			if ( !$(this).attr( 'value' ) ) {
			
				// colour label red
				$(this).parent().prev().children().addClass( 'req' );
				
				// set flag
				passed = false;
				
			} else {
				
				// colour label black
				$(this).parent().prev().children().removeClass( 'req' );
				
			}
			
		});
		
		// check current checkboxes...
		$('.required-current').each( function() {
		
			// if checked...
			if ( $(this).prop( 'checked' ) ) {
				current_checked = true;
			}
		
		});
		
		// do we have a checked box for current?
		if ( !current_checked ) {
			$('label.current_label').addClass( 'req' );
		} else {
			$('label.current_label').removeClass( 'req' );
		}

		// check expire checkboxes...
		$('.required-expire').each( function() {
		
			// if checked...
			if ( $(this).prop( 'checked' ) ) {
				expire_checked = true;
			}
		
		});
		
		// do we have a checked box for expire?
		if ( !expire_checked ) {
			$('label.expire_label').addClass( 'req' );
		} else {
			$('label.expire_label').removeClass( 'req' );
		}

		// did we pass?
		if ( !passed || !current_checked || !expire_checked ) {

			// no, prevent form submission
			e.preventDefault();
		
		}
		
	});

});


