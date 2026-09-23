<?php
/**
 * Asset cache-busting.
 *
 * @package FavrEvents
 */

declare(strict_types=1);

namespace FavrEvents\Support;

use FavrEvents\Vendor\FavrCore\Support\AssetVersion as CoreVersion;

/**
 * Plugin version, plus file mtime under WP_DEBUG.
 */
final class AssetVersion {

	/**
	 * Version for a plugin-relative path.
	 *
	 * @param string $relative Path.
	 */
	public static function of( string $relative ): string {
		return CoreVersion::of( FAVR_EVENTS_PATH . $relative, FAVR_EVENTS_VERSION );
	}
}
