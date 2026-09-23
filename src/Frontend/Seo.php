<?php
/**
 * Structured data, breadcrumbs and robots rules for events.
 *
 * @package FavrEvents
 */

declare(strict_types=1);

namespace FavrEvents\Frontend;

use FavrEvents\Model\Event;
use FavrEvents\Schema\Identifiers as ID;
use FavrEvents\Support\Settings;

/**
 * Event pages get schema.org Event (for the occurrence being viewed, or the next one) and a
 * Home › Events › Event breadcrumb. With Yoast SEO or Rank Math the entities are merged into
 * their graph; otherwise they are printed on their own. Filtered calendar views (other months,
 * categories, pages) are noindex so the calendar can't create endless thin URLs.
 */
final class Seo {

	/**
	 * Whether an SEO plugin merged our entities on this request.
	 *
	 * @var bool
	 */
	private static bool $merged = false;

	/** Hook. */
	public function hook(): void {
		add_filter( 'wpseo_schema_graph', array( $this, 'yoastGraph' ), 20 );
		add_filter( 'wpseo_breadcrumb_links', array( $this, 'yoastBreadcrumbs' ) );
		add_filter( 'rank_math/json_ld', array( $this, 'rankMathGraph' ), 99 );
		add_action( 'wp_head', array( $this, 'printGraph' ), 30 );
		add_filter( 'wp_robots', array( $this, 'robots' ) );
	}

	/** Standalone JSON-LD when no SEO plugin merged it. */
	public function printGraph(): void {
		if ( self::$merged ) {
			return;
		}
		$graph = self::graph( true );
		if ( $graph ) {
			printf(
				'<script type="application/ld+json" class="favr-events-schema">%s</script>' . "\n",
				wp_json_encode(
					array(
						'@context' => 'https://schema.org',
						'@graph'   => $graph,
					),
					JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP
				)
			);
		}
	}

	/**
	 * Yoast: add the Event (Yoast provides breadcrumbs, following our trail below).
	 *
	 * @param mixed $graph Pieces.
	 * @return array<int, mixed>
	 */
	public function yoastGraph( $graph ): array {
		$graph = is_array( $graph ) ? $graph : array();
		foreach ( self::graph( false ) as $piece ) {
			$graph[] = $piece;
		}
		self::$merged = true;
		return $graph;
	}

	/**
	 * Yoast breadcrumbs on event pages: Home › Events › Event.
	 *
	 * @param mixed $links Links.
	 * @return array<int, mixed>
	 */
	public function yoastBreadcrumbs( $links ): array {
		$links = is_array( $links ) ? $links : array();
		if ( ! is_singular( ID::POST_TYPE ) ) {
			return $links;
		}
		$out = array();
		foreach ( self::trail() as $index => $crumb ) {
			$out[] = 0 === $index && isset( $links[0] ) ? $links[0] : array(
				'url'  => $crumb[1],
				'text' => $crumb[0],
			);
		}
		return $out;
	}

	/**
	 * Rank Math: add entities and our breadcrumb.
	 *
	 * @param mixed $data Entities.
	 * @return array<string, mixed>
	 */
	public function rankMathGraph( $data ): array {
		$data = is_array( $data ) ? $data : array();
		foreach ( self::graph( true ) as $index => $piece ) {
			$data[ 'BreadcrumbList' === $piece['@type'] ? 'BreadcrumbList' : 'favr_event_' . $index ] = $piece;
		}
		self::$merged = true;
		return $data;
	}

	/**
	 * Entities for the current request.
	 *
	 * @param bool $with_breadcrumbs Include BreadcrumbList.
	 * @return list<array<string, mixed>>
	 */
	public static function graph( bool $with_breadcrumbs ): array {
		if ( self::isEventsPage() ) {
			$list = self::itemList();
			return $list ? array( $list ) : array();
		}
		if ( ! is_singular( ID::POST_TYPE ) ) {
			return array();
		}
		$event = Event::find( (int) get_queried_object_id() );
		if ( ! $event || 'publish' !== get_post_status( $event->post() ) || '' !== $event->post()->post_password ) {
			return array();
		}
		$occurrence = Single::occurrence( $event );
		$graph      = $occurrence ? array( self::schema( $event, $occurrence['start'], $occurrence['end'] ) ) : array();
		if ( $with_breadcrumbs ) {
			$items = array();
			foreach ( self::trail() as $i => $crumb ) {
				$items[] = array(
					'@type'    => 'ListItem',
					'position' => $i + 1,
					'name'     => $crumb[0],
					'item'     => $crumb[1],
				);
			}
			$graph[] = array(
				'@type'           => 'BreadcrumbList',
				'@id'             => (string) get_permalink( $event->post() ) . '#breadcrumb',
				'itemListElement' => $items,
			);
		}
		return $graph;
	}

