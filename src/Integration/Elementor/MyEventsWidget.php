<?php
/**
 * Elementor: My Events.
 *
 * @package FavrEvents
 */

declare(strict_types=1);

namespace FavrEvents\Integration\Elementor;

use FavrEvents\Editing\Submissions;
use FavrEvents\Vendor\FavrCore\Integrations\Elementor\Widget;

/**
 * Member event submission and management (same as the block and [favr_my_events]).
 */
final class MyEventsWidget extends Widget {

	/** Name. */
	public function get_name(): string {
		return 'favr-my-events';
	}

	/** Title. */
	public function get_title(): string {
		return __( 'My Events (submit & manage)', 'favr-events' );
	}

	/** Icon. */
	public function get_icon(): string {
		return 'eicon-form-horizontal';
	}

	/** Keywords. */
	public function get_keywords(): array {
		return array( 'events', 'submit', 'member', 'favr' );
	}

	/** Styles. */
	public function get_style_depends(): array {
		return array( 'favr-events' );
	}

	/** Settings. */
	protected function settings(): array {
		return array();
	}

	/**
	 * Render.
	 *
	 * @param array<string, mixed> $settings Settings.
	 */
	protected function output( array $settings ): string {
		return Submissions::render();
	}
}
