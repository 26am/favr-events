<?php
/**
 * Elementor Pro dynamic tag: an event link.
 *
 * @package FavrEvents
 */

declare(strict_types=1);

namespace FavrEvents\Integration\Elementor\Tags;

use Elementor\Controls_Manager;
use Elementor\Core\DynamicTags\Data_Tag;
use Elementor\Modules\DynamicTags\Module;
use FavrEvents\Integration\FieldValues;

/**
 * "Favr Events → Event Link": register, join online, map, add to calendar, host.
 */
final class UrlTag extends Data_Tag {

	/** Name. */
	public function get_name(): string {
		return 'favr-event-url';
	}

	/** Title. */
	public function get_title(): string {
		return __( 'Event Link', 'favr-events' );
	}

	/** Group. */
	public function get_group(): string {
		return 'favr-events';
	}

	/** Categories. */
	public function get_categories(): array {
		return array( Module::URL_CATEGORY );
	}

	/** Controls. */
	protected function register_controls(): void {
		$this->add_control(
			'key',
			array(
				'label'   => __( 'Link', 'favr-events' ),
				'type'    => Controls_Manager::SELECT,
				'default' => 'registration',
				'options' => FieldValues::urlOptions(),
			)
		);
	}

	/**
	 * Value.
	 *
	 * @param array<string, mixed> $options Options.
	 */
	public function get_value( array $options = array() ): string {
		$event = FieldValues::event();
		return $event ? FieldValues::url( $event, (string) $this->get_settings( 'key' ) ) : '';
	}
}
