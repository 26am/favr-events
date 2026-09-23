<?php
/**
 * Member event submissions ("My Events").
 *
 * @package FavrEvents
 */

declare(strict_types=1);

namespace FavrEvents\Editing;

use FavrEvents\Frontend\Format;
use FavrEvents\Model\Event;
use FavrEvents\Model\Fields;
use FavrEvents\Schema\Identifiers as ID;
use FavrEvents\Support\AssetVersion;
use FavrEvents\Support\Settings;
use FavrEvents\Vendor\FavrCore\Admin\FieldRenderer;
use FavrEvents\Vendor\FavrCore\Moderation\PendingChanges;
use FavrEvents\Vendor\FavrCore\Moderation\Uploads;
use FavrEvents\Vendor\FavrCore\Support\RateLimit;

/**
 * Members submit events from the Favr Members dashboard ("My Events" tab) or any page with
 * [favr_my_events]. New events wait as WordPress "pending" posts for staff approval; until
 * then the submitter can keep editing. After publication their changes follow Items::access().
 */
final class Submissions {

	public const ACTION  = 'favr_event_submit';
	public const QV_EDIT = 'fe_edit';
	public const QV_MSG  = 'fe_msg';

	/** Saves per person per hour. */
	private const LIMIT = 30;

	/** Statuses a submitter can still see and edit. */
	private const EDITABLE = array( 'pending', 'draft', 'publish', 'future' );

	/** Hook. */
	public function hook(): void {
		add_shortcode( ID::SHORTCODE_MINE, array( self::class, 'render' ) );
		add_action( 'template_redirect', array( $this, 'handle' ), 5 );
		add_filter( 'favr_members_dashboard_tabs', array( $this, 'dashboardTab' ), 10, 2 );
	}

	/**
	 * Whether a person may submit events.
	 *
	 * @param int $user_id User.
	 */
	public static function canSubmit( int $user_id ): bool {
		$mode = (string) Settings::get( 'submissions' );
		if ( $user_id <= 0 || 'off' === $mode ) {
			$allowed = false;
		} elseif ( 'users' === $mode ) {
			$allowed = true;
		} else {
			$allowed = function_exists( 'favr_members_is_active' ) && favr_members_is_active( $user_id );
		}

		/**
		 * Filter whether a person may submit events.
		 *
		 * @param bool $allowed Allowed.
		 * @param int  $user_id User.
		 */
		return (bool) apply_filters( 'favr_events_can_submit', $allowed, $user_id );
	}

	/**
	 * Whether a person may edit this event from the front end (their own submission).
	 *
	 * @param int $user_id User.
	 * @param int $post_id Event.
	 */
	public static function canEdit( int $user_id, int $post_id ): bool {
		$post = get_post( $post_id );
		if ( ! $post || ID::POST_TYPE !== $post->post_type || (int) $post->post_author !== $user_id || ! in_array( $post->post_status, self::EDITABLE, true ) || ! self::canSubmit( $user_id ) ) {
			return false;
		}
		// A draft is only theirs to edit when staff declined it (to fix and resubmit); events
		// staff moved to draft themselves stay with staff.
		return 'draft' !== $post->post_status || (bool) get_post_meta( $post_id, Queues::DECLINED, true );
	}

	/**
	 * Members dashboard tab.
	 *
	 * @param array<string, array<string, mixed>> $tabs Tabs.
	 * @param \WP_User                            $user Person.
	 * @return array<string, array<string, mixed>>
	 */
	public function dashboardTab( array $tabs, $user ): array {
		if ( $user instanceof \WP_User && ( self::canSubmit( $user->ID ) || self::mine( $user->ID ) ) ) {
			$tabs['events'] = array(
				'label'    => __( 'My Events', 'favr-events' ),
				'priority' => 30,
				'render'   => static fn(): string => self::render(),
			);
		}
		return $tabs;
	}

