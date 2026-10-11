<?php
/**
 * Words an Editor reads on the event screen, in the page builder and after a decision.
 *
 * @package FavrEvents
 */

declare(strict_types=1);

namespace FavrEvents\Tests\Unit;

use FavrEvents\Editing\Queues;
use FavrEvents\Model\Fields;

final class WordsTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Fields::reset();
	}

	protected function tearDown(): void {
		Fields::reset();
		parent::tearDown();
	}

	/** The Directory's admin word is "listing": its entries are people as often as businesses. */
	public function test_the_host_is_a_directory_listing(): void {
		$host      = Fields::set()->get( 'host_business' );
		$organizer = Fields::set()->get( 'organizer_name' );

		$this->assertSame( 'Hosted by (directory listing)', $host['label'] );
		$this->assertSame( 'Shows the listing’s card on the event and the event on the listing.', $host['description'] );
		$this->assertSame( 'A person or group, if not in the directory.', $organizer['description'] );
	}

	/**
	 * One address, one set of words: these are the labels of Favr Directory
	 * (Fields\FieldRegistry) and Favr Members (Model\Fields), word for word.
	 */
	public function test_the_address_is_labelled_as_in_the_directory_and_on_a_member(): void {
		$this->assertSame( 'City', Fields::set()->get( 'city' )['label'] );
		$this->assertSame( 'State / province', Fields::set()->get( 'state' )['label'] );
		$this->assertSame( 'ZIP / postal code', Fields::set()->get( 'postal_code' )['label'] );
	}

	public function test_no_field_says_business(): void {
		foreach ( Fields::set()->all() as $field ) {
			$this->assertStringNotContainsStringIgnoringCase( 'business', (string) ( $field['label'] ?? '' ) . ' ' . (string) ( $field['description'] ?? '' ), 'Field ' . $field['id'] );
		}
	}

	/** The page builder lists the widget and the block in sentence case, without "&". */
	public function test_the_my_events_widget_and_block_are_named_in_sentence_case(): void {
		$root  = dirname( __DIR__, 2 );
		$block = json_decode( (string) file_get_contents( $root . '/blocks/my-events/block.json' ), true );

		$this->assertSame( 'My events (submit and manage)', $block['title'] );
		$this->assertStringContainsString( "__( 'My events (submit and manage)', 'favr-events' )", (string) file_get_contents( $root . '/src/Integration/Elementor/MyEventsWidget.php' ) );
	}

	/** The button says "Reject", so the message says "Rejected", not "Declined". */
	public function test_the_message_after_rejecting_uses_the_buttons_word(): void {
		$this->assertSame( 'Rejected Harbor Mixer.', Queues::rejectedMessage( 'Harbor Mixer' ) );
	}
}
