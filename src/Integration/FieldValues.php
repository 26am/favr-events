<?php
/**
 * Event values for page builders (Elementor dynamic tags, block bindings).
 *
 * @package FavrEvents
 */

declare(strict_types=1);

namespace FavrEvents\Integration;

use FavrEvents\Frontend\Format;
use FavrEvents\Frontend\Ical;
use FavrEvents\Frontend\Single;
use FavrEvents\Model\Event;
use FavrEvents\Schema\Identifiers as ID;

/**
 * One list of event values shared by every builder integration. Dates refer to the occurrence
 * being viewed (?occurrence=) or the next one.
 */
final class FieldValues {

	/**
	 * Text values.
	 *
	 * @return array<string, string>
	 */
	public static function textOptions(): array {
		return array(
			'title'           => __( 'Event name', 'favr-events' ),
			'when'            => __( 'Date and time', 'favr-events' ),
			'date'            => __( 'Date', 'favr-events' ),
			'time'            => __( 'Time', 'favr-events' ),
			'repeats'         => __( 'Repeats (e.g. "Every second Tuesday")', 'favr-events' ),
			'where'           => __( 'Where (venue, city / Online)', 'favr-events' ),
			'venue'           => __( 'Venue', 'favr-events' ),
			'address'         => __( 'Address', 'favr-events' ),
			'cost'            => __( 'Cost', 'favr-events' ),
			'host'            => __( 'Host / organizer', 'favr-events' ),
			'categories'      => __( 'Categories', 'favr-events' ),
			'organizer_email' => __( 'Organizer email', 'favr-events' ),
			'organizer_phone' => __( 'Organizer phone', 'favr-events' ),
		);
	}

	/**
	 * Link values.
	 *
	 * @return array<string, string>
	 */
	public static function urlOptions(): array {
		return array(
			'registration' => __( 'Registration / tickets', 'favr-events' ),
			'online'       => __( 'Online link', 'favr-events' ),
			'map'          => __( 'Map & directions', 'favr-events' ),
			'google'       => __( 'Add to Google Calendar', 'favr-events' ),
			'ics'          => __( 'Download .ics (Apple / Outlook)', 'favr-events' ),
			'host'         => __( 'Host business page', 'favr-events' ),
			'organizer'    => __( 'Organizer website', 'favr-events' ),
			'event'        => __( 'Event page', 'favr-events' ),
		);
	}

	/**
	 * The event for a context (published or readable, not password-locked).
	 *
	 * @param int $post_id Post id (0 = current).
	 */
	public static function event( int $post_id = 0 ): ?Event {
		$event = Event::find( $post_id ?: (int) get_the_ID() );
		if ( ! $event || post_password_required( $event->post() ) ) {
			return null;
		}
		return 'publish' === $event->post()->post_status || current_user_can( 'read_post', $event->id() ) ? $event : null;
	}

	/**
	 * Plain text.
	 *
	 * @param Event  $event Event.
	 * @param string $key   Key.
	 */
	public static function text( Event $event, string $key ): string {
		$occurrence = Single::occurrence( $event );
		switch ( $key ) {
			case 'title':
				return wp_specialchars_decode( $event->title(), ENT_QUOTES );
			case 'when':
				return $occurrence ? Format::when( $event, $occurrence['start'], $occurrence['end'] ) : '';
			case 'date':
				return $occurrence ? (string) wp_date( (string) get_option( 'date_format' ), $occurrence['start']->getTimestamp() ) : '';
			case 'time':
				if ( ! $occurrence ) {
					return '';
				}
				return $event->isAllDay() ? __( 'All day', 'favr-events' ) : wp_date( (string) get_option( 'time_format' ), $occurrence['start']->getTimestamp() ) . ' – ' . wp_date( (string) get_option( 'time_format' ), $occurrence['end']->getTimestamp() );
			case 'repeats':
				return Format::rule( $event );
			case 'where':
				return Format::where( $event );
			case 'address':
				return implode( ', ', $event->addressLines() );
			case 'host':
				return wp_specialchars_decode( $event->organizerName(), ENT_QUOTES );
			case 'categories':
				$terms = get_the_terms( $event->post(), ID::TAX_CATEGORY );
				return $terms && ! is_wp_error( $terms ) ? implode( ', ', wp_list_pluck( $terms, 'name' ) ) : '';
		}
		return in_array( $key, array( 'venue', 'cost', 'organizer_email', 'organizer_phone' ), true ) ? $event->text( $key ) : '';
	}

	/**
	 * Link.
	 *
	 * @param Event  $event Event.
	 * @param string $key   Key.
	 */
	public static function url( Event $event, string $key ): string {
		$occurrence = Single::occurrence( $event );
		switch ( $key ) {
			case 'registration':
				return $event->text( 'registration_url' );
			case 'online':
				return 'in_person' !== $event->attendance() ? $event->text( 'online_url' ) : '';
			case 'map':
				return $event->hasPlace() ? $event->mapUrl() : '';
			case 'google':
				return $occurrence ? Ical::googleUrl( $event, $occurrence['start'], $occurrence['end'] ) : '';
			case 'ics':
				return add_query_arg( 'ics', $occurrence ? $occurrence['start']->format( 'Y-m-d' ) : '1', (string) get_permalink( $event->post() ) );
			case 'host':
				return $event->hostId() ? (string) get_permalink( $event->hostId() ) : '';
			case 'organizer':
				return $event->text( 'organizer_url' );
			case 'event':
				return (string) get_permalink( $event->post() );
		}
		return '';
	}
}
