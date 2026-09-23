<?php
/**
 * The event field set.
 *
 * @package FavrEvents
 */

declare(strict_types=1);

namespace FavrEvents\Model;

use FavrEvents\Schema\Identifiers as ID;
use FavrEvents\Vendor\FavrCore\Fields\FieldSet;

/**
 * One declarative definition drives the admin editor, the member submission form, sanitizing,
 * meta registration and display. Extend with the `favr_events_fields` filter.
 */
final class Fields {

	/**
	 * Cached set.
	 *
	 * @var FieldSet|null
	 */
	private static ?FieldSet $set = null;

	/** The field set. */
	public static function set(): FieldSet {
		if ( null !== self::$set ) {
			return self::$set;
		}
		$timed   = array(
			'field' => 'all_day',
			'value' => '',
		);
		$place   = array(
			'field' => 'attendance',
			'value' => array( 'in_person', 'hybrid' ),
		);
		$online  = array(
			'field' => 'attendance',
			'value' => array( 'online', 'hybrid' ),
		);
		$repeats = array(
			'field' => 'repeat',
			'value' => array( 'weekly', 'monthly_date', 'monthly_nth' ),
		);

		$fields = array(
			array(
				'id'       => 'start_date',
				'label'    => __( 'Starts', 'favr-events' ),
				'type'     => 'date',
				'tab'      => 'when',
				'width'    => 'half',
				'required' => true,
			),
			array(
				'id'         => 'start_time',
				'label'      => __( 'Start time', 'favr-events' ),
				'type'       => 'time',
				'tab'        => 'when',
				'width'      => 'half',
				'conditions' => $timed,
			),
			array(
				'id'          => 'end_date',
				'label'       => __( 'Ends', 'favr-events' ),
				'type'        => 'date',
				'tab'         => 'when',
				'width'       => 'half',
				'description' => __( 'Leave blank for a one-day event.', 'favr-events' ),
			),
			array(
				'id'         => 'end_time',
				'label'      => __( 'End time', 'favr-events' ),
				'type'       => 'time',
				'tab'        => 'when',
				'width'      => 'half',
				'conditions' => $timed,
			),
			array(
				'id'    => 'all_day',
				'label' => __( 'All-day event', 'favr-events' ),
				'type'  => 'toggle',
				'tab'   => 'when',
			),
			array(
				'id'      => 'repeat',
				'label'   => __( 'Repeats', 'favr-events' ),
				'type'    => 'select',
				'tab'     => 'when',
				'width'   => 'half',
				'default' => 'none',
				'options' => array(
					'none'         => __( 'Does not repeat', 'favr-events' ),
					'weekly'       => __( 'Weekly (same weekday)', 'favr-events' ),
					'monthly_nth'  => __( 'Monthly (e.g. 2nd Tuesday)', 'favr-events' ),
					'monthly_date' => __( 'Monthly (same date)', 'favr-events' ),
				),
			),
			array(
				'id'          => 'repeat_interval',
				'label'       => __( 'Every', 'favr-events' ),
				'type'        => 'number',
				'tab'         => 'when',
				'width'       => 'third',
				'min'         => 1,
				'max'         => 12,
				'placeholder' => '1',
				'description' => __( 'weeks or months', 'favr-events' ),
				'conditions'  => $repeats,
			),
			array(
				'id'          => 'repeat_until',
				'label'       => __( 'Until', 'favr-events' ),
				'type'        => 'date',
				'tab'         => 'when',
				'width'       => 'third',
				'description' => __( 'Blank repeats indefinitely.', 'favr-events' ),
				'conditions'  => $repeats,
			),
			array(
				'id'      => 'attendance',
				'label'   => __( 'Where', 'favr-events' ),
				'type'    => 'radio',
				'tab'     => 'where',
				'default' => 'in_person',
				'options' => array(
					'in_person' => __( 'In person', 'favr-events' ),
					'online'    => __( 'Online', 'favr-events' ),
					'hybrid'    => __( 'Both', 'favr-events' ),
				),
			),
			array(
				'id'          => 'venue',
				'label'       => __( 'Venue', 'favr-events' ),
				'tab'         => 'where',
				'placeholder' => __( 'e.g. Chamber Office, Main Street Bakery', 'favr-events' ),
				'maxlength'   => 120,
				'conditions'  => $place,
			),
			array(
				'id'         => 'address',
				'label'      => __( 'Street address', 'favr-events' ),
				'tab'        => 'where',
				'conditions' => $place,
			),
			array(
				'id'         => 'city',
				'label'      => __( 'City', 'favr-events' ),
				'tab'        => 'where',
				'width'      => 'third',
				'conditions' => $place,
			),
			array(
				'id'         => 'state',
				'label'      => __( 'State / region', 'favr-events' ),
				'tab'        => 'where',
				'width'      => 'third',
				'conditions' => $place,
			),
			array(
				'id'         => 'postal_code',
				'label'      => __( 'Postal code', 'favr-events' ),
				'tab'        => 'where',
				'width'      => 'third',
				'conditions' => $place,
			),
			array(
				'id'          => 'online_url',
				'label'       => __( 'Online link', 'favr-events' ),
				'type'        => 'url',
				'tab'         => 'where',
				'description' => __( 'Zoom, Teams, livestream… Shown on the event page.', 'favr-events' ),
				'conditions'  => $online,
			),
			array(
				'id'          => 'cost',
				'label'       => __( 'Cost', 'favr-events' ),
				'tab'         => 'details',
				'width'       => 'half',
				'placeholder' => __( 'Free · $15 members / $25 guests', 'favr-events' ),
				'maxlength'   => 80,
			),
			array(
				'id'          => 'registration_url',
				'label'       => __( 'Registration / tickets link', 'favr-events' ),
				'type'        => 'url',
				'tab'         => 'details',
				'width'       => 'half',
				'placeholder' => 'https://',
			),
			array(
				'id'          => 'featured',
				'label'       => __( 'Feature this event', 'favr-events' ),
				'type'        => 'toggle',
				'tab'         => 'details',
				'description' => __( 'Featured events are highlighted and can be shown on their own.', 'favr-events' ),
			),
			array(
				'id'          => 'host_business',
				'label'       => __( 'Hosted by (directory business)', 'favr-events' ),
				'type'        => 'number', // Stored as a listing id; shown as a select by Fields::forForm().
				'tab'         => 'host',
				'min'         => 1,
				'description' => __( 'Shows the business card on the event and the event on the business profile.', 'favr-events' ),
			),
			array(
				'id'          => 'organizer_name',
				'label'       => __( 'Organizer', 'favr-events' ),
				'tab'         => 'host',
				'width'       => 'half',
				'description' => __( 'A person or group, if not a directory business.', 'favr-events' ),
			),
			array(
				'id'    => 'organizer_email',
				'label' => __( 'Organizer email', 'favr-events' ),
				'type'  => 'email',
				'tab'   => 'host',
				'width' => 'half',
			),
			array(
				'id'    => 'organizer_phone',
				'label' => __( 'Organizer phone', 'favr-events' ),
				'type'  => 'tel',
				'tab'   => 'host',
				'width' => 'half',
			),
			array(
				'id'    => 'organizer_url',
				'label' => __( 'Organizer website', 'favr-events' ),
				'type'  => 'url',
				'tab'   => 'host',
				'width' => 'half',
			),
		);

		$tabs = array(
			'when'    => array(
				'label' => __( 'Date & time', 'favr-events' ),
				'icon'  => 'dashicons-calendar-alt',
			),
			'where'   => array(
				'label' => __( 'Location', 'favr-events' ),
				'icon'  => 'dashicons-location',
			),
			'details' => array(
				'label' => __( 'Tickets & details', 'favr-events' ),
				'icon'  => 'dashicons-tickets-alt',
			),
			'host'    => array(
				'label' => __( 'Host', 'favr-events' ),
				'icon'  => 'dashicons-businessperson',
			),
		);

		/**
		 * Filter the event fields.
		 *
		 * @param array $fields Field definitions (see favr/core FieldSet).
		 */
		$fields = (array) apply_filters( 'favr_events_fields', $fields );

		/**
		 * Filter the event editor tabs.
		 *
		 * @param array $tabs Tab id => { label, icon }.
		 */
		$tabs = (array) apply_filters( 'favr_events_tabs', $tabs );

		self::$set = new FieldSet( $fields, $tabs );
		return self::$set;
	}

	/**
	 * Adapt a field for a form: the host becomes a select of directory businesses (all of
	 * them for staff, or just the given ids for a member), and is dropped without the Directory.
	 *
	 * @param array<string, mixed> $field     Field.
	 * @param list<int>|null       $only_ids  Restrict host choices, or null for all.
	 * @return array<string, mixed>|null Null to hide the field.
	 */
	public static function forForm( array $field, ?array $only_ids = null ): ?array {
		if ( 'host_business' !== $field['id'] ) {
			return $field;
		}
		if ( ! Hosts::available() ) {
			return null;
		}
		$options = Hosts::options();
		if ( null !== $only_ids ) {
			$options = array_intersect_key( $options, array_flip( array_map( 'strval', $only_ids ) ) );
			if ( array() === $options ) {
				return null;
			}
		}
		$field['type']    = 'select';
		$field['options'] = array( '' => __( '— None —', 'favr-events' ) ) + $options;
		return $field;
	}

	/** Reset (tests, late filters). */
	public static function reset(): void {
		self::$set = null;
	}

	/**
	 * Meta key for a field.
	 *
	 * @param string $id Field id.
	 */
	public static function key( string $id ): string {
		return ID::meta( $id );
	}
}
