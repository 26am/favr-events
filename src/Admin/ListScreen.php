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
 * Adds When and Host columns, sorts by date, and filters upcoming/past.
 */
final class ListScreen {

	/** Hook. */
	public function hook(): void {
		add_filter( 'manage_' . ID::POST_TYPE . '_posts_columns', array( $this, 'columns' ) );
		add_action( 'manage_' . ID::POST_TYPE . '_posts_custom_column', array( $this, 'column' ), 10, 2 );
		add_filter( 'manage_edit-' . ID::POST_TYPE . '_sortable_columns', array( $this, 'sortable' ) );
		add_action( 'pre_get_posts', array( $this, 'query' ) );
		add_filter( 'views_edit-' . ID::POST_TYPE, array( $this, 'views' ) );
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
	 * Default: upcoming first; ?favr_when=past for past events.
	 *
	 * @param \WP_Query $query Query.
	 */
	public function query( \WP_Query $query ): void {
		if ( ! is_admin() || ! $query->is_main_query() || ID::POST_TYPE !== $query->get( 'post_type' ) ) {
			return;
		}
		$orderby = (string) $query->get( 'orderby' );
		if ( '' === $orderby || 'favr_when' === $orderby ) {
			$query->set( 'meta_key', ID::META_FIRST );
			$query->set( 'orderby', 'meta_value_num' );
			if ( '' === $orderby ) {
				$query->set( 'order', 'ASC' );
			}
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- list filter.
		$when = isset( $_GET['favr_when'] ) ? sanitize_key( wp_unslash( $_GET['favr_when'] ) ) : '';
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
}