	/**
	 * The person's events, newest first.
	 *
	 * @param int $user_id User.
	 * @return list<\WP_Post>
	 */
	public static function mine( int $user_id ): array {
		if ( $user_id <= 0 ) {
			return array();
		}
		$posts = get_posts(
			array(
				'post_type'      => ID::POST_TYPE,
				'post_status'    => self::EDITABLE,
				'author'         => $user_id,
				'posts_per_page' => 50,
				'orderby'        => 'date',
				'order'          => 'DESC',
			)
		);
		return array_values( array_filter( $posts, static fn( \WP_Post $p ): bool => 'draft' !== $p->post_status || (bool) get_post_meta( $p->ID, Queues::DECLINED, true ) ) );
	}

	/** Render the list or the form. */
	public static function render(): string {
		$here = self::currentUrl();
		if ( ! is_user_logged_in() ) {
			/** This filter is documented in Favr Directory (login URL for member features). */
			$login = (string) apply_filters( 'favr_directory_login_url', wp_login_url( $here ), $here );
			return sprintf( '<div class="favr-my-events"><p>%1$s</p><p><a class="favr-ev-btn favr-ev-btn--primary" href="%2$s">%3$s</a></p></div>', esc_html__( 'Log in to submit and manage your events.', 'favr-events' ), esc_url( $login ), esc_html__( 'Log in', 'favr-events' ) );
		}
		$user_id = get_current_user_id();
		wp_enqueue_style( 'favr-events' );
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- navigation.
		$edit = isset( $_GET[ self::QV_EDIT ] ) ? sanitize_key( wp_unslash( $_GET[ self::QV_EDIT ] ) ) : '';

		ob_start();
		echo '<div class="favr-my-events favr-front" id="favr-my-events">';
		self::notice( $user_id );
		if ( 'new' === $edit && self::canSubmit( $user_id ) ) {
			self::form( null, $user_id, $here );
		} elseif ( '' !== $edit && ctype_digit( $edit ) && self::canEdit( $user_id, (int) $edit ) ) {
			self::form( get_post( (int) $edit ), $user_id, $here );
		} else {
			self::listing( $user_id, $here );
		}
		echo '</div>';
		return (string) ob_get_clean();
	}

	/**
	 * The person's events with statuses.
	 *
	 * @param int    $user_id User.
	 * @param string $here    URL.
	 */
	private static function listing( int $user_id, string $here ): void {
		$base = remove_query_arg( array( self::QV_EDIT, self::QV_MSG ), $here );
		echo '<div class="favr-my-events__head"><h2>' . esc_html__( 'My events', 'favr-events' ) . '</h2>';
		if ( self::canSubmit( $user_id ) ) {
			printf( '<a class="favr-ev-btn favr-ev-btn--primary" href="%1$s">+ %2$s</a>', esc_url( add_query_arg( self::QV_EDIT, 'new', $base ) ), esc_html__( 'Submit an event', 'favr-events' ) );
		}
		echo '</div>';
		if ( ! self::canSubmit( $user_id ) ) {
			echo '<p class="favr-notice">' . esc_html__( 'Event submissions are open to current members.', 'favr-events' ) . '</p>';
		}
		$events = self::mine( $user_id );
		if ( ! $events ) {
			echo '<p>' . esc_html__( 'You haven’t submitted any events yet. Share workshops, open houses, ribbon cuttings and more with the community.', 'favr-events' ) . '</p>';
			return;
		}
		echo '<ul class="favr-my-events__list">';
		foreach ( $events as $post ) {
			$event   = new Event( $post );
			$next    = $event->nextOccurrence();
			$pending = PendingChanges::get( $post->ID );
			$labels  = array(
				'pending' => __( 'Waiting for approval', 'favr-events' ),
				'draft'   => get_post_meta( $post->ID, '_favr_event_declined', true ) ? __( 'Not approved', 'favr-events' ) : __( 'Draft', 'favr-events' ),
				'publish' => $pending ? __( 'Published · changes waiting for review', 'favr-events' ) : __( 'Published', 'favr-events' ),
				'future'  => __( 'Scheduled', 'favr-events' ),
			);
			printf(
				'<li><div><strong>%1$s</strong><br><small>%2$s</small></div><span class="favr-ev-tag favr-ev-tag--%3$s">%4$s</span><span class="favr-my-events__actions">%5$s<a href="%6$s">%7$s</a></span></li>',
				esc_html( get_the_title( $post ) ),
				esc_html( $next ? Format::when( $event, $next['start'], $next['end'] ) : __( 'No date yet', 'favr-events' ) ),
				esc_attr( $post->post_status ),
				esc_html( $labels[ $post->post_status ] ?? $post->post_status ),
				'publish' === $post->post_status ? '<a href="' . esc_url( (string) get_permalink( $post ) ) . '">' . esc_html__( 'View', 'favr-events' ) . '</a> · ' : '',
				esc_url( add_query_arg( self::QV_EDIT, $post->ID, $base ) ),
				esc_html__( 'Edit', 'favr-events' )
			);
		}
		echo '</ul>';
	}

