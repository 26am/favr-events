<?php
/**
 * Human-readable dates and links for occurrences.
 *
 * @package FavrEvents
 */

declare(strict_types=1);

namespace FavrEvents\Frontend;

use FavrEvents\Model\Event;

/**
 * Formatting helpers shared by templates, feeds and emails.
 */
final class Format {

	/**
	 * "Tue, Oct 13 · 7:30 – 9:00 AM" style label.
	 *
	 * @param Event              $event Event.
	 * @param \DateTimeImmutable $start Start.
	 * @param \DateTimeImmutable $end   End.
	 */
	public static function when( Event $event, \DateTimeImmutable $start, \DateTimeImmutable $end ): string {
		$date_format = (string) get_option( 'date_format', 'F j, Y' );
		$time_format = (string) get_option( 'time_format', 'g:i a' );
		$same_day    = $start->format( 'Y-m-d' ) === $end->format( 'Y-m-d' );
		$day         = wp_date( 'l, ' . $date_format, $start->getTimestamp() );

		if ( $event->isAllDay() ) {
			return $same_day
				/* translators: %s: date. */
				? sprintf( __( '%s (all day)', 'favr-events' ), $day )
				: $day . ' – ' . wp_date( 'l, ' . $date_format, $end->getTimestamp() );
		}
		if ( $same_day ) {
			$times = wp_date( $time_format, $start->getTimestamp() );
			if ( $end > $start ) {
				$times .= ' – ' . wp_date( $time_format, $end->getTimestamp() );
			}
			return $day . ' · ' . $times;
		}
		return wp_date( $date_format . ' ' . $time_format, $start->getTimestamp() ) . ' – ' . wp_date( $date_format . ' ' . $time_format, $end->getTimestamp() );
	}

	/**
	 * Time-only label for calendar cells ("All day" or "7:30 AM").
	 *
	 * @param Event              $event Event.
	 * @param \DateTimeImmutable $start Start.
	 */
	public static function time( Event $event, \DateTimeImmutable $start ): string {
		return $event->isAllDay() ? __( 'All day', 'favr-events' ) : wp_date( (string) get_option( 'time_format', 'g:i a' ), $start->getTimestamp() );
	}

	/**
	 * Link to an event, pinned to one occurrence of a repeating event.
	 *
	 * @param Event              $event Event.
	 * @param \DateTimeImmutable $start Occurrence start.
	 */
	public static function url( Event $event, \DateTimeImmutable $start ): string {
		$url = (string) get_permalink( $event->post() );
		return $event->repeats() ? add_query_arg( 'occurrence', $start->format( 'Y-m-d' ), $url ) : $url;
	}

	/**
	 * Plain-language repeat rule ("Every 2nd Tuesday of the month").
	 *
	 * @param Event $event Event.
	 */
	public static function rule( Event $event ): string {
		$start = $event->start();
		if ( ! $start || ! $event->repeats() ) {
			return '';
		}
		$weekday  = wp_date( 'l', $start->getTimestamp() );
		$interval = $event->interval();
		switch ( $event->rule() ) {
			case 'weekly':
				/* translators: 1: weekday, 2: number of weeks. */
				$text = 1 === $interval ? sprintf( __( 'Every %s', 'favr-events' ), $weekday ) : sprintf( __( 'Every %2$d weeks on %1$s', 'favr-events' ), $weekday, $interval );
				break;
			case 'monthly_date':
				/* translators: 1: day of month, 2: number of months. */
				$text = 1 === $interval ? sprintf( __( 'Monthly on day %1$d', 'favr-events' ), (int) $start->format( 'j' ) ) : sprintf( __( 'Every %2$d months on day %1$d', 'favr-events' ), (int) $start->format( 'j' ), $interval );
				break;
			default:
				$positions = array(
					1 => __( 'first', 'favr-events' ),
					2 => __( 'second', 'favr-events' ),
					3 => __( 'third', 'favr-events' ),
					4 => __( 'fourth', 'favr-events' ),
				);
				$day       = (int) $start->format( 'j' );
				$position  = $day > 28 ? __( 'last', 'favr-events' ) : $positions[ (int) ceil( $day / 7 ) ];
				/* translators: 1: first/second/…/last, 2: weekday, 3: number of months. */
				$text = 1 === $interval ? sprintf( __( 'Every %1$s %2$s of the month', 'favr-events' ), $position, $weekday ) : sprintf( __( 'Every %3$d months on the %1$s %2$s', 'favr-events' ), $position, $weekday, $interval );
		}
		$until = $event->until();
		if ( $until ) {
			/* translators: 1: rule, 2: date. */
			$text = sprintf( __( '%1$s until %2$s', 'favr-events' ), $text, wp_date( (string) get_option( 'date_format' ), $until->getTimestamp() ) );
		}
		return $text;
	}

	/**
	 * Where, in one line.
	 *
	 * @param Event $event Event.
	 */
	public static function where( Event $event ): string {
		$parts = array();
		if ( $event->hasPlace() ) {
			$parts[] = implode( ', ', array_filter( array( $event->text( 'venue' ), $event->text( 'city' ) ) ) );
		}
		if ( 'in_person' !== $event->attendance() ) {
			$parts[] = __( 'Online', 'favr-events' );
		}
		return implode( ' · ', array_filter( $parts ) );
	}
}
