<?php
/**
 * View switch and category filter.
 *
 * Override: yourtheme/favr-events/parts/filters.php
 *
 * @package FavrEvents
 *
 * @var string              $view       Current view.
 * @var string              $category   Current category slug.
 * @var array<int, WP_Term> $categories Categories.
 * @var string              $base       Base URL.
 * @var array               $atts       Attributes.
 */

use FavrEvents\Schema\Identifiers as ID;

defined( 'ABSPATH' ) || exit;

if ( empty( $atts['filters'] ) ) {
	return;
}
$favr_link = static fn( array $args ): string => add_query_arg(
	array_filter(
		array_merge(
			array(
				ID::QV_VIEW => 'list' === $view ? '' : $view,
				ID::QV_CAT  => $category,
			),
			$args
		)
	),
	$base
);
?>
<div class="favr-ev-toolbar">
	<nav class="favr-ev-views" aria-label="<?php esc_attr_e( 'Calendar view', 'favr-events' ); ?>">
		<a href="<?php echo esc_url( $favr_link( array( ID::QV_VIEW => '' ) ) ); ?>"<?php echo 'list' === $view ? ' aria-current="page"' : ''; ?>><?php esc_html_e( 'List', 'favr-events' ); ?></a>
		<a href="<?php echo esc_url( $favr_link( array( ID::QV_VIEW => 'month' ) ) ); ?>"<?php echo 'month' === $view ? ' aria-current="page"' : ''; ?>><?php esc_html_e( 'Month', 'favr-events' ); ?></a>
	</nav>
	<?php if ( ! empty( $categories ) && ! is_wp_error( $categories ) ) : ?>
		<nav class="favr-ev-cats" aria-label="<?php esc_attr_e( 'Event categories', 'favr-events' ); ?>">
			<a href="<?php echo esc_url( $favr_link( array( ID::QV_CAT => '' ) ) ); ?>"<?php echo '' === $category ? ' aria-current="page"' : ''; ?>><?php esc_html_e( 'All', 'favr-events' ); ?></a>
			<?php foreach ( $categories as $favr_term ) : ?>
				<a href="<?php echo esc_url( $favr_link( array( ID::QV_CAT => $favr_term->slug ) ) ); ?>"<?php echo $category === $favr_term->slug ? ' aria-current="page"' : ''; ?>><?php echo esc_html( $favr_term->name ); ?></a>
			<?php endforeach; ?>
		</nav>
	<?php endif; ?>
</div>
