<?php
/**
 * The few WordPress classes the unit tests need as type hints.
 *
 * @package FavrEvents
 */

declare(strict_types=1);

if ( ! class_exists( 'WP_Post' ) ) {
	/**
	 * Just the properties the code under test reads.
	 */
	class WP_Post {
		public int $ID             = 0;
		public string $post_type    = 'favr_event';
		public string $post_status  = 'publish';
		public string $post_title   = '';
		public string $post_content = '';
		public string $post_excerpt = '';
		public string $filter       = 'raw';
	}
}
