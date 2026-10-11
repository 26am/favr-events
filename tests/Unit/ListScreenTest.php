<?php
/**
 * The events list.
 *
 * @package FavrEvents
 */

declare(strict_types=1);

namespace FavrEvents\Tests\Unit;

use Brain\Monkey\Functions;
use FavrEvents\Admin\ListScreen;

final class ListScreenTest extends TestCase {

	/**
	 * Trashing or restoring from the list says "event", not "post".
	 */
	public function test_list_notices_say_event(): void {
		Functions\when( '_n' )->alias( static fn( string $one, string $many, int $count ): string => 1 === $count ? $one : $many );

		$messages = ( new ListScreen() )->bulkMessages(
			array( 'post' => array( 'trashed' => '%s post moved to the Trash.' ) ),
			array(
				'updated'   => 1,
				'locked'    => 2,
				'deleted'   => 2,
				'trashed'   => 1,
				'untrashed' => 3,
			)
		);

		$this->assertSame( '%s post moved to the Trash.', $messages['post']['trashed'] );
		$this->assertSame(
			array(
				'updated'   => '%s event updated.',
				'locked'    => '%s events not updated, somebody is editing them.',
				'deleted'   => '%s events permanently deleted.',
				'trashed'   => '%s event moved to the Trash.',
				'untrashed' => '%s events restored from the Trash.',
			),
			$messages['favr_event']
		);
	}
}
