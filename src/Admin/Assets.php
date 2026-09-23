<?php
/**
 * Admin assets.
 *
 * @package FavrEvents
 */

declare(strict_types=1);

namespace FavrEvents\Admin;

use FavrEvents\Schema\Identifiers as ID;
use FavrEvents\Support\AssetVersion;

/**
 * Shared field UI (favr/core, copied to assets/core) on event screens.
 */
final class Assets {

	/** Hook. */
	public function hook(): void {
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
	}

	/** Enqueue. */
	public function enqueue(): void {
		$screen = get_current_screen();
		if ( ! $screen || ID::POST_TYPE !== $screen->post_type ) {
			return;
		}
		wp_enqueue_style( 'favr-core-fields', FAVR_EVENTS_URL . 'assets/core/fields.css', array(), AssetVersion::of( 'assets/core/fields.css' ) );
		wp_enqueue_style( 'favr-events-admin', FAVR_EVENTS_URL . 'assets/admin/admin.css', array( 'favr-core-fields' ), AssetVersion::of( 'assets/admin/admin.css' ) );
		$deps = array( 'jquery' );
		if ( 'post' === $screen->base ) {
			wp_enqueue_media();
			$deps[] = 'jquery-ui-sortable';
		}
		if ( str_contains( (string) $screen->id, SettingsPage::SLUG ) ) {
			wp_enqueue_style( 'wp-color-picker' );
			$deps[] = 'wp-color-picker';
			wp_add_inline_script( 'wp-color-picker', 'jQuery(function($){$(".favr-color").wpColorPicker();});' );
		}
		wp_enqueue_script( 'favr-core-fields', FAVR_EVENTS_URL . 'assets/core/fields.js', $deps, AssetVersion::of( 'assets/core/fields.js' ), true );
		wp_localize_script(
			'favr-core-fields',
			'favrCoreFields',
			array(
				'i18n' => array(
					'chooseLogo' => __( 'Choose an image', 'favr-events' ),
					'useImage'   => __( 'Use this image', 'favr-events' ),
					/* translators: %d: number of characters. */
					'charsLeft'  => __( '%d characters left', 'favr-events' ),
				),
			)
		);
	}
}
