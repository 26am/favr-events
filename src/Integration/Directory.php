<?php
/**
 * Favr Directory integration (optional).
 *
 * @package FavrEvents
 */

declare(strict_types=1);

namespace FavrEvents\Integration;

use FavrEvents\Frontend\Calendar;
use FavrEvents\Model\Repository;

/**
 * A business profile lists the events that business hosts.
 */
final class Directory {

	/** Hook. */
	public function hook(): void {
		add_action( 'favr_directory_after_profile', array( $this, 'upcoming' ), 20, 2 );
	}

	/**
	 * Upcoming events on a business profile.
	 *
	 * @param object $business Favr Directory business.
	 * @param string $context  Render context.
	 */
	public function upcoming( $business, $context = '' ): void {
		if ( ! is_object( $business ) || ! method_exists( $business, 'id' ) ) {
			return;
		}
		$id = (int) $business->id();
		if ( ! Repository::upcoming( 1, array( 'host' => $id ) ) ) {
			return;
		}
		$html = Calendar::render(
			array(
				'view'    => 'compact',
				'host'    => $id,
				'limit'   => 5,
				'filters' => '0',
			)
		);
		echo '<section class="favr-section favr-business-events"><h2 class="favr-section__title">' . esc_html__( 'Upcoming events', 'favr-events' ) . '</h2>' . $html . '</section>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- template output is escaped.
	}
}
