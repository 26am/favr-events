<?php
/**
 * Searching the events list.
 *
 * @package FavrEvents
 */

declare(strict_types=1);

namespace FavrEvents\Tests\Unit;

use FavrEvents\Admin\ListSearch;

/**
 * The search box only looked at the event's name and description, so "Harbor" (a venue) or
 * "Tampa" (a city) found nothing.
 */
final class ListSearchTest extends TestCase {

	/** The search SQL WordPress builds for one word. */
	private const HARBOR = " AND (((wp_posts.post_title LIKE '{x}Harbor{x}') OR (wp_posts.post_excerpt LIKE '{x}Harbor{x}') OR (wp_posts.post_content LIKE '{x}Harbor{x}'))) ";

	/** The condition added for one word. */
	private const HARBOR_PLACE = "EXISTS ( SELECT 1 FROM wp_postmeta AS favr_ev_s WHERE favr_ev_s.post_id = wp_posts.ID AND favr_ev_s.meta_key IN ( 'favr_event_venue', 'favr_event_city' ) AND favr_ev_s.meta_value LIKE '{x}Harbor{x}' )";

	/**
	 * Just enough of $wpdb: table names, LIKE escaping and prepare() with WordPress's
	 * placeholder for a literal percent sign.
	 */
	private function database(): object {
		return new class() {
			public string $posts    = 'wp_posts';
			public string $postmeta = 'wp_postmeta';

			public function esc_like( string $text ): string {
				return addcslashes( $text, '_%\\' );
			}

			public function prepare( string $query, ...$args ): string {
				$args = array_map( static fn( $arg ): string => "'" . str_replace( '%', '{x}', addslashes( (string) $arg ) ) . "'", $args );
				return vsprintf( $query, $args );
			}
		};
	}

	public function test_a_word_is_also_looked_for_in_the_venue_and_the_city(): void {
		$search = ListSearch::widen( self::HARBOR, array( 'Harbor' ), $this->database() );

		$this->assertSame(
			" AND (((wp_posts.post_title LIKE '{x}Harbor{x}') OR " . self::HARBOR_PLACE . " OR (wp_posts.post_excerpt LIKE '{x}Harbor{x}') OR (wp_posts.post_content LIKE '{x}Harbor{x}'))) ",
			$search
		);
	}

	/** "Harbor Tampa" finds an event at the Harbor Club in Tampa: each word may match anywhere. */
	public function test_each_word_may_match_in_a_different_place(): void {
		$sql    = " AND (((wp_posts.post_title LIKE '{x}Harbor{x}') OR (wp_posts.post_content LIKE '{x}Harbor{x}')) AND ((wp_posts.post_title LIKE '{x}Tampa{x}') OR (wp_posts.post_content LIKE '{x}Tampa{x}'))) ";
		$search = ListSearch::widen( $sql, array( 'Harbor', 'Tampa' ), $this->database() );

		$this->assertSame( 2, substr_count( $search, 'EXISTS ( SELECT 1 FROM wp_postmeta AS favr_ev_s' ) );
		$this->assertStringContainsString( "(wp_posts.post_title LIKE '{x}Tampa{x}') OR EXISTS", $search );
		$this->assertStringContainsString( "favr_ev_s.meta_value LIKE '{x}Tampa{x}' ) OR (wp_posts.post_content LIKE '{x}Tampa{x}'))) ", $search );
	}

	/** It is a condition, not a join: an event is one row however many of its fields match. */
	public function test_nothing_is_joined_so_no_event_is_listed_twice(): void {
		$search = ListSearch::widen( self::HARBOR, array( 'Harbor' ), $this->database() );

		$this->assertStringNotContainsString( 'JOIN', $search );
	}

	/** "-Harbor" leaves events out; WordPress writes that as NOT LIKE, which stays as it is. */
	public function test_a_word_to_leave_out_is_untouched(): void {
		$sql = " AND (((wp_posts.post_title NOT LIKE '{x}Harbor{x}') AND (wp_posts.post_content NOT LIKE '{x}Harbor{x}'))) ";

		$this->assertSame( $sql, ListSearch::widen( $sql, array( 'Harbor' ), $this->database() ) );
	}

	public function test_wildcards_typed_in_the_box_are_searched_as_typed(): void {
		$sql    = " AND (((wp_posts.post_title LIKE '{x}50\\\\{x} off{x}'))) ";
		$search = ListSearch::widen( $sql, array( '50% off' ), $this->database() );

		$this->assertStringContainsString( "favr_ev_s.meta_value LIKE '{x}50\\\\{x} off{x}' )", $search );
	}

	public function test_no_search_no_change(): void {
		$this->assertSame( '', ListSearch::widen( '', array( 'Harbor' ), $this->database() ) );
		$this->assertSame( self::HARBOR, ListSearch::widen( self::HARBOR, array(), $this->database() ) );
	}
}
