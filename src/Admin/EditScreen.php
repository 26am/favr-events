<?php
/**
 * The event add/edit screen.
 *
 * @package FavrEvents
 */

declare(strict_types=1);

namespace FavrEvents\Admin;

use FavrEvents\Model\Event;
use FavrEvents\Model\Fields;
use FavrEvents\Model\Hosts;
use FavrEvents\Schema\Identifiers as ID;
use FavrEvents\Vendor\FavrCore\Admin\FieldRenderer;
use FavrEvents\Vendor\FavrCore\Fields\Sanitizer;

/**
 * Title, then the tabbed event panel (date & time, location, tickets, host), then the
 * description editor. Classic editor so staff get one clean form.
 */
final class EditScreen {

	private const INPUT = 'favr_event';

	/** Hook. */
	public function hook(): void {
		add_filter( 'use_block_editor_for_post_type', array( $this, 'classicEditor' ), 10, 2 );
		add_action( 'edit_form_after_title', array( $this, 'renderPanel' ) );
		add_action( 'save_post_' . ID::POST_TYPE, array( $this, 'save' ), 10, 2 );
		add_action( 'add_meta_boxes_' . ID::POST_TYPE, array( $this, 'metaBoxes' ) );
		add_filter( 'post_updated_messages', array( $this, 'messages' ) );
		add_action( 'admin_notices', array( $this, 'notices' ) );
	}

	/**
	 * Classic form for events.
	 *
	 * @param bool   $use_block Current.
	 * @param string $post_type Type.
	 */
	public function classicEditor( bool $use_block, string $post_type ): bool {
		return ID::POST_TYPE === $post_type ? false : $use_block;
	}

	/** Tidy the sidebar. */
	public function metaBoxes(): void {
		remove_meta_box( 'postcustom', ID::POST_TYPE, 'normal' );
	}

	/**
	 * The panel.
	 *
	 * @param \WP_Post $post Post.
	 */
	public function renderPanel( \WP_Post $post ): void {
		if ( ID::POST_TYPE !== $post->post_type ) {
			return;
		}
		wp_nonce_field( ID::NONCE_META, '_favr_event_nonce' );
		$event = new Event( $post );
		$set   = Fields::set();

		$by_tab = array();
		foreach ( $set->all() as $field ) {
			$field = Fields::forForm( $field );
			if ( $field ) {
				$by_tab[ (string) $field['tab'] ][] = $field;
			}
		}

		if ( 'auto-draft' !== $post->post_status && $event->repeats() ) {
			$next = $event->nextOccurrence();
			if ( $next ) {
				printf(
					'<p class="favr-event-next">%s</p>',
					esc_html(
						sprintf(
							/* translators: %s: date. */
							__( 'Next: %s', 'favr-events' ),
							wp_date( (string) get_option( 'date_format' ), $next['start']->getTimestamp() )
						)
					)
				);
			}
		}

		( new FieldRenderer( self::INPUT ) )->panel(
			$set->tabs(),
			$by_tab,
			static fn( array $field ): string => $event->text( (string) $field['id'] ),
			array(
				'id'    => 'favr-event',
				'label' => __( 'Event details', 'favr-events' ),
			)
		);
	}

	/**
	 * Save the panel.
	 *
	 * @param int      $post_id Post.
	 * @param \WP_Post $post    Post.
	 */
	public function save( int $post_id, \WP_Post $post ): void {
		if ( ! isset( $_POST['_favr_event_nonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['_favr_event_nonce'] ) ), ID::NONCE_META ) ) {
			return;
		}
		if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || wp_is_post_revision( $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized per field.
		$input    = isset( $_POST[ self::INPUT ] ) && is_array( $_POST[ self::INPUT ] ) ? wp_unslash( $_POST[ self::INPUT ] ) : array();
		$warnings = self::store( $post_id, $input );
		if ( $warnings ) {
			set_transient( 'favr_events_warnings_' . get_current_user_id(), $warnings, 60 );
		}
		Event::reindex( $post_id );
	}

	/**
	 * Sanitize and store every field. Returns labels of values that were rejected.
	 *
	 * @param int                  $post_id Event.
	 * @param array<string, mixed> $input   Raw values.
	 * @param list<string>|null    $only    Limit to these field ids.
	 * @return list<string>
	 */
	public static function store( int $post_id, array $input, ?array $only = null ): array {
		$warnings = array();
		foreach ( Fields::set()->all() as $id => $field ) {
			if ( null !== $only && ! in_array( $id, $only, true ) ) {
				continue;
			}
			$raw   = $input[ $id ] ?? null;
			$value = Sanitizer::sanitize( $field, $raw );
			if ( 'host_business' === $id && '' !== $value && ! isset( Hosts::options()[ (string) $value ] ) ) {
				$value = '';
			}
			if ( in_array( $field['type'], array( 'email', 'url', 'date', 'time' ), true ) && is_string( $raw ) && '' !== trim( $raw ) && '' === $value ) {
				$warnings[] = (string) $field['label'];
			}
			if ( Sanitizer::isEmpty( $field, $value ) ) {
				delete_post_meta( $post_id, ID::meta( (string) $id ) );
			} else {
				update_post_meta( $post_id, ID::meta( (string) $id ), (string) $value );
			}
		}
		return $warnings;
	}

	/** Warnings from the last save. */
	public function notices(): void {
		$screen = get_current_screen();
		if ( $screen && 'post' === $screen->base && ID::POST_TYPE === $screen->post_type ) {
			$post  = get_post();
			$event = $post ? new Event( $post ) : null;
			if ( $event && 'auto-draft' !== $post->post_status && ! $event->start() ) {
				echo '<div class="notice notice-warning"><p>' . esc_html__( 'Add a start date so this event appears on the calendar.', 'favr-events' ) . '</p></div>';
			}
		}
		$key      = 'favr_events_warnings_' . get_current_user_id();
		$warnings = get_transient( $key );
		if ( is_array( $warnings ) && $warnings ) {
			delete_transient( $key );
			printf(
				'<div class="notice notice-warning is-dismissible"><p>%s</p></div>',
				esc_html(
					sprintf(
						/* translators: %s: field names. */
						__( 'Some values were not valid and were not saved: %s.', 'favr-events' ),
						implode( ', ', $warnings )
					)
				)
			);
		}
	}

	/**
	 * Messages.
	 *
	 * @param array<string, array<int, string>> $messages Messages.
	 * @return array<string, array<int, string>>
	 */
	public function messages( array $messages ): array {
		$post = get_post();
		$link = $post ? sprintf( ' <a href="%s">%s</a>', esc_url( (string) get_permalink( $post ) ), esc_html__( 'View event', 'favr-events' ) ) : '';

		$messages[ ID::POST_TYPE ] = array(
			0  => '',
			1  => __( 'Event updated.', 'favr-events' ) . $link,
			4  => __( 'Event updated.', 'favr-events' ),
			6  => __( 'Event published.', 'favr-events' ) . $link,
			7  => __( 'Event saved.', 'favr-events' ),
			8  => __( 'Event submitted.', 'favr-events' ),
			9  => __( 'Event scheduled.', 'favr-events' ),
			10 => __( 'Event draft updated.', 'favr-events' ),
		);
		return $messages;
	}
}
