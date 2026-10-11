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
		$this->assertSame( 'NEXT', ListScreen::dateOrder( '', '', '' ) );
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

	/**
	 * With no view chosen the list used to open on the oldest event: on a site with a year of
	 * history the first page was last year. It opens on what needs attention: events that
	 * still need a date, then upcoming ones soonest first, then past ones most recent first.
	 * "Past" is the Upcoming view's rule (the last end is over), so a repeating event that
	 * started long ago is still upcoming.
	 */
	public function test_with_no_view_chosen_the_next_event_comes_before_last_years(): void {
		$clauses = ListScreen::dateClauses(
			array(
				'join'    => '',
				'where'   => " AND wp_posts.post_type = 'favr_event'",
				'orderby' => 'wp_posts.post_date DESC',
			),
			'wp_posts',
			'wp_postmeta',
			'NEXT',
			1790000000
		);

		$this->assertSame(
			" LEFT JOIN wp_postmeta AS favr_ev_first ON ( favr_ev_first.post_id = wp_posts.ID AND favr_ev_first.meta_key = '_favr_event_first' )"
			. " LEFT JOIN wp_postmeta AS favr_ev_last ON ( favr_ev_last.post_id = wp_posts.ID AND favr_ev_last.meta_key = '_favr_event_last' )",
			$clauses['join']
		);
		$this->assertSame(
			'( favr_ev_first.meta_value IS NULL ) DESC, '
			. '( CAST( favr_ev_last.meta_value AS SIGNED ) < 1790000000 ) ASC, '
			. 'CASE WHEN CAST( favr_ev_last.meta_value AS SIGNED ) < 1790000000 THEN -CAST( favr_ev_first.meta_value AS SIGNED ) ELSE CAST( favr_ev_first.meta_value AS SIGNED ) END ASC, '
			. 'wp_posts.ID DESC',
			$clauses['orderby']
		);
		$this->assertSame( " AND wp_posts.post_type = 'favr_event'", $clauses['where'], 'Search, the view tabs and the filters keep their own conditions.' );
	}

	/** The order above, applied to rows the way MySQL applies it. */
	public function test_next_first_order_on_a_years_worth_of_events(): void {
		$now  = 1790000000;
		$day  = 86400;
		$rows = array(
			'last year'        => array( $now - 400 * $day, $now - 400 * $day + 3600 ),
			'last week'        => array( $now - 7 * $day, $now - 7 * $day + 3600 ),
			'no date yet'      => array( null, null ),
			'next month'       => array( $now + 30 * $day, $now + 30 * $day + 3600 ),
			'tomorrow'         => array( $now + $day, $now + $day + 3600 ),
			'weekly since May' => array( $now - 150 * $day, 4102444800 ),
			'on right now'     => array( $now - 1800, $now + 1800 ),
		);
		$key  = static fn( array $row ): array => array(
			null === $row[0] ? 0 : 1,
			null !== $row[1] && $row[1] < $now ? 1 : 0,
			null !== $row[1] && $row[1] < $now ? -$row[0] : (int) $row[0],
		);
		uasort( $rows, static fn( array $a, array $b ): int => $key( $a ) <=> $key( $b ) );

		$this->assertSame( array( 'no date yet', 'weekly since May', 'on right now', 'tomorrow', 'next month', 'last week', 'last year' ), array_keys( $rows ) );
	}

	public function test_clicking_the_when_heading_still_sorts_plainly_by_date(): void {
		$clauses = ListScreen::dateClauses( array(), 'wp_posts', 'wp_postmeta', 'ASC', 1790000000 );

		$this->assertSame( '( favr_ev_first.meta_value IS NULL ) DESC, CAST( favr_ev_first.meta_value AS SIGNED ) ASC, wp_posts.ID DESC', $clauses['orderby'] );
		$this->assertStringNotContainsString( 'favr_ev_last', $clauses['join'] );
	}

	/* ------------------------------------------------------------ View tabs */

	/** Upcoming and Past were added after Trash, which left Trash in the middle of the row. */
	public function test_upcoming_and_past_come_before_trash(): void {
		$views = ListScreen::beforeTrash(
			array(
				'all'     => 'All (14)',
				'mine'    => 'Mine (1)',
				'publish' => 'Published (13)',
				'trash'   => 'Trash (1)',
			),
			array(
				'favr_upcoming' => 'Upcoming (12)',
				'favr_past'     => 'Past (1)',
			)
		);

		$this->assertSame( array( 'all', 'mine', 'publish', 'favr_upcoming', 'favr_past', 'trash' ), array_keys( $views ) );
	}

	public function test_without_a_trash_tab_they_close_the_row(): void {
		$views = ListScreen::beforeTrash( array( 'all' => 'All (3)' ), array( 'favr_upcoming' => 'Upcoming (3)' ) );

		$this->assertSame( array( 'all', 'favr_upcoming' ), array_keys( $views ) );
	}

	/** Every other tab carries a count: "Upcoming (12)". */
	public function test_a_view_tab_carries_its_count(): void {
		Functions\when( 'esc_url' )->returnArg();
		Functions\when( 'esc_html' )->returnArg();
		Functions\when( 'number_format_i18n' )->alias( static fn( $number ): string => number_format( (float) $number ) );

		$this->assertSame(
			'<a href="edit.php?post_type=favr_event&favr_when=upcoming" class="current" aria-current="page">Upcoming <span class="count">(1,204)</span></a>',
			ListScreen::viewLink( 'edit.php?post_type=favr_event&favr_when=upcoming', 'Upcoming', 1204, true )
		);
		$this->assertSame(
			'<a href="edit.php?post_type=favr_event&favr_when=past">Past <span class="count">(0)</span></a>',
			ListScreen::viewLink( 'edit.php?post_type=favr_event&favr_when=past', 'Past', 0, false )
		);
	}

	/* -------------------------------------------------------------- Columns */

	/** Directory says "Listing" and Members "Member"; News, Directory and Documents say "Categories". */
	public function test_column_heads_say_event_and_categories(): void {
		$columns = ( new ListScreen() )->columns(
			array(
				'cb'                      => '<input type="checkbox" />',
				'title'                   => 'Title',
				'taxonomy-favr_event_cat' => 'Event categories',
				'date'                    => 'Date',
			)
		);

		$this->assertSame(
			array(
				'cb'                      => '<input type="checkbox" />',
				'title'                   => 'Event',
				'favr_when'               => 'When',
				'favr_host'               => 'Host',
				'taxonomy-favr_event_cat' => 'Categories',
			),
			$columns
		);
	}

	/** "Oct 16, 2026 · 8:00 am", like the other lists' dates, with the time never broken in two. */
	public function test_when_reads_like_the_other_lists_dates(): void {
		Functions\when( 'esc_html' )->returnArg();
		Functions\when( 'get_option' )->alias( static fn( string $name ) => 'time_format' === $name ? 'g:i a' : 'F j, Y' );
		Functions\when( 'wp_date' )->alias( static fn( string $format, int $timestamp ): string => gmdate( $format, $timestamp ) );
		$start = new \DateTimeImmutable( '2026-10-16 08:00', new \DateTimeZone( 'UTC' ) );

		$this->assertSame( 'Oct 16, 2026 · <span class="favr-ev-time">8:00 am</span>', ListScreen::when( $start, false ) );
		$this->assertSame( 'Oct 16, 2026', ListScreen::when( $start, true ), 'An all-day event shows its date only.' );
	}

	/** An empty Host cell was blank where the other lists print a dash. */
	public function test_an_event_without_a_host_shows_a_dash(): void {
		Functions\when( 'esc_html' )->returnArg();
		Functions\when( 'esc_html__' )->returnArg();

		$this->assertSame( 'Bell Advisory Group', ListScreen::host( 'Bell Advisory Group' ) );
		$this->assertSame( '<span aria-hidden="true">—</span><span class="screen-reader-text">No host</span>', ListScreen::host( '' ) );
	}

	public function test_date_order_can_run_latest_first(): void {
		$clauses = ListScreen::dateClauses( array(), 'wp_posts', 'wp_postmeta', 'DESC' );

		$this->assertStringContainsString( 'CAST( favr_ev_first.meta_value AS SIGNED ) DESC', $clauses['orderby'] );
		$this->assertStringStartsWith( ' LEFT JOIN wp_postmeta AS favr_ev_first', $clauses['join'] );
	}
}
