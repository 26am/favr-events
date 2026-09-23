<?php
/**
 * The iCalendar feeds and "Add to calendar" links.
 *
 * @package FavrEvents
 */

declare(strict_types=1);

namespace FavrEvents\Frontend;

use FavrEvents\Model\Event;
use FavrEvents\Model\Repository;
use FavrEvents\Schema\Identifiers as ID;

/**
 * Feed: /?favr_events_ical=1 (optionally &category=slug) lists every occurrence from 30 days
 * ago to a year ahead. One event: its permalink with ?ics=Y-m-d (that occurrence) or ?ics=1.
 * Occurrences are written out individually (no RRULE), which every calendar app understands.
 */
final class Ical {

	/** Hook. */
	public function hook(): void {
		add_action( 'template_redirect', array( $this, 'serve' ), 1 );
		add_action( 'wp_head', array( $this, 'discovery' ) );
	}

	/** Feed discovery link on the events page. */
	public function discovery(): void {
		if ( is_page( (int) \FavrEvents\Support\Settings::get( 'events_page' ) ) ) {
			printf( '<link rel="alternate" type="text/calendar" title="%s" href="%s">' . "\n", esc_attr( get_bloginfo( 'name' ) . ' – ' . __( 'Events', 'favr-events' ) ), esc_url( self::feedUrl() ) );
		}
	}

	/** Full feed URL. */
	public static function feedUrl(): string {
		return add_query_arg( ID::QV_ICAL, '1', home_url( '/' ) );
	}

