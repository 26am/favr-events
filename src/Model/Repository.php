<?php
/**
 * Finding event occurrences.
 *
 * @package FavrEvents
 */

declare(strict_types=1);

namespace FavrEvents\Model;

use FavrEvents\Schema\Identifiers as ID;

/**
 * Queries candidate events through the first/last index, expands their schedules and returns
 * occurrences in date order.
 */
final class Repository {

	/**
	 * Occurrences overlapping a window.
	 *
	 * @param \DateTimeImmutable   $from Window start.
	 * @param \DateTimeImmutable   $to   Window end.
	 * @param array<string, mixed> $args category (slug or id), host (listing id), featured (bool), limit, ids.
	 * @return list<array{event: Event, start: \DateTimeImmutable, end: \DateTimeImmutable}>
	 */
	public static function occurrences( \DateTimeImmutable $from, \DateTimeImmutable $to, array $args = array() ): array {
		$limit = max( 1, min( 500, (int) ( $args['limit'] ?? 200 ) ) );
		$query = array(
			'post_type'        => ID::POST_TYPE,
			'post_status'      => 'publish',
			'posts_per_page'   => 500, // phpcs:ignore WordPress.WP.PostsPerPage.posts_per_page_posts_per_page -- bounded, indexed.
			'no_found_rows'    => true,
			'suppress_filters' => false,
			'meta_query'       => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- indexed numeric range on two keys.
				'relation' => 'AND',
				array(
					'key'     => ID::META_FIRST,
					'value'   => $to->getTimestamp(),
					'compare' => '<',
					'type'    => 'NUMERIC',
				),
				array(
					'key'     => ID::META_LAST,
					'value'   => $from->getTimestamp(),
					'compare' => '>=',
					'type'    => 'NUMERIC',
				),
			),
		);
		if ( ! empty( $args['category'] ) ) {
			$query['tax_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
				array(
					'taxonomy' => ID::TAX_CATEGORY,
					'field'    => is_numeric( $args['category'] ) ? 'term_id' : 'slug',
					'terms'    => $args['category'],
				),
			);
		}
		if ( ! empty( $args['host'] ) ) {
			$query['meta_query'][] = array(
				'key'   => ID::meta( 'host_business' ),
				'value' => (string) (int) $args['host'],
			);
		}
		if ( ! empty( $args['featured'] ) ) {
			$query['meta_query'][] = array(
				'key'   => ID::meta( 'featured' ),
				'value' => '1',
			);
		}
		if ( ! empty( $args['ids'] ) ) {
			$query['post__in'] = array_map( 'intval', (array) $args['ids'] );
		}

		$out = array();
		foreach ( get_posts( $query ) as $post ) {
			$event = new Event( $post );
			foreach ( $event->occurrences( $from, $to, $limit ) as $occurrence ) {
				$out[] = array(
					'event' => $event,
					'start' => $occurrence['start'],
					'end'   => $occurrence['end'],
				);
			}
		}
		usort(
			$out,
			static fn( array $a, array $b ): int => array( $a['start']->getTimestamp(), $a['event']->title() ) <=> array( $b['start']->getTimestamp(), $b['event']->title() )
		);
		return array_slice( $out, 0, $limit );
	}

	/**
	 * Upcoming occurrences from now.
	 *
	 * @param int                  $limit Max.
	 * @param array<string, mixed> $args  See occurrences().
	 * @return list<array{event: Event, start: \DateTimeImmutable, end: \DateTimeImmutable}>
	 */
	public static function upcoming( int $limit = 10, array $args = array() ): array {
		$now = new \DateTimeImmutable( 'now', wp_timezone() );
		return self::occurrences( $now, $now->modify( '+1 year' ), array_merge( $args, array( 'limit' => $limit ) ) );
	}
}
