<?php
/**
 * Every identifier the plugin stores or registers, in one place.
 *
 * @package FavrEvents
 */

declare(strict_types=1);

namespace FavrEvents\Schema;

/**
 * Constants only.
 */
final class Identifiers {

	public const POST_TYPE       = 'favr_event';
	public const TAX_CATEGORY    = 'favr_event_cat';
	public const META_PREFIX     = 'favr_event_';
	public const META_FIRST      = '_favr_event_first'; // UTC timestamp of the first start (index).
	public const META_LAST       = '_favr_event_last';  // UTC timestamp of the last end, or OPEN_ENDED (index).
	public const OPEN_ENDED      = 4102444800;          // 2100-01-01: repeats with no end date.
	public const OPTION_SETTINGS = 'favr_events_settings';
	public const OPTION_VERSION  = 'favr_events_version';
	public const CAP_TYPE        = 'favr_event';
	public const CAP_PLURAL      = 'favr_events';
	public const CAP_SETTINGS    = 'manage_favr_events';
	public const NONCE_META      = 'favr_events_meta';
	public const SHORTCODE       = 'favr_events';
	public const SHORTCODE_MINE  = 'favr_my_events';
	public const BLOCK_EVENTS    = 'favr-events/events';
	public const TEMPLATE_DIR    = 'favr-events';
	public const QV_ICAL         = 'favr_events_ical';
	public const DIRECTORY_TYPE  = 'favr_business';

	// Front-end query parameters.
	public const QV_VIEW  = 'fe_view';
	public const QV_MONTH = 'fe_month';
	public const QV_CAT   = 'fe_cat';
	public const QV_PAGE  = 'fe_page';

	/**
	 * Meta key for a field.
	 *
	 * @param string $field_id Field id.
	 */
	public static function meta( string $field_id ): string {
		return self::META_PREFIX . $field_id;
	}
}
