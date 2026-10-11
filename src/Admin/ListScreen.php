<?php
/**
 * Events list table.
 *
 * @package FavrEvents
 */

declare(strict_types=1);

namespace FavrEvents\Admin;

use FavrEvents\Model\Event;
use FavrEvents\Schema\Identifiers as ID;

/**
 * Adds When and Host columns, opens on the next event (events without a date first, past
 * ones last), filters upcoming/past, and words the list's notices for events.
 */
final class ListScreen {

	/** Query flag set on the list's own query: sort by event date, NEXT, ASC or DESC. */
	private const BY_DATE = 'favr_events_by_date';

	/** Query var: the rows are shown soonest first (nobody clicked a column heading). */
	private const SOONEST = 'favr_events_soonest';

	/** Hook. */
	public function hook(): void {
		add_filter( 'manage_' . ID::POST_TYPE . '_posts_columns', array( $this, 'columns' ) );
		add_action( 'manage_' . ID::POST_TYPE . '_posts_custom_column', array( $this, 'column' ), 10, 2 );
		add_filter( 'manage_edit-' . ID::POST_TYPE . '_sortable_columns', array( $this, 'sortable' ) );
		add_action( 'pre_get_posts', array( $this, 'query' ) );
		add_filter( 'posts_clauses', array( $this, 'clauses' ), 10, 2 );
		add_filter( 'the_posts', array( $this, 'soonest' ), 10, 2 );
		add_filter( 'views_edit-' . ID::POST_TYPE, array( $this, 'views' ) );
		add_filter( 'bulk_post_updated_messages', array( $this, 'bulkMessages' ), 10, 2 );
	}

	/**
	 * Columns.
	 *
	 * @param array<string, string> $columns Columns.
	 * @return array<string, string>
	 */
	public function columns( array $columns ): array {
		$out = array();
		foreach ( $columns as $key => $label ) {
			$out[ $key ] = $label;
			if ( 'title' === $key ) {
				$out['title']     = __( 'Event', 'favr-events' );
				$out['favr_when'] = __( 'When', 'favr-events' );
				$out['favr_host'] = __( 'Host', 'favr-events' );
			} elseif ( 'taxonomy-' . ID::TAX_CATEGORY === $key ) {
				// "Categories", as on News, Directory and Documents: the list is already the events'.
				$out[ $key ] = __( 'Categories', 'favr-events' );
			}
		}
		unset( $out['date'] );
		return $out;
	}

	/**
	 * Cell.
	 *
	 * @param string $column  Column.
	 * @param int    $post_id Post.
	 */
	public function column( string $column, int $post_id ): void {
		$event = Event::find( $post_id );
		if ( ! $event ) {
			return;
		}
		if ( 'favr_when' === $column ) {
			$next = $event->nextOccurrence();
			if ( ! $next ) {
				echo '<span class="favr-muted">' . esc_html__( 'No date', 'favr-events' ) . '</span>';
				return;
			}
			echo wp_kses( self::when( $next['start'], $event->isAllDay() ), array( 'span' => array( 'class' => true ) ) );
			if ( $event->repeats() ) {
				echo ' <span class="dashicons dashicons-update" title="' . esc_attr__( 'Repeats', 'favr-events' ) . '"></span>';
			}
			if ( $next['end']->getTimestamp() < time() ) {
				echo ' <span class="favr-muted">(' . esc_html__( 'past', 'favr-events' ) . ')</span>';
			}
			if ( $event->isFeatured() ) {
				echo ' <span class="dashicons dashicons-star-filled" title="' . esc_attr__( 'Featured', 'favr-events' ) . '"></span>';
			}
		} elseif ( 'favr_host' === $column ) {
			echo wp_kses(
				self::host( $event->organizerName() ),
				array(
					'span' => array(
						'class'       => true,
						'aria-hidden' => true,
					),
				)
			);
		}
	}

	/**
	 * The When cell: "Oct 16, 2026 · 8:00 am", the short date of the other lists, with the
	 * time kept on one line. An all-day event shows its date only.
	 *
	 * @param \DateTimeImmutable $start   Start of the occurrence shown.
	 * @param bool               $all_day All-day event.
	 * @return string HTML.
	 */
	public static function when( \DateTimeImmutable $start, bool $all_day ): string {
		$date = esc_html( wp_date( 'M j, Y', $start->getTimestamp() ) );
		if ( $all_day ) {
			return $date;
		}
		return $date . ' · <span class="favr-ev-time">' . esc_html( wp_date( (string) get_option( 'time_format' ), $start->getTimestamp() ) ) . '</span>';
	}

