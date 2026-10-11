<?php
/**
 * What the shared field script is loaded with.
 *
 * @package FavrEvents
 */

declare(strict_types=1);

namespace FavrEvents\Tests\Unit;

use FavrEvents\Admin\Assets;
use PHPUnit\Framework\TestCase as Base;

final class AdminAssetsTest extends Base {

	/**
	 * The field script (favr/core) calls jQuery UI sortable as soon as the page is ready, on
	 * every screen it is loaded on, whether or not the screen has a gallery. Without sortable
	 * it stops with "$(...).sortable is not a function" (seen on the events list, the
	 * categories screen and Event settings).
	 */
	public function test_field_script_always_gets_sortable(): void {
		foreach ( array( false, true ) as $settings ) {
			$deps = Assets::scriptDependencies( $settings );
			$this->assertContains( 'jquery', $deps );
			$this->assertContains( 'jquery-ui-sortable', $deps );
		}
	}

	public function test_color_picker_only_on_settings(): void {
		$this->assertNotContains( 'wp-color-picker', Assets::scriptDependencies( false ) );
		$this->assertContains( 'wp-color-picker', Assets::scriptDependencies( true ) );
	}
}
