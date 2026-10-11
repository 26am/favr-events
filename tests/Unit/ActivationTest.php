<?php
/**
 * Upgrade steps.
 *
 * @package FavrEvents
 */

declare(strict_types=1);

namespace FavrEvents\Tests\Unit;

use Brain\Monkey\Functions;
use FavrEvents\Model\Activation;

final class ActivationTest extends TestCase {

	/**
	 * An event with a date and no start time used to be indexed as a one-hour event at
	 * midnight, so the list filed it under Past from 1:00 am on its own day. It is an all-day
	 * event now, and the events saved before that get their index rebuilt on upgrade.
	 */
	public function test_events_without_a_start_time_are_reindexed_as_all_day(): void {
		$meta    = array(
			'favr_event_start_date' => '2026-11-21',
			'_favr_event_first'     => '1795237200',
			'_favr_event_last'      => '1795240800', // 1:00 am, New York.
		);
		$asked   = array();
		$written = array();

		Functions\when( 'get_posts' )->alias(
			static function ( array $args ) use ( &$asked ): array {
				$asked = $args;
				return array( 7 );
			}
		);
		Functions\when( 'get_post' )->alias(
			static function ( int $id ): \WP_Post {
				$post     = new \WP_Post();
				$post->ID = $id;
				return $post;
			}
		);
		Functions\when( 'metadata_exists' )->alias( static fn( string $type, int $id, string $key ): bool => isset( $meta[ $key ] ) );
		Functions\when( 'get_post_meta' )->alias( static fn( int $id, string $key ) => $meta[ $key ] ?? '' );
		Functions\when( 'wp_timezone' )->alias( static fn(): \DateTimeZone => new \DateTimeZone( 'America/New_York' ) );
		Functions\when( 'update_post_meta' )->alias(
			static function ( int $id, string $key, $value ) use ( &$written ): bool {
				$written[ $key ] = $value;
				return true;
			}
		);

		$this->assertSame( 1, Activation::reindexUntimed() );

		$end_of_day = ( new \DateTimeImmutable( '2026-11-21 23:59', new \DateTimeZone( 'America/New_York' ) ) )->getTimestamp();
		$this->assertSame( $end_of_day, $written['_favr_event_last'] );
		$this->assertSame( 'favr_event', $asked['post_type'] );
		$this->assertSame( 'ids', $asked['fields'] );
		$this->assertSame( 'favr_event_start_time', $asked['meta_query'][1][0]['key'], 'Only events without a start time are touched.' );
		$this->assertSame( 'NOT EXISTS', $asked['meta_query'][1][0]['compare'] );
		$this->assertSame( '', $asked['meta_query'][1][1]['value'] );
	}
}
