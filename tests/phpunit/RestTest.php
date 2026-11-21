<?php
/**
 * REST routes: copies of offered (published) library patterns only, Livery's ready styles.
 *
 * @package Windrose\Knotboard
 */

use Windrose\Knotboard\Rest;

/**
 * @covers \Windrose\Knotboard\Rest
 */
class RestTest extends Knotboard_TestCase {

	/**
	 * GET a route as a visitor.
	 *
	 * @param string               $path  Path.
	 * @param array<string, mixed> $query Query.
	 * @return WP_REST_Response
	 */
	private function get( string $path, array $query = array() ): WP_REST_Response {
		wp_set_current_user( 0 );
		$request = new WP_REST_Request( 'GET', '/' . Rest::NAMESPACE . $path );
		$request->set_query_params( $query );
		return rest_do_request( $request );
	}

	public function test_copy_offered_patterns_in_order_with_detached_pictures(): void {
		$img  = '<!-- wp:image {"id":3} --><figure class="wp-block-image"><img src="https://library.example/a.webp" alt="" class="wp-image-3"/></figure><!-- /wp:image -->';
		$a    = $this->pattern( 'A', $img, 'section', 'publish', 'ar' );
		$b    = $this->pattern( 'B', '', 'footer' );
		$off  = $this->pattern( 'Draft', '', 'section', 'draft' );
		$data = $this->get( '/copy', array( 'ids' => "$b,$off,$a,$b,abc" ) )->get_data();
		$this->assertSame( array( $b, $a ), array_column( $data['patterns'], 'id' ) );
		$this->assertSame( 'footer', $data['patterns'][0]['kind'] );
		$this->assertSame( 'ar', $data['patterns'][1]['lang'] );
		$this->assertSame( 'rtl', $data['patterns'][1]['dir'] );
		$this->assertStringContainsString( 'src="https://library.example/a.webp"', $data['patterns'][1]['content'] );
		$this->assertStringNotContainsString( 'wp-image-3', $data['patterns'][1]['content'] );
		$this->assertTrue( $data['patterns'][1]['pictures'] );
		$this->assertFalse( $data['patterns'][0]['pictures'] );
		$this->assertSame( 404, $this->get( '/copy', array( 'ids' => (string) $off ) )->get_status() );
		wp_update_post(
			array(
				'ID'            => $b,
				'post_password' => 'secret',
			)
		);
		$this->assertSame( 404, $this->get( '/copy', array( 'ids' => (string) $b ) )->get_status(), 'A password-protected pattern is not offered.' );
		$this->assertSame( 400, $this->get( '/copy' )->get_status() );
		$this->assertCount( Rest::MAX_COPY, Rest::ids( implode( ',', range( 1, 100 ) ) ) );
		$this->assertSame( array( 5, 2 ), Rest::ids( '5, 2,5,-1,0,x' ) );
	}

	public function test_ready_styles(): void {
		$this->needs_livery();
		$list = $this->get( '/styles' )->get_data()['styles'];
		$this->assertSame( 'ipsum', $list[0]['slug'] );
		$this->assertArrayNotHasKey( 'recipe', $list[0] );
		$one = $this->get( '/styles/jukebox' );
		$this->assertSame( 200, $one->get_status() );
		$this->assertSame( 'bundled', $one->get_data()['recipe']['fonts']['heading']['source'] );
		$this->assertSame( 404, $this->get( '/styles/nothing-here' )->get_status() );
	}
}
