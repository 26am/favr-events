<?php
/**
 * An event and its schedule.
 *
 * @package FavrEvents
 */

declare(strict_types=1);

namespace FavrEvents\Model;

use FavrEvents\Schema\Identifiers as ID;

/**
 * Wraps a favr_event post. Dates are stored as local (site time zone) strings: start_date and
 * end_date as Y-m-d, start_time and end_time as H:i.
 */
final class Event {

	/**
	 * Post.
	 *
	 * @var \WP_Post
	 */
	private \WP_Post $post;

	/**
	 * Constructor.
	 *
	 * @param \WP_Post $post Post.
	 */
	public function __construct( \WP_Post $post ) {
		$this->post = $post;
	}

	/**
	 * Load by id.
	 *
	 * @param int $id Post id.
	 */
	public static function find( int $id ): ?self {
		$post = get_post( $id );
		return $post && ID::POST_TYPE === $post->post_type ? new self( $post ) : null;
	}

	/** Id. */
	public function id(): int {
		return (int) $this->post->ID;
	}

	/** Post. */
	public function post(): \WP_Post {
		return $this->post;
	}

	/** Title. */
	public function title(): string {
		return get_the_title( $this->post );
	}

	/**
	 * Field as a trimmed string (only real stored values; no registered defaults).
	 *
	 * @param string $id Field id.
	 */
	public function text( string $id ): string {
		if ( ! metadata_exists( 'post', $this->id(), ID::meta( $id ) ) ) {
			return '';
		}
		$value = get_post_meta( $this->id(), ID::meta( $id ), true );
		return is_scalar( $value ) ? trim( (string) $value ) : '';
	}

	/** All day? */
	public function isAllDay(): bool {
		return '1' === $this->text( 'all_day' );
	}

	/** Repeat rule. */
	public function rule(): string {
		$rule = $this->text( 'repeat' );
		return in_array( $rule, Occurrences::RULES, true ) ? $rule : 'none';
	}

	/** Repeat interval. */
	public function interval(): int {
		return max( 1, min( 12, (int) $this->text( 'repeat_interval' ) ?: 1 ) );
	}

	/** Until (local midnight), or null. */
	public function until(): ?\DateTimeImmutable {
		$until = $this->text( 'repeat_until' );
		return '' !== $until ? self::date( $until, '00:00' ) : null;
	}

	/** First start, or null when no valid start date. */
	public function start(): ?\DateTimeImmutable {
		return self::bounds( $this->text( 'start_date' ), $this->text( 'start_time' ), $this->text( 'end_date' ), $this->text( 'end_time' ), $this->isAllDay() )[0];
	}

	/** First end, or null. */
	public function end(): ?\DateTimeImmutable {
		return self::bounds( $this->text( 'start_date' ), $this->text( 'start_time' ), $this->text( 'end_date' ), $this->text( 'end_time' ), $this->isAllDay() )[1];
	}

	/**
	 * Pure: first start and end from stored strings. Missing end time = start + 1 hour (or the
	 * end of day for all-day events); an end before the start is clamped to the start.
	 *
	 * @param string $start_date Y-m-d.
	 * @param string $start_time H:i or ''.
	 * @param string $end_date   Y-m-d or ''.
	 * @param string $end_time   H:i or ''.
	 * @param bool   $all_day    All day.
	 * @return array{0: ?\DateTimeImmutable, 1: ?\DateTimeImmutable}
	 */
	public static function bounds( string $start_date, string $start_time, string $end_date, string $end_time, bool $all_day ): array {
		$start = self::date( $start_date, $all_day ? '00:00' : ( '' !== $start_time ? $start_time : '00:00' ) );
		if ( ! $start ) {
			return array( null, null );
		}
		$end_day = '' !== $end_date ? $end_date : $start_date;
		if ( $all_day ) {
			$end = self::date( $end_day, '23:59' );
		} elseif ( '' !== $end_time ) {
			$end = self::date( $end_day, $end_time );
		} elseif ( '' !== $end_date && $end_date !== $start_date ) {
			$end = self::date( $end_day, '' !== $start_time ? $start_time : '23:59' );
		} else {
			$end = $start->modify( '+1 hour' );
		}
		if ( ! $end || $end < $start ) {
			$end = $start;
		}
		return array( $start, $end );
	}

