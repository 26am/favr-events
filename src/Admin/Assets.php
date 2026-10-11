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
 * Shared field UI (favr/core, copied to assets/core) on event screens, plus the edit screen's
 * own script.
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
		$settings = str_contains( (string) $screen->id, SettingsPage::SLUG );
		if ( 'post' === $screen->base ) {
			wp_enqueue_media();
			// After WordPress's own edit-screen script (it removes its Tab-from-title shortcut)
			// and after the field script (it picks the tab a new event opens on).
			wp_enqueue_script( 'favr-events-admin', FAVR_EVENTS_URL . 'assets/admin/admin.js', array( 'jquery', 'post', 'favr-core-fields' ), AssetVersion::of( 'assets/admin/admin.js' ), true );
		}
		if ( $settings ) {
			wp_enqueue_style( 'wp-color-picker' );
			wp_add_inline_script( 'wp-color-picker', 'jQuery(function($){$(".favr-color").wpColorPicker();});' );
		}
		wp_enqueue_script( 'favr-core-fields', FAVR_EVENTS_URL . 'assets/core/fields.js', self::scriptDependencies( $settings ), AssetVersion::of( 'assets/core/fields.js' ), true );
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

	/**
	 * What the field script needs before it runs. It calls jQuery UI sortable as soon as the
	 * page is ready on every screen, gallery or not, so sortable is always loaded with it: on
	 * the events list, the categories screen and Event settings the script used to stop with
	 * "$(...).sortable is not a function".
	 *
	 * @param bool $settings The Event settings screen (adds the color picker).
	 * @return list<string>
	 */
	public static function scriptDependencies( bool $settings ): array {
		$deps = array( 'jquery', 'jquery-ui-sortable' );
		if ( $settings ) {
			$deps[] = 'wp-color-picker';
		}
		return $deps;
	}
}
