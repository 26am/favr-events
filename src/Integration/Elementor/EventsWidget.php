<?php
/**
 * Elementor: Events.
 *
 * @package FavrEvents
 */

declare(strict_types=1);

namespace FavrEvents\Integration\Elementor;

use FavrEvents\Frontend\Calendar;
use FavrEvents\Schema\Identifiers as ID;
use FavrEvents\Vendor\FavrCore\Integrations\Elementor\Widget;

/**
 * List, month calendar or compact list (same renderer as the block and [favr_events]).
 */
final class EventsWidget extends Widget {

	/** Name. */
	public function get_name(): string {
		return 'favr-events';
	}

	/** Title. */
	public function get_title(): string {
		return __( 'Events', 'favr-events' );
	}

	/** Icon. */
	public function get_icon(): string {
		return 'eicon-calendar';
	}

	/** Keywords. */
	public function get_keywords(): array {
		return array( 'events', 'calendar', 'upcoming', 'favr' );
	}

	/** Styles. */
	public function get_style_depends(): array {
		return array( 'favr-events' );
	}

	/** Settings. */
	protected function settings(): array {
		$cats  = array( '' => __( 'All categories', 'favr-events' ) );
		$terms = get_terms(
			array(
				'taxonomy'   => ID::TAX_CATEGORY,
				'hide_empty' => false,
			)
		);
		foreach ( is_array( $terms ) ? $terms : array() as $term ) {
			$cats[ $term->slug ] = $term->name;
		}
		return array(
			'title'    => array( 'label' => __( 'Heading', 'favr-events' ) ),
			'view'     => array(
				'label'   => __( 'Layout', 'favr-events' ),
				'type'    => 'select',
				'options' => array(
					''        => __( 'Default (settings)', 'favr-events' ),
					'list'    => __( 'List', 'favr-events' ),
					'month'   => __( 'Month calendar', 'favr-events' ),
					'compact' => __( 'Compact list (sidebars, home page)', 'favr-events' ),
				),
			),
			'category' => array(
				'label'   => __( 'Category', 'favr-events' ),
				'type'    => 'select',
				'options' => $cats,
			),
			'limit'    => array(
				'label'       => __( 'Events per page', 'favr-events' ),
				'type'        => 'number',
				'min'         => 0,
				'max'         => 50,
				'default'     => 0,
				'description' => __( '0 uses the Events setting.', 'favr-events' ),
			),
			'featured' => array(
				'label' => __( 'Featured events only', 'favr-events' ),
				'type'  => 'switcher',
			),
			'filters'  => array(
				'label'   => __( 'View and category filters', 'favr-events' ),
				'type'    => 'switcher',
				'default' => true,
			),
		);
	}

	/**
	 * Render.
	 *
	 * @param array<string, mixed> $settings Settings.
	 */
	protected function output( array $settings ): string {
		$atts = array(
			'title'    => (string) $settings['title'],
			'category' => (string) $settings['category'],
			'featured' => '1' === (string) $settings['featured'] ? '1' : '0',
			'filters'  => '1' === (string) $settings['filters'] ? '1' : '0',
		);
		if ( '' !== (string) $settings['view'] ) {
			$atts['view'] = (string) $settings['view'];
		}
		if ( (int) $settings['limit'] > 0 ) {
			$atts['limit'] = (int) $settings['limit'];
		}
		return Calendar::render( $atts );
	}
}