	/**
	 * The submission form.
	 *
	 * @param \WP_Post|null $post    Event being edited, or null for new.
	 * @param int           $user_id User.
	 * @param string        $here    URL.
	 */
	private static function form( ?\WP_Post $post, int $user_id, string $here ): void {
		self::enqueue();
		$post_id   = $post ? $post->ID : 0;
		$published = $post && in_array( $post->post_status, array( 'publish', 'future' ), true );
		$pending   = $post_id ? PendingChanges::get( $post_id ) : array();
		$items     = Items::forUser( $user_id, $published );
		$back      = remove_query_arg( array( self::QV_EDIT, self::QV_MSG ), $here );

		printf( '<p><a href="%1$s">← %2$s</a></p>', esc_url( $back ), esc_html__( 'My events', 'favr-events' ) );
		printf( '<h2>%s</h2>', esc_html( $post ? get_the_title( $post ) : __( 'Submit an event', 'favr-events' ) ) );
		if ( ! $post ) {
			echo '<p class="favr-notice">' . esc_html__( 'Our team reviews new events before they appear on the calendar. You can keep editing until then.', 'favr-events' ) . '</p>';
		} elseif ( $published ) {
			echo '<p class="favr-notice">' . esc_html__( 'This event is live. Changes to the date, place or description are reviewed before they go live; ticket and contact details update right away.', 'favr-events' ) . '</p>';
		}

		$by_tab = array();
		foreach ( $items as $id => $item ) {
			if ( $published && Items::REVIEW === Items::access( $id ) ) {
				$item['description'] = trim( $item['description'] . ' ' . __( 'Reviewed before it goes live.', 'favr-events' ) );
			}
			$by_tab[ (string) $item['tab'] ][] = $item;
		}

		printf( '<form class="favr-my-events__form" method="post" action="%s">', esc_url( $here ) );
		wp_nonce_field( self::ACTION . '_' . $post_id, '_favr_event_nonce' );
		printf(
			'<input type="hidden" name="favr_action" value="%1$s"><input type="hidden" name="favr_event" value="%2$d"><input type="hidden" name="favr_redirect" value="%3$s">',
			esc_attr( self::ACTION ),
			(int) $post_id,
			esc_attr( $back )
		);
		$renderer = new FieldRenderer(
			'favr',
			array(
				'endpoint' => rest_url( UploadRoute::NAMESPACE . UploadRoute::ROUTE ),
				'nonce'    => wp_create_nonce( 'wp_rest' ),
				'parent'   => $post_id,
			)
		);
		$renderer->panel(
			Fields::set()->tabs(),
			$by_tab,
			static function ( array $item ) use ( $post_id, $pending ) {
				$id = (string) $item['id'];
				if ( isset( $pending[ $id ] ) ) {
					return 'categories' === $id ? array_map( 'strval', (array) $pending[ $id ]['new'] ) : $pending[ $id ]['new'];
				}
				if ( ! $post_id ) {
					return 'attendance' === $id ? 'in_person' : ( 'repeat' === $id ? 'none' : '' );
				}
				$value = Items::current( $post_id, $id );
				return 'categories' === $id ? array_map( 'strval', (array) $value ) : $value;
			},
			array(
				'id'          => 'favr-event-form',
				'label'       => __( 'Event sections', 'favr-events' ),
				'after_field' => static function ( array $item ) use ( $pending, $post_id ): void {
					$id = (string) $item['id'];
					if ( isset( $pending[ $id ] ) ) {
						$live = Items::display( $id, Items::current( $post_id, $id ) );
						echo '<div class="favr-pending"><strong>' . esc_html__( 'Waiting for review.', 'favr-events' ) . '</strong> ' . ( '' !== $live ? esc_html__( 'Live now:', 'favr-events' ) . ' ' . $live : '' ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- display() escapes.
					}
				},
			)
		);
		printf(
			'<p class="favr-my-events__submit"><button type="submit" class="favr-ev-btn favr-ev-btn--primary">%s</button></p></form>',
			esc_html( $post ? __( 'Save changes', 'favr-events' ) : __( 'Submit for review', 'favr-events' ) )
		);
	}

	/** Form assets (shared favr-core field UI). */
	private static function enqueue(): void {
		$v = array( AssetVersion::class, 'of' );
		wp_enqueue_style( 'dashicons' );
		wp_enqueue_style( 'favr-core-fields', FAVR_EVENTS_URL . 'assets/core/fields.css', array( 'dashicons' ), $v( 'assets/core/fields.css' ) );
		wp_enqueue_style( 'favr-core-front', FAVR_EVENTS_URL . 'assets/core/front-fields.css', array( 'favr-core-fields' ), $v( 'assets/core/front-fields.css' ) );
		wp_enqueue_script( 'favr-core-fields', FAVR_EVENTS_URL . 'assets/core/fields.js', array( 'jquery', 'jquery-ui-sortable' ), $v( 'assets/core/fields.js' ), true );
		wp_localize_script(
			'favr-core-fields',
			'favrCoreFields',
			array(
				'i18n' => array(
					'removeImage' => __( 'Remove image', 'favr-events' ),
					/* translators: %d: number of characters. */
					'charsLeft'   => __( '%d characters left', 'favr-events' ),
				),
			)
		);
		wp_enqueue_script( 'favr-core-uploader', FAVR_EVENTS_URL . 'assets/core/uploader.js', array( 'favr-core-fields' ), $v( 'assets/core/uploader.js' ), true );
	}

	/**
	 * Result message.
	 *
	 * @param int $user_id User.
	 */
	private static function notice( int $user_id ): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display only.
		$code     = isset( $_GET[ self::QV_MSG ] ) ? sanitize_key( wp_unslash( $_GET[ self::QV_MSG ] ) ) : '';
		$messages = array(
			'submitted' => __( 'Thanks! Your event was submitted. We’ll email you when it’s approved.', 'favr-events' ),
			'saved'     => __( 'Your changes were saved.', 'favr-events' ),
			'review'    => __( 'Saved. Some changes will appear once our team reviews them.', 'favr-events' ),
			'nochange'  => __( 'Nothing changed.', 'favr-events' ),
			'invalid'   => __( 'Please add an event name and a start date.', 'favr-events' ),
			'expired'   => __( 'Your session expired. Please try again.', 'favr-events' ),
			'slow'      => __( 'You’ve made a lot of changes in a short time. Please try again later.', 'favr-events' ),
		);
		if ( isset( $messages[ $code ] ) ) {
			printf( '<div class="favr-notice favr-notice--%1$s" role="status">%2$s</div>', esc_attr( in_array( $code, array( 'invalid', 'expired', 'slow' ), true ) ? 'error' : 'success' ), esc_html( $messages[ $code ] ) );
		}
		$invalid = get_transient( 'favr_event_invalid_' . $user_id );
		if ( is_array( $invalid ) && $invalid ) {
			delete_transient( 'favr_event_invalid_' . $user_id );
			/* translators: %s: field names. */
			printf( '<div class="favr-notice favr-notice--error" role="alert">%s</div>', esc_html( sprintf( __( 'These values weren’t valid and were not saved: %s.', 'favr-events' ), implode( ', ', $invalid ) ) ) );
		}
	}

