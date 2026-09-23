<?php
/**
 * Emails about submitted events.
 *
 * @package FavrEvents
 */

declare(strict_types=1);

namespace FavrEvents\Editing;

use FavrEvents\Support\Settings;

/**
 * Plain-text emails, each filterable with `favr_events_email` (return false to skip).
 */
final class Notifier {

	/**
	 * Staff: a new event was submitted.
	 *
	 * @param int $post_id Event.
	 * @param int $user_id Submitter.
	 */
	public static function submitted( int $post_id, int $user_id ): void {
		$user = get_userdata( $user_id );
		self::send(
			'event_submitted',
			Settings::staffEmail(),
			/* translators: %s: event name. */
			sprintf( __( 'New event to review: %s', 'favr-events' ), get_the_title( $post_id ) ),
			sprintf(
				/* translators: 1: person, 2: event, 3: link. */
				__( "%1\$s submitted \"%2\$s\".\n\nReview it here:\n%3\$s", 'favr-events' ),
				$user ? $user->display_name : __( 'A member', 'favr-events' ),
				get_the_title( $post_id ),
				admin_url( 'admin.php?page=favr-approvals' )
			),
			compact( 'post_id', 'user_id' )
		);
	}

	/**
	 * Staff: changes proposed to a published event.
	 *
	 * @param int          $post_id Event.
	 * @param int          $user_id Submitter.
	 * @param list<string> $fields  Item ids.
	 */
	public static function proposed( int $post_id, int $user_id, array $fields ): void {
		$user = get_userdata( $user_id );
		self::send(
			'event_changes_proposed',
			Settings::staffEmail(),
			/* translators: %s: event name. */
			sprintf( __( 'Event update to review: %s', 'favr-events' ), get_the_title( $post_id ) ),
			sprintf(
				/* translators: 1: person, 2: event, 3: fields, 4: link. */
				__( "%1\$s suggested changes to \"%2\$s\": %3\$s.\n\nReview them here:\n%4\$s", 'favr-events' ),
				$user ? $user->display_name : __( 'A member', 'favr-events' ),
				get_the_title( $post_id ),
				implode( ', ', array_map( array( Items::class, 'label' ), $fields ) ),
				admin_url( 'admin.php?page=favr-approvals' )
			),
			compact( 'post_id', 'user_id', 'fields' )
		);
	}

	/**
	 * Submitter: decision on their event or changes.
	 *
	 * @param int    $post_id  Event.
	 * @param int    $user_id  Submitter.
	 * @param string $type     event_approved | event_declined | event_changes_approved | event_changes_rejected.
	 * @param string $note     Staff note.
	 */
	public static function decided( int $post_id, int $user_id, string $type, string $note ): void {
		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return;
		}
		$title    = get_the_title( $post_id );
		$messages = array(
			/* translators: %s: event. */
			'event_approved'         => array( __( 'Your event is live: %s', 'favr-events' ), __( 'Good news! "%s" is now on the calendar.', 'favr-events' ) ),
			/* translators: %s: event. */
			'event_declined'         => array( __( 'About your event: %s', 'favr-events' ), __( 'Thanks for submitting "%s". We weren’t able to add it to the calendar.', 'favr-events' ) ),
			/* translators: %s: event. */
			'event_changes_approved' => array( __( 'Your event update is live: %s', 'favr-events' ), __( 'Your changes to "%s" are now live.', 'favr-events' ) ),
			/* translators: %s: event. */
			'event_changes_rejected' => array( __( 'About your event update: %s', 'favr-events' ), __( 'Your suggested changes to "%s" were not approved.', 'favr-events' ) ),
		);
		if ( ! isset( $messages[ $type ] ) ) {
			return;
		}
		$body = sprintf( $messages[ $type ][1], $title );
		if ( '' !== $note ) {
			$body .= "\n\n" . __( 'Note from our team:', 'favr-events' ) . "\n" . $note;
		}
		if ( 'publish' === get_post_status( $post_id ) ) {
			$body .= "\n\n" . get_permalink( $post_id );
		}
		self::send( $type, $user->user_email, sprintf( $messages[ $type ][0], $title ), $body, compact( 'post_id', 'user_id', 'note' ) );
	}

	/**
	 * Filter and send.
	 *
	 * @param string               $type    Type.
	 * @param string               $to      To.
	 * @param string               $subject Subject.
	 * @param string               $message Body.
	 * @param array<string, mixed> $context Context.
	 */
	private static function send( string $type, string $to, string $subject, string $message, array $context ): void {
		/**
		 * Filter a Favr Events email. Return false to skip it.
		 *
		 * @param array|false $mail    { to, subject, message, headers }.
		 * @param string      $type    Message type.
		 * @param array       $context Context.
		 */
		$mail = apply_filters(
			'favr_events_email',
			array(
				'to'      => $to,
				'subject' => wp_specialchars_decode( $subject ),
				'message' => wp_specialchars_decode( $message ),
				'headers' => array(),
			),
			$type,
			$context
		);
		if ( is_array( $mail ) && ! empty( $mail['to'] ) ) {
			wp_mail( $mail['to'], (string) $mail['subject'], (string) $mail['message'], $mail['headers'] ?? array() );
		}
	}
}
