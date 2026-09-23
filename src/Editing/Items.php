<?php
/**
 * Everything a submitter can edit, and where it is stored.
 *
 * @package FavrEvents
 */

declare(strict_types=1);

namespace FavrEvents\Editing;

use FavrEvents\Model\Event;
use FavrEvents\Model\Fields;
use FavrEvents\Model\Hosts;
use FavrEvents\Schema\Identifiers as ID;
use FavrEvents\Vendor\FavrCore\Fields\FieldSet;
use FavrEvents\Vendor\FavrCore\Fields\Sanitizer;
use FavrEvents\Vendor\FavrCore\Moderation\Uploads;

/**
 * Items are the event fields plus four stored on the post: title, description, categories
 * and image. Once an event is published, changes follow a policy: `edit` items go live,
 * `review` items wait in Approvals, `none` items are staff-only.
 */
final class Items {

	public const EDIT   = 'edit';
	public const REVIEW = 'review';
	public const NONE   = 'none';

	/** Live immediately after publication: practical details that change often. */
	private const EDIT_DEFAULTS = array( 'cost', 'registration_url', 'organizer_name', 'organizer_email', 'organizer_phone', 'organizer_url' );

	/** Staff only. */
	private const NONE_DEFAULTS = array( 'featured' );

	/**
	 * Core items (normalized field definitions).
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public static function core(): array {
		return array_map(
			array( FieldSet::class, 'normalize' ),
			array(
				'title'       => array(
					'id'        => 'title',
					'label'     => __( 'Event name', 'favr-events' ),
					'type'      => 'text',
					'tab'       => 'when',
					'maxlength' => 120,
					'required'  => true,
				),
				'description' => array(
					'id'          => 'description',
					'label'       => __( 'Description', 'favr-events' ),
					'type'        => 'textarea',
					'tab'         => 'details',
					'description' => __( 'What to expect, who it’s for, what to bring. Blank lines start new paragraphs.', 'favr-events' ),
				),
				'categories'  => array(
					'id'      => 'categories',
					'label'   => __( 'Category', 'favr-events' ),
					'type'    => 'checkboxes',
					'tab'     => 'details',
					'options' => array(),
				),
				'image'       => array(
					'id'          => 'image',
					'label'       => __( 'Event image', 'favr-events' ),
					'type'        => 'image',
					'tab'         => 'details',
					'description' => __( 'Optional. A wide image works best.', 'favr-events' ),
				),
			)
		);
	}

	/**
	 * Access level for an item on a published event.
	 *
	 * @param string $id Item id.
	 */
	public static function access( string $id ): string {
		if ( ! isset( self::core()[ $id ] ) && ! Fields::set()->get( $id ) ) {
			return self::NONE;
		}
		if ( in_array( $id, self::NONE_DEFAULTS, true ) ) {
			$level = self::NONE;
		} else {
			$level = in_array( $id, self::EDIT_DEFAULTS, true ) ? self::EDIT : self::REVIEW;
		}

		/**
		 * Filter what submitters may change on a published event.
		 *
		 * @param string $level edit | review | none.
		 * @param string $id    Item id.
		 */
		$level = (string) apply_filters( 'favr_events_member_access', $level, $id );
		return in_array( $level, array( self::EDIT, self::REVIEW, self::NONE ), true ) ? $level : self::NONE;
	}