	/** Handle a submission. */
	public function handle(): void {
		if ( ! isset( $_POST['favr_action'] ) || self::ACTION !== $_POST['favr_action'] ) {
			return;
		}
		$post_id  = isset( $_POST['favr_event'] ) ? absint( $_POST['favr_event'] ) : 0;
		$redirect = isset( $_POST['favr_redirect'] ) ? wp_validate_redirect( esc_url_raw( wp_unslash( $_POST['favr_redirect'] ) ), home_url( '/' ) ) : home_url( '/' );
		$user_id  = get_current_user_id();
		$nonce    = isset( $_POST['_favr_event_nonce'] ) ? sanitize_key( wp_unslash( $_POST['_favr_event_nonce'] ) ) : '';

		if ( ! wp_verify_nonce( $nonce, self::ACTION . '_' . $post_id ) ) {
			self::back( $redirect, 'expired', $post_id );
		}
		if ( $post_id ? ! self::canEdit( $user_id, $post_id ) : ! self::canSubmit( $user_id ) ) {
			wp_die( esc_html__( 'You can’t edit this event.', 'favr-events' ), '', array( 'response' => 403 ) );
		}
		if ( ! RateLimit::hit( 'event_save_' . $user_id, self::LIMIT ) ) {
			self::back( $redirect, 'slow', $post_id );
		}
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Items::sanitize() per item.
		$input  = isset( $_POST['favr'] ) && is_array( $_POST['favr'] ) ? wp_unslash( $_POST['favr'] ) : array();
		$result = self::save( $post_id, $user_id, $input );
		if ( $result['invalid'] ) {
			set_transient( 'favr_event_invalid_' . $user_id, $result['invalid'], 120 );
		}
		self::back( $redirect, $result['code'], 'invalid' === $result['code'] ? $post_id : 0 );
	}

