<?php
/**
 * Previews through Windrose Livery: which library patterns each person gets (never the site's own patterns), when visitors get previews at all, and (with
 * Livery loaded) Livery's own checks around them.
 *
 * @package Windrose\Knotboard
 */

use Windrose\Knotboard\Render;

/**
 * @covers \Windrose\Knotboard\Render
 */
class RenderTest extends Knotboard_TestCase {

	public function tear_down(): void {
		$_POST = array();
		parent::tear_down();
	}

	public function test_visitors_get_only_offered_patterns_in_order(): void {
		$a     = $this->pattern( 'A' );
		$b     = $this->pattern( 'B', '<!-- wp:heading --><h2 class="wp-block-heading">B</h2><!-- /wp:heading -->', 'header' );
		$off   = $this->pattern( 'Private', '', 'section', 'private' );
		$draft = $this->pattern( 'Draft', '', 'section', 'draft' );
		$post  = self::factory()->post->create( array( 'post_title' => 'A post' ) );
		$block = self::factory()->post->create(
			array(
				'post_type'  => 'wp_block',
				'post_title' => 'A site pattern',
			)
		);
		$keys  = array( (string) $b, (string) $off, (string) $draft, (string) $post, (string) $block, 'x1', (string) $a );

		wp_set_current_user( 0 );
		$items = Render::items( null, Render::SOURCE, $keys, true );
		$this->assertSame( array( 'knotboard/' . $b, 'knotboard/' . $a ), array_column( $items, 'name' ) );
		$this->assertSame( 'B', $items[0]['title'] );
		$this->assertStringContainsString( '<h2 class="wp-block-heading">B</h2>', $items[0]['content'] );

		// Livery's administrators (its nonce and rights) see any pattern they can read.
		wp_set_current_user( self::$super );
		$items = Render::items( null, Render::SOURCE, $keys, false );
		$this->assertSame( array( 'knotboard/' . $b, 'knotboard/' . $off, 'knotboard/' . $draft, 'knotboard/' . $a ), array_column( $items, 'name' ) );

		$this->assertSame( 'other answer', Render::items( 'other answer', 'other', $keys, true ), 'Other sources pass through.' );
	}

	public function test_visitors_previews_only_where_patterns_are_offered(): void {
		$this->assertFalse( Render::visitors( false, Render::SOURCE ) );
		$this->pattern( 'A' );
		$this->assertTrue( Render::visitors( false, Render::SOURCE ) );
		$this->assertFalse( Render::visitors( false, 'other' ) );
		$this->assertTrue( Render::visitors( true, 'other' ) );
	}

	public function test_style_mermaid_opens_the_library_where_patterns_are_offered(): void {
		$this->assertSame( '', Render::library( '' ) );
		$this->pattern( 'A' );
		$this->assertSame( \Windrose\Knotboard\Content::library_url(), Render::library( '' ) );
		if ( \Windrose\Knotboard\Plugin::has_livery() ) {
			$this->assertSame( \Windrose\Knotboard\Content::library_url(), \Windrose\Livery\Mermaid::pattern_library() );
		}
	}

	public function test_through_livery(): void {
		$this->needs_livery();
		switch_theme( 'ipsum' );
		$a = $this->pattern( 'A' );
		$this->pattern( 'Draft', '', 'section', 'draft' );
		$recipes = \Windrose\Livery\Catalog::ready_recipes();
		update_option( \Windrose\Livery\Mermaid::OPTION, array( 'public' => false ) );
		wp_set_current_user( 0 );
		$_POST   = array(
			'recipe' => (string) wp_json_encode( $recipes[0]['recipe'] ),
			'source' => Render::SOURCE,
			'items'  => $a . ',999999',
			'layout' => 'page',
		);
		$context = \Windrose\Livery\Patterns::context();
		$this->assertIsArray( $context, 'A visitor of a library gets previews with the public Style Mermaid off.' );
		$this->assertTrue( $context['visitor'] );
		$this->assertSame( array( 'knotboard/' . $a ), array_column( $context['items'], 'name' ) );

		wp_update_post(
			array(
				'ID'          => $a,
				'post_status' => 'draft',
			)
		);
		$this->assertSame( 403, \Windrose\Livery\Patterns::context()->get_error_data()['status'], 'Nothing offered: no previews for visitors.' );
		delete_option( \Windrose\Livery\Mermaid::OPTION );
		switch_theme( WP_DEFAULT_THEME );
	}
}