	/**
	 * Items a submitter sees (with categories and host adapted for them).
	 *
	 * @param int  $user_id   Submitter.
	 * @param bool $published The event is live (so `none` items are hidden).
	 * @return array<string, array<string, mixed>>
	 */
	public static function forUser( int $user_id, bool $published = false ): array {
		$items = array();
		foreach ( self::core() as $id => $item ) {
			if ( $published && 'title' !== $id && self::NONE === self::access( $id ) ) {
				continue;
			}
			if ( 'categories' === $id ) {
				$terms = get_terms(
					array(
						'taxonomy'   => ID::TAX_CATEGORY,
						'hide_empty' => false,
					)
				);
				if ( is_wp_error( $terms ) || ! $terms ) {
					continue;
				}
				foreach ( $terms as $term ) {
					$item['options'][ (string) $term->term_id ] = $term->name;
				}
			}
			$items[ $id ] = $item;
		}
		foreach ( Fields::set()->all() as $id => $field ) {
			if ( self::NONE === self::access( (string) $id ) ) {
				continue;
			}
			$field = Fields::forForm( $field, Hosts::forUser( $user_id ) );
			if ( $field ) {
				$items[ (string) $id ] = $field;
			}
		}
		// The event name goes first.
		return array( 'title' => $items['title'] ) + $items;
	}

