<?php
/**
 * An event saved with a date and no start time.
 *
 * @package FavrEvents
 */

declare(strict_types=1);

namespace FavrEvents\Tests\Unit;

use Brain\Monkey\Functions;
use FavrEvents\Frontend\Format;
use FavrEvents\Model\Event;

/**
 * Nobody means "12:00 am" by leaving the time empty, so such an event is an all-day event
 * everywhere it is shown: the list, the event page, the Approvals card and the calendar feeds
 * all ask Event::isAllDay().
 */
final class EventNoStartTimeTest extends TestCase {

	/**
	 * An event whose stored fields are exactly the given ones.
	 *
	 * @param array<string, string> $fields Field id => stored value.
	 */
	private function event( array $fields ): Event {
		$meta = array();
		foreach ( $fields as $id => $value ) {
			$meta[ 'favr_event_' . $id ] = $value;
		}
		Functions\when( 'metadata_exists' )->alias( static fn( string $type, int $id, string $key ): bool => isset( $meta[ $key ] ) );
		Functions\when( 'get_post_meta' )->alias( static fn( int $id, string $key ) => $meta[ $key ] ?? '' );
		Functions\when( 'wp_timezone' )->alias( static fn(): \DateTimeZone => new \DateTimeZone( 'America/New_York' ) );

		$post     = new \WP_Post();
		$post->ID = 7;
		return new Event( $post );
	}

	public function test_a_date_without_a_start_time_is_an_all_day_event(): void {
		$event = $this->event( array( 'start_date' => '2026-11-21' ) );

		$this->assertTrue( $event->isAllDay() );
		$this->assertTrue( $event->isAllDayForLackOfATime() );
		$this->assertSame( '2026-11-21 00:00', $event->start()->format( 'Y-m-d H:i' ) );
		$this->assertSame( '2026-11-21 23:59', $event->end()->format( 'Y-m-d H:i' ), 'It lasts the whole day, not until 1:00 am.' );
	}

	public function test_an_end_time_alone_does_not_make_it_a_timed_event(): void {
		$event = $this->event(
			array(
				'start_date' => '2026-11-21',
				'end_time'   => '17:00',
			)
		);

		$this->assertTrue( $event->isAllDay() );
	}

	public function test_an_event_with_a_start_time_keeps_its_time(): void {
		$event = $this->event(
			array(
				'start_date' => '2026-11-21',
				'start_time' => '08:00',
			)
		);

		$this->assertFalse( $event->isAllDay() );
		$this->assertFalse( $event->isAllDayForLackOfATime() );
		$this->assertSame( '2026-11-21 08:00', $event->start()->format( 'Y-m-d H:i' ) );
	}

	public function test_an_event_switched_to_all_day_is_not_one_for_lack_of_a_time(): void {
		$event = $this->event(
			array(
				'start_date' => '2026-11-21',
				'all_day'    => '1',
			)
		);

		$this->assertTrue( $event->isAllDay() );
		$this->assertFalse( $event->isAllDayForLackOfATime() );
	}

	public function test_an_event_without_a_date_is_not_called_all_day(): void {
		$event = $this->event( array() );

		$this->assertFalse( $event->isAllDayForLackOfATime() );
		$this->assertNull( $event->start() );
	}

	/** The event page and the Approvals card print Format::when(): no "12:00 am – 1:00 am". */
	public function test_the_event_page_shows_the_date_only(): void {
		Functions\when( 'get_option' )->alias( static fn( string $name, $fallback = false ) => 'date_format' === $name ? 'F j, Y' : ( 'time_format' === $name ? 'g:i a' : $fallback ) );
		Functions\when( 'wp_date' )->alias( static fn( string $format, int $timestamp ): string => ( new \DateTimeImmutable( '@' . $timestamp ) )->setTimezone( new \DateTimeZone( 'America/New_York' ) )->format( $format ) );

		$event = $this->event( array( 'start_date' => '2026-11-21' ) );
		$when  = Format::when( $event, $event->start(), $event->end() );

		$this->assertSame( 'Saturday, November 21, 2026 (all day)', $when );
		$this->assertSame( 'All day', Format::time( $event, $event->start() ) );
	}
}
