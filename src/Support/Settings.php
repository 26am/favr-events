<?php
/**
 * Typed access to the plugin settings option.
 *
 * @package FavrEvents
 */

declare(strict_types=1);

namespace FavrEvents\Support;

use FavrEvents\Schema\Identifiers as ID;

/**
 * Settings are one serialized option; defaults live here.
 */
final class Settings {

	/**
	 * Cache.
	 *
	 * @var array<string, mixed>|null
	 */
	private static ?array $cache = null;

	/**
	 * Defaults.
	 *
	 * @return array<string, mixed>
	 */
	public static function defaults(): array {
		return array(
			'events_page'  => 0,
			'event_slug'   => 'events',
			'default_view' => 'list',     // list | month.
			'per_page'     => 10,
			'submissions'  => 'members',  // members | users | off.
			'notify_email' => '',
			'accent_color' => '',
			'delete_data'  => '0',
		);
	}

	/**
	 * All settings.
	 *
	 * @return array<string, mixed>
	 */
	public static function all(): array {
		if ( null === self::$cache ) {
			$stored      = get_option( ID::OPTION_SETTINGS, array() );
			self::$cache = array_merge( self::defaults(), is_array( $stored ) ? $stored : array() );
		}
		return self::$cache;
	}

	/**
	 * One setting.
	 *
	 * @param string $key Key.
	 * @return mixed
	 */
	public static function get( string $key ) {
		return self::all()[ $key ] ?? ( self::defaults()[ $key ] ?? null );
	}

	/**
	 * Update some settings.
	 *
	 * @param array<string, mixed> $values Values.
	 */
	public static function update( array $values ): void {
		update_option( ID::OPTION_SETTINGS, array_merge( self::all(), $values ) );
		self::flush();
	}

	/** Forget the cache. */
	public static function flush(): void {
		self::$cache = null;
	}

	/** The public events page URL (falls back to the home page). */
	public static function eventsUrl(): string {
		$id = (int) self::get( 'events_page' );
		return $id && 'publish' === get_post_status( $id ) ? (string) get_permalink( $id ) : home_url( '/' );
	}

	/** Staff notification address. */
	public static function staffEmail(): string {
		$email = (string) self::get( 'notify_email' );
		return is_email( $email ) ? $email : (string) get_option( 'admin_email' );
	}
}
