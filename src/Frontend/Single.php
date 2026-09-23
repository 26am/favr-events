<?php
/**
 * Event pages: the details panel above the description.
 *
 * @package FavrEvents
 */

declare(strict_types=1);

namespace FavrEvents\Frontend;

use FavrEvents\Model\Event;
use FavrEvents\Schema\Identifiers as ID;
use FavrEvents\Support\Request;

/**
 * Works with any theme: the theme renders the title and image as usual, and the details
 * (when, where, tickets, add to calendar, host) are added to the content.
 */
final class Single {

	/** Hook. */
	public function hook(): void {
		add_filter( 'the_content', array( $this, 'content' ), 8 );
	}

	/**
	 * The occurrence being viewed: ?occurrence=Y-m-d when it matches, else the next one.
	 *
	 * @param Event $event Event.
	 * @return array{start: \DateTimeImmutable, end: \DateTimeImmutable}|null
	 */
	public static function occurrence( Event $event ): ?array {
		$day = sanitize_text_field( Request::get( 'occurrence' ) );
		if ( $event->repeats() && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $day ) ) {
			$from = \DateTimeImmutable::createFromFormat( '!Y-m-d', $day, wp_timezone() );
			if ( $from ) {
				$found = $event->occurrences( $from, $from->modify( '+1 day' ), 1 );
				if ( $found && $found[0]['start']->format( 'Y-m-d' ) === $day ) {
					return $found[0];
				}
			}
		}
		return $event->nextOccurrence();
	}

	/**
	 * Add the details panel.
	 *
	 * @param string $content Content.
	 */
	public function content( string $content ): string {
		if ( ! is_singular( ID::POST_TYPE ) || ! in_the_loop() || ! is_main_query() || get_the_ID() !== get_queried_object_id() ) {
			return $content;
		}
		$event = Event::find( (int) get_the_ID() );
		if ( ! $event || post_password_required( $event->post() ) ) {
			return $content; // Nothing about a protected event (times, place, online link) before the password.
		}
		wp_enqueue_style( 'favr-events' );
		$occurrence = self::occurrence( $event );
		$details    = View::render(
			'single-details',
			array(
				'event'      => $event,
				'occurrence' => $occurrence,
				'google'     => $occurrence ? Ical::googleUrl( $event, $occurrence['start'], $occurrence['end'] ) : '',
				'ics'        => add_query_arg( 'ics', $occurrence ? $occurrence['start']->format( 'Y-m-d' ) : '1', (string) get_permalink( $event->post() ) ),
			)
		);
		$after      = View::render( 'single-after', array( 'event' => $event ) );
		return $details . '<div class="favr-ev-description">' . $content . '</div>' . $after;
	}
}
