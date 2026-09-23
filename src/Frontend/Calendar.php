<?php
/**
 * The events list and month calendar ([favr_events] and the Events block).
 *
 * @package FavrEvents
 */

declare(strict_types=1);

namespace FavrEvents\Frontend;

use FavrEvents\Model\Repository;
use FavrEvents\Schema\Identifiers as ID;
use FavrEvents\Support\Request;
use FavrEvents\Support\Settings;

/**
 * Plain links and GET parameters (fe_view, fe_month, fe_cat, fe_page), so every view is
 * shareable, cacheable and works without JavaScript.
 */
final class Calendar {

	/**
	 * Normalize attributes.
	 *
	 * @param array<string, mixed> $atts Raw.
	 * @return array<string, mixed>
	 */
	public static function atts( array $atts ): array {
		$atts             = shortcode_atts(
			array(
				'view'     => (string) Settings::get( 'default_view' ),
				'category' => '',
				'limit'    => (int) Settings::get( 'per_page' ),
				'featured' => '0',
				'host'     => '0',
				'filters'  => '1',
				'title'    => '',
			),
			$atts,
			ID::SHORTCODE
		);
		$atts['view']     = in_array( $atts['view'], array( 'list', 'month', 'compact' ), true ) ? $atts['view'] : 'list';
		$atts['limit']    = max( 1, min( 100, (int) $atts['limit'] ) );
		$atts['featured'] = in_array( (string) $atts['featured'], array( '1', 'true', 'yes' ), true );
		$atts['filters']  = ! in_array( (string) $atts['filters'], array( '0', 'false', 'no' ), true );
		$atts['host']     = absint( $atts['host'] );
		return $atts;
	}

	/**
	 * Render.
	 *
	 * @param array<string, mixed> $atts Attributes.
	 */
	public static function render( array $atts = array() ): string {
		$atts     = self::atts( $atts );
		$req_view = Request::get( ID::QV_VIEW );
		$req_cat  = Request::get( ID::QV_CAT );
		$view     = $atts['filters'] && in_array( $req_view, array( 'list', 'month' ), true ) ? $req_view : $atts['view'];
		$cat      = $atts['filters'] && '' !== $req_cat ? sanitize_title( $req_cat ) : (string) $atts['category'];
		$page     = min( 20, max( 1, absint( Request::get( ID::QV_PAGE ) ) ) ); // Deep pages are pointless and costly.
		$month    = sanitize_text_field( Request::get( ID::QV_MONTH ) );

		wp_enqueue_style( 'favr-events' );
		$args = array(
			'category' => $cat,
			'featured' => $atts['featured'],
			'host'     => $atts['host'],
		);
		$vars = array(
			'atts'       => $atts,
			'view'       => $view,
			'category'   => $cat,
			'categories' => $atts['filters'] ? get_terms(
				array(
					'taxonomy'   => ID::TAX_CATEGORY,
					'hide_empty' => true,
				)
			) : array(),
			'base'       => self::baseUrl(),
			'ical'       => add_query_arg( ID::QV_ICAL, '1', home_url( '/' ) ),
		);

		if ( 'month' === $view ) {
			$vars += self::month( $month, $args );
			return View::render( 'month', $vars );
		}

		// List: fetch one page plus one to know whether there's a next page.
		$per   = (int) $atts['limit'];
		$now   = new \DateTimeImmutable( 'now', wp_timezone() );
		$items = Repository::occurrences( $now, $now->modify( '+2 years' ), array_merge( $args, array( 'limit' => $page * $per + 1 ) ) );
		$vars += array(
			'items'    => array_slice( $items, ( $page - 1 ) * $per, $per ),
			'page'     => $page,
			'has_more' => count( $items ) > $page * $per,
		);
		return View::render( 'compact' === $view ? 'compact' : 'list', $vars );
	}

	/**
	 * Month grid data.
	 *
	 * @param string               $month Requested "Y-m".
	 * @param array<string, mixed> $args  Query args.
	 * @return array<string, mixed>
	 */
	private static function month( string $month, array $args ): array {
		$tz    = wp_timezone();
		$first = preg_match( '/^\d{4}-\d{2}$/', $month ) ? \DateTimeImmutable::createFromFormat( '!Y-m-d', $month . '-01', $tz ) : false;
		if ( ! $first || (int) $first->format( 'Y' ) < 1970 || (int) $first->format( 'Y' ) > 2099 ) {
			$first = ( new \DateTimeImmutable( 'now', $tz ) )->modify( 'first day of this month' )->setTime( 0, 0 );
		}
		$start_of_week = (int) get_option( 'start_of_week', 0 );
		$offset        = ( (int) $first->format( 'w' ) - $start_of_week + 7 ) % 7;
		$grid_start    = $first->modify( '-' . $offset . ' days' );
		$next_month    = $first->modify( '+1 month' );
		$days_needed   = (int) ceil( ( $offset + (int) $first->format( 't' ) ) / 7 ) * 7;
		$grid_end      = $grid_start->modify( '+' . $days_needed . ' days' );

		$by_day = array();
		foreach ( Repository::occurrences( $grid_start, $grid_end, array_merge( $args, array( 'limit' => 500 ) ) ) as $item ) {
			// Multi-day occurrences appear on every day they span.
			$day = $item['start']->setTime( 0, 0 );
			while ( $day < $item['end'] || $day->format( 'Y-m-d' ) === $item['start']->format( 'Y-m-d' ) ) {
				if ( $day >= $grid_start && $day < $grid_end ) {
					$by_day[ $day->format( 'Y-m-d' ) ][] = $item;
				}
				$day = $day->modify( '+1 day' );
				if ( $day >= $grid_end ) {
					break;
				}
			}
		}

		$weekdays = array();
		for ( $i = 0; $i < 7; $i++ ) {
			$weekdays[] = $grid_start->modify( '+' . $i . ' days' );
		}

		return array(
			'first'    => $first,
			'prev'     => $first->modify( '-1 month' )->format( 'Y-m' ),
			'next'     => $next_month->format( 'Y-m' ),
			'days'     => array_map( static fn( int $i ): \DateTimeImmutable => $grid_start->modify( '+' . $i . ' days' ), range( 0, $days_needed - 1 ) ),
			'by_day'   => $by_day,
			'weekdays' => $weekdays,
			'today'    => ( new \DateTimeImmutable( 'now', $tz ) )->format( 'Y-m-d' ),
		);
	}

	/** Current page URL without our parameters. */
	public static function baseUrl(): string {
		$url = is_singular() ? (string) get_permalink() : Settings::eventsUrl();
		return remove_query_arg( array( ID::QV_VIEW, ID::QV_MONTH, ID::QV_CAT, ID::QV_PAGE ), $url );
	}
}
