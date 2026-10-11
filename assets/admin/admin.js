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

		// The panel reopens on the tab used last. That suits going back to an event, but a new
		// event starts with its date, so "Add event" always opens on the first tab.
		var $panel = $( '#favr-event-panel' );
		if ( $( document.body ).hasClass( 'post-new-php' ) && $panel.length && window.favrCore ) {
			window.favrCore.activateTab( $panel, $panel.find( '.favr-tab' ).first().data( 'tab' ) );
		}
	} );
} )( jQuery );