	/**
	 * The Host cell: the name, or the dash the other lists print in an empty cell.
	 *
	 * @param string $name Host or organizer.
	 * @return string HTML.
	 */
	public static function host( string $name ): string {
		if ( '' === $name ) {
			return '<span aria-hidden="true">—</span><span class="screen-reader-text">' . esc_html__( 'No host', 'favr-events' ) . '</span>';
		}
		return esc_html( $name );
	}

	/**
	 * Sortable.
	 *
	 * @param array<string, string> $columns Columns.
	 * @return array<string, string>
	 */
	public function sortable( array $columns ): array {
		$columns['favr_when'] = 'favr_when';
		return $columns;
	}

	/**
	 * Default: the next event first (see dateClauses()); ?favr_when=upcoming|past narrows the list.
	 *
	 * @param \WP_Query $query Query.
	 */
	public function query( \WP_Query $query ): void {
		if ( ! is_admin() || ! $query->is_main_query() || ID::POST_TYPE !== $query->get( 'post_type' ) ) {
			return;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- list filter.
		$when      = isset( $_GET['favr_when'] ) ? sanitize_key( wp_unslash( $_GET['favr_when'] ) ) : '';
		$direction = self::dateOrder( (string) $query->get( 'orderby' ), (string) $query->get( 'order' ), $when );
		if ( '' !== $direction ) {
			$query->set( self::BY_DATE, $direction );
		}
		if ( 'NEXT' === $direction || ( 'upcoming' === $when && '' === (string) $query->get( 'orderby' ) ) ) {
			$query->set( self::SOONEST, 1 );
		}
		if ( in_array( $when, array( 'upcoming', 'past' ), true ) ) {
			$query->set( 'meta_query', array( self::whenQuery( $when ) ) );
			if ( 'past' === $when ) {
				$query->set( 'order', 'DESC' );
			}
		}
	}

	/**
	 * How the list is sorted by date, or '' when it is sorted by another column: NEXT when
	 * nothing was chosen (the next event first), ASC or DESC after a click on the When heading
	 * and in the Upcoming view. Past events always start with the most recent.
	 *
	 * @param string $orderby Requested column ('' = none, 'favr_when' = the When column).
	 * @param string $order   Requested direction.
	 * @param string $when    The upcoming/past view, or ''.
	 */
	public static function dateOrder( string $orderby, string $order, string $when ): string {
		if ( '' !== $orderby && 'favr_when' !== $orderby ) {
			return '';
		}
		if ( 'past' === $when ) {
			return 'DESC';
		}
		if ( '' === $orderby && 'upcoming' !== $when ) {
			return 'NEXT';
		}
		return 'favr_when' === $orderby && 'DESC' === strtoupper( $order ) ? 'DESC' : 'ASC';
	}

	/**
	 * Apply the date sort to the list's query.
	 *
	 * @param array<string, string> $clauses Query clauses.
	 * @param \WP_Query             $query   Query.
	 * @return array<string, string>
	 */
	public function clauses( array $clauses, \WP_Query $query ): array {
		$direction = (string) $query->get( self::BY_DATE );
		if ( ! in_array( $direction, array( 'NEXT', 'ASC', 'DESC' ), true ) ) {
			return $clauses;
		}
		global $wpdb;
		return self::dateClauses( $clauses, $wpdb->posts, $wpdb->postmeta, $direction, time() );
	}

	/**
	 * Sort by the first start (the date index) without losing events that have no date yet.
	 * A meta_key sort would leave those out, so a draft saved without a date was counted in
	 * "All" but missing from the rows. They are listed first: they still need a date.
	 *
	 * NEXT is the order the list opens in: after the events without a date come the upcoming
	 * ones, soonest first, then the past ones, most recent first, so page one is never last
	 * year. "Past" is the Upcoming view's rule (the last end, META_LAST, is over), which keeps
	 * a repeating event that started long ago among the upcoming ones. Each index is one row
	 * per event, so the joins never repeat an event.
	 *
	 * @param array<string, string> $clauses   Query clauses.
	 * @param string                $posts     Posts table.
	 * @param string                $postmeta  Post meta table.
	 * @param string                $direction NEXT, ASC or DESC.
	 * @param int                   $now       The current time (UTC timestamp), for NEXT.
	 * @return array<string, string>
	 */
	public static function dateClauses( array $clauses, string $posts, string $postmeta, string $direction, int $now = 0 ): array {
		$first           = 'CAST( favr_ev_first.meta_value AS SIGNED )';
		$clauses['join'] = ( $clauses['join'] ?? '' ) . " LEFT JOIN {$postmeta} AS favr_ev_first ON ( favr_ev_first.post_id = {$posts}.ID AND favr_ev_first.meta_key = '" . ID::META_FIRST . "' )";
		if ( 'NEXT' !== $direction ) {
			$direction          = 'DESC' === $direction ? 'DESC' : 'ASC';
			$clauses['orderby'] = "( favr_ev_first.meta_value IS NULL ) DESC, {$first} {$direction}, {$posts}.ID DESC";
			return $clauses;
		}
		$over               = "CAST( favr_ev_last.meta_value AS SIGNED ) < {$now}";
		$clauses['join']   .= " LEFT JOIN {$postmeta} AS favr_ev_last ON ( favr_ev_last.post_id = {$posts}.ID AND favr_ev_last.meta_key = '" . ID::META_LAST . "' )";
		$clauses['orderby'] = "( favr_ev_first.meta_value IS NULL ) DESC, ( {$over} ) ASC, CASE WHEN {$over} THEN -{$first} ELSE {$first} END ASC, {$posts}.ID DESC";
		return $clauses;
	}

	/**
	 * The page of events as the list shows it when it is "soonest first": the query sorts by each
	 * event's first date (the index), but a repeating event shows its next date, so the upcoming
	 * rows of the page are put in the order of the dates their When cells print.
	 *
	 * @param mixed     $posts The page of events.
	 * @param \WP_Query $query Query.
	 * @return mixed
	 */
	public function soonest( $posts, $query ) {
		if ( ! is_array( $posts ) || count( $posts ) < 2 || ! $query instanceof \WP_Query || ! $query->get( self::SOONEST ) || ! $query->is_main_query() ) {
			return $posts;
		}
		$by_id = array();
		$next  = array();
		foreach ( $posts as $post ) {
			$id           = (int) ( $post->ID ?? 0 );
			$event        = Event::find( $id );
			$occurrence   = $event ? $event->nextOccurrence() : null;
			$by_id[ $id ] = $post;
			$next[ $id ]  = $occurrence ? array( $occurrence['start']->getTimestamp(), $occurrence['end']->getTimestamp() ) : null;
		}
		if ( count( $by_id ) !== count( $posts ) ) {
			return $posts;
		}
		return array_map( static fn( int $id ) => $by_id[ $id ], self::soonestFirst( array_keys( $by_id ), $next, time() ) );
	}

	/**
	 * Pure: events without a date first (they still need one), then the ones not over yet by
	 * the start of their next date, then the past ones; each group otherwise keeps the order it
	 * came in.
	 *
	 * @template T of int|string
	 * @param list<T>                                 $ids  Events in the order the query gave them.
	 * @param array<int|string, array{int, int}|null> $next Per event: start and end of the date its row shows, or null.
	 * @param int                                     $now  The current time (UTC timestamp).
	 * @return list<T>
	 */
	public static function soonestFirst( array $ids, array $next, int $now ): array {
		$undated  = array();
		$upcoming = array();
		$past     = array();
		foreach ( $ids as $position => $id ) {
			$when = $next[ $id ] ?? null;
			if ( null === $when ) {
				$undated[] = $id;
			} elseif ( $when[1] < $now ) {
				$past[] = $id;
			} else {
				$upcoming[] = array( $when[0], $position, $id );
			}
		}
		sort( $upcoming ); // By start, then by the position the query gave.
		return array_merge( $undated, array_column( $upcoming, 2 ), $past );
	}

	/**
	 * Upcoming / Past views, with their counts, before Trash.
	 *
	 * @param array<string, string> $views Views.
	 * @return array<string, string>
	 */
	public function views( array $views ): array {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- list filter.
		$when = isset( $_GET['favr_when'] ) ? sanitize_key( wp_unslash( $_GET['favr_when'] ) ) : '';
		$own  = array();
		foreach ( array(
			'upcoming' => __( 'Upcoming', 'favr-events' ),
			'past'     => __( 'Past', 'favr-events' ),
		) as $key => $label ) {
			$own[ 'favr_' . $key ] = self::viewLink(
				add_query_arg( 'favr_when', $key, admin_url( 'edit.php?post_type=' . ID::POST_TYPE ) ),
				$label,
				self::countWhen( $key ),
				$when === $key
			);
		}
		return self::beforeTrash( $views, $own );
	}

	/**
	 * One view tab, written like WordPress's own: "Upcoming (12)".
	 *
	 * @param string $url     Where it leads.
	 * @param string $label   Name.
	 * @param int    $count   How many events it lists.
	 * @param bool   $current The view being shown.
	 */
	public static function viewLink( string $url, string $label, int $count, bool $current ): string {
		return sprintf(
			'<a href="%1$s"%2$s>%3$s <span class="count">(%4$s)</span></a>',
			esc_url( $url ),
			$current ? ' class="current" aria-current="page"' : '',
			esc_html( $label ),
			esc_html( number_format_i18n( $count ) )
		);
	}

	/**
	 * Put the plugin's own views before Trash: Trash closes the row on every other list.
	 *
	 * @param array<string, string> $views WordPress's views.
	 * @param array<string, string> $own   The views to add.
	 * @return array<string, string>
	 */
	public static function beforeTrash( array $views, array $own ): array {
		if ( ! isset( $views['trash'] ) ) {
			return $views + $own;
		}
		$trash = $views['trash'];
		unset( $views['trash'] );
		return $views + $own + array( 'trash' => $trash );
	}

	/**
	 * How many events the Upcoming or the Past view lists: the same condition as the view
	 * itself (see query()), over the statuses the list shows.
	 *
	 * @param string $when upcoming | past.
	 */
	private static function countWhen( string $when ): int {
		$query = new \WP_Query(
			array(
				'post_type'              => ID::POST_TYPE,
				'post_status'            => array_values( get_post_stati( array( 'show_in_admin_all_list' => true ) ) ),
				'perm'                   => 'readable',
				'fields'                 => 'ids',
				'posts_per_page'         => 1,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- one indexed key, counted once per list view.
				'meta_query'             => array( self::whenQuery( $when ) ),
			)
		);
		return (int) $query->found_posts;
	}

	/**
	 * The meta query behind the Upcoming and Past views: is the event's last end over?
	 *
	 * @param string $when upcoming | past.
	 * @return array<string, mixed>
	 */
	private static function whenQuery( string $when ): array {
		return array(
			'key'     => ID::META_LAST,
			'value'   => time(),
			'compare' => 'upcoming' === $when ? '>=' : '<',
			'type'    => 'NUMERIC',
		);
	}

	/**
	 * Notices after trashing, restoring or bulk-editing from the list: "1 event moved to the
	 * Trash." instead of "1 post…".
	 *
	 * @param array<string, array<string, string>> $messages Messages per post type.
	 * @param array<string, int>                   $counts   How many items each action touched.
	 * @return array<string, array<string, string>>
	 */
	public function bulkMessages( array $messages, array $counts ): array {
		$count = static fn( string $key ): int => (int) ( $counts[ $key ] ?? 0 );

		$messages[ ID::POST_TYPE ] = array(
			/* translators: %s: number of events. */
			'updated'   => _n( '%s event updated.', '%s events updated.', $count( 'updated' ), 'favr-events' ),
			/* translators: %s: number of events. */
			'locked'    => _n( '%s event not updated, somebody is editing it.', '%s events not updated, somebody is editing them.', $count( 'locked' ), 'favr-events' ),
			/* translators: %s: number of events. */
			'deleted'   => _n( '%s event permanently deleted.', '%s events permanently deleted.', $count( 'deleted' ), 'favr-events' ),
			/* translators: %s: number of events. */
			'trashed'   => _n( '%s event moved to the Trash.', '%s events moved to the Trash.', $count( 'trashed' ), 'favr-events' ),
			/* translators: %s: number of events. */
			'untrashed' => _n( '%s event restored from the Trash.', '%s events restored from the Trash.', $count( 'untrashed' ), 'favr-events' ),
		);
		return $messages;
	}
}
