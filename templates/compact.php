<?php
/**
 * Compact upcoming list (sidebars, home page, business profiles).
 *
 * Override: yourtheme/favr-events/compact.php
 *
 * @package FavrEvents
 *
 * @var array $atts  Attributes.
 * @var array $items Occurrences.
 */

use FavrEvents\Frontend\Format;
use FavrEvents\Support\Settings;

defined( 'ABSPATH' ) || exit;

if ( ! $items ) {
	return;
}
?>
<div class="favr-ev favr-ev--compact">
	<?php if ( '' !== (string) $atts['title'] ) : ?>
		<h2 class="favr-ev__title"><?php echo esc_html( (string) $atts['title'] ); ?></h2>
	<?php endif; ?>
	<ul class="favr-ev-compact">
		<?php foreach ( $items as $favr_item ) : ?>
			<li>
				<span class="favr-ev-compact__date"><span><?php echo esc_html( wp_date( 'M', $favr_item['start']->getTimestamp() ) ); ?></span><strong><?php echo esc_html( wp_date( 'j', $favr_item['start']->getTimestamp() ) ); ?></strong></span>
				<span class="favr-ev-compact__text">
					<a href="<?php echo esc_url( Format::url( $favr_item['event'], $favr_item['start'] ) ); ?>"><?php echo esc_html( $favr_item['event']->title() ); ?></a>
					<small><?php echo esc_html( Format::time( $favr_item['event'], $favr_item['start'] ) . ( '' !== Format::where( $favr_item['event'] ) ? ' · ' . Format::where( $favr_item['event'] ) : '' ) ); ?></small>
				</span>
			</li>
		<?php endforeach; ?>
	</ul>
	<p class="favr-ev-compact__all"><a href="<?php echo esc_url( Settings::eventsUrl() ); ?>"><?php esc_html_e( 'All events →', 'favr-events' ); ?></a></p>
</div>
