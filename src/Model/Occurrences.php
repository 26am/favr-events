<?php
/**
 * Expanding an event's schedule into dated occurrences.
 *
 * @package FavrEvents
 */

declare(strict_types=1);

namespace FavrEvents\Model;

/**
 * Pure date logic (no WordPress calls), so it is fully unit tested.
 *
 * Rules:
 *  - none:         one occurrence.
 *  - weekly:       every N weeks on the start weekday.
 *  - monthly_date: every N months on the start day of month (skips months without that day).
 *  - monthly_nth:  every N months on the same weekday position, e.g. "2nd Tuesday"; a start on
 *                  the 29th–31st (a 5th weekday) means "last Tuesday".
 * Occurrences keep the first occurrence's duration and local wall-clock time.
 */
final class Occurrences {

	public const RULES = array( 'none', 'weekly', 'monthly_date', 'monthly_nth' );

	/** Hard cap so a bad rule can never loop forever. */
	private const MAX = 500;

	/**
	 * Occurrences overlapping [$from, $to).
	 *
	 * @param \DateTimeImmutable      $start    First start (local time zone).
	 * @param \DateTimeImmutable      $end      First end.
	 * @param string                  $rule     One of RULES.
	 * @param int                     $interval Every N weeks/months (>= 1).
	 * @param \DateTimeImmutable|null $until    Last date a repeat may start on (inclusive), or null.
	 * @param \DateTimeImmutable      $from     Window start.
	 * @param \DateTimeImmutable      $to       Window end.
	 * @param int                     $limit    Maximum to return.
	 * @return list<array{start: \DateTimeImmutable, end: \DateTimeImmutable}>
	 */
	public static function between( \DateTimeImmutable $start, \DateTimeImmutable $end, string $rule, int $interval, ?\DateTimeImmutable $until, \DateTimeImmutable $from, \DateTimeImmutable $to, int $limit = 100 ): array {
		$interval = max( 1, $interval );
		$duration = max( 0, $end->getTimestamp() - $start->getTimestamp() );
		$out      = array();
		$last_day = $until ? $until->setTime( 23, 59, 59 ) : null;

		foreach ( self::starts( $start, $rule, $interval ) as $i => $occurrence ) {
			if ( $i >= self::MAX || count( $out ) >= $limit || $occurrence >= $to || ( $last_day && $occurrence > $last_day ) ) {
				break;
			}
			$finish = $occurrence->modify( '+' . $duration . ' seconds' );
			if ( $finish > $from || ( 0 === $duration && $occurrence >= $from ) ) {
				$out[] = array(
					'start' => $occurrence,
					'end'   => $finish,
				);
			}
		}
		return $out;
	}

	/**
	 * Start of the last occurrence, or null when it repeats forever.
	 *
	 * @param \DateTimeImmutable      $start    First start.
	 * @param string                  $rule     Rule.
	 * @param int                     $interval Interval.
	 * @param \DateTimeImmutable|null $until    Until date.
	 */
	public static function lastStart( \DateTimeImmutable $start, string $rule, int $interval, ?\DateTimeImmutable $until ): ?\DateTimeImmutable {
		if ( 'none' === $rule || ! in_array( $rule, self::RULES, true ) ) {
			return $start;
		}
		if ( ! $until ) {
			return null;
		}
		$last     = $start;
		$last_day = $until->setTime( 23, 59, 59 );
		foreach ( self::starts( $start, $rule, max( 1, $interval ) ) as $i => $occurrence ) {
			if ( $i >= self::MAX || $occurrence > $last_day ) {
				break;
			}
			$last = $occurrence;
		}
		return $last;
	}

	/**
	 * Candidate starts, in order.
	 *
	 * @param \DateTimeImmutable $start    First start.
	 * @param string             $rule     Rule.
	 * @param int                $interval Interval.
	 * @return \Generator<int, \DateTimeImmutable>
	 */
	private static function starts( \DateTimeImmutable $start, string $rule, int $interval ): \Generator {
		yield $start;
		if ( 'none' === $rule || ! in_array( $rule, self::RULES, true ) ) {
			return;
		}
		$time = array( (int) $start->format( 'G' ), (int) $start->format( 'i' ), (int) $start->format( 's' ) );
		for ( $n = 1; $n <= self::MAX * 2; $n++ ) {
			if ( 'weekly' === $rule ) {
				yield $start->modify( '+' . ( $n * $interval * 7 ) . ' days' );
				continue;
			}
			$month = $start->modify( 'first day of this month' )->modify( '+' . ( $n * $interval ) . ' months' );
			if ( 'monthly_date' === $rule ) {
				$day = (int) $start->format( 'j' );
				if ( $day <= (int) $month->format( 't' ) ) {
					yield $month->setDate( (int) $month->format( 'Y' ), (int) $month->format( 'n' ), $day )->setTime( ...$time );
				}
				continue;
			}
			$candidate = self::nthWeekday( $month, $start );
			if ( $candidate ) {
				yield $candidate->setTime( ...$time );
			}
		}
	}

	/**
	 * Same weekday position as $start (e.g. 2nd Tuesday, or last Friday) in $month.
	 *
	 * @param \DateTimeImmutable $month Any date in the target month.
	 * @param \DateTimeImmutable $start The first occurrence.
	 */
	public static function nthWeekday( \DateTimeImmutable $month, \DateTimeImmutable $start ): ?\DateTimeImmutable {
		$weekday = strtolower( $start->format( 'l' ) );
		$ym      = $month->format( 'Y-m' );
		if ( self::isLastWeek( $start ) ) {
			return new \DateTimeImmutable( 'last ' . $weekday . ' of ' . $ym, $month->getTimezone() );
		}
		$nth       = (int) ceil( (int) $start->format( 'j' ) / 7 );
		$ordinals  = array(
			1 => 'first',
			2 => 'second',
			3 => 'third',
			4 => 'fourth',
		);
		$candidate = new \DateTimeImmutable( $ordinals[ $nth ] . ' ' . $weekday . ' of ' . $ym, $month->getTimezone() );
		return $candidate->format( 'Y-m' ) === $ym ? $candidate : null;
	}

	/**
	 * Whether a date is a 5th weekday of its month (treated as "last").
	 *
	 * @param \DateTimeImmutable $date Date.
	 */
	public static function isLastWeek( \DateTimeImmutable $date ): bool {
		return (int) $date->format( 'j' ) > 28; // A 5th weekday doesn't exist every month: use "last".
	}
}