	/** Serve a feed or a single event file. */
	public function serve(): void {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- public, read-only feeds.
		if ( isset( $_GET[ ID::QV_ICAL ] ) ) {
			$now      = new \DateTimeImmutable( 'now', wp_timezone() );
			$category = isset( $_GET['category'] ) ? sanitize_title( wp_unslash( $_GET['category'] ) ) : '';
			$items    = Repository::occurrences(
				$now->modify( '-30 days' ),
				$now->modify( '+1 year' ),
				array(
					'category' => $category,
					'limit'    => 500,
				)
			);
			$this->output( $items, sanitize_file_name( get_bloginfo( 'name' ) . '-events' ) . '.ics', false );
		}
		if ( is_singular( ID::POST_TYPE ) && isset( $_GET['ics'] ) ) {
			$event = Event::find( (int) get_queried_object_id() );
			$day   = sanitize_text_field( wp_unslash( $_GET['ics'] ) );
			if ( $event && 'publish' === get_post_status( $event->post() ) ) {
				$items = array();
				if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $day ) ) {
					$from  = \DateTimeImmutable::createFromFormat( '!Y-m-d', $day, wp_timezone() );
					$found = $from ? $event->occurrences( $from, $from->modify( '+1 day' ), 1 ) : array();
					foreach ( $found as $occurrence ) {
						$items[] = array( 'event' => $event ) + $occurrence;
					}
				}
				if ( ! $items ) {
					$next = $event->nextOccurrence();
					if ( $next ) {
						$items[] = array( 'event' => $event ) + $next;
					}
				}
				$this->output( $items, sanitize_file_name( $event->post()->post_name ) . '.ics', true );
			}
		}
		// phpcs:enable
	}

	/**
	 * Send the calendar and stop.
	 *
	 * @param list<array{event: Event, start: \DateTimeImmutable, end: \DateTimeImmutable}> $items      Occurrences.
	 * @param string                                                                        $filename   File name.
	 * @param bool                                                                          $attachment Download (single) or inline (feed).
	 */
	private function output( array $items, string $filename, bool $attachment ): void {
		nocache_headers();
		header( 'Content-Type: text/calendar; charset=utf-8' );
		header( 'Content-Disposition: ' . ( $attachment ? 'attachment' : 'inline' ) . '; filename="' . $filename . '"' );
		echo self::calendar( $items ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- text/calendar, escaped per RFC 5545.
		exit;
	}

	/**
	 * Build a VCALENDAR.
	 *
	 * @param list<array{event: Event, start: \DateTimeImmutable, end: \DateTimeImmutable}> $items Occurrences.
	 */
	public static function calendar( array $items ): string {
		$host  = (string) wp_parse_url( home_url(), PHP_URL_HOST );
		$lines = array(
			'BEGIN:VCALENDAR',
			'VERSION:2.0',
			'PRODID:-//Favr Sites//Favr Events//EN',
			'CALSCALE:GREGORIAN',
			'METHOD:PUBLISH',
			'X-WR-CALNAME:' . self::text( wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ) . ' – ' . __( 'Events', 'favr-events' ) ),
		);
		$stamp = gmdate( 'Ymd\THis\Z' );
		foreach ( $items as $item ) {
			$event   = $item['event'];
			$lines[] = 'BEGIN:VEVENT';
			$lines[] = 'UID:' . $event->id() . '-' . $item['start']->format( 'Ymd' ) . '@' . $host;
			$lines[] = 'DTSTAMP:' . $stamp;
			if ( $event->isAllDay() ) {
				$lines[] = 'DTSTART;VALUE=DATE:' . $item['start']->format( 'Ymd' );
				$lines[] = 'DTEND;VALUE=DATE:' . $item['end']->modify( '+1 day' )->format( 'Ymd' );
			} else {
				$lines[] = 'DTSTART:' . gmdate( 'Ymd\THis\Z', $item['start']->getTimestamp() );
				$lines[] = 'DTEND:' . gmdate( 'Ymd\THis\Z', $item['end']->getTimestamp() );
			}
			$lines[]  = 'SUMMARY:' . self::text( wp_specialchars_decode( $event->title(), ENT_QUOTES ) );
			$location = self::location( $event );
			if ( '' !== $location ) {
				$lines[] = 'LOCATION:' . self::text( $location );
			}
			$lines[] = 'DESCRIPTION:' . self::text( self::description( $event ) );
			$lines[] = 'URL:' . Format::url( $event, $item['start'] );
			$lines[] = 'END:VEVENT';
		}
		$lines[] = 'END:VCALENDAR';
		return implode( "\r\n", array_map( array( self::class, 'fold' ), $lines ) ) . "\r\n";
	}

	/**
	 * Google Calendar "add event" link.
	 *
	 * @param Event              $event Event.
	 * @param \DateTimeImmutable $start Start.
	 * @param \DateTimeImmutable $end   End.
	 */
	public static function googleUrl( Event $event, \DateTimeImmutable $start, \DateTimeImmutable $end ): string {
		$dates = $event->isAllDay()
			? $start->format( 'Ymd' ) . '/' . $end->modify( '+1 day' )->format( 'Ymd' )
			: gmdate( 'Ymd\THis\Z', $start->getTimestamp() ) . '/' . gmdate( 'Ymd\THis\Z', $end->getTimestamp() );
		return add_query_arg(
			array_map(
				'rawurlencode',
				array(
					'action'   => 'TEMPLATE',
					'text'     => wp_specialchars_decode( $event->title(), ENT_QUOTES ),
					'dates'    => $dates,
					'details'  => mb_substr( self::description( $event ), 0, 1000 ),
					'location' => self::location( $event ),
				)
			),
			'https://calendar.google.com/calendar/render'
		);
	}

	/**
	 * Plain location.
	 *
	 * @param Event $event Event.
	 */
	private static function location( Event $event ): string {
		if ( $event->hasPlace() ) {
			return implode( ', ', array_filter( array_merge( array( $event->text( 'venue' ) ), $event->addressLines() ) ) );
		}
		return $event->text( 'online_url' );
	}

	/**
	 * Plain description (excerpt + link).
	 *
	 * @param Event $event Event.
	 */
	private static function description( Event $event ): string {
		$text = wp_strip_all_tags( has_excerpt( $event->post() ) ? $event->post()->post_excerpt : wp_trim_words( strip_shortcodes( $event->post()->post_content ), 60 ) );
		return trim( html_entity_decode( $text, ENT_QUOTES ) . "\n\n" . get_permalink( $event->post() ) );
	}

	/**
	 * Escape a TEXT value (RFC 5545 §3.3.11).
	 *
	 * @param string $value Value.
	 */
	public static function text( string $value ): string {
		$value = str_replace( array( '\\', ';', ',' ), array( '\\\\', '\;', '\\,' ), $value );
		return str_replace( array( "\r\n", "\r", "\n" ), '\\n', $value );
	}

	/**
	 * Fold lines longer than 75 octets without splitting UTF-8 characters.
	 *
	 * @param string $line Line.
	 */
	public static function fold( string $line ): string {
		if ( strlen( $line ) <= 75 ) {
			return $line;
		}
		$out   = '';
		$chunk = '';
		foreach ( preg_split( '//u', $line, -1, PREG_SPLIT_NO_EMPTY ) as $char ) {
			if ( strlen( $chunk . $char ) > ( '' === $out ? 75 : 74 ) ) {
				$out  .= ( '' === $out ? '' : "\r\n " ) . $chunk;
				$chunk = '';
			}
			$chunk .= $char;
		}
		return $out . "\r\n " . $chunk;
	}
}
