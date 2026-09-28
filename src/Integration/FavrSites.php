<?php
/**
 * Favr dashboard card and quick action (the Favr Sites plugin).
 *
 * @package FavrEvents
 */

declare(strict_types=1);

namespace FavrEvents\Integration;

use FavrEvents\Model\Repository;
use FavrEvents\Schema\Identifiers as ID;

/**
 * Contributes plain data to the Favr dashboard; inert when Favr Sites isn't installed.
 */
final class FavrSites {

	/** Hooks. */
	public function hook(): void {
		add_filter( 'favr_sites_quick_actions', array( $this, 'actions' ) );
		add_filter( 'favr_sites_dashboard_cards', array( $this, 'cards' ) );
	}

	/**
	 * Quick action.
	 *
	 * @param array<mixed> $actions Actions.
	 * @return array<mixed>
	 */
	public function actions( array $actions ): array {
		$actions[] = array(
			'id'         => 'add-event',
			'label'      => __( 'Add event', 'favr-events' ),
			'url'        => admin_url( 'post-new.php?post_type=' . ID::POST_TYPE ),
			'capability' => 'edit_' . ID::CAP_PLURAL,
			'icon'       => 'calendar',
			'priority'   => 30,
		);
		return $actions;
	}

	/**
	 * Card, built only when the dashboard renders.
	 *
	 * @param array<mixed> $cards Cards.
	 * @return array<mixed>
	 */
	public function cards( array $cards ): array {
		$cards[] = array( $this, 'card' );
		return $cards;
	}

	/**
	 * The events card: dates in the next 30 days (a repeating event counts each date).
	 *
	 * @return array<string, mixed>
	 */
	public function card(): array {
		$now      = new \DateTimeImmutable( 'now', wp_timezone() );
		$upcoming = Repository::occurrences( $now, $now->modify( '+30 days' ), array( 'limit' => 100 ) );
		$list     = admin_url( 'edit.php?post_type=' . ID::POST_TYPE );
		return array(
			'id'         => 'events',
			'title'      => __( 'Events', 'favr-events' ),
			'capability' => 'edit_' . ID::CAP_PLURAL,
			'priority'   => 30,
			'stats'      => $upcoming ? array(
				array(
					'label' => __( 'in the next 30 days', 'favr-events' ),
					'value' => number_format_i18n( count( $upcoming ) ),
					'url'   => $list,
				),
			) : array(),
			'items'      => array_map(
				static fn( array $row ): array => array(
					'title' => $row['event']->title(),
					'meta'  => wp_date( 'D j M', $row['start']->getTimestamp() ),
					'url'   => (string) get_edit_post_link( $row['event']->id(), 'raw' ),
				),
				array_slice( $upcoming, 0, 3 )
			),
			'link'       => array(
				'label' => __( 'All events', 'favr-events' ),
				'url'   => $list,
			),
			'empty'      => array(
				'text'  => __( 'No upcoming events.', 'favr-events' ),
				'label' => __( 'Add an event', 'favr-events' ),
				'url'   => admin_url( 'post-new.php?post_type=' . ID::POST_TYPE ),
			),
		);
	}
}
