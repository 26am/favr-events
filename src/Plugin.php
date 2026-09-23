<?php
/**
 * Composition root.
 *
 * @package FavrEvents
 */

declare(strict_types=1);

namespace FavrEvents;

/**
 * Wires every service.
 */
final class Plugin {

	/**
	 * Booted.
	 *
	 * @var bool
	 */
	private static bool $booted = false;

	/** Boot once. */
	public static function boot(): void {
		if ( self::$booted ) {
			return;
		}
		self::$booted = true;

		add_action( 'init', array( self::class, 'loadTextDomain' ), 1 );
		( new Model\Registrar() )->hook();
		add_action( 'init', array( Model\Activation::class, 'maybeUpgrade' ), 20 );

		( new Frontend\Blocks() )->hook();
		( new Frontend\Single() )->hook();
		( new Frontend\Ical() )->hook();
		( new Frontend\Seo() )->hook();
		( new Integration\Directory() )->hook();
		( new Integration\Elementor() )->hook();
		( new Integration\BlockBindings() )->hook();

		( new Editing\Submissions() )->hook();
		( new Editing\UploadRoute() )->hook();
		( new Editing\Queues() )->hook();
		if ( ! has_action( 'favr_core_uploads_cleanup' ) ) {
			add_action( 'favr_core_uploads_cleanup', array( Vendor\FavrCore\Moderation\Uploads::class, 'cleanup' ) );
		}
		add_action(
			'init',
			static function (): void {
				if ( ! wp_next_scheduled( 'favr_core_uploads_cleanup' ) ) {
					wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'favr_core_uploads_cleanup' );
				}
			}
		);

		if ( is_admin() ) {
			( new Admin\Assets() )->hook();
			( new Admin\EditScreen() )->hook();
			( new Admin\ListScreen() )->hook();
			( new Admin\SettingsPage() )->hook();
			Vendor\FavrCore\Approvals\Inbox::boot();
		}

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			\WP_CLI::add_command( 'favr-events', Cli\Command::class );
		}

		/**
		 * Fires after Favr Events has wired its services.
		 */
		do_action( 'favr_events_loaded' );
	}

	/** Translations. */
	public static function loadTextDomain(): void {
		load_plugin_textdomain( 'favr-events', false, dirname( plugin_basename( FAVR_EVENTS_FILE ) ) . '/languages' );
	}
}
