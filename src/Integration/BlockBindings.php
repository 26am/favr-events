<?php
/**
 * Block bindings: connect core blocks to event data.
 *
 * @package FavrEvents
 */

declare(strict_types=1);

namespace FavrEvents\Integration;

/**
 * Source `favr-events/event`. Bind a Paragraph, Heading, Button or Image caption to an event
 * value; link attributes (url) get links. Same values as the Elementor dynamic tags.
 */
final class BlockBindings {

	/** Hook. */
	public function hook(): void {
		add_action( 'init', array( $this, 'register' ) );
	}

	/** Register. */
	public function register(): void {
		if ( ! function_exists( 'register_block_bindings_source' ) ) {
			return;
		}
		register_block_bindings_source(
			'favr-events/event',
			array(
				'label'              => __( 'Event (Favr Events)', 'favr-events' ),
				'get_value_callback' => array( $this, 'value' ),
				'uses_context'       => array( 'postId', 'postType' ),
			)
		);
	}

	/**
	 * Value.
	 *
	 * @param array<string, mixed> $args      { key }.
	 * @param \WP_Block            $block     Block.
	 * @param string               $attribute Attribute.
	 * @return string|null
	 */
	public function value( array $args, $block, string $attribute ) {
		$event = FieldValues::event( isset( $block->context['postId'] ) ? (int) $block->context['postId'] : 0 );
		$key   = sanitize_key( (string) ( $args['key'] ?? '' ) );
		if ( ! $event || '' === $key ) {
			return null;
		}
		if ( in_array( $attribute, array( 'url', 'href' ), true ) ) {
			return FieldValues::url( $event, $key ) ?: null;
		}
		$text = FieldValues::text( $event, $key );
		return '' === $text ? null : esc_html( $text );
	}
}
