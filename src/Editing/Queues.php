<?php
/**
 * "Event submissions" and "Event updates" in the shared Approvals inbox.
 *
 * @package FavrEvents
 */

declare(strict_types=1);

namespace FavrEvents\Editing;

use FavrEvents\Frontend\Format;
use FavrEvents\Model\Event;
use FavrEvents\Schema\Identifiers as ID;
use FavrEvents\Vendor\FavrCore\Approvals\Inbox;
use FavrEvents\Vendor\FavrCore\Moderation\PendingChanges;

/**
 * Submissions are pending posts: approving publishes them, declining returns them to the
 * submitter as a draft with the note. Updates are proposals on published events.
 */
final class Queues {

	public const DECLINED = '_favr_event_declined';

	/** Hook. */
	public function hook(): void {
		add_filter( 'favr_approvals_providers', array( $this, 'providers' ) );
	}

	/**
	 * Register queues.
	 *
	 * @param array<int, array<string, mixed>> $providers Providers.
	 * @return array<int, array<string, mixed>>
	 */
	public function providers( array $providers ): array {
		$cap         = 'edit_others_' . ID::CAP_PLURAL;
		$providers[] = array(
			'id'         => 'favr_events_submissions',
			'label'      => __( 'Event submissions', 'favr-events' ),
			'capability' => $cap,
			'items'      => array( $this, 'submissions' ),
			'decide'     => array( $this, 'decideSubmission' ),
			'count'      => static fn(): int => (int) ( wp_count_posts( ID::POST_TYPE )->pending ?? 0 ),
		);
		$providers[] = array(
			'id'         => 'favr_events_changes',
			'label'      => __( 'Event updates', 'favr-events' ),
			'capability' => $cap,
			'items'      => array( $this, 'changes' ),
			'decide'     => array( $this, 'decideChanges' ),
			'count'      => static fn(): int => count( self::withChanges() ),
		);
		return $providers;
	}

	/**
	 * Pending submissions.
	 *
	 * @return list<array<string, mixed>>
	 */
	public function submissions(): array {
		$items = array();
		$posts = get_posts(
			array(
				'post_type'      => ID::POST_TYPE,
				'post_status'    => 'pending',
				'posts_per_page' => 100,
				'orderby'        => 'date',
				'order'          => 'ASC',
			)
		);
		foreach ( $posts as $post ) {
			$event = new Event( $post );
			$next  = $event->nextOccurrence();
			$user  = get_userdata( (int) $post->post_author );
			$rows  = array_filter(
				array(
					__( 'When', 'favr-events' )  => $next ? esc_html( Format::when( $event, $next['start'], $next['end'] ) . ( $event->repeats() ? ' — ' . Format::rule( $event ) : '' ) ) : '',
					__( 'Where', 'favr-events' ) => esc_html( implode( ', ', array_filter( array_merge( array( $event->text( 'venue' ) ), $event->addressLines(), array( 'in_person' !== $event->attendance() ? __( 'Online', 'favr-events' ) : '' ) ) ) ) ),
					__( 'Host', 'favr-events' )  => esc_html( $event->organizerName() ),
					__( 'Cost', 'favr-events' )  => esc_html( $event->text( 'cost' ) ),
					__( 'About', 'favr-events' ) => Items::display( 'description', $post->post_content ),
					__( 'Image', 'favr-events' ) => Items::display( 'image', (int) get_post_thumbnail_id( $post ) ),
				)
			);
			$html  = '<table class="widefat striped favr-diff"><tbody>';
			foreach ( $rows as $label => $value ) {
				$html .= '<tr><th scope="row">' . esc_html( $label ) . '</th><td>' . $value . '</td></tr>';
			}
			$html   .= '</tbody></table>';
			$items[] = array(
				'id'       => (int) $post->ID,
				'title'    => get_the_title( $post ),
				/* translators: %s: person. */
				'subtitle' => $user ? sprintf( __( 'submitted by %s', 'favr-events' ), $user->display_name ) : '',
				'edit_url' => (string) get_edit_post_link( $post->ID, 'raw' ),
				'time'     => (int) get_post_time( 'U', true, $post ),
				'details'  => $html,
				'version'  => self::fingerprint( $post ),
			);
		}
		return $items;
	}

	/**
	 * What the reviewer sees of a submission: title, content, image, terms and event fields
	 * (not bookkeeping meta such as edit locks).
	 *
	 * @param \WP_Post $post Event.
	 */
	private static function fingerprint( \WP_Post $post ): string {
		$meta = array_filter(
			(array) get_post_meta( $post->ID ),
			static fn( string $key ): bool => str_starts_with( $key, ID::META_PREFIX ) || '_thumbnail_id' === $key,
			ARRAY_FILTER_USE_KEY
		);
		ksort( $meta );
		return md5( (string) wp_json_encode( array( $post->post_title, $post->post_content, $meta, wp_get_object_terms( $post->ID, ID::TAX_CATEGORY, array( 'fields' => 'ids' ) ) ) ) );
	}

