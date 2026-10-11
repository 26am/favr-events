<?php
/**
 * The events list.
 *
 * @package FavrEvents
 */

declare(strict_types=1);

namespace FavrEvents\Tests\Unit;

use Brain\Monkey\Functions;
use FavrEvents\Admin\ListScreen;

final class ListScreenTest extends TestCase {

	/**
	 * Trashing or restoring from the list says "event", not "post".
	 */
	public function test_list_notices_say_event(): void {
		Functions\when( '_n' )->alias( static fn( string $one, string $many, int $count ): string => 1 === $count ? $one : $many );

		$messages = ( new ListScreen() )->bulkMessages(
			array( 'post' => array( 'trashed' => '%s post moved to the Trash.' ) ),
			array(
				'updated'   => 1,
				'locked'    => 2,
				'deleted'   => 2,
				'trashed'   => 1,
				'untrashed' => 3,
			)
		);

		$this->assertSame( '%s post moved to the Trash.', $messages['post']['trashed'] );
		$this->assertSame(
			array(
				'updated'   => '%s event updated.',
				'locked'    => '%s events not updated, somebody is editing them.',
				'deleted'   => '%s events permanently deleted.',
				'trashed'   => '%s event moved to the Trash.',
				'untrashed' => '%s events restored from the Trash.',
			),
			$messages['favr_event']
		);
	}

	/* ------------------------------------------------------------ Date order */

	public function test_list_is_in_date_order_unless_another_column_is_chosen(): void {
		$this->assertSame( 'ASC', ListScreen::dateOrder( '', '', '' ) );
		$this->assertSame( 'ASC', ListScreen::dateOrder( 'favr_when', 'asc', '' ) );
		$this->assertSame( 'DESC', ListScreen::dateOrder( 'favr_when', 'desc', '' ) );
		$this->assertSame( 'ASC', ListScreen::dateOrder( 'favr_when', 'sideways', '' ) );
		$this->assertSame( '', ListScreen::dateOrder( 'title', 'asc', '' ) );
		$this->assertSame( '', ListScreen::dateOrder( 'modified', 'ASC', 'upcoming' ) );
	}

	public function test_past_events_start_with_the_most_recent(): void {
		$this->assertSame( 'DESC', ListScreen::dateOrder( '', '', 'past' ) );
		$this->assertSame( 'DESC', ListScreen::dateOrder( 'favr_when', 'asc', 'past' ) );
		$this->assertSame( 'ASC', ListScreen::dateOrder( '', '', 'upcoming' ) );
	}

	/**
	 * Sorting on the date index with meta_key drops every event that has no date yet, so a
	 * draft saved without a date was counted in "All (14)" but missing from the 13 rows.
	 * A left join keeps them, and lists them first: they still need a date.
	 */
	public function test_events_without_a_date_stay_in_the_list_and_come_first(): void {
		$clauses = ListScreen::dateClauses(
			array(
				'join'    => ' LEFT JOIN wp_term_relationships ON (wp_posts.ID = wp_term_relationships.object_id)',
				'where'   => " AND wp_posts.post_type = 'favr_event'",
				'orderby' => 'wp_posts.post_date DESC',
			),
			'wp_posts',
			'wp_postmeta',
			'ASC'
		);

		$this->assertSame(
			" LEFT JOIN wp_term_relationships ON (wp_posts.ID = wp_term_relationships.object_id) LEFT JOIN wp_postmeta AS favr_ev_first ON ( favr_ev_first.post_id = wp_posts.ID AND favr_ev_first.meta_key = '_favr_event_first' )",
			$clauses['join']
		);
		$this->assertSame( '( favr_ev_first.meta_value IS NULL ) DESC, CAST( favr_ev_first.meta_value AS SIGNED ) ASC, wp_posts.ID DESC', $clauses['orderby'] );
		$this->assertSame( " AND wp_posts.post_type = 'favr_event'", $clauses['where'] );
	}

	public function test_date_order_can_run_latest_first(): void {
		$clauses = ListScreen::dateClauses( array(), 'wp_posts', 'wp_postmeta', 'DESC' );

		$this->assertStringContainsString( 'CAST( favr_ev_first.meta_value AS SIGNED ) DESC', $clauses['orderby'] );
		$this->assertStringStartsWith( ' LEFT JOIN wp_postmeta AS favr_ev_first', $clauses['join'] );
	}
}
