<?php
/**
 * PluginLinks unit tests.
 *
 * @package RemoteMediaSource
 */

namespace RemoteMediaSource\Tests\Unit\Admin;

use PHPUnit\Framework\TestCase;
use RemoteMediaSource\Admin\PluginLinks;

#[\PHPUnit\Framework\Attributes\CoversClass( PluginLinks::class )]
class PluginLinksTest extends TestCase {

	public function test_settings_link_is_prepended(): void {
		$links = PluginLinks::add_settings_link( array( 'deactivate' => '<a href="#">Deactivate</a>' ) );

		$first = reset( $links );
		$this->assertStringContainsString( 'page=remote-media-source', $first );
		$this->assertStringContainsString( '>Settings<', $first );
	}

	public function test_existing_links_are_preserved(): void {
		$existing = array( 'deactivate' => '<a href="#">Deactivate</a>' );
		$links    = PluginLinks::add_settings_link( $existing );

		$this->assertCount( 2, $links );
		$this->assertContains( $existing['deactivate'], $links );
	}

	public function test_handles_empty_links_array(): void {
		$links = PluginLinks::add_settings_link( array() );
		$this->assertCount( 1, $links );
	}
}