	/** Whether this is the configured Events page (unfiltered). */
	private static function isEventsPage(): bool {
		$page = (int) Settings::get( 'events_page' );
		return $page > 0 && is_page( $page );
	}

	/**
	 * ItemList of upcoming events for the Events page (each item points at the event page).
	 *
	 * @return array<string, mixed>|null
	 */
	public static function itemList(): ?array {
		$items = array();
		$seen  = array();
		foreach ( \FavrEvents\Model\Repository::upcoming( 30 ) as $item ) {
			$id = $item['event']->id();
			if ( isset( $seen[ $id ] ) ) {
				continue; // A repeating event appears once.
			}
			$seen[ $id ] = true;
			$items[]     = array(
				'@type'    => 'ListItem',
				'position' => count( $items ) + 1,
				'url'      => (string) get_permalink( $item['event']->post() ),
				'name'     => wp_specialchars_decode( $item['event']->title(), ENT_QUOTES ),
			);
			if ( count( $items ) >= 20 ) {
				break;
			}
		}
		if ( ! $items ) {
			return null;
		}
		return array(
			'@type'           => 'ItemList',
			'@id'             => (string) get_permalink( (int) Settings::get( 'events_page' ) ) . '#events',
			'name'            => __( 'Upcoming events', 'favr-events' ),
			'itemListElement' => $items,
		);
	}

