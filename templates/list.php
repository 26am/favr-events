<?php
/**
 * Upcoming events, grouped by month.
 *
 * Override: yourtheme/favr-events/list.php
 *
 * @package FavrEvents
 *
 * @var array  $atts     Attributes.
 * @var array  $items    Occurrences { event, start, end }.
 * @var int    $page     Page.
 * @var bool   $has_more More pages.
 * @var string $base     Base URL.
 * @var string $ical     iCal feed URL.
 * @var string $view     View.
 * @var string $category Category.
 */

use FavrEvents\Frontend\View;
use FavrEvents\Schema\Identifiers as ID;

defined( 'ABSPATH' ) || exit;

$favr_month = '';
?>
<div class="favr-ev favr-ev--list">
	<?php if ( '' !== (string) $atts['title'] ) : ?>
		<h2 class="favr-ev__title"><?php echo esc_html( (string) $atts['title'] ); ?></h2>
	<?php endif; ?>
	<?php echo View::render( 'parts/filters', get_defined_vars() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- template escapes. ?>

	<?php if ( ! $items ) : ?>
		<p class="favr-ev__empty"><?php echo 1 === $page ? esc_html__( 'No upcoming events right now. Check back soon!', 'favr-events' ) : esc_html__( 'No more events.', 'favr-events' ); ?></p>
	<?php endif; ?>

	<?php foreach ( $items as $favr_item ) : ?>
		<?php
		$favr_heading = wp_date( 'F Y', $favr_item['start']->getTimestamp() );
		if ( $favr_heading !== $favr_month ) :
			if ( '' !== $favr_month ) {
				echo '</div>';
			}
			$favr_month = $favr_heading;
			?>
			<h3 class="favr-ev__month"><?php echo esc_html( $favr_heading ); ?></h3>
			<div class="favr-ev__group">
		<?php endif; ?>
		<?php echo View::render( 'parts/card', $favr_item ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- template escapes. ?>
	<?php endforeach; ?>
	<?php if ( '' !== $favr_month ) : ?>
		</div>
	<?php endif; ?>

	<nav class="favr-ev__pager" aria-label="<?php esc_attr_e( 'More events', 'favr-events' ); ?>">
		<?php if ( $page > 1 ) : ?>
			<a class="favr-ev-btn" href="
			<?php
			echo esc_url(
				add_query_arg(
					array_filter(
						array(
							ID::QV_PAGE => $page - 1 > 1 ? $page - 1 : '',
							ID::QV_CAT  => $category,
						)
					),
					$base
				)
			);
			?>
											">← <?php esc_html_e( 'Earlier', 'favr-events' ); ?></a>
		<?php endif; ?>
		<?php if ( $has_more ) : ?>
			<a class="favr-ev-btn" href="
			<?php
			echo esc_url(
				add_query_arg(
					array_filter(
						array(
							ID::QV_PAGE => $page + 1,
							ID::QV_CAT  => $category,
						)
					),
					$base
				)
			);
			?>
											"><?php esc_html_e( 'Later events', 'favr-events' ); ?> →</a>
		<?php endif; ?>
		<a class="favr-ev__ical" href="<?php echo esc_url( $ical ); ?>"><?php esc_html_e( 'Subscribe (iCal)', 'favr-events' ); ?></a>
	</nav>
</div>
