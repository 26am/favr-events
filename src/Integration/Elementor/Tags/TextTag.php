<?php
/**
 * Elementor Pro dynamic tag: an event value as text.
 *
 * @package FavrEvents
 */

declare(strict_types=1);

namespace FavrEvents\Integration\Elementor\Tags;

use Elementor\Controls_Manager;
use Elementor\Core\DynamicTags\Tag;
use Elementor\Modules\DynamicTags\Module;
use FavrEvents\Integration\FieldValues;

/**
 * "Favr Events → Event Field" (date, time, where, cost…) for single-event templates.
 */
final class TextTag extends Tag {

	/** Name. */
	public function get_name(): string {
		return 'favr-event-text';
	}

	/** Title. */
	public function get_title(): string {
		return __( 'Event Field', 'favr-events' );
	}

	/** Group. */
	public function get_group(): string {
		return 'favr-events';
	}

	/** Categories. */
	public function get_categories(): array {
		return array( Module::TEXT_CATEGORY );
	}

	/** Controls. */
	protected function register_controls(): void {
		$this->add_control(
			'key',
			array(
				'label'   => __( 'Field', 'favr-events' ),
				'type'    => Controls_Manager::SELECT,
				'default' => 'when',
				'options' => FieldValues::textOptions(),
			)
		);
	}

	/** Render. */
	public function render(): void {
		$event = FieldValues::event();
		if ( $event ) {
			echo esc_html( FieldValues::text( $event, (string) $this->get_settings( 'key' ) ) );
		}
	}
}
