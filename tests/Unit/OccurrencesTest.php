<?php
/**
 * Recurrence expansion.
 *
 * @package FavrEvents
 */

declare(strict_types=1);

namespace FavrEvents\Tests\Unit;

use FavrEvents\Model\Occurrences;
use PHPUnit\Framework\TestCase as Base;

final class OccurrencesTest extends Base {

	private \DateTimeZone $tz;

	protected function setUp(): void {
		$this->tz = new \DateTimeZone( 'America/Chicago' );
	}

	private function d( string $s ): \DateTimeImmutable {
		return new \DateTimeImmutable( $s, $this->tz );
	}

	/** @param list<array{start: \DateTimeImmutable, end: \DateTimeImmutable}> $o */
	private function starts( array $o ): array {
		return array_map( static fn( array $x ): string => $x['start']->format( 'Y-m-d H:i' ), $o );
	}

	public function test_single_event_in_and_out_of_window(): void {
		$o = Occurrences::between( $this->d( '2026-10-01 18:00' ), $this->d( '2026-10-01 20:00' ), 'none', 1, null, $this->d( '2026-10-01' ), $this->d( '2026-11-01' ) );
		$this->assertSame( array( '2026-10-01 18:00' ), $this->starts( $o ) );
		$o = Occurrences::between( $this->d( '2026-10-01 18:00' ), $this->d( '2026-10-01 20:00' ), 'none', 1, null, $this->d( '2026-11-01' ), $this->d( '2026-12-01' ) );
		$this->assertSame( array(), $o );
	}

	public function test_multi_day_event_overlapping_window_start_is_included(): void {
		$o = Occurrences::between( $this->d( '2026-09-30 09:00' ), $this->d( '2026-10-02 17:00' ), 'none', 1, null, $this->d( '2026-10-01' ), $this->d( '2026-11-01' ) );
		$this->assertCount( 1, $o );
	}

	public function test_weekly_until_is_inclusive_and_keeps_wall_clock_across_dst(): void {
		$o = Occurrences::between( $this->d( '2026-10-20 12:00' ), $this->d( '2026-10-20 13:00' ), 'weekly', 1, $this->d( '2026-11-10' ), $this->d( '2026-10-01' ), $this->d( '2027-01-01' ) );
		$this->assertSame( array( '2026-10-20 12:00', '2026-10-27 12:00', '2026-11-03 12:00', '2026-11-10 12:00' ), $this->starts( $o ) );
		$this->assertSame( '13:00', $o[2]['end']->format( 'H:i' ), 'one-hour duration after the DST change' );
	}

	public function test_biweekly(): void {
		$o = Occurrences::between( $this->d( '2026-10-05 08:00' ), $this->d( '2026-10-05 09:00' ), 'weekly', 2, null, $this->d( '2026-10-01' ), $this->d( '2026-11-15' ) );
		$this->assertSame( array( '2026-10-05 08:00', '2026-10-19 08:00', '2026-11-02 08:00' ), $this->starts( $o ) );
	}

	public function test_monthly_date_skips_short_months(): void {
		$o = Occurrences::between( $this->d( '2027-01-31 10:00' ), $this->d( '2027-01-31 11:00' ), 'monthly_date', 1, null, $this->d( '2027-01-01' ), $this->d( '2027-06-01' ) );
		$this->assertSame( array( '2027-01-31 10:00', '2027-03-31 10:00', '2027-05-31 10:00' ), $this->starts( $o ) );
	}

	public function test_second_tuesday_and_last_friday(): void {
		$o = Occurrences::between( $this->d( '2026-10-13 07:30' ), $this->d( '2026-10-13 09:00' ), 'monthly_nth', 1, null, $this->d( '2026-10-01' ), $this->d( '2027-01-01' ) );
		$this->assertSame( array( '2026-10-13 07:30', '2026-11-10 07:30', '2026-12-08 07:30' ), $this->starts( $o ) );

		$o = Occurrences::between( $this->d( '2026-10-30 17:00' ), $this->d( '2026-10-30 19:00' ), 'monthly_nth', 1, null, $this->d( '2026-10-01' ), $this->d( '2027-01-01' ) );
		$this->assertSame( array( '2026-10-30 17:00', '2026-11-27 17:00', '2026-12-25 17:00' ), $this->starts( $o ) );
	}

	public function test_last_start_and_open_ended(): void {
		$this->assertNull( Occurrences::lastStart( $this->d( '2026-10-01 09:00' ), 'weekly', 1, null ) );
		$last = Occurrences::lastStart( $this->d( '2026-10-01 09:00' ), 'weekly', 1, $this->d( '2026-10-20' ) );
		$this->assertSame( '2026-10-15 09:00', $last ? $last->format( 'Y-m-d H:i' ) : '' );
		$this->assertSame( '2026-10-01', Occurrences::lastStart( $this->d( '2026-10-01 09:00' ), 'none', 1, null )->format( 'Y-m-d' ) );
	}

	public function test_limit(): void {
		$o = Occurrences::between( $this->d( '2026-01-01 09:00' ), $this->d( '2026-01-01 10:00' ), 'weekly', 1, null, $this->d( '2026-01-01' ), $this->d( '2030-01-01' ), 5 );
		$this->assertCount( 5, $o );
	}

	public function test_old_repeating_events_still_produce_occurrences(): void {
		$o = Occurrences::between( $this->d( '2012-01-06 07:30' ), $this->d( '2012-01-06 08:30' ), 'weekly', 1, null, $this->d( '2026-10-01' ), $this->d( '2026-10-15' ) );
		$this->assertSame( array( '2026-10-02 07:30', '2026-10-09 07:30' ), $this->starts( $o ) );

		$o = Occurrences::between( $this->d( '2010-03-09 12:00' ), $this->d( '2010-03-09 13:00' ), 'monthly_nth', 1, null, $this->d( '2026-10-01' ), $this->d( '2026-11-01' ) );
		$this->assertSame( array( '2026-10-13 12:00' ), $this->starts( $o ), 'second Tuesday, 16 years on' );

		$last = Occurrences::lastStart( $this->d( '2012-01-06 07:30' ), 'weekly', 2, $this->d( '2026-10-10' ) );
		$this->assertSame( '2026-10-09', $last ? $last->format( 'Y-m-d' ) : '' ); // 5,390 days = 385 fortnights.
	}

	public function test_long_event_overlapping_window_start_after_skip(): void {
		// Three-day event every week; the one starting Sunday Sep 27 overlaps Oct 1.
		$o = Occurrences::between( $this->d( '2020-01-05 09:00' ), $this->d( '2020-01-08 09:00' ), 'weekly', 1, null, $this->d( '2026-09-29' ), $this->d( '2026-10-05' ) );
		$this->assertSame( '2026-09-27 09:00', $o[0]['start']->format( 'Y-m-d H:i' ) );
	}
}
