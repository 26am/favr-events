<?php
/**
 * Event date bounds and iCalendar encoding.
 *
 * @package FavrEvents
 */

declare(strict_types=1);

namespace FavrEvents\Tests\Unit;

use FavrEvents\Frontend\Ical;
use FavrEvents\Model\Event;
use PHPUnit\Framework\TestCase as Base;

final class EventAndIcalTest extends Base {

	private function fmt( array $bounds ): array {
		return array_map( static fn( $d ) => $d ? $d->format( 'Y-m-d H:i' ) : null, $bounds );
	}

	public function test_bounds_default_to_one_hour(): void {
		$this->assertSame( array( '2026-10-01 18:00', '2026-10-01 19:00' ), $this->fmt( Event::bounds( '2026-10-01', '18:00', '', '', false ) ) );
	}

	public function test_bounds_with_end_time_and_multi_day(): void {
		$this->assertSame( array( '2026-10-01 18:00', '2026-10-01 20:30' ), $this->fmt( Event::bounds( '2026-10-01', '18:00', '', '20:30', false ) ) );
		$this->assertSame( array( '2026-10-01 09:00', '2026-10-03 17:00' ), $this->fmt( Event::bounds( '2026-10-01', '09:00', '2026-10-03', '17:00', false ) ) );
	}

	public function test_all_day_spans_whole_days(): void {
		$this->assertSame( array( '2026-10-01 00:00', '2026-10-02 23:59' ), $this->fmt( Event::bounds( '2026-10-01', '10:00', '2026-10-02', '', true ) ) );
	}

	public function test_end_before_start_is_clamped_and_bad_dates_are_null(): void {
		$this->assertSame( array( '2026-10-01 18:00', '2026-10-01 18:00' ), $this->fmt( Event::bounds( '2026-10-01', '18:00', '', '09:00', false ) ) );
		$this->assertSame( array( null, null ), $this->fmt( Event::bounds( 'soon', '', '', '', false ) ) );
	}

	public function test_ical_text_escaping(): void {
		$this->assertSame( 'Coffee\\, Tea \; More\\nLine 2 \\\\ done', Ical::text( "Coffee, Tea ; More\nLine 2 \\ done" ) );
	}

	public function test_ical_folding_keeps_utf8_intact(): void {
		$line   = 'DESCRIPTION:' . str_repeat( 'é', 60 );
		$folded = Ical::fold( $line );
		foreach ( explode( "\r\n", $folded ) as $part ) {
			$this->assertLessThanOrEqual( 75, strlen( $part ) );
			$this->assertTrue( mb_check_encoding( $part, 'UTF-8' ) );
		}
		$this->assertSame( $line, str_replace( "\r\n ", '', $folded ) );
	}
}
