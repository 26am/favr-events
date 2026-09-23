<?php
/**
 * After the description: more dates and the host.
 *
 * Override: yourtheme/favr-events/single-after.php
 *
 * @package FavrEvents
 *
 * @var FavrEvents\Model\Event $event Event.
 */

use FavrEvents\Frontend\Format;
use FavrEvents\Support\Settings;

defined( 'ABSPATH' ) || exit;

$favr_now      = new DateTimeImmutable( 'now', wp_timezone() );
$favr_upcoming = $event->repeats() ? $event->occurrences( $favr_now, $favr_now->modify( '+1 year' ), 7 ) : array();
$favr_host     = $event->hostId();
$favr_org      = $event->text( 'organizer_name' );
?>
<?php if ( count( $favr_upcoming ) > 1 ) : ?>
	<section class="favr-ev-more">
		<h2><?php esc_html_e( 'Upcoming dates', 'favr-events' ); ?></h2>
		<ul>
			<?php foreach ( $favr_upcoming as $favr_o ) : ?>
				<li><a href="<?php echo esc_url( Format::url( $event, $favr_o['start'] ) ); ?>"><?php echo esc_html( Format::when( $event, $favr_o['start'], $favr_o['end'] ) ); ?></a></li>
			<?php endforeach; ?>
		</ul>
	</section>
<?php endif; ?>

<?php if ( $favr_host || '' !== $favr_org ) : ?>
	<section class="favr-ev-host">
		<h2><?php esc_html_e( 'Hosted by', 'favr-events' ); ?></h2>
		<?php if ( $favr_host ) : ?>
			<?php $favr_logo = (int) get_post_meta( $favr_host, 'favr_logo', true ); ?>
			<a class="favr-ev-host__card" href="<?php echo esc_url( (string) get_permalink( $favr_host ) ); ?>">
				<?php if ( $favr_logo ) : ?>
					<?php
					echo wp_get_attachment_image(
						$favr_logo,
						'thumbnail',
						false,
						array(
							'class' => 'favr-ev-host__logo',
							'alt'   => '',
						)
					);
					?>
				<?php endif; ?>
				<span>
					<strong><?php echo esc_html( get_the_title( $favr_host ) ); ?></strong>
					<?php $favr_tagline = (string) get_post_meta( $favr_host, 'favr_tagline', true ); ?>
					<?php if ( '' !== $favr_tagline ) : ?>
						<small><?php echo esc_html( $favr_tagline ); ?></small>
					<?php endif; ?>
				</span>
			</a>
		<?php else : ?>
			<p><strong><?php echo esc_html( $favr_org ); ?></strong></p>
		<?php endif; ?>
		<?php
		$favr_contact = array_filter(
			array(
				'' !== $event->text( 'organizer_email' ) ? '<a href="mailto:' . esc_attr( antispambot( $event->text( 'organizer_email' ) ) ) . '">' . esc_html( antispambot( $event->text( 'organizer_email' ) ) ) . '</a>' : '',
				'' !== $event->text( 'organizer_phone' ) ? '<a href="tel:' . esc_attr( preg_replace( '/[^0-9+]/', '', $event->text( 'organizer_phone' ) ) ) . '">' . esc_html( $event->text( 'organizer_phone' ) ) . '</a>' : '',
				'' !== $event->text( 'organizer_url' ) ? '<a href="' . esc_url( $event->text( 'organizer_url' ) ) . '" target="_blank" rel="noopener">' . esc_html__( 'Website', 'favr-events' ) . '</a>' : '',
			)
		);
		if ( $favr_contact ) {
			echo '<p class="favr-ev-host__contact">' . implode( ' · ', $favr_contact ) . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- each part escaped above.
		}
		?>
	</section>
<?php endif; ?>

<p class="favr-ev-back"><a href="<?php echo esc_url( Settings::eventsUrl() ); ?>">← <?php esc_html_e( 'All events', 'favr-events' ); ?></a></p>
