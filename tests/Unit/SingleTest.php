<?php
/**
 * The event page: the description inside its wrapper, once WordPress has formatted it.
 *
 * @package FavrEvents
 */

declare(strict_types=1);

namespace FavrEvents\Tests\Unit;

use FavrEvents\Frontend\Single;

require_once dirname( __DIR__ ) . '/fixtures/wordpress-autop.php';

/**
 * Frontend\Single filters the_content after WordPress's wpautop() (priority 10) has turned
 * the description's blank lines into paragraphs, so the details panel never goes through it.
 * These tests run that same function (the copy in tests/fixtures) over the description and
 * then add the panel, which is what a visitor's browser receives.
 */
final class SingleTest extends TestCase {

	private const DETAILS = '<div class="favr-ev-details"><p class="favr-ev-when">Fri, Oct 16</p></div>';
	private const AFTER   = '<p class="favr-ev-back"><a href="/events/">All events</a></p>';

	/**
	 * The page as WordPress prints it.
	 *
	 * @param string $content The description as stored.
	 */
	private function page( string $content ): string {
		return Single::assemble( self::DETAILS, \wpautop( $content ), self::AFTER );
	}

	/**
	 * The details panel as templates/single-details.php prints it: a row per fact, indented.
	 */
	private function panel(): string {
		return "<section class=\"favr-ev-details\" aria-label=\"Event details\">\n"
			. "\t<dl class=\"favr-ev-details__list\">\n"
			. "\t\t\t<div>\n\t\t\t\t<dt>When</dt>\n\t\t\t\t<dd>\n\t\t\t\t\tThursday, October 22, 2026 · 7:30 am – 9:00 am\t\t\t\t</dd>\n\t\t\t</div>\n"
			. "\t\t\t<div>\n\t\t\t\t<dt>Cost</dt>\n\t\t\t\t<dd>\$15 members / \$25 guests</dd>\n\t\t\t</div>\n"
			. "\t</dl>\n"
			. "</section>\n";
	}

	/* ------------------------------------------------------------- The panel */

	/**
	 * The panel used to be added before wpautop ran. wpautop is made for prose: it closed a
	 * paragraph it never opened in every row (`</dd> </p></div>`), which a browser turns into
	 * an empty paragraph inside the row. The panel is now added after it and reaches the page
	 * byte for byte.
	 */
	public function test_the_details_panel_reaches_the_page_exactly_as_the_template_printed_it(): void {
		$page = Single::assemble( $this->panel(), \wpautop( "First paragraph\n\nSecond paragraph" ), self::AFTER );

		$this->assertStringStartsWith( $this->panel() . '<div class="favr-ev-description">', $page );
		$this->assertStringNotContainsString( '</p></div>', $page );
		$this->assertParagraphsBalanced( $page );
	}

	/** Why: this is what wpautop does to the panel when it gets hold of it. */
	public function test_wordpress_paragraph_formatting_would_break_the_panel(): void {
		$this->assertStringContainsString( '</p></div>', \wpautop( $this->panel() ) );
	}

	public function test_the_panel_is_added_after_wordpress_has_formatted_the_description(): void {
		$single = new Single();
		$single->hook();

		$this->assertGreaterThan( 10, has_filter( 'the_content', array( $single, 'content' ) ), 'wpautop runs at priority 10.' );
		$this->assertLessThan( 99999, has_filter( 'the_content', array( $single, 'content' ) ), 'Favr Members replaces a members-only page last of all.' );
	}

	/* ------------------------------------------------------- The description */

	/**
	 * What is inside the description wrapper on the printed page.
	 *
	 * @param string $content The description as stored.
	 */
	private function description( string $content ): string {
		$this->assertSame( 1, preg_match( '#<div class="favr-ev-description">(.*?)</div>#s', $this->page( $content ), $found ), 'The description wrapper is on the page.' );
		return trim( $found[1] );
	}

	/**
	 * Every paragraph that is closed was opened, and the other way round.
	 *
	 * @param string $html HTML.
	 */
	private function assertParagraphsBalanced( string $html ): void {
		$depth = 0;
		preg_match_all( '#<(/?)p\b[^>]*>#', $html, $tags );
		foreach ( $tags[1] as $closing ) {
			$depth += '/' === $closing ? -1 : 1;
			$this->assertContains( $depth, array( 0, 1 ), 'A </p> without its <p>, or a <p> inside a <p>: ' . $html );
		}
		$this->assertSame( 0, $depth, 'A <p> is never closed: ' . $html );
	}

