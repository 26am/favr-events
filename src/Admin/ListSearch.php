<?php
/**
 * Searching the events list.
 *
 * @package FavrEvents
 */

declare(strict_types=1);

namespace FavrEvents\Admin;

use FavrEvents\Schema\Identifiers as ID;

/**
 * The list's search box also looks in the venue and the city: "Harbor" finds the events at
 * the Harbor Club. WordPress only looks in the name and the description.
 */
final class ListSearch {

	/** The fields searched besides the name and the description. */
	private const FIELDS = array( 'venue', 'city' );

	/** Hook. */
	public function hook(): void {
		add_filter( 'posts_search', array( $this, 'search' ), 10, 2 );
	}

	/**
	 * Widen the search of the events list (the main query of the list screen only).
	 *
	 * @param string    $search WordPress's search SQL.
	 * @param \WP_Query $query  Query.
	 */
	public function search( string $search, \WP_Query $query ): string {
		if ( '' === $search || ! is_admin() || ! $query->is_main_query() || ID::POST_TYPE !== $query->get( 'post_type' ) ) {
			return $search;
		}
		global $wpdb;
		return self::widen( $search, array_map( 'strval', (array) $query->get( 'search_terms' ) ), $wpdb );
	}

	/**
	 * Let each searched word also match the venue or the city.
	 *
	 * WordPress writes one group per word, "(name LIKE word) OR (description LIKE word)", and
	 * joins the groups with AND. The place is added to each group as one more OR, so every
	 * word may match somewhere else ("Harbor Tampa"). It is a condition on the event, not a
	 * join: an event stays one row however many of its fields match, and the view tabs, the
	 * filters, the counts and the pages keep working on the same query. A word to leave out
	 * ("-word") is written NOT LIKE and stays as WordPress wrote it.
	 *
	 * @param string       $search WordPress's search SQL.
	 * @param list<string> $terms  The words WordPress searched for.
	 * @param object       $wpdb   The database: posts, postmeta, esc_like(), prepare().
	 */
	public static function widen( string $search, array $terms, object $wpdb ): string {
		if ( '' === $search ) {
			return $search;
		}
		$keys = "'" . implode( "', '", array_map( array( ID::class, 'meta' ), self::FIELDS ) ) . "'";
		foreach ( array_unique( $terms ) as $term ) {
			$like = '%' . $wpdb->esc_like( $term ) . '%';
			// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table names and the plugin's own meta keys.
			$name   = $wpdb->prepare( "({$wpdb->posts}.post_title LIKE %s)", $like );
			$place  = $wpdb->prepare( "EXISTS ( SELECT 1 FROM {$wpdb->postmeta} AS favr_ev_s WHERE favr_ev_s.post_id = {$wpdb->posts}.ID AND favr_ev_s.meta_key IN ( {$keys} ) AND favr_ev_s.meta_value LIKE %s )", $like );
			$search = str_replace( $name, $name . ' OR ' . $place, $search );
			// phpcs:enable
		}
		return $search;
	}
}
