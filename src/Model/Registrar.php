<?php
/**
 * Post type, categories and meta.
 *
 * @package FavrEvents
 */

declare(strict_types=1);

namespace FavrEvents\Model;

use FavrEvents\Schema\Identifiers as ID;
use FavrEvents\Support\Settings;
use FavrEvents\Vendor\FavrCore\Fields\Sanitizer;

/**
 * Events live at /events/{slug}/; the list and calendar are on the Events page (a normal page
 * with the Events block), so /events/ is fully editable in the site editor or page builder.
 */
final class Registrar {

	/** Hook. */
	public function hook(): void {
		add_action( 'init', array( $this, 'registerPostType' ), 6 );
		add_action( 'init', array( $this, 'registerTaxonomy' ), 7 );
		add_action( 'init', array( $this, 'registerMeta' ), 8 );
		add_action( 'save_post_' . ID::POST_TYPE, array( $this, 'reindex' ), 99 );
		add_filter( 'rest_prepare_' . ID::POST_TYPE, array( $this, 'protectRest' ), 10, 2 );
	}

	/** The event post type. */
	public function registerPostType(): void {
		register_post_type(
			ID::POST_TYPE,
			array(
				'labels'          => self::postTypeLabels(),
				'public'          => true,
				'show_in_rest'    => true,
				'has_archive'     => false,
				'menu_icon'       => 'dashicons-calendar-alt',
				'menu_position'   => 27,
				'supports'        => array( 'title', 'editor', 'thumbnail', 'excerpt', 'author', 'revisions', 'custom-fields' ),
				'capability_type' => array( ID::CAP_TYPE, ID::CAP_PLURAL ),
				'map_meta_cap'    => true,
				'rewrite'         => array(
					'slug'       => sanitize_title( (string) Settings::get( 'event_slug' ) ) ?: 'events',
					'with_front' => false,
				),
			)
		);
	}

	/**
	 * The words for events, in sentence case ("All events / Add event / Edit event").
	 *
	 * @return array<string, string>
	 */
	public static function postTypeLabels(): array {
		return array(
			'name'                  => __( 'Events', 'favr-events' ),
			'singular_name'         => __( 'Event', 'favr-events' ),
			'menu_name'             => __( 'Events', 'favr-events' ),
			'all_items'             => __( 'All events', 'favr-events' ),
			'add_new'               => __( 'Add event', 'favr-events' ),
			'add_new_item'          => __( 'Add event', 'favr-events' ),
			'edit_item'             => __( 'Edit event', 'favr-events' ),
			'new_item'              => __( 'New event', 'favr-events' ),
			'view_item'             => __( 'View event', 'favr-events' ),
			'view_items'            => __( 'View events', 'favr-events' ),
			'search_items'          => __( 'Search events', 'favr-events' ),
			'not_found'             => __( 'No events found.', 'favr-events' ),
			'not_found_in_trash'    => __( 'No events found in Trash.', 'favr-events' ),
			'featured_image'        => __( 'Event image', 'favr-events' ),
			'set_featured_image'    => __( 'Set event image', 'favr-events' ),
			'remove_featured_image' => __( 'Remove event image', 'favr-events' ),
			'use_featured_image'    => __( 'Use as event image', 'favr-events' ),
			'insert_into_item'      => __( 'Insert into event', 'favr-events' ),
			'uploaded_to_this_item' => __( 'Uploaded to this event', 'favr-events' ),
			'filter_items_list'     => __( 'Filter events list', 'favr-events' ),
			'items_list_navigation' => __( 'Events list navigation', 'favr-events' ),
			'items_list'            => __( 'Events list', 'favr-events' ),
			'item_published'        => __( 'Event published.', 'favr-events' ),
			'item_updated'          => __( 'Event updated.', 'favr-events' ),
		);
	}

