<?php
/**
 * One occurrence in a list.
 *
 * Override: yourtheme/favr-events/parts/card.php
 *
 * @package FavrEvents
 *
 * @var FavrEvents\Model\Event $event Event.
 * @var DateTimeImmutable      $start Start.
 * @var DateTimeImmutable      $end   End.
 */

use FavrEvents\Frontend\Format;

defined( 'ABSPATH' ) || exit;

$favr_url   = Format::url( $event, $start );
$favr_where = Format::where( $event );
$favr_host  = $event->organizerName();
$favr_thumb = get_the_post_thumbnail(
	$event->post(),
	'medium',
	array(
		'class'   => 'favr-ev-card__img',
		'loading' => 'lazy',
		'alt'     => '',
	)
);
?>
<article class="favr-ev-card<?php echo $event->isFeatured() ? ' is-featured' : ''; ?>">
	<a class="favr-ev-card__date" href="<?php echo esc_url( $favr_url ); ?>" aria-hidden="true" tabindex="-1">
		<span class="favr-ev-card__month"><?php echo esc_html( wp_date( 'M', $start->getTimestamp() ) ); ?></span>
		<span class="favr-ev-card__day"><?php echo esc_html( wp_date( 'j', $start->getTimestamp() ) ); ?></span>
		<span class="favr-ev-card__dow"><?php echo esc_html( wp_date( 'D', $start->getTimestamp() ) ); ?></span>
	</a>
	<div class="favr-ev-card__body">
		<h3 class="favr-ev-card__title"><a href="<?php echo esc_url( $favr_url ); ?>"><?php echo esc_html( $event->title() ); ?></a></h3>
		<p class="favr-ev-card__when"><?php echo esc_html( Format::when( $event, $start, $end ) ); ?><?php echo $event->repeats() ? ' <span class="favr-ev-tag">' . esc_html__( 'Repeats', 'favr-events' ) . '</span>' : ''; ?><?php echo $event->isFeatured() ? ' <span class="favr-ev-tag favr-ev-tag--featured">' . esc_html__( 'Featured', 'favr-events' ) . '</span>' : ''; ?></p>
		<?php if ( '' !== $favr_where || '' !== $favr_host ) : ?>
			<p class="favr-ev-card__meta">
				<?php echo esc_html( $favr_where ); ?>
				<?php if ( '' !== $favr_host ) : ?>
					<?php echo '' !== $favr_where ? ' · ' : ''; ?><?php /* translators: %s: host name. */ echo esc_html( sprintf( __( 'Hosted by %s', 'favr-events' ), $favr_host ) ); ?>
				<?php endif; ?>
			</p>
		<?php endif; ?>
	</div>
	<?php if ( $favr_thumb ) : ?>
		<a class="favr-ev-card__media" href="<?php echo esc_url( $favr_url ); ?>" aria-hidden="true" tabindex="-1"><?php echo $favr_thumb; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core markup. ?></a>
	<?php endif; ?>
</article>
