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
 * Title, then one tabbed event panel: date & time, description (the WordPress editor and the
 * short summary), location, tickets, host. Classic editor so staff get one clean form.
 */
final class EditScreen {

	/** The tab that holds the description editor and the short summary. */
	public const TAB_DESCRIPTION = 'description';

	private const INPUT = 'favr_event';

	/** Hook. */
	public function hook(): void {
		add_filter( 'use_block_editor_for_post_type', array( $this, 'classicEditor' ), 10, 2 );
		add_action( 'load-post.php', array( $this, 'prepareScreen' ) );
		add_action( 'load-post-new.php', array( $this, 'prepareScreen' ) );
		add_filter( 'enter_title_here', array( $this, 'titlePlaceholder' ), 10, 2 );
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

	/**
	 * The description editor is drawn inside the panel (Description tab), so WordPress must not
	 * draw its own below it.
	 */
	public function prepareScreen(): void {
		$screen = get_current_screen();
		if ( ! $screen || ID::POST_TYPE !== $screen->post_type ) {
			return;
		}
		// Only for this request: REST, the front end and member submissions keep editor support.
		remove_post_type_support( ID::POST_TYPE, 'editor' );
	}

	/**
	 * Title placeholder.
	 *
	 * @param string   $text Placeholder.
	 * @param \WP_Post $post Post.
	 */
	public function titlePlaceholder( string $text, $post ): string {
		return $post instanceof \WP_Post && ID::POST_TYPE === $post->post_type ? __( 'Event name', 'favr-events' ) : $text;
	}

	/** Drop the boxes whose job the panel does: the summary is on the Description tab. */
	public function metaBoxes(): void {
		remove_meta_box( 'postexcerpt', ID::POST_TYPE, 'normal' );
		remove_meta_box( 'postcustom', ID::POST_TYPE, 'normal' );
	}

	/**
	 * The panel's tabs in order: every field tab that has fields, with Description straight
	 * after Date & time (first when there is no date tab). A site that declares a `description`
	 * tab with the `favr_events_tabs` filter decides its place, label and icon itself.
	 *
	 * @param array<string, array<string, string>> $tabs   Field tabs: id => { label, icon }.
	 * @param list<int|string>                     $filled Ids of the tabs that have fields.
	 * @return array<string, array<string, string>>
	 */
	public static function panelTabs( array $tabs, array $filled ): array {
		$filled      = array_map( 'strval', $filled );
		$declared    = isset( $tabs[ self::TAB_DESCRIPTION ] );
		$description = $tabs[ self::TAB_DESCRIPTION ] ?? array(
			'label' => __( 'Description', 'favr-events' ),
			'icon'  => 'dashicons-text-page',
		);

		$out = array();
		foreach ( $tabs as $id => $tab ) {
			$id = (string) $id;
			if ( self::TAB_DESCRIPTION === $id ) {
				$out[ $id ] = $description;
				continue;
			}
			if ( ! in_array( $id, $filled, true ) ) {
				continue;
			}
			$out[ $id ] = $tab;
			if ( 'when' === $id && ! $declared ) {
				$out[ self::TAB_DESCRIPTION ] = $description;
			}
		}
		if ( ! isset( $out[ self::TAB_DESCRIPTION ] ) ) {
			$out = array( self::TAB_DESCRIPTION => $description ) + $out;
		}
		return $out;
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

		// The same markup as favr/core's FieldRenderer::panel(), which cannot hold a tab of
		// custom content (the editor), so the shared styles and script apply unchanged.
		$renderer = new FieldRenderer( self::INPUT );
		$tabs     = self::panelTabs( $set->tabs(), array_keys( $by_tab ) );

		echo '<div class="favr-panel" id="favr-event-panel">';
		printf( '<nav class="favr-tabs" role="tablist" aria-label="%s">', esc_attr__( 'Event details', 'favr-events' ) );
		$first = true;
		foreach ( $tabs as $tab_id => $tab ) {
			printf(
				'<button type="button" role="tab" class="favr-tab%1$s" id="favr-event-tab-%2$s" aria-controls="favr-event-pane-%2$s" aria-selected="%3$s" data-tab="%2$s"><span class="dashicons %4$s" aria-hidden="true"></span><span class="favr-tab__label">%5$s</span><span class="favr-tab__dot" aria-hidden="true"></span></button>',
				$first ? ' is-active' : '',
				esc_attr( (string) $tab_id ),
				$first ? 'true' : 'false',
				esc_attr( (string) ( $tab['icon'] ?? 'dashicons-admin-generic' ) ),
				esc_html( (string) $tab['label'] )
			);
			$first = false;
		}
		echo '</nav><div class="favr-panes">';

		$first = true;
		foreach ( $tabs as $tab_id => $tab ) {
			printf(
				'<section class="favr-pane%1$s" role="tabpanel" id="favr-event-pane-%2$s" aria-labelledby="favr-event-tab-%2$s"%3$s><h2 class="favr-pane__title">%4$s</h2><div class="favr-grid">',
				$first ? ' is-active' : '',
				esc_attr( (string) $tab_id ),
				$first ? '' : ' hidden',
				esc_html( (string) $tab['label'] )
			);
			if ( self::TAB_DESCRIPTION === (string) $tab_id ) {
				$this->renderDescription( $post );
			}
			foreach ( $by_tab[ $tab_id ] ?? array() as $field ) {
				$renderer->render( $field, $event->text( (string) $field['id'] ) );
			}
			echo '</div></section>';
			$first = false;
		}
		echo '</div></div>';
	}

	/**
	 * The Description tab: the full description (the post content, in the WordPress editor)
	 * and the short summary (the post excerpt). Both keep WordPress's own field names, so
	 * WordPress saves and autosaves them exactly as it did from its own boxes.
	 *
	 * @param \WP_Post $post Event.
	 */
	private function renderDescription( \WP_Post $post ): void {
		echo '<div class="favr-field favr-w-full favr-field--editor">';
		printf( '<label class="favr-field__label" for="content">%s</label>', esc_html__( 'About this event', 'favr-events' ) );
		wp_editor(
			$post->post_content,
			'content',
			array(
				'textarea_name'    => 'content',
				'textarea_rows'    => 12,
				'editor_height'    => 300,
				'media_buttons'    => true,
				'drag_drop_upload' => true,
				'tinymce'          => array( 'add_unload_trigger' => false ),
			)
		);
		echo '</div>';
		echo self::summaryField( self::summaryText( $post ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in summaryField().
	}

	/**
	 * The short summary as stored. A post prepared for the edit screen arrives already escaped
	 * for a textarea, so read the stored text and let summaryField() escape it exactly once.
	 *
	 * @param \WP_Post $post Event.
	 */
	public static function summaryText( \WP_Post $post ): string {
		return 'edit' === $post->filter ? (string) get_post_field( 'post_excerpt', $post->ID, 'raw' ) : (string) $post->post_excerpt;
	}

	/**
	 * The "Short summary" field: the post excerpt under a plain name, in place of WordPress's
	 * Excerpt box. Search engines (Frontend\Seo) and calendar apps (Frontend\Ical) use it.
	 *
	 * @param string $summary Stored summary, unescaped.
	 */
	public static function summaryField( string $summary ): string {
		return sprintf(
			'<div class="favr-field favr-field--textarea favr-w-full"><label class="favr-field__label" for="excerpt">%1$s</label><div class="favr-field__input"><textarea id="excerpt" name="excerpt" rows="3" class="widefat" aria-describedby="favr-event-summary-help">%2$s</textarea></div><p class="favr-field__help" id="favr-event-summary-help">%3$s</p></div>',
			esc_html__( 'Short summary', 'favr-events' ),
			esc_textarea( $summary ),
			esc_html__( 'Shown to search engines and in calendar apps. Leave empty to use the start of the description.', 'favr-events' )
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
			5  => __( 'Event restored to an earlier version.', 'favr-events' ),
			6  => __( 'Event published.', 'favr-events' ) . $link,
			7  => __( 'Event saved.', 'favr-events' ),
			8  => __( 'Event submitted.', 'favr-events' ),
			9  => __( 'Event scheduled.', 'favr-events' ),
			10 => __( 'Event draft updated.', 'favr-events' ),
		);
		return $messages;
	}
}
