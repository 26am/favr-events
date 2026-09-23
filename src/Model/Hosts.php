<?php
/**
 * Directory businesses that can host events (only when Favr Directory is active).
 *
 * @package FavrEvents
 */

declare(strict_types=1);

namespace FavrEvents\Model;

use FavrEvents\Schema\Identifiers as ID;

/**
 * Thin, optional bridge to Favr Directory: everything goes through public WordPress APIs and
 * Directory filters, so Events works the same with or without it.
 */
final class Hosts {

	/**
	 * Cached options.
	 *
	 * @var array<string, string>|null
	 */
	private static ?array $options = null;

	/** Whether Favr Directory is active. */
	public static function available(): bool {
		return post_type_exists( ID::DIRECTORY_TYPE );
	}

	/**
	 * Published listings as select options (id => name).
	 *
	 * @return array<string, string>
	 */
	public static function options(): array {
		if ( null !== self::$options ) {
			return self::$options;
		}
		self::$options = array();
		if ( ! self::available() ) {
			return self::$options;
		}
		$posts = get_posts(
			array(
				'post_type'      => ID::DIRECTORY_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => 1000, // phpcs:ignore WordPress.WP.PostsPerPage.posts_per_page_posts_per_page -- bounded, indexed.
				'orderby'        => 'title',
				'order'          => 'ASC',
				'no_found_rows'  => true,
			)
		);
		foreach ( $posts as $post ) {
			self::$options[ (string) $post->ID ] = get_the_title( $post );
		}
		return self::$options;
	}

	/**
	 * Listings a person may host events for (the ones they can edit in the Directory).
	 *
	 * @param int $user_id User.
	 * @return list<int>
	 */
	public static function forUser( int $user_id ): array {
		if ( ! self::available() || $user_id <= 0 ) {
			return array();
		}
		/** This filter is documented in Favr Directory: src/Editing/Editors.php */
		$ids = (array) apply_filters( 'favr_directory_listings_for_user', array(), $user_id );
		return array_values( array_filter( array_map( 'intval', $ids ), static fn( int $id ): bool => 'publish' === get_post_status( $id ) ) );
	}

	/** Forget cached options. */
	public static function reset(): void {
		self::$options = null;
	}
}
