<?php
/**
 * The words staff read: sentence case, "All x / Add x / Edit x".
 *
 * @package FavrEvents
 */

declare(strict_types=1);

namespace FavrEvents\Tests\Unit;

use FavrEvents\Model\Registrar;

final class LabelsTest extends TestCase {

	public function test_event_labels_are_sentence_case(): void {
		$labels = Registrar::postTypeLabels();

		$this->assertSame( 'Events', $labels['name'] );
		$this->assertSame( 'Event', $labels['singular_name'] );
		$this->assertSame( 'Events', $labels['menu_name'] );
		$this->assertSame( 'All events', $labels['all_items'] );
		$this->assertSame( 'Add event', $labels['add_new'] );
		$this->assertSame( 'Add event', $labels['add_new_item'] );
		$this->assertSame( 'Edit event', $labels['edit_item'] );
		$this->assertSame( 'New event', $labels['new_item'] );
		$this->assertSame( 'View event', $labels['view_item'] );
		$this->assertSame( 'View events', $labels['view_items'] );
		$this->assertSame( 'Search events', $labels['search_items'] );
		$this->assertSame( 'No events found.', $labels['not_found'] );
		$this->assertSame( 'Event published.', $labels['item_published'] );
		$this->assertSame( 'Event updated.', $labels['item_updated'] );
	}

	public function test_event_image_and_media_window_say_event_not_post(): void {
		$labels = Registrar::postTypeLabels();

		$this->assertSame( 'Event image', $labels['featured_image'] );
		$this->assertSame( 'Set event image', $labels['set_featured_image'] );
		$this->assertSame( 'Remove event image', $labels['remove_featured_image'] );
		$this->assertSame( 'Use as event image', $labels['use_featured_image'] );
		$this->assertSame( 'Insert into event', $labels['insert_into_item'] );
		$this->assertSame( 'Uploaded to this event', $labels['uploaded_to_this_item'] );
	}

	public function test_category_labels_are_sentence_case(): void {
		$labels = Registrar::categoryLabels();

		$this->assertSame( 'Event categories', $labels['name'] );
		$this->assertSame( 'Category', $labels['singular_name'] );
		$this->assertSame( 'Categories', $labels['menu_name'] );
		$this->assertSame( 'All categories', $labels['all_items'] );
		$this->assertSame( 'Add category', $labels['add_new_item'] );
		$this->assertSame( 'Edit category', $labels['edit_item'] );
		$this->assertSame( 'Update category', $labels['update_item'] );
		$this->assertSame( 'Search categories', $labels['search_items'] );
		$this->assertSame( 'New category name', $labels['new_item_name'] );
		$this->assertSame( 'Parent category', $labels['parent_item'] );
		$this->assertSame( 'No categories found.', $labels['not_found'] );
		$this->assertSame( 'Most used', $labels['most_used'] );
	}

	/**
	 * No label keeps a capital after its first word, apart from "Trash" (the name of a place
	 * in WordPress).
	 */
	public function test_no_label_is_title_case(): void {
		foreach ( array_merge( array_values( Registrar::postTypeLabels() ), array_values( Registrar::categoryLabels() ) ) as $label ) {
			$rest = preg_replace( '/^\W*\w+/u', '', str_replace( 'Trash', 'trash', (string) $label ) );
			$this->assertDoesNotMatchRegularExpression( '/\p{Lu}/u', (string) $rest, "Not sentence case: {$label}" );
		}
	}
}
