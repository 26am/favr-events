<?php
/**
 * Month calendar.
 *
 * Override: yourtheme/favr-events/month.php
 *
 * @package FavrEvents
 *
 * @var array                   $atts     Attributes.
 * @var DateTimeImmutable       $first    First day of the month.
 * @var string                  $prev     Previous "Y-m".
 * @var string                  $next     Next "Y-m".
 * @var list<DateTimeImmutable> $days     Grid days.
 * @var array                   $by_day   Y-m-d => occurrences.
 * @var list<DateTimeImmutable> $weekdays First week (for headings).
 * @var string                  $today    Y-m-d.
 * @var string                  $base     Base URL.
 * @var string                  $category Category.
 * @var string                  $ical     Feed URL.
 */

use FavrEvents\Frontend\Format;
use FavrEvents\Frontend\View;
use FavrEvents\Schema\Identifiers as ID;

defined( 'ABSPATH' ) || exit;

$favr_nav = static fn( string $month ): string => add_query_arg(
	array_filter(
		array(
			ID::QV_VIEW  => 'month',
			ID::QV_MONTH => $month,
			ID::QV_CAT   => $category,
		)
	),
	$base
);
$favr_ym  = $first->format( 'Y-m' );
?>
<div class="favr-ev favr-ev--month">
	<?php if ( '' !== (string) $atts['title'] ) : ?>
		<h2 class="favr-ev__title"><?php echo esc_html( (string) $atts['title'] ); ?></h2>
	<?php endif; ?>
	<?php echo View::render( 'parts/filters', get_defined_vars() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- template escapes. ?>

	<div class="favr-ev-month__head">
		<a class="favr-ev-btn" href="<?php echo esc_url( $favr_nav( $prev ) ); ?>" rel="nofollow"><span aria-hidden="true">←</span><span class="screen-reader-text"><?php esc_html_e( 'Previous month', 'favr-events' ); ?></span></a>
		<h3 class="favr-ev-month__title"><?php echo esc_html( wp_date( 'F Y', $first->getTimestamp() ) ); ?></h3>
		<a class="favr-ev-btn" href="<?php echo esc_url( $favr_nav( $next ) ); ?>" rel="nofollow"><span aria-hidden="true">→</span><span class="screen-reader-text"><?php esc_html_e( 'Next month', 'favr-events' ); ?></span></a>
	</div>

	<div class="favr-ev-grid" role="table" aria-label="<?php echo esc_attr( wp_date( 'F Y', $first->getTimestamp() ) ); ?>">
		<div class="favr-ev-grid__row favr-ev-grid__row--head" role="row">
			<?php foreach ( $weekdays as $favr_wd ) : ?>
				<div class="favr-ev-grid__wd" role="columnheader"><abbr title="<?php echo esc_attr( wp_date( 'l', $favr_wd->getTimestamp() ) ); ?>"><?php echo esc_html( wp_date( 'D', $favr_wd->getTimestamp() ) ); ?></abbr></div>
			<?php endforeach; ?>
		</div>
		<?php foreach ( array_chunk( $days, 7 ) as $favr_week ) : ?>
			<div class="favr-ev-grid__row" role="row">
				<?php
				foreach ( $favr_week as $favr_day ) :
					$favr_key   = $favr_day->format( 'Y-m-d' );
					$favr_items = $by_day[ $favr_key ] ?? array();
					$favr_class = 'favr-ev-grid__day' . ( $favr_day->format( 'Y-m' ) !== $favr_ym ? ' is-other' : '' ) . ( $favr_key === $today ? ' is-today' : '' ) . ( $favr_items ? ' has-events' : '' );
					?>
					<div class="<?php echo esc_attr( $favr_class ); ?>" role="cell">
						<span class="favr-ev-grid__num"><span class="favr-ev-grid__label"><?php echo esc_html( wp_date( 'D, M', $favr_day->getTimestamp() ) ); ?> </span><?php echo esc_html( $favr_day->format( 'j' ) ); ?></span>
						<?php if ( $favr_items ) : ?>
							<ul>
								<?php foreach ( $favr_items as $favr_item ) : ?>
									<li class="<?php echo $favr_item['event']->isFeatured() ? 'is-featured' : ''; ?>"><a href="<?php echo esc_url( Format::url( $favr_item['event'], $favr_item['start'] ) ); ?>"><span class="favr-ev-grid__time"><?php echo esc_html( Format::time( $favr_item['event'], $favr_item['start'] ) ); ?></span> <?php echo esc_html( $favr_item['event']->title() ); ?></a></li>
								<?php endforeach; ?>
							</ul>
						<?php endif; ?>
					</div>
				<?php endforeach; ?>
			</div>
		<?php endforeach; ?>
	</div>
	<p class="favr-ev__pager"><a class="favr-ev__ical" href="<?php echo esc_url( $ical ); ?>"><?php esc_html_e( 'Subscribe (iCal)', 'favr-events' ); ?></a></p>
</div>
