/**
 * Favr Events: edit-screen extras on top of favr-core's field UI.
 */
( function ( $ ) {
	'use strict';

	$( function () {
		// WordPress sends Tab from the title straight into the description editor. That editor
		// now sits on the Description tab, usually out of sight, so focus would vanish. Let Tab
		// move on to the panel (its tabs, then the date fields) like any other field.
		$( '#title' ).off( 'keydown.editor-focus' );
	} );
} )( jQuery );
