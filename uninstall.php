<?php
/**
 * Uninstall: remove data only when the site chose to.
 *
 * @package FavrEvents
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

$favr_settings = get_option( 'favr_events_settings', array() );
if ( ! is_array( $favr_settings ) || empty( $favr_settings['delete_data'] ) ) {
	return;
}

foreach ( get_posts( array( 'post_type' => 'favr_event', 'post_status' => 'any', 'posts_per_page' => -1, 'fields' => 'ids' ) ) as $favr_id ) { // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound
	wp_delete_post( (int) $favr_id, true );
}
$favr_terms = get_terms( array( 'taxonomy' => 'favr_event_cat', 'hide_empty' => false, 'fields' => 'ids' ) ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound
if ( is_array( $favr_terms ) ) {
	foreach ( $favr_terms as $favr_term ) {
		wp_delete_term( (int) $favr_term, 'favr_event_cat' );
	}
}
foreach ( wp_roles()->role_objects as $favr_role ) {
	foreach ( array( 'edit_favr_events', 'edit_others_favr_events', 'edit_private_favr_events', 'edit_published_favr_events', 'publish_favr_events', 'read_private_favr_events', 'delete_favr_events', 'delete_others_favr_events', 'delete_private_favr_events', 'delete_published_favr_events', 'manage_favr_events' ) as $favr_cap ) {
		$favr_role->remove_cap( $favr_cap );
	}
}
delete_option( 'favr_events_settings' );
delete_option( 'favr_events_version' );