	/**
	 * The editor stores what staff type without <p> tags: paragraphs are blank lines. That is
	 * the main way an event is written now, and the first paragraph came out as
	 * `<div class="favr-ev-description">First paragraph</p>`.
	 */
	public function test_a_description_typed_in_the_editor_has_every_paragraph_wrapped(): void {
		$description = $this->description( "First paragraph\n\nSecond paragraph\n\nThird paragraph" );

		$this->assertSame( "<p>First paragraph</p>\n<p>Second paragraph</p>\n<p>Third paragraph</p>", $description );
		$this->assertParagraphsBalanced( $this->page( "First paragraph\n\nSecond paragraph\n\nThird paragraph" ) );
	}

	public function test_a_one_line_description_is_a_paragraph(): void {
		$this->assertSame( '<p>Doors open at six.</p>', $this->description( 'Doors open at six.' ) );
	}

	public function test_a_line_break_inside_a_paragraph_stays_inside_it(): void {
		$this->assertSame( "<p>Line one<br />\nLine two</p>\n<p>Second paragraph</p>", $this->description( "Line one\nLine two\n\nSecond paragraph" ) );
	}

	/** Text that starts or ends with an inline tag lost a <p> at the start and a </p> at the end. */
	public function test_formatting_at_either_end_does_not_cost_a_paragraph_its_tags(): void {
		$content = "<strong>Free for members.</strong> Guests pay at the door.\n\nQuestions? <a href=\"mailto:a@example.org\">Write to us</a>";

		$this->assertSame( "<p><strong>Free for members.</strong> Guests pay at the door.</p>\n<p>Questions? <a href=\"mailto:a@example.org\">Write to us</a></p>", $this->description( $content ) );
		$this->assertParagraphsBalanced( $this->page( $content ) );
	}

	public function test_text_around_a_list_is_wrapped_and_the_list_is_not(): void {
		$content = "Bring:\n\n<ul>\n<li>Business cards</li>\n<li>A question</li>\n</ul>\n\nSee you there.";

		$this->assertSame( "<p>Bring:</p>\n<ul>\n<li>Business cards</li>\n<li>A question</li>\n</ul>\n<p>See you there.</p>", $this->description( $content ) );
		$this->assertParagraphsBalanced( $this->page( $content ) );
	}

	/**
	 * Events whose description already carries its own <p> tags (the seeded events, anything
	 * pasted as HTML) come out as WordPress formats them anywhere else.
	 *
	 * @dataProvider alreadyMarkedUp
	 *
	 * @param string $content The description as stored.
	 */
	public function test_a_description_with_its_own_paragraph_tags_keeps_them( string $content ): void {
		$this->assertSame( trim( \wpautop( $content ) ), $this->description( $content ) );
		$this->assertParagraphsBalanced( $this->page( $content ) );
	}

	/** @return array<string, array{string}> */
	public static function alreadyMarkedUp(): array {
		return array(
			'paragraphs on one line'   => array( '<p>Start the day with coffee.</p><p>Guests are welcome twice before joining.</p>' ),
			'paragraphs on two lines'  => array( "<p>Start the day with coffee.</p>\n<p>Guests are welcome twice before joining.</p>" ),
			'paragraphs, blank line'   => array( "<p>Start the day with coffee.</p>\n\n<p>Guests are welcome twice before joining.</p>" ),
			'a single paragraph'       => array( '<p>Start the day with coffee.</p>' ),
			'paragraphs around a list' => array( '<p>Bring:</p><ul><li>Cards</li></ul><p>See you there.</p>' ),
		);
	}

	/** Nothing to wrap: the wrapper stays empty, so a theme's `:empty` rule still matches it. */
	public function test_an_event_without_a_description_keeps_an_empty_wrapper(): void {
		$this->assertStringContainsString( '<div class="favr-ev-description"></div>', Single::assemble( self::DETAILS, '', self::AFTER ) );
		$this->assertStringContainsString( '<div class="favr-ev-description"></div>', $this->page( '' ) );
	}

	/** The panel comes first, what follows the description comes last. */
	public function test_the_details_and_what_follows_stay_where_they_were(): void {
		$page = $this->page( "First paragraph\n\nSecond paragraph" );

		$this->assertStringStartsWith( self::DETAILS . '<div class="favr-ev-description">', $page );
		$this->assertStringEndsWith( '</div>' . self::AFTER, $page );
	}
}
