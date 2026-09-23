<?php
/**
 * Elementor integration (optional).
 *
 * @package FavrEvents
 */

declare(strict_types=1);

namespace FavrEvents\Integration;

use FavrEvents\Vendor\FavrCore\Integrations\Elementor\Widget;

/**
 * Events and My Events widgets in the shared "Favr" category, and Elementor Pro dynamic tags for
 * single-event Theme Builder templates. Loaded only through Elementor's hooks.
 */
final class Elementor {

	/** Hook. */
	public function hook(): void {
		add_action( 'elementor/elements/categories_registered', array( Widget::class, 'registerCategory' ) );
		add_action( 'elementor/widgets/register', array( $this, 'widgets' ) );
		add_action( 'elementor/dynamic_tags/register', array( $this, 'tags' ) );
	}

	/**
	 * Widgets.
	 *
	 * @param object $manager Widgets manager.
	 */
	public function widgets( $manager ): void {
		$manager->register( new Elementor\EventsWidget() );
		$manager->register( new Elementor\MyEventsWidget() );
	}

	/**
	 * Tags.
	 *
	 * @param object $tags Dynamic tags manager.
	 */
	public function tags( $tags ): void {
		$tags->register_group( 'favr-events', array( 'title' => __( 'Favr Events', 'favr-events' ) ) );
		$tags->register( new Elementor\Tags\TextTag() );
		$tags->register( new Elementor\Tags\UrlTag() );
	}
}
