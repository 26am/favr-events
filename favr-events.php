<?php
/**
 * Plugin Name:       Favr Events
 * Plugin URI:        https://github.com/26am/favr-events
 * Description:       Events and a community calendar for Chambers of Commerce and associations, with member-submitted events, iCal feeds and event structured data. Part of Favr Sites.
 * Version:           1.0.0
 * Requires at least: 6.7
 * Requires PHP:      8.1
 * Author:            Favr Sites
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       favr-events
 * Domain Path:       /languages
 *
 * @package FavrEvents
 */

defined( 'ABSPATH' ) || exit;

define( 'FAVR_EVENTS_VERSION', '1.0.0' );
define( 'FAVR_EVENTS_FILE', __FILE__ );
define( 'FAVR_EVENTS_PATH', plugin_dir_path( __FILE__ ) );
define( 'FAVR_EVENTS_URL', plugin_dir_url( __FILE__ ) );

// Namespace-prefixed shared library (favr/core via Strauss), committed with the plugin.
require_once __DIR__ . '/vendor-prefixed/autoload.php';

spl_autoload_register(
	static function ( string $class_name ): void {
		$prefix = 'FavrEvents\\';
		if ( strncmp( $class_name, $prefix, strlen( $prefix ) ) !== 0 || str_starts_with( $class_name, 'FavrEvents\\Vendor\\' ) ) {
			return;
		}
		$file = __DIR__ . '/src/' . str_replace( '\\', '/', substr( $class_name, strlen( $prefix ) ) ) . '.php';
		if ( is_readable( $file ) ) {
			require_once $file;
		}
	}
);

register_activation_hook( __FILE__, array( \FavrEvents\Model\Activation::class, 'activate' ) );
register_deactivation_hook( __FILE__, array( \FavrEvents\Model\Activation::class, 'deactivate' ) );

\FavrEvents\Plugin::boot();
