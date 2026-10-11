<?php
/**
 * Activation, upgrades, capabilities.
 *
 * @package FavrEvents
 */

declare(strict_types=1);

namespace FavrEvents\Model;

use FavrEvents\Schema\Identifiers as ID;
use FavrEvents\Support\Settings;

/**
 * Grants event capabilities to administrators and editors, creates the Events page and a few
 * starter categories, and flushes rewrites.
 */
final class Activation {

	/** Activate. */
	public static function activate(): void {
		( new Registrar() )->registerPostType();
		( new Registrar() )->registerTaxonomy();
		self::install();
		flush_rewrite_rules();
	}

	/** Deactivate. */
	public static function deactivate(): void {
		wp_clear_scheduled_hook( 'favr_core_uploads_cleanup' ); // Other active Favr plugins reschedule it.
		flush_rewrite_rules();
	}

	/** Run install steps when the stored version is older. */
	public static function maybeUpgrade(): void {
		if ( version_compare( (string) get_option( ID::OPTION_VERSION, '0' ), FAVR_EVENTS_VERSION, '<' ) ) {
			self::install();
		}
	}

	/**
	 * Primitive capabilities.
	 *
	 * @return list<string>
	 */
	public static function caps(): array {
		$p = ID::CAP_PLURAL;
		return array( "edit_{$p}", "edit_others_{$p}", "edit_private_{$p}", "edit_published_{$p}", "publish_{$p}", "read_private_{$p}", "delete_{$p}", "delete_others_{$p}", "delete_private_{$p}", "delete_published_{$p}", ID::CAP_SETTINGS );
	}

	/** Idempotent install. */
	public static function install(): void {
		foreach ( array( 'administrator', 'editor' ) as $role_name ) {
			$role = get_role( $role_name );
			if ( ! $role ) {
				continue;
			}
			foreach ( self::caps() as $cap ) {
				if ( ID::CAP_SETTINGS !== $cap || 'administrator' === $role_name ) {
					$role->add_cap( $cap );
				}
			}
		}

		$page = (int) Settings::get( 'events_page' );
		if ( ! $page || ! get_post( $page ) ) {
			// Many sites already have an Events page: use it (content untouched) rather than
			// creating /events-2/. Staff then place the Events block or Elementor widget on it.
			$existing = get_page_by_path( 'events', OBJECT, 'page' );
			if ( $existing instanceof \WP_Post && 'publish' === $existing->post_status ) {
				Settings::update( array( 'events_page' => (int) $existing->ID ) );
				$page = (int) $existing->ID;
			}
		}
		if ( ! $page || ! get_post( $page ) ) {
			$page = (int) wp_insert_post(
				array(
					'post_type'    => 'page',
					'post_status'  => 'publish',
					'post_title'   => __( 'Events', 'favr-events' ),
					'post_name'    => 'events',
					'post_content' => '<!-- wp:favr-events/events /-->',
				)
			);
			if ( $page ) {
				Settings::update( array( 'events_page' => $page ) );
			}
		}

		if ( taxonomy_exists( ID::TAX_CATEGORY ) && ! get_terms(
			array(
				'taxonomy'   => ID::TAX_CATEGORY,
				'hide_empty' => false,
				'number'     => 1,
			)
		) ) {
			foreach ( array( __( 'Networking', 'favr-events' ), __( 'Workshops & Training', 'favr-events' ), __( 'Ribbon Cuttings', 'favr-events' ), __( 'Community', 'favr-events' ), __( 'Member Meetings', 'favr-events' ) ) as $name ) {
				wp_insert_term( $name, ID::TAX_CATEGORY );
			}
		}
		self::reindexUntimed();
		update_option( ID::OPTION_VERSION, FAVR_EVENTS_VERSION );
	}

	/**
	 * Rebuild the date index of the events that have a date and no start time. They are all-day
	 * events (Event::isAllDay()); those saved before that rule were indexed as ending at
	 * 1:00 am, so the list filed them under Past during their own day. Safe to run again.
	 *
	 * @return int How many events were reindexed.
	 */
	public static function reindexUntimed(): int {
		$time = ID::meta( 'start_time' );
		$ids  = get_posts(
			array(
				'post_type'      => ID::POST_TYPE,
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- once per upgrade.
				'meta_query'     => array(
					array(
						'key'     => ID::META_FIRST,
						'compare' => 'EXISTS',
					),
					array(
						'relation' => 'OR',
						array(
							'key'     => $time,
							'compare' => 'NOT EXISTS',
						),
						array(
							'key'   => $time,
							'value' => '',
						),
					),
				),
			)
		);
		foreach ( $ids as $id ) {
			Event::reindex( (int) $id );
		}
		return count( $ids );
	}

	/** Remove capabilities (uninstall). */
	public static function uninstall(): void {
		foreach ( wp_roles()->role_objects as $role ) {
			foreach ( self::caps() as $cap ) {
				$role->remove_cap( $cap );
			}
		}
	}
}