	/**
	 * The words for event categories. The screen is "Event categories"; one of them is simply
	 * a "Category", as the menu and the box on the event say.
	 *
	 * @return array<string, string>
	 */
	public static function categoryLabels(): array {
		return array(
			'name'                  => __( 'Event categories', 'favr-events' ),
			'singular_name'         => __( 'Category', 'favr-events' ),
			'menu_name'             => __( 'Categories', 'favr-events' ),
			'all_items'             => __( 'All categories', 'favr-events' ),
			'edit_item'             => __( 'Edit category', 'favr-events' ),
			'view_item'             => __( 'View category', 'favr-events' ),
			'update_item'           => __( 'Update category', 'favr-events' ),
			'add_new_item'          => __( 'Add category', 'favr-events' ),
			'new_item_name'         => __( 'New category name', 'favr-events' ),
			'parent_item'           => __( 'Parent category', 'favr-events' ),
			'parent_item_colon'     => __( 'Parent category:', 'favr-events' ),
			'search_items'          => __( 'Search categories', 'favr-events' ),
			'not_found'             => __( 'No categories found.', 'favr-events' ),
			'no_terms'              => __( 'No categories', 'favr-events' ),
			'most_used'             => __( 'Most used', 'favr-events' ),
			'filter_by_item'        => __( 'Filter by category', 'favr-events' ),
			'items_list_navigation' => __( 'Categories list navigation', 'favr-events' ),
			'items_list'            => __( 'Categories list', 'favr-events' ),
			'back_to_items'         => __( '← All categories', 'favr-events' ),
		);
	}

	/** Event categories. */
	public function registerTaxonomy(): void {
		register_taxonomy(
			ID::TAX_CATEGORY,
			array( ID::POST_TYPE ),
			array(
				'labels'             => self::categoryLabels(),
				'hierarchical'       => true,
				// No archive pages: a theme's generic archive would list events without dates.
				// Categories filter the Events page (?fe_cat=) instead, so nothing thin reaches
				// search engines or the sitemap.
				'public'             => false,
				'publicly_queryable' => false,
				'show_ui'            => true,
				'show_admin_column'  => true,
				'show_in_rest'       => true,
				'rewrite'            => false,
				'capabilities'       => array(
					'manage_terms' => 'edit_others_' . ID::CAP_PLURAL,
					'edit_terms'   => 'edit_others_' . ID::CAP_PLURAL,
					'delete_terms' => 'edit_others_' . ID::CAP_PLURAL,
					'assign_terms' => 'edit_' . ID::CAP_PLURAL,
				),
			)
		);
	}

	/** Typed meta for every field plus the index. */
	public function registerMeta(): void {
		foreach ( Fields::set()->all() as $field ) {
			$id = (string) $field['id'];
			register_post_meta(
				ID::POST_TYPE,
				ID::meta( $id ),
				array(
					'type'              => 'string',
					'single'            => true,
					'show_in_rest'      => true,
					'sanitize_callback' => static fn( $value ) => (string) Sanitizer::sanitize( Fields::set()->get( $id ) ?? $field, $value ),
					'auth_callback'     => static fn( $allowed, $meta_key, $post_id ): bool => current_user_can( 'edit_post', (int) $post_id ),
				)
			);
		}
		foreach ( array( ID::META_FIRST, ID::META_LAST ) as $key ) {
			register_post_meta(
				ID::POST_TYPE,
				$key,
				array(
					'type'          => 'integer',
					'single'        => true,
					'show_in_rest'  => false,
					'auth_callback' => '__return_false',
				)
			);
		}
	}

	/**
	 * No event details over REST for password-protected events the reader hasn't unlocked.
	 *
	 * @param \WP_REST_Response $response Response.
	 * @param \WP_Post          $post     Post.
	 * @return \WP_REST_Response
	 */
	public function protectRest( $response, $post ) {
		if ( $response instanceof \WP_REST_Response && $post instanceof \WP_Post && '' !== $post->post_password && ! current_user_can( 'edit_post', $post->ID ) ) {
			$data = $response->get_data();
			unset( $data['meta'] );
			$response->set_data( $data );
		}
		return $response;
	}

	/**
	 * Keep the index current after any save.
	 *
	 * @param int $post_id Post.
	 */
	public function reindex( int $post_id ): void {
		if ( ! wp_is_post_revision( $post_id ) ) {
			Event::reindex( $post_id );
		}
	}
}
