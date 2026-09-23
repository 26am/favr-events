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
		update_option( ID::OPTION_VERSION, FAVR_EVENTS_VERSION );
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
