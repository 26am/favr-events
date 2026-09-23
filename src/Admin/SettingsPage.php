<?php
/**
 * Events → Settings.
 *
 * @package FavrEvents
 */

declare(strict_types=1);

namespace FavrEvents\Admin;

use FavrEvents\Frontend\Ical;
use FavrEvents\Schema\Identifiers as ID;
use FavrEvents\Support\Settings;

/**
 * One settings screen using the Settings API.
 */
final class SettingsPage {

	public const SLUG = 'favr-events-settings';

	/** Hook. */
	public function hook(): void {
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_init', array( $this, 'register' ) );
	}

	/** Menu. */
	public function menu(): void {
		add_submenu_page( 'edit.php?post_type=' . ID::POST_TYPE, __( 'Event Settings', 'favr-events' ), __( 'Settings', 'favr-events' ), ID::CAP_SETTINGS, self::SLUG, array( $this, 'render' ) );
	}

	/** Register. */
	public function register(): void {
		register_setting(
			'favr_events',
			ID::OPTION_SETTINGS,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize' ),
				'default'           => Settings::defaults(),
			)
		);
	}

	/**
	 * Sanitize.
	 *
	 * @param mixed $input Raw.
	 * @return array<string, mixed>
	 */
	public function sanitize( $input ): array {
		$input = is_array( $input ) ? $input : array();
		$old   = Settings::all();
		$out   = array(
			'events_page'  => absint( $input['events_page'] ?? 0 ),
			'event_slug'   => sanitize_title( (string) ( $input['event_slug'] ?? '' ) ) ?: 'events',
			'default_view' => in_array( $input['default_view'] ?? '', array( 'list', 'month' ), true ) ? $input['default_view'] : 'list',
			'per_page'     => min( 50, max( 1, absint( $input['per_page'] ?? 10 ) ) ),
			'submissions'  => in_array( $input['submissions'] ?? '', array( 'members', 'users', 'off' ), true ) ? $input['submissions'] : 'members',
			'notify_email' => (string) sanitize_email( (string) ( $input['notify_email'] ?? '' ) ),
			'accent_color' => (string) sanitize_hex_color( (string) ( $input['accent_color'] ?? '' ) ),
			'delete_data'  => empty( $input['delete_data'] ) ? '0' : '1',
		);
		if ( $out['event_slug'] !== $old['event_slug'] ) {
			add_action( 'shutdown', 'flush_rewrite_rules' );
		}
		Settings::flush();
		return $out;
	}

	/** Render. */
	public function render(): void {
		if ( ! current_user_can( ID::CAP_SETTINGS ) ) {
			return;
		}
		$s   = Settings::all();
		$opt = ID::OPTION_SETTINGS;
		?>
		<div class="wrap favr-settings">
			<h1><?php esc_html_e( 'Event Settings', 'favr-events' ); ?></h1>
			<form method="post" action="options.php" class="favr-card">
				<?php settings_fields( 'favr_events' ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="favr-ev-page"><?php esc_html_e( 'Events page', 'favr-events' ); ?></label></th>
						<td>
							<?php
							wp_dropdown_pages(
								array(
									'name'              => esc_attr( $opt ) . '[events_page]',
									'id'                => 'favr-ev-page',
									'selected'          => (int) $s['events_page'],
									'show_option_none'  => esc_html__( '— Select —', 'favr-events' ),
									'option_none_value' => '0',
								)
							);
							?>
							<p class="description"><?php esc_html_e( 'The page showing the calendar (it contains the Events block). Used for breadcrumbs and “All events” links.', 'favr-events' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="favr-ev-slug"><?php esc_html_e( 'Event URL', 'favr-events' ); ?></label></th>
						<td><code><?php echo esc_html( trailingslashit( home_url() ) ); ?></code><input type="text" id="favr-ev-slug" class="regular-text code" name="<?php echo esc_attr( $opt ); ?>[event_slug]" value="<?php echo esc_attr( (string) $s['event_slug'] ); ?>"><code>/my-event/</code></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Default view', 'favr-events' ); ?></th>
						<td>
							<label><input type="radio" name="<?php echo esc_attr( $opt ); ?>[default_view]" value="list" <?php checked( $s['default_view'], 'list' ); ?>> <?php esc_html_e( 'List', 'favr-events' ); ?></label>&nbsp;&nbsp;
							<label><input type="radio" name="<?php echo esc_attr( $opt ); ?>[default_view]" value="month" <?php checked( $s['default_view'], 'month' ); ?>> <?php esc_html_e( 'Month calendar', 'favr-events' ); ?></label>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="favr-ev-per"><?php esc_html_e( 'Events per page', 'favr-events' ); ?></label></th>
						<td><input type="number" id="favr-ev-per" class="small-text" min="1" max="50" name="<?php echo esc_attr( $opt ); ?>[per_page]" value="<?php echo esc_attr( (string) $s['per_page'] ); ?>"></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Who can submit events', 'favr-events' ); ?></th>
						<td>
							<fieldset>
								<label><input type="radio" name="<?php echo esc_attr( $opt ); ?>[submissions]" value="members" <?php checked( $s['submissions'], 'members' ); ?>> <?php esc_html_e( 'Current members (requires Favr Members)', 'favr-events' ); ?></label><br>
								<label><input type="radio" name="<?php echo esc_attr( $opt ); ?>[submissions]" value="users" <?php checked( $s['submissions'], 'users' ); ?>> <?php esc_html_e( 'Anyone with an account', 'favr-events' ); ?></label><br>
								<label><input type="radio" name="<?php echo esc_attr( $opt ); ?>[submissions]" value="off" <?php checked( $s['submissions'], 'off' ); ?>> <?php esc_html_e( 'Nobody (staff add events)', 'favr-events' ); ?></label>
							</fieldset>
							<p class="description"><?php esc_html_e( 'Submissions appear in Approvals before they’re published. Members submit from their dashboard (“My Events”) or any page with [favr_my_events].', 'favr-events' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="favr-ev-notify"><?php esc_html_e( 'Notify', 'favr-events' ); ?></label></th>
						<td><input type="email" id="favr-ev-notify" class="regular-text" name="<?php echo esc_attr( $opt ); ?>[notify_email]" value="<?php echo esc_attr( (string) $s['notify_email'] ); ?>" placeholder="<?php echo esc_attr( (string) get_option( 'admin_email' ) ); ?>"><p class="description"><?php esc_html_e( 'Who hears about new submissions. Blank uses the site admin email.', 'favr-events' ); ?></p></td>
					</tr>
					<tr>
						<th scope="row"><label for="favr-ev-accent"><?php esc_html_e( 'Accent color', 'favr-events' ); ?></label></th>
						<td><input type="text" id="favr-ev-accent" class="favr-color" name="<?php echo esc_attr( $opt ); ?>[accent_color]" value="<?php echo esc_attr( (string) $s['accent_color'] ); ?>"><p class="description"><?php esc_html_e( 'Blank uses your theme’s primary color.', 'favr-events' ); ?></p></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'On uninstall', 'favr-events' ); ?></th>
						<td><label><input type="checkbox" name="<?php echo esc_attr( $opt ); ?>[delete_data]" value="1" <?php checked( $s['delete_data'], '1' ); ?>> <?php esc_html_e( 'Delete all events, categories and settings when the plugin is deleted', 'favr-events' ); ?></label></td>
					</tr>
				</table>
				<?php submit_button(); ?>
			</form>
			<div class="favr-card">
				<h2><?php esc_html_e( 'Show events anywhere', 'favr-events' ); ?></h2>
				<ul class="favr-help">
					<li><strong><?php esc_html_e( 'Block:', 'favr-events' ); ?></strong> <?php esc_html_e( 'add the “Events” block (list, month calendar or compact list).', 'favr-events' ); ?></li>
					<li><code>[favr_events]</code> · <code>[favr_events view="month"]</code> · <code>[favr_events view="compact" limit="5" filters="0"]</code> · <code>[favr_events category="networking"]</code></li>
					<li><?php esc_html_e( 'Member submissions:', 'favr-events' ); ?> <code>[favr_my_events]</code></li>
					<li><?php esc_html_e( 'Calendar feed (Google, Apple, Outlook):', 'favr-events' ); ?> <code><?php echo esc_html( Ical::feedUrl() ); ?></code></li>
				</ul>
			</div>
		</div>
		<?php
	}
}
