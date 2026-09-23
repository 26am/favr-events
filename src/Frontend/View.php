<?php
/**
 * Template rendering.
 *
 * @package FavrEvents
 */

declare(strict_types=1);

namespace FavrEvents\Frontend;

use FavrEvents\Schema\Identifiers as ID;
use FavrEvents\Vendor\FavrCore\Support\Template;

/**
 * Templates in /templates, overridable from yourtheme/favr-events/.
 */
final class View {

	/**
	 * Shared loader.
	 *
	 * @var Template|null
	 */
	private static ?Template $template = null;

	/**
	 * Render a template.
	 *
	 * @param string               $name Template name.
	 * @param array<string, mixed> $vars Variables.
	 */
	public static function render( string $name, array $vars = array() ): string {
		if ( null === self::$template ) {
			self::$template = new Template( FAVR_EVENTS_PATH . 'templates', ID::TEMPLATE_DIR, 'favr_events_template' );
		}
		return self::$template->render( $name, $vars );
	}
}
