<?php
/**
 * Event image uploads for submitters.
 *
 * @package FavrEvents
 */

declare(strict_types=1);

namespace FavrEvents\Editing;

use FavrEvents\Vendor\FavrCore\Moderation\Uploads;
use FavrEvents\Vendor\FavrCore\Support\RateLimit;

/**
 * POST /favr-events/v1/upload (file, parent). parent 0 is a not-yet-saved submission; the
 * image is owned by the uploader and can only be used by them.
 */
final class UploadRoute {

	public const NAMESPACE = 'favr-events/v1';
	public const ROUTE     = '/upload';

	/** Hook. */
	public function hook(): void {
		add_action( 'rest_api_init', array( $this, 'register' ) );
	}

	/** Register. */
	public function register(): void {
		register_rest_route(
			self::NAMESPACE,
			self::ROUTE,
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'upload' ),
				'permission_callback' => array( $this, 'allowed' ),
				'args'                => array(
					'parent' => array(
						'type'              => 'integer',
						'default'           => 0,
						'sanitize_callback' => 'absint',
					),
				),
			)
		);
	}

	/**
	 * Permission.
	 *
	 * @param \WP_REST_Request $request Request.
	 */
	public function allowed( \WP_REST_Request $request ): bool {
		$user   = get_current_user_id();
		$parent = (int) $request->get_param( 'parent' );
		return $user > 0 && ( $parent ? Submissions::canEdit( $user, $parent ) : Submissions::canSubmit( $user ) );
	}

	/**
	 * Upload.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function upload( \WP_REST_Request $request ) {
		$user = get_current_user_id();
		if ( ! RateLimit::hit( 'event_upload_' . $user, 20 ) ) {
			return new \WP_Error( 'favr_upload_limit', __( 'You’ve uploaded a lot of images recently. Please try again later.', 'favr-events' ), array( 'status' => 429 ) );
		}
		$files = $request->get_file_params();
		if ( empty( $files['file'] ) || ! is_array( $files['file'] ) ) {
			return new \WP_Error( 'favr_upload', __( 'No file received.', 'favr-events' ), array( 'status' => 400 ) );
		}
		$id = Uploads::handle( $files['file'], $user, (int) $request->get_param( 'parent' ), min( 8 * MB_IN_BYTES, (int) wp_max_upload_size() ) );
		if ( is_wp_error( $id ) ) {
			return $id;
		}
		return new \WP_REST_Response(
			array(
				'id'    => $id,
				'thumb' => (string) wp_get_attachment_image_url( $id, 'thumbnail' ),
			),
			201
		);
	}
}