	/**
	 * Create or update an event from submitted values.
	 *
	 * @param int                  $post_id 0 for new.
	 * @param int                  $user_id Submitter (already authorized).
	 * @param array<string, mixed> $input   Raw values by item id.
	 * @return array{code: string, post_id: int, invalid: list<string>}
	 */
	public static function save( int $post_id, int $user_id, array $input ): array {
		$existing = $post_id ? get_post( $post_id ) : null;
		$items    = Items::forUser( $user_id, $existing && in_array( $existing->post_status, array( 'publish', 'future' ), true ) );
		$values   = array();
		$invalid  = array();
		foreach ( $items as $id => $item ) {
			$raw   = $input[ $id ] ?? null;
			$value = Items::sanitize( $id, $raw, $user_id );
			if ( 'image' === $item['type'] ) {
				$known = $post_id ? array( (int) Items::current( $post_id, $id ) ) : array();
				$ok    = Uploads::filterUsable( (int) $value > 0 ? array( (int) $value ) : array(), $user_id, $post_id, $known );
				$value = $ok[0] ?? ( 'image' === $id ? 0 : '' );
			}
			if ( is_string( $raw ) && '' !== trim( $raw ) && '' === $value && in_array( $item['type'], array( 'email', 'url', 'tel', 'date', 'time' ), true ) ) {
				$invalid[] = (string) $item['label'];
				continue;
			}
			$values[ $id ] = $value;
		}
		if ( '' === (string) ( $values['title'] ?? '' ) || '' === (string) ( $values['start_date'] ?? '' ) ) {
			return array(
				'code'    => 'invalid',
				'post_id' => $post_id,
				'invalid' => $invalid,
			);
		}

		$post = $post_id ? get_post( $post_id ) : null;
		if ( ! $post ) {
			$post_id = (int) wp_insert_post(
				array(
					'post_type'   => ID::POST_TYPE,
					'post_status' => 'pending',
					'post_author' => $user_id,
					'post_title'  => (string) $values['title'],
				)
			);
			if ( ! $post_id ) {
				return array(
					'code'    => 'invalid',
					'post_id' => 0,
					'invalid' => $invalid,
				);
			}
			foreach ( $values as $id => $value ) {
				Items::apply( $post_id, $id, $value );
			}
			// Attach images uploaded before the event existed.
			$image = (int) ( $values['image'] ?? 0 );
			if ( $image && ! wp_get_post_parent_id( $image ) ) {
				wp_update_post(
					array(
						'ID'          => $image,
						'post_parent' => $post_id,
					)
				);
			}
			Event::reindex( $post_id );
			PendingChanges::log( $post_id, 'submitted', array_keys( $values ), $user_id );
			Notifier::submitted( $post_id, $user_id );
			return array(
				'code'    => 'submitted',
				'post_id' => $post_id,
				'invalid' => $invalid,
			);
		}

		if ( 'draft' === $post->post_status ) {
			// Fixing a declined event sends it back for review.
			foreach ( $values as $id => $value ) {
				Items::apply( $post_id, $id, $value );
			}
			delete_post_meta( $post_id, Queues::DECLINED );
			wp_update_post(
				array(
					'ID'          => $post_id,
					'post_status' => 'pending',
				)
			);
			Event::reindex( $post_id );
			PendingChanges::log( $post_id, 'submitted', array_keys( $values ), $user_id );
			Notifier::submitted( $post_id, $user_id );
			return array(
				'code'    => 'submitted',
				'post_id' => $post_id,
				'invalid' => $invalid,
			);
		}

		$live_now  = in_array( $post->post_status, array( 'publish', 'future' ), true );
		$pending   = PendingChanges::get( $post_id );
		$saved     = array();
		$proposals = array();
		foreach ( $values as $id => $value ) {
			$live    = Items::effective( $id, Items::current( $post_id, $id ) );
			$changed = PendingChanges::differs( $live, Items::effective( $id, $value ) );
			if ( ! $live_now || Items::EDIT === Items::access( $id ) ) {
				if ( $changed ) {
					Items::apply( $post_id, $id, $value );
					$saved[] = $id;
				}
				continue;
			}
			$was = isset( $pending[ $id ] );
			if ( ( $was && PendingChanges::differs( $pending[ $id ]['new'], $value ) ) || ( ! $was && $changed ) ) {
				$proposals[ $id ] = array(
					'old' => $live,
					'new' => $value,
				);
			}
		}
		$proposed = $proposals ? PendingChanges::propose( $post_id, $proposals, $user_id ) : array();
		if ( $saved ) {
			Event::reindex( $post_id );
			PendingChanges::log( $post_id, 'saved', $saved, $user_id );
		}
		if ( $proposed ) {
			PendingChanges::log( $post_id, 'proposed', $proposed, $user_id );
			Notifier::proposed( $post_id, $user_id, $proposed );
		}
		if ( $proposed ) {
			$code = 'review';
		} else {
			$code = $saved ? 'saved' : 'nochange';
		}
		return array(
			'code'    => $code,
			'post_id' => $post_id,
			'invalid' => $invalid,
		);
	}

	/**
	 * Redirect back.
	 *
	 * @param string $url     URL.
	 * @param string $code    Result.
	 * @param int    $post_id Reopen this event's form (0 = list).
	 */
	private static function back( string $url, string $code, int $post_id ): void {
		$args = array( self::QV_MSG => $code );
		if ( 'invalid' === $code || 'expired' === $code ) {
			$args[ self::QV_EDIT ] = $post_id ? (string) $post_id : 'new';
		}
		wp_safe_redirect( add_query_arg( $args, $url ) . '#favr-my-events', 303 );
		exit;
	}

	/** Current front-end URL. */
	private static function currentUrl(): string {
		$uri  = isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '/';
		$base = rtrim( (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH ), '/' );
		if ( '' !== $base && str_starts_with( $uri, $base . '/' ) ) {
			$uri = substr( $uri, strlen( $base ) );
		}
		return home_url( $uri );
	}
}