	/**
	 * Stored value.
	 *
	 * @param int    $post_id Event.
	 * @param string $id      Item.
	 * @return mixed
	 */
	public static function current( int $post_id, string $id ) {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return '';
		}
		switch ( $id ) {
			case 'title':
				return $post->post_title;
			case 'description':
				return $post->post_content;
			case 'categories':
				$ids = wp_get_object_terms( $post_id, ID::TAX_CATEGORY, array( 'fields' => 'ids' ) );
				$ids = is_wp_error( $ids ) ? array() : array_map( 'intval', $ids );
				sort( $ids );
				return $ids;
			case 'image':
				return (int) get_post_thumbnail_id( $post_id );
		}
		$event = Event::find( $post_id );
		return $event ? $event->text( $id ) : '';
	}

	/**
	 * Sanitize a submitted value.
	 *
	 * @param string $id      Item.
	 * @param mixed  $raw     Raw.
	 * @param int    $user_id Submitter (limits host choices).
	 * @return mixed
	 */
	public static function sanitize( string $id, $raw, int $user_id ) {
		switch ( $id ) {
			case 'title':
				return mb_substr( sanitize_text_field( is_string( $raw ) ? $raw : '' ), 0, 120 );
			case 'description':
				return self::cleanDescription( is_string( $raw ) ? $raw : '' );
			case 'categories':
				$valid = get_terms(
					array(
						'taxonomy'   => ID::TAX_CATEGORY,
						'hide_empty' => false,
						'fields'     => 'ids',
					)
				);
				$valid = is_wp_error( $valid ) ? array() : array_map( 'intval', $valid );
				$ids   = array_values( array_unique( array_intersect( array_map( 'intval', (array) $raw ), $valid ) ) );
				sort( $ids );
				return array_slice( $ids, 0, 3 );
			case 'image':
				return absint( is_scalar( $raw ) ? $raw : 0 );
		}
		$field = Fields::set()->get( $id );
		if ( ! $field ) {
			return '';
		}
		$value = Sanitizer::sanitize( $field, $raw );
		if ( 'host_business' === $id && '' !== $value && ! in_array( (int) $value, Hosts::forUser( $user_id ), true ) ) {
			return ''; // Members may only name businesses they represent.
		}
		return is_scalar( $value ) ? (string) $value : $value;
	}

	/**
	 * Comparable form of a value.
	 *
	 * @param string $id    Item.
	 * @param mixed  $value Value.
	 * @return mixed
	 */
	public static function effective( string $id, $value ) {
		if ( 'description' === $id ) {
			return self::cleanDescription( is_string( $value ) ? $value : '' );
		}
		if ( 'all_day' === $id ) {
			return '1' === (string) $value ? '1' : '0';
		}
		return $value;
	}

	/**
	 * Store a value.
	 *
	 * @param int    $post_id Event.
	 * @param string $id      Item.
	 * @param mixed  $value   Sanitized value.
	 */
	public static function apply( int $post_id, string $id, $value ): void {
		switch ( $id ) {
			case 'title':
				if ( '' !== (string) $value ) {
					wp_update_post(
						array(
							'ID'         => $post_id,
							'post_title' => (string) $value,
						)
					);
				}
				return;
			case 'description':
				wp_update_post(
					array(
						'ID'           => $post_id,
						'post_content' => (string) $value,
					)
				);
				return;
			case 'categories':
				wp_set_object_terms( $post_id, array_map( 'intval', (array) $value ), ID::TAX_CATEGORY );
				return;
			case 'image':
				// Submitter uploads stay private until the event is live (see Queues on approval).
				if ( (int) $value > 0 && in_array( get_post_status( $post_id ), array( 'publish', 'future' ), true ) ) {
					Uploads::publish( array( (int) $value ), $post_id );
				}
				if ( (int) $value > 0 ) {
					set_post_thumbnail( $post_id, (int) $value );
				} else {
					delete_post_thumbnail( $post_id );
				}
				return;
		}
		$field = Fields::set()->get( $id );
		if ( ! $field ) {
			return;
		}
		if ( Sanitizer::isEmpty( $field, $value ) ) {
			delete_post_meta( $post_id, ID::meta( $id ) );
		} else {
			update_post_meta( $post_id, ID::meta( $id ), (string) $value );
		}
		Event::reindex( $post_id );
	}

	/**
	 * Short escaped HTML for reviewers.
	 *
	 * @param string $id    Item.
	 * @param mixed  $value Value.
	 */
	public static function display( string $id, $value ): string {
		$value = self::effective( $id, $value );
		switch ( $id ) {
			case 'categories':
				$names = array();
				foreach ( (array) $value as $term_id ) {
					$term = get_term( (int) $term_id, ID::TAX_CATEGORY );
					if ( $term instanceof \WP_Term ) {
						$names[] = $term->name;
					}
				}
				return esc_html( implode( ', ', $names ) );
			case 'image':
				return (int) $value > 0 ? (string) wp_get_attachment_image( (int) $value, 'thumbnail' ) : '';
			case 'description':
				$text = wp_strip_all_tags( (string) $value );
				return nl2br( esc_html( mb_strlen( $text ) > 600 ? mb_substr( $text, 0, 600 ) . '…' : $text ) );
			case 'host_business':
				return (int) $value > 0 ? esc_html( get_the_title( (int) $value ) ) : '';
		}
		$field = Fields::set()->get( $id );
		if ( $field && in_array( $field['type'], array( 'select', 'radio' ), true ) ) {
			return esc_html( (string) ( $field['options'][ (string) $value ] ?? $value ) );
		}
		if ( $field && 'toggle' === $field['type'] ) {
			return '1' === (string) $value ? esc_html__( 'Yes', 'favr-events' ) : esc_html__( 'No', 'favr-events' );
		}
		return is_scalar( $value ) ? esc_html( (string) $value ) : '';
	}

	/**
	 * Label for an item.
	 *
	 * @param string $id Item.
	 */
	public static function label( string $id ): string {
		$core = self::core();
		if ( isset( $core[ $id ] ) ) {
			return (string) $core[ $id ]['label'];
		}
		$field = Fields::set()->get( $id );
		return $field ? (string) $field['label'] : $id;
	}

	/**
	 * Description as a submitter may write it.
	 *
	 * @param string $html Raw.
	 */
	public static function cleanDescription( string $html ): string {
		$html = str_replace( array( "\r\n", "\r" ), "\n", $html );
		$html = (string) preg_replace( '/<!--.*?-->/s', '', $html );
		$html = wp_kses(
			$html,
			array(
				'p'      => array(),
				'br'     => array(),
				'strong' => array(),
				'b'      => array(),
				'em'     => array(),
				'i'      => array(),
				'ul'     => array(),
				'ol'     => array(),
				'li'     => array(),
				'a'      => array( 'href' => true ),
			)
		);
		return trim( (string) preg_replace( "/\n{3,}/", "\n\n", $html ) );
	}
}
