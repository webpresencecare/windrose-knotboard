<?php
/**
 * Knotboard on a single site (no multisite): its administrators manage the library; no network switch. Run with
 * phpunit-single.xml.dist.
 *
 * @package Windrose\Knotboard
 */

use Windrose\Knotboard\Network;
use Windrose\Knotboard\Content;
use Windrose\Knotboard\Render;

/**
 * @covers \Windrose\Knotboard\Network
 * @covers \Windrose\Knotboard\Content
 */
class SingleSiteTest extends WP_UnitTestCase {

	public function test_administrators_manage_and_visitors_get_offered_patterns(): void {
		$this->assertFalse( is_multisite() );
		$admin = self::factory()->user->create( array( 'role' => 'administrator' ) );
		$id    = self::factory()->post->create(
			array(
				'post_type'   => Content::TYPE,
				'post_status' => 'publish',
				'post_title'  => 'Hours',
			)
		);
		wp_set_current_user( $admin );
		update_site_option( Network::OPTION, array( 'site_admins' => false ) );
		$this->assertTrue( current_user_can( Content::CAP ), 'The network switch means nothing on a single site.' );
		$this->assertTrue( current_user_can( 'edit_post', $id ) );

		wp_set_current_user( 0 );
		$this->assertSame( array( 'knotboard/' . $id ), array_column( Render::items( null, Render::SOURCE, array( (string) $id ), true ), 'name' ) );
	}
}