	/**
	 * The schema.org Event entity.
	 *
	 * @param Event              $event Event.
	 * @param \DateTimeImmutable $start Start.
	 * @param \DateTimeImmutable $end   End.
	 * @return array<string, mixed>
	 */
	public static function schema( Event $event, \DateTimeImmutable $start, \DateTimeImmutable $end ): array {
		$url         = Format::url( $event, $start );
		$modes       = array(
			'in_person' => 'OfflineEventAttendanceMode',
			'online'    => 'OnlineEventAttendanceMode',
			'hybrid'    => 'MixedEventAttendanceMode',
		);
		$format      = $event->isAllDay() ? 'Y-m-d' : 'c';
		$data        = array(
			'@type'               => 'Event',
			'@id'                 => $url . '#event-' . $start->format( 'Ymd' ),
			'name'                => wp_specialchars_decode( $event->title(), ENT_QUOTES ),
			'url'                 => $url,
			'startDate'           => $start->format( $format ),
			'endDate'             => $end->format( $format ),
			'eventStatus'         => 'https://schema.org/EventScheduled',
			'eventAttendanceMode' => 'https://schema.org/' . $modes[ $event->attendance() ],
		);
		$description = has_excerpt( $event->post() ) ? $event->post()->post_excerpt : wp_trim_words( wp_strip_all_tags( strip_shortcodes( $event->post()->post_content ) ), 50 );
		if ( '' !== trim( $description ) ) {
			$data['description'] = html_entity_decode( $description, ENT_QUOTES );
		}
		$image = get_the_post_thumbnail_url( $event->post(), 'full' );
		if ( $image ) {
			$data['image'] = array( $image );
		}

		$locations = array();
		if ( $event->hasPlace() ) {
			$place   = array(
				'@type' => 'Place',
				'name'  => '' !== $event->text( 'venue' ) ? $event->text( 'venue' ) : implode( ', ', $event->addressLines() ),
			);
			$address = array_filter(
				array(
					'@type'           => 'PostalAddress',
					'streetAddress'   => $event->text( 'address' ),
					'addressLocality' => $event->text( 'city' ),
					'addressRegion'   => $event->text( 'state' ),
					'postalCode'      => $event->text( 'postal_code' ),
				)
			);
			if ( count( $address ) > 1 ) {
				$place['address'] = $address;
			}
			$locations[] = $place;
		}
		if ( 'in_person' !== $event->attendance() ) {
			$locations[] = array_filter(
				array(
					'@type' => 'VirtualLocation',
					'url'   => '' !== $event->text( 'online_url' ) ? $event->text( 'online_url' ) : $url,
				)
			);
		}
		if ( $locations ) {
			$data['location'] = 1 === count( $locations ) ? $locations[0] : $locations;
		}

		$registration = $event->text( 'registration_url' );
		$cost         = $event->text( 'cost' );
		if ( '' !== $registration || '' !== $cost ) {
			$offer = array(
				'@type' => 'Offer',
				'url'   => '' !== $registration ? $registration : $url,
			);
			/**
			 * Currency for simple prices like "$15" in the cost field.
			 *
			 * @param string $currency ISO 4217 code.
			 */
			$currency = (string) apply_filters( 'favr_events_currency', 'USD' );
			if ( preg_match( '/^\s*free\s*$/i', $cost ) ) {
				$offer['price']         = 0;
				$offer['priceCurrency'] = $currency;
			} elseif ( preg_match( '/^\s*\$\s*(\d+(?:\.\d{1,2})?)\s*$/', $cost, $m ) ) {
				$offer['price']         = (float) $m[1];
				$offer['priceCurrency'] = $currency;
			}
			$data['offers'] = $offer;
		}

		$host = $event->hostId();
		if ( $host ) {
			$data['organizer'] = array(
				'@type' => 'Organization',
				'@id'   => (string) get_permalink( $host ) . '#business',
				'name'  => wp_specialchars_decode( get_the_title( $host ), ENT_QUOTES ),
				'url'   => (string) get_permalink( $host ),
			);
		} elseif ( '' !== $event->text( 'organizer_name' ) ) {
			$data['organizer'] = array_filter(
				array(
					'@type' => 'Organization',
					'name'  => $event->text( 'organizer_name' ),
					'url'   => $event->text( 'organizer_url' ),
				)
			);
		} else {
			$data['organizer'] = array(
				'@type' => 'Organization',
				'@id'   => home_url( '/' ) . '#organization',
				'name'  => wp_specialchars_decode( (string) get_bloginfo( 'name' ), ENT_QUOTES ),
				'url'   => home_url( '/' ),
			);
		}

		/**
		 * Filter an event's schema.org data.
		 *
		 * @param array              $data  Event entity.
		 * @param Event              $event Event.
		 * @param \DateTimeImmutable $start Occurrence start.
		 */
		return (array) apply_filters( 'favr_events_schema', $data, $event, $start );
	}

	/**
	 * Breadcrumb trail: list of [ name, url ].
	 *
	 * @return list<array{0: string, 1: string}>
	 */
	public static function trail(): array {
		$trail = array( array( __( 'Home', 'favr-events' ), home_url( '/' ) ) );
		$page  = (int) Settings::get( 'events_page' );
		if ( $page && 'publish' === get_post_status( $page ) ) {
			$trail[] = array( wp_specialchars_decode( get_the_title( $page ), ENT_QUOTES ), (string) get_permalink( $page ) );
		}
		if ( is_singular( ID::POST_TYPE ) ) {
			$trail[] = array( wp_specialchars_decode( get_the_title( get_queried_object_id() ), ENT_QUOTES ), (string) get_permalink( get_queried_object_id() ) );
		}
		return $trail;
	}

	/**
	 * Noindex filtered calendar views and per-occurrence event URLs (canonical is the event).
	 *
	 * @param array<string, mixed> $robots Directives.
	 * @return array<string, mixed>
	 */
	public function robots( array $robots ): array {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only check.
		$filtered = isset( $_GET[ ID::QV_MONTH ] ) || isset( $_GET[ ID::QV_CAT ] ) || isset( $_GET[ ID::QV_PAGE ] ) || isset( $_GET[ ID::QV_VIEW ] ) || ( is_singular( ID::POST_TYPE ) && isset( $_GET['occurrence'] ) );
		// phpcs:enable
		if ( $filtered ) {
			$robots['noindex'] = true;
			$robots['follow']  = true;
		}
		return $robots;
	}
}
