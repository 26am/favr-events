<?php
/**
 * WP-CLI: wp favr-events …
 *
 * @package FavrEvents
 */

declare(strict_types=1);

namespace FavrEvents\Cli;

use FavrEvents\Frontend\Format;
use FavrEvents\Model\Event;
use FavrEvents\Model\Repository;
use FavrEvents\Schema\Identifiers as ID;

/**
 * Manage Favr Events.
 */
final class Command {

	/**
	 * List upcoming occurrences.
	 *
	 * ## OPTIONS
	 *
	 * [--limit=<n>]
	 * : How many. Default 20.
	 *
	 * @param array<int, string>    $args  Args.
	 * @param array<string, string> $assoc Options.
	 */
	public function upcoming( array $args, array $assoc ): void {
		$rows = array();
		foreach ( Repository::upcoming( (int) ( $assoc['limit'] ?? 20 ) ) as $item ) {
			$rows[] = array(
				'id'    => $item['event']->id(),
				'title' => $item['event']->title(),
				'when'  => Format::when( $item['event'], $item['start'], $item['end'] ),
			);
		}
		\WP_CLI\Utils\format_items( 'table', $rows, array( 'id', 'title', 'when' ) );
	}

	/**
	 * Rebuild the date index for every event (after imports or direct database edits).
	 */
	public function reindex(): void {
		$ids = get_posts(
			array(
				'post_type'      => ID::POST_TYPE,
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'fields'         => 'ids',
			)
		);
		foreach ( $ids as $id ) {
			Event::reindex( (int) $id );
		}
		\WP_CLI::success( sprintf( 'Reindexed %d events.', count( $ids ) ) );
	}

	/**
	 * Create a few sample events (a monthly luncheon, a weekly coffee, a ribbon cutting, a webinar).
	 */
	public function seed(): void {
		$tz             = wp_timezone();
		$month          = new \DateTimeImmutable( 'first day of next month', $tz );
		$second_tuesday = new \DateTimeImmutable( 'second tuesday of ' . $month->format( 'Y-m' ), $tz );
		$samples        = array(
			array(
				'Monthly Membership Luncheon',
				$second_tuesday->format( 'Y-m-d' ),
				'11:30',
				'13:00',
				array(
					'repeat'   => 'monthly_nth',
					'venue'    => 'Riverside Event Center',
					'address'  => '200 Water St',
					'city'     => 'Springfield',
					'state'    => 'IL',
					'cost'     => '$20 members / $30 guests',
					'featured' => '1',
				),
				'Networking',
			),
			array(
				'Coffee & Connections',
				( new \DateTimeImmutable( 'next friday', $tz ) )->format( 'Y-m-d' ),
				'07:30',
				'08:30',
				array(
					'repeat' => 'weekly',
					'venue'  => 'Harbor Light Coffee',
					'city'   => 'Springfield',
					'cost'   => 'Free',
				),
				'Networking',
			),
			array(
				'Ribbon Cutting: Pixel & Pine Web Studio',
				( new \DateTimeImmutable( '+10 days', $tz ) )->format( 'Y-m-d' ),
				'16:00',
				'17:00',
				array(
					'venue'   => 'Pixel & Pine Web Studio',
					'address' => '12 Main St',
					'city'    => 'Springfield',
				),
				'Ribbon Cuttings',
			),
			array(
				'Webinar: Marketing on a Small Budget',
				( new \DateTimeImmutable( '+17 days', $tz ) )->format( 'Y-m-d' ),
				'12:00',
				'13:00',
				array(
					'attendance' => 'online',
					'online_url' => 'https://example.com/webinar',
					'cost'       => 'Free',
				),
				'Workshops & Training',
			),
		);
		foreach ( $samples as $sample ) {
			$id     = wp_insert_post(
				array(
					'post_type'    => ID::POST_TYPE,
					'post_status'  => 'publish',
					'post_title'   => $sample[0],
					'post_content' => 'Join fellow members and neighbors. Everyone is welcome.',
				)
			);
			$fields = array_merge(
				array(
					'start_date' => $sample[1],
					'start_time' => $sample[2],
					'end_time'   => $sample[3],
				),
				$sample[4]
			);
			foreach ( $fields as $key => $value ) {
				update_post_meta( (int) $id, ID::meta( $key ), $value );
			}
			wp_set_object_terms( (int) $id, $sample[5], ID::TAX_CATEGORY );
			Event::reindex( (int) $id );
			\WP_CLI::log( sprintf( 'Created %d: %s', $id, $sample[0] ) );
		}
		\WP_CLI::success( 'Sample events created.' );
	}
}