	/**
	 * Approve (publish) or decline a submission.
	 *
	 * @param int               $post_id  Event.
	 * @param string            $decision approve | reject.
	 * @param list<string>|null $fields   Unused.
	 * @param string            $note     Note.
	 * @param string            $version  Fingerprint seen.
	 */
	public function decideSubmission( int $post_id, string $decision, ?array $fields, string $note, string $version = '' ): string {
		$post = get_post( $post_id );
		if ( ! $post || ID::POST_TYPE !== $post->post_type || 'pending' !== $post->post_status ) {
			return __( 'That event was already handled.', 'favr-events' );
		}
		if ( ! current_user_can( 'publish_post', $post_id ) && ! current_user_can( 'edit_post', $post_id ) ) {
			return __( 'You can’t publish that event.', 'favr-events' );
		}
		if ( self::fingerprint( $post ) !== $version ) {
			return __( 'That event was edited while you were reviewing it, so nothing changed. Please look again.', 'favr-events' );
		}
		$author = (int) $post->post_author;
		if ( 'approve' === $decision ) {
			delete_post_meta( $post_id, self::DECLINED );
			wp_update_post(
				array(
					'ID'          => $post_id,
					'post_status' => 'publish',
				)
			);
			PendingChanges::log( $post_id, 'approved', array(), get_current_user_id(), $note );
			Notifier::decided( $post_id, $author, 'event_approved', $note );
			/* translators: %s: event. */
			return sprintf( __( 'Published %s.', 'favr-events' ), get_the_title( $post_id ) );
		}
		update_post_meta( $post_id, self::DECLINED, '' !== $note ? $note : '1' );
		wp_update_post(
			array(
				'ID'          => $post_id,
				'post_status' => 'draft',
			)
		);
		PendingChanges::log( $post_id, 'rejected', array(), get_current_user_id(), $note );
		Notifier::decided( $post_id, $author, 'event_declined', $note );
		/* translators: %s: event. */
		return sprintf( __( 'Declined %s.', 'favr-events' ), get_the_title( $post_id ) );
	}

	/**
	 * Published events with proposals.
	 *
	 * @return list<int>
	 */
	public static function withChanges(): array {
		return array_map(
			'intval',
			get_posts(
				array(
					'post_type'      => ID::POST_TYPE,
					'post_status'    => array( 'publish', 'future' ),
					'posts_per_page' => 100,
					'fields'         => 'ids',
					'meta_key'       => PendingChanges::META, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- rarely set.
				)
			)
		);
	}

	/**
	 * Proposal items.
	 *
	 * @return list<array<string, mixed>>
	 */
	public function changes(): array {
		$items = array();
		foreach ( self::withChanges() as $post_id ) {
			$pending = PendingChanges::get( $post_id );
			if ( ! $pending ) {
				continue;
			}
			$rows   = array();
			$fields = array();
			$time   = 0;
			$users  = array();
			foreach ( $pending as $id => $change ) {
				$fields[ $id ] = Items::label( (string) $id );
				$rows[]        = array(
					'label' => $fields[ $id ],
					'old'   => Items::display( (string) $id, Items::current( $post_id, (string) $id ) ),
					'new'   => Items::display( (string) $id, $change['new'] ),
				);
				$time          = max( $time, (int) $change['time'] );
				$users[]       = (int) $change['user'];
			}
			$names   = array_filter( array_map( static fn( int $u ): string => (string) ( get_userdata( $u )->display_name ?? '' ), array_unique( $users ) ) );
			$items[] = array(
				'id'       => $post_id,
				'title'    => get_the_title( $post_id ),
				/* translators: %s: names. */
				'subtitle' => sprintf( __( 'suggested by %s', 'favr-events' ), implode( ', ', $names ) ),
				'edit_url' => (string) get_edit_post_link( $post_id, 'raw' ),
				'time'     => $time,
				'details'  => Inbox::diff( $rows ),
				'fields'   => $fields,
				'version'  => md5( (string) wp_json_encode( $pending ) ),
			);
		}
		return $items;
	}

	/**
	 * Approve or reject proposals.
	 *
	 * @param int               $post_id  Event.
	 * @param string            $decision approve | reject.
	 * @param list<string>|null $fields   Ticked fields or null for all.
	 * @param string            $note     Note.
	 * @param string            $version  Fingerprint seen.
	 */
	public function decideChanges( int $post_id, string $decision, ?array $fields, string $note, string $version = '' ): string {
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return __( 'You can’t edit that event.', 'favr-events' );
		}
		if ( md5( (string) wp_json_encode( PendingChanges::get( $post_id ) ) ) !== $version ) {
			return __( 'Those suggestions changed while you were reviewing them, so nothing was applied. Please look again.', 'favr-events' );
		}
		if ( 'approve' === $decision && is_array( $fields ) && ! $fields ) {
			return __( 'Nothing was selected, so nothing changed.', 'favr-events' );
		}
		$taken = PendingChanges::take( $post_id, 'approve' === $decision ? $fields : null );
		if ( ! $taken ) {
			return __( 'Those changes were already handled.', 'favr-events' );
		}
		$by_user = array();
		foreach ( $taken as $id => $change ) {
			if ( 'approve' === $decision && Items::NONE !== Items::access( (string) $id ) ) {
				Items::apply( $post_id, (string) $id, $change['new'] );
			}
			$by_user[ (int) $change['user'] ] = true;
		}
		Event::reindex( $post_id );
		PendingChanges::log( $post_id, 'approve' === $decision ? 'approved' : 'rejected', array_map( 'strval', array_keys( $taken ) ), get_current_user_id(), $note );
		foreach ( array_keys( $by_user ) as $user_id ) {
			Notifier::decided( $post_id, $user_id, 'approve' === $decision ? 'event_changes_approved' : 'event_changes_rejected', $note );
		}
		return sprintf(
			/* translators: 1: count, 2: event. */
			'approve' === $decision ? _n( 'Approved %1$d change to %2$s.', 'Approved %1$d changes to %2$s.', count( $taken ), 'favr-events' ) : _n( 'Rejected %1$d change to %2$s.', 'Rejected %1$d changes to %2$s.', count( $taken ), 'favr-events' ),
			count( $taken ),
			get_the_title( $post_id )
		);
	}
}
