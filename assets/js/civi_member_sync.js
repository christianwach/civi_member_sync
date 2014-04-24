/* 
--------------------------------------------------------------------------------
CiviCRM Member Role Sync Javascript
--------------------------------------------------------------------------------
*/

/** 
 * When the page is ready...
 */
jQuery(document).ready( function($) {

	$(':submit').click( function(e) {
	
		$(".required").each( function() {
		
			var input = $(this);
			
			if ( !input.attr( 'value' ) ) {
				$(this).attr( 'style', 'border-color:#FF0000;' );
				e.preventDefault();
			} else {
				$('.required').attr( 'style', 'border-color:#DFDFDF;' );
			}
			
		});
		
	});

});


