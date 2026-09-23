<?php
/**
 * Event details, shown above the description on an event page.
 *
 * Override: yourtheme/favr-events/single-details.php
 *
 * @package FavrEvents
 *
 * @var FavrEvents\Model\Event $event      Event.
 * @var array|null             $occurrence { start, end } being viewed.
 * @var string                 $google     Google Calendar link.
 * @var string                 $ics        .ics download link.
 */

use FavrEvents\Frontend\Format;
use FavrEvents\Schema\Identifiers as ID;

defined( 'ABSPATH' ) || exit;

$favr_past   = $occurrence && $occurrence['end']->getTimestamp() < time();
$favr_cost   = $event->text( 'cost' );
$favr_reg    = $event->text( 'registration_url' );
$favr_online = 'in_person' !== $event->attendance() ? $event->text( 'online_url' ) : '';
$favr_map    = $event->hasPlace() ? $event->mapUrl() : '';
$favr_terms  = get_the_terms( $event->post(), ID::TAX_CATEGORY );
?>
<section class="favr-ev-details" aria-label="<?php esc_attr_e( 'Event details', 'favr-events' ); ?>">
	<?php if ( $favr_past ) : ?>
		<p class="favr-ev-details__ended"><?php esc_html_e( 'This event has ended.', 'favr-events' ); ?></p>
	<?php endif; ?>

	<dl class="favr-ev-details__list">
		<?php if ( $occurrence ) : ?>
			<div>
				<dt><?php esc_html_e( 'When', 'favr-events' ); ?></dt>
				<dd>
					<?php echo esc_html( Format::when( $event, $occurrence['start'], $occurrence['end'] ) ); ?>
					<?php if ( $event->repeats() ) : ?>
						<br><small><?php echo esc_html( Format::rule( $event ) ); ?></small>
					<?php endif; ?>
				</dd>
			</div>
		<?php endif; ?>

		<?php if ( $event->hasPlace() ) : ?>
			<div>
				<dt><?php esc_html_e( 'Where', 'favr-events' ); ?></dt>
				<dd>
					<?php if ( '' !== $event->text( 'venue' ) ) : ?>
						<strong><?php echo esc_html( $event->text( 'venue' ) ); ?></strong><br>
					<?php endif; ?>
					<?php echo esc_html( implode( ', ', $event->addressLines() ) ); ?>
					<?php if ( '' !== $favr_map ) : ?>
						<br><a href="<?php echo esc_url( $favr_map ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Map & directions', 'favr-events' ); ?></a>
					<?php endif; ?>
				</dd>
			</div>
		<?php endif; ?>

		<?php if ( 'in_person' !== $event->attendance() ) : ?>
			<div>
				<dt><?php esc_html_e( 'Online', 'favr-events' ); ?></dt>
				<dd>
					<?php if ( '' !== $favr_online && ! $favr_past ) : ?>
						<a href="<?php echo esc_url( $favr_online ); ?>" target="_blank" rel="noopener nofollow"><?php esc_html_e( 'Join online', 'favr-events' ); ?></a>
					<?php else : ?>
						<?php esc_html_e( 'This is an online event.', 'favr-events' ); ?>
					<?php endif; ?>
				</dd>
			</div>
		<?php endif; ?>

		<?php if ( '' !== $favr_cost ) : ?>
			<div>
				<dt><?php esc_html_e( 'Cost', 'favr-events' ); ?></dt>
				<dd><?php echo esc_html( $favr_cost ); ?></dd>
			</div>
		<?php endif; ?>

		<?php if ( $favr_terms && ! is_wp_error( $favr_terms ) ) : ?>
			<div>
				<dt><?php esc_html_e( 'Category', 'favr-events' ); ?></dt>
				<dd><?php echo esc_html( implode( ', ', wp_list_pluck( $favr_terms, 'name' ) ) ); ?></dd>
			</div>
		<?php endif; ?>
	</dl>

	<?php if ( ! $favr_past ) : ?>
		<div class="favr-ev-details__actions">
			<?php if ( '' !== $favr_reg ) : ?>
				<a class="favr-ev-btn favr-ev-btn--primary" href="<?php echo esc_url( $favr_reg ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Register', 'favr-events' ); ?></a>
			<?php endif; ?>
			<details class="favr-ev-add">
				<summary class="favr-ev-btn"><?php esc_html_e( 'Add to calendar', 'favr-events' ); ?></summary>
				<ul>
					<li><a href="<?php echo esc_url( $google ); ?>" target="_blank" rel="noopener nofollow"><?php esc_html_e( 'Google Calendar', 'favr-events' ); ?></a></li>
					<li><a href="<?php echo esc_url( $ics ); ?>" rel="nofollow"><?php esc_html_e( 'Apple / Outlook (.ics)', 'favr-events' ); ?></a></li>
				</ul>
			</details>
		</div>
	<?php endif; ?>
</section>