	/**
	 * Local date + time.
	 *
	 * @param string $date Y-m-d.
	 * @param string $time H:i.
	 */
	private static function date( string $date, string $time ): ?\DateTimeImmutable {
		$tz  = function_exists( 'wp_timezone' ) ? wp_timezone() : new \DateTimeZone( 'UTC' );
		$out = \DateTimeImmutable::createFromFormat( '!Y-m-d H:i', $date . ' ' . $time, $tz );
		return false === $out ? null : $out;
	}

	/**
	 * Occurrences in a window.
	 *
	 * @param \DateTimeImmutable $from  From.
	 * @param \DateTimeImmutable $to    To.
	 * @param int                $limit Max.
	 * @return list<array{start: \DateTimeImmutable, end: \DateTimeImmutable}>
	 */
	public function occurrences( \DateTimeImmutable $from, \DateTimeImmutable $to, int $limit = 100 ): array {
		$start = $this->start();
		$end   = $this->end();
		if ( ! $start || ! $end ) {
			return array();
		}
		return Occurrences::between( $start, $end, $this->rule(), $this->interval(), $this->until(), $from, $to, $limit );
	}

	/**
	 * The next (or current) occurrence from now, else the most recent one.
	 *
	 * @return array{start: \DateTimeImmutable, end: \DateTimeImmutable}|null
	 */
	public function nextOccurrence(): ?array {
		$now  = new \DateTimeImmutable( 'now', wp_timezone() );
		$next = $this->occurrences( $now, $now->modify( '+5 years' ), 1 );
		if ( $next ) {
			return $next[0];
		}
		$start = $this->start();
		$last  = $start ? Occurrences::lastStart( $start, $this->rule(), $this->interval(), $this->until() ) : null;
		$end   = $this->end();
		if ( ! $last || ! $start || ! $end ) {
			return null;
		}
		return array(
			'start' => $last,
			'end'   => $last->modify( '+' . ( $end->getTimestamp() - $start->getTimestamp() ) . ' seconds' ),
		);
	}

	/** Recurs? */
	public function repeats(): bool {
		return 'none' !== $this->rule();
	}

	/** Attendance mode: in_person | online | hybrid. */
	public function attendance(): string {
		$mode = $this->text( 'attendance' );
		return in_array( $mode, array( 'in_person', 'online', 'hybrid' ), true ) ? $mode : 'in_person';
	}

	/** Has a physical place? */
	public function hasPlace(): bool {
		return 'online' !== $this->attendance() && ( '' !== $this->text( 'venue' ) || '' !== $this->text( 'address' ) || '' !== $this->text( 'city' ) );
	}

	/**
	 * Address lines.
	 *
	 * @return list<string>
	 */
	public function addressLines(): array {
		$city = trim( implode( ', ', array_filter( array( $this->text( 'city' ), trim( $this->text( 'state' ) . ' ' . $this->text( 'postal_code' ) ) ) ) ) );
		return array_values( array_filter( array( $this->text( 'address' ), $city ) ) );
	}

	/** Map search URL. */
	public function mapUrl(): string {
		$query = implode( ', ', array_filter( array_merge( array( $this->text( 'venue' ) ), $this->addressLines() ) ) );
		return '' !== $query ? 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode( $query ) : '';
	}

	/** Host listing id, when it is a published directory business. */
	public function hostId(): int {
		$id = (int) $this->text( 'host_business' );
		return $id > 0 && ID::DIRECTORY_TYPE === get_post_type( $id ) && 'publish' === get_post_status( $id ) ? $id : 0;
	}

	/** Organizer display name (host business, else organizer). */
	public function organizerName(): string {
		$host = $this->hostId();
		return $host ? get_the_title( $host ) : $this->text( 'organizer_name' );
	}

	/** Featured? */
	public function isFeatured(): bool {
		return '1' === $this->text( 'featured' );
	}

	/**
	 * Recompute the query index (first start / last end as UTC timestamps).
	 *
	 * @param int $post_id Event.
	 */
	public static function reindex( int $post_id ): void {
		$event = self::find( $post_id );
		$start = $event ? $event->start() : null;
		$end   = $event ? $event->end() : null;
		if ( ! $event || ! $start || ! $end ) {
			delete_post_meta( $post_id, ID::META_FIRST );
			delete_post_meta( $post_id, ID::META_LAST );
			return;
		}
		$last = Occurrences::lastStart( $start, $event->rule(), $event->interval(), $event->until() );
		update_post_meta( $post_id, ID::META_FIRST, $start->getTimestamp() );
		update_post_meta( $post_id, ID::META_LAST, $last ? $last->getTimestamp() + ( $end->getTimestamp() - $start->getTimestamp() ) : ID::OPEN_ENDED );
	}
}
