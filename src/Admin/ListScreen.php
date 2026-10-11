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
 * Adds When and Host columns, sorts by date (events without a date first), filters
 * upcoming/past, and words the list's notices for events.
 */
final class ListScreen {

	/** Query flag set on the list's own query: sort by event date, ASC or DESC. */
	private const BY_DATE = 'favr_events_by_date';

	/** Hook. */
	public function hook(): void {
		add_filter( 'manage_' . ID::POST_TYPE . '_posts_columns', array( $this, 'columns' ) );
		add_action( 'manage_' . ID::POST_TYPE . '_posts_custom_column', array( $this, 'column' ), 10, 2 );
		add_filter( 'manage_edit-' . ID::POST_TYPE . '_sortable_columns', array( $this, 'sortable' ) );
		add_action( 'pre_get_posts', array( $this, 'query' ) );
		add_filter( 'posts_clauses', array( $this, 'clauses' ), 10, 2 );
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
				$out['favr_when'] = __( 'When', 'favr-events' );
				$out['favr_host'] = __( 'Host', 'favr-events' );
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
			echo esc_html( wp_date( (string) get_option( 'date_format' ) . ( $event->isAllDay() ? '' : ' ' . get_option( 'time_format' ) ), $next['start']->getTimestamp() ) );
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
			echo esc_html( $event->organizerName() );
		}
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
	 * Default: in date order, soonest first; ?favr_when=upcoming|past narrows the list.
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
		if ( in_array( $when, array( 'upcoming', 'past' ), true ) ) {
			$query->set(
				'meta_query',
				array(
					array(
						'key'     => ID::META_LAST,
						'value'   => time(),
						'compare' => 'upcoming' === $when ? '>=' : '<',
						'type'    => 'NUMERIC',
					),
				)
			);
			if ( 'past' === $when ) {
				$query->set( 'order', 'DESC' );
			}
		}
	}

	/**
	 * Direction of the date sort, or '' when the list is sorted by another column. Past events
	 * always start with the most recent.
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
		if ( ! in_array( $direction, array( 'ASC', 'DESC' ), true ) ) {
			return $clauses;
		}
		global $wpdb;
		return self::dateClauses( $clauses, $wpdb->posts, $wpdb->postmeta, $direction );
	}

	/**
	 * Sort by the first start (the date index) without losing events that have no date yet.
	 * A meta_key sort would leave those out, so a draft saved without a date was counted in
	 * "All" but missing from the rows. They are listed first: they still need a date.
	 *
	 * @param array<string, string> $clauses   Query clauses.
	 * @param string                $posts     Posts table.
	 * @param string                $postmeta  Post meta table.
	 * @param string                $direction ASC or DESC.
	 * @return array<string, string>
	 */
	public static function dateClauses( array $clauses, string $posts, string $postmeta, string $direction ): array {
		$direction          = 'DESC' === $direction ? 'DESC' : 'ASC';
		$clauses['join']    = ( $clauses['join'] ?? '' ) . " LEFT JOIN {$postmeta} AS favr_ev_first ON ( favr_ev_first.post_id = {$posts}.ID AND favr_ev_first.meta_key = '" . ID::META_FIRST . "' )";
		$clauses['orderby'] = "( favr_ev_first.meta_value IS NULL ) DESC, CAST( favr_ev_first.meta_value AS SIGNED ) {$direction}, {$posts}.ID DESC";
		return $clauses;
	}

	/**
	 * Upcoming / Past views.
	 *
	 * @param array<string, string> $views Views.
	 * @return array<string, string>
	 */
	public function views( array $views ): array {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- list filter.
		$when = isset( $_GET['favr_when'] ) ? sanitize_key( wp_unslash( $_GET['favr_when'] ) ) : '';
		foreach ( array(
			'upcoming' => __( 'Upcoming', 'favr-events' ),
			'past'     => __( 'Past', 'favr-events' ),
		) as $key => $label ) {
			$views[ 'favr_' . $key ] = sprintf(
				'<a href="%1$s"%2$s>%3$s</a>',
				esc_url( add_query_arg( 'favr_when', $key, admin_url( 'edit.php?post_type=' . ID::POST_TYPE ) ) ),
				$when === $key ? ' class="current" aria-current="page"' : '',
				esc_html( $label )
			);
		}
		return $views;
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
