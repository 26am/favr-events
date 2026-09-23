<?php
/**
 * The Events block and shortcodes.
 *
 * @package FavrEvents
 */

declare(strict_types=1);

namespace FavrEvents\Frontend;

use FavrEvents\Schema\Identifiers as ID;
use FavrEvents\Support\AssetVersion;

/**
 * [favr_events view="list|month|compact" category="slug" limit="10" featured="1" filters="0" title="…"]
 * and the equivalent "Events" block.
 */
final class Blocks {

	/** Hook. */
	public function hook(): void {
		add_action( 'init', array( $this, 'register' ) );
		add_shortcode( ID::SHORTCODE, array( Calendar::class, 'render' ) );
	}

	/** Register assets and block. */
	public function register(): void {
		wp_register_style( 'favr-events', FAVR_EVENTS_URL . 'assets/public/events.css', array(), AssetVersion::of( 'assets/public/events.css' ) );
		$accent = sanitize_hex_color( (string) \FavrEvents\Support\Settings::get( 'accent_color' ) );
		if ( $accent ) {
			wp_add_inline_style( 'favr-events', '.favr-ev,.favr-ev-details,.favr-ev-host,.favr-ev-more{--favr-ev-accent:' . $accent . '}' );
		}
		wp_register_script(
			'favr-events-blocks',
			FAVR_EVENTS_URL . 'assets/blocks/editor.js',
			array( 'wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-server-side-render', 'wp-i18n', 'wp-data', 'wp-core-data' ),
			AssetVersion::of( 'assets/blocks/editor.js' ),
			true
		);
		wp_set_script_translations( 'favr-events-blocks', 'favr-events', FAVR_EVENTS_PATH . 'languages' );
		register_block_type( FAVR_EVENTS_PATH . 'blocks/events', array( 'render_callback' => array( $this, 'renderEvents' ) ) );
	}

	/**
	 * Events block.
	 *
	 * @param array<string, mixed> $attributes Attributes.
	 */
	public function renderEvents( array $attributes ): string {
		$atts = array_filter(
			array(
				'view'     => (string) ( $attributes['view'] ?? '' ),
				'category' => (string) ( $attributes['category'] ?? '' ),
				'limit'    => (int) ( $attributes['limit'] ?? 0 ) ?: '',
				'featured' => ! empty( $attributes['featuredOnly'] ) ? '1' : '',
				'filters'  => isset( $attributes['showFilters'] ) && ! $attributes['showFilters'] ? '0' : '',
				'title'    => (string) ( $attributes['title'] ?? '' ),
			),
			static fn( $v ): bool => '' !== $v
		);
		return sprintf( '<div %s>%s</div>', get_block_wrapper_attributes(), Calendar::render( $atts ) );
	}
}
