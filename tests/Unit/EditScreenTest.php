<?php
/**
 * The event edit screen: one panel that also holds the description and the short summary.
 *
 * @package FavrEvents
 */

declare(strict_types=1);

namespace FavrEvents\Tests\Unit;

use Brain\Monkey\Functions;
use FavrEvents\Admin\EditScreen;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;

final class EditScreenTest extends TestCase {

	use MockeryPHPUnitIntegration;

	private const TABS = array(
		'when'    => array(
			'label' => 'Date & time',
			'icon'  => 'dashicons-calendar-alt',
		),
		'where'   => array(
			'label' => 'Location',
			'icon'  => 'dashicons-location',
		),
		'details' => array(
			'label' => 'Tickets & details',
			'icon'  => 'dashicons-tickets-alt',
		),
		'host'    => array(
			'label' => 'Host',
			'icon'  => 'dashicons-businessperson',
		),
	);

	protected function setUp(): void {
		parent::setUp();
		$escape = static fn( $text ): string => htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
		Functions\stubs(
			array(
				'esc_html'     => $escape,
				'esc_attr'     => $escape,
				'esc_textarea' => $escape,
				'esc_html__'   => $escape,
				'esc_attr__'   => $escape,
				'esc_url'      => static fn( $url ): string => (string) $url,
			)
		);
	}

	/* ------------------------------------------------------------------ Tabs */

	public function test_description_tab_comes_straight_after_date_and_time(): void {
		$tabs = EditScreen::panelTabs( self::TABS, array( 'when', 'where', 'details', 'host' ) );

		$this->assertSame( array( 'when', 'description', 'where', 'details', 'host' ), array_keys( $tabs ) );
		$this->assertSame( 'Description', $tabs['description']['label'] );
		$this->assertStringStartsWith( 'dashicons-', $tabs['description']['icon'] );
	}

	public function test_tabs_without_fields_are_left_out_but_description_always_shows(): void {
		$tabs = EditScreen::panelTabs( self::TABS, array( 'when', 'details' ) );

		$this->assertSame( array( 'when', 'description', 'details' ), array_keys( $tabs ) );
	}

	public function test_description_leads_when_there_is_no_date_tab(): void {
		$tabs = EditScreen::panelTabs( self::TABS, array( 'where', 'host' ) );

		$this->assertSame( array( 'description', 'where', 'host' ), array_keys( $tabs ) );
	}

	/**
	 * A site can move or rename the tab with the `favr_events_tabs` filter by declaring it.
	 */
	public function test_a_site_can_place_and_name_the_description_tab_itself(): void {
		$declared = self::TABS + array(
			'description' => array(
				'label' => 'About',
				'icon'  => 'dashicons-info',
			),
		);
		$tabs     = EditScreen::panelTabs( $declared, array( 'when', 'where', 'details', 'host' ) );

		$this->assertSame( array( 'when', 'where', 'details', 'host', 'description' ), array_keys( $tabs ) );
		$this->assertSame( 'About', $tabs['description']['label'] );
	}

	/* ------------------------------------------------- Description and summary */

	public function test_wordpress_does_not_draw_its_own_editor_on_the_event_screen(): void {
		Functions\when( 'get_current_screen' )->justReturn( (object) array( 'post_type' => 'favr_event' ) );
		Functions\expect( 'remove_post_type_support' )->once()->with( 'favr_event', 'editor' );

		( new EditScreen() )->prepareScreen();
	}

	public function test_other_post_types_keep_their_editor(): void {
		Functions\when( 'get_current_screen' )->justReturn( (object) array( 'post_type' => 'page' ) );
		Functions\expect( 'remove_post_type_support' )->never();

		( new EditScreen() )->prepareScreen();
	}

	public function test_wordpress_excerpt_and_custom_fields_boxes_are_removed(): void {
		Functions\expect( 'remove_meta_box' )->once()->with( 'postexcerpt', 'favr_event', 'normal' );
		Functions\expect( 'remove_meta_box' )->once()->with( 'postcustom', 'favr_event', 'normal' );

		( new EditScreen() )->metaBoxes();
	}

	/**
	 * The field keeps WordPress's own name and id ("excerpt"), so WordPress saves it to
	 * post_excerpt and autosave keeps reading it.
	 */
	public function test_short_summary_is_the_excerpt_under_a_plain_name(): void {
		$html = EditScreen::summaryField( 'Coffee & <b>intros</b>' );

		$this->assertStringContainsString( '<label class="favr-field__label" for="excerpt">Short summary</label>', $html );
		$this->assertMatchesRegularExpression( '/<textarea[^>]* id="excerpt"[^>]* name="excerpt"[^>]*>Coffee &amp; &lt;b&gt;intros&lt;\/b&gt;<\/textarea>/', $html );
		$this->assertStringContainsString( '<p class="favr-field__help" id="favr-event-summary-help">Shown to search engines and in calendar apps. Leave empty to use the start of the description.</p>', $html );
		$this->assertStringContainsString( 'aria-describedby="favr-event-summary-help"', $html );
	}

	public function test_summary_of_a_post_prepared_for_editing_is_read_unescaped(): void {
		$post               = new \WP_Post();
		$post->ID           = 7;
		$post->filter       = 'edit';
		$post->post_excerpt = 'Tea &amp; cake';
		Functions\expect( 'get_post_field' )->once()->with( 'post_excerpt', 7, 'raw' )->andReturn( 'Tea & cake' );

		$this->assertSame( 'Tea & cake', EditScreen::summaryText( $post ) );
	}

	public function test_summary_of_a_new_event_is_used_as_given(): void {
		$post               = new \WP_Post();
		$post->post_excerpt = 'Tea & cake';
		Functions\expect( 'get_post_field' )->never();

		$this->assertSame( 'Tea & cake', EditScreen::summaryText( $post ) );
	}

	/* ----------------------------------------------------------------- Words */

	public function test_title_field_asks_for_the_event_name(): void {
		$event = new \WP_Post();
		$page  = new \WP_Post();

		$page->post_type = 'page';

		$this->assertSame( 'Event name', ( new EditScreen() )->titlePlaceholder( 'Add title', $event ) );
		$this->assertSame( 'Add title', ( new EditScreen() )->titlePlaceholder( 'Add title', $page ) );
	}

	public function test_save_messages_name_the_event(): void {
		Functions\when( 'get_post' )->justReturn( null );

		$messages = ( new EditScreen() )->messages( array( 'post' => array( 1 => 'Post updated.' ) ) );

		$this->assertSame( 'Post updated.', $messages['post'][1] );
		$this->assertSame(
			array(
				0  => '',
				1  => 'Event updated.',
				4  => 'Event updated.',
				5  => 'Event restored to an earlier version.',
				6  => 'Event published.',
				7  => 'Event saved.',
				8  => 'Event submitted.',
				9  => 'Event scheduled.',
				10 => 'Event draft updated.',
			),
			$messages['favr_event']
		);
	}

	public function test_save_messages_link_to_the_event(): void {
		Functions\when( 'get_post' )->justReturn( new \WP_Post() );
		Functions\when( 'get_permalink' )->justReturn( 'https://example.test/events/breakfast/' );

		$messages = ( new EditScreen() )->messages( array() );

		$this->assertSame( 'Event published. <a href="https://example.test/events/breakfast/">View event</a>', $messages['favr_event'][6] );
		$this->assertSame( 'Event updated. <a href="https://example.test/events/breakfast/">View event</a>', $messages['favr_event'][1] );
	}
}
