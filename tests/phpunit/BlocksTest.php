<?php
/**
 * The templates and the blocks' output: filters (links that keep the other filters), search, actions (the markup to
 * copy, without pictures), previews (language and direction), the selection bar, the selection.
 *
 * @package Windrose\Knotboard
 */

use Windrose\Knotboard\Blocks;
use Windrose\Knotboard\Content;
use Windrose\Knotboard\Templates;

/**
 * @covers \Windrose\Knotboard\Blocks
 * @covers \Windrose\Knotboard\Templates
 */
class BlocksTest extends Knotboard_TestCase {

	public function test_blocks_come_from_an_up_to_date_manifest_with_their_render_callbacks(): void {
		$dir      = WINDROSE_KNOTBOARD_DIR . '/blocks';
		$manifest = require $dir . '/blocks-manifest.php';
		$files    = glob( $dir . '/*/block.json' );
		$this->assertCount( 10, $files );
		foreach ( $files as $file ) {
			$folder = basename( dirname( $file ) );
			// block.json changed without npm run build: core would register the old metadata.
			$this->assertSame( wp_json_file_decode( $file, array( 'associative' => true ) ), $manifest[ $folder ] ?? null, "$folder: run npm run build" );
			$type = WP_Block_Type_Registry::get_instance()->get_registered( $manifest[ $folder ]['name'] );
			$this->assertNotNull( $type, $folder );
			$this->assertTrue( $type->is_dynamic(), $folder );
			$this->assertSame( Blocks::class, $type->render_callback[0] ?? null, $folder );
			$this->assertSame( array( Blocks::VIEW ), $type->view_script_handles, $folder );
		}
		$this->assertCount( 10, $manifest );
	}

	public function test_templates_are_registered_and_made_of_known_blocks(): void {
		foreach ( array( 'archive-' . Content::TYPE, 'single-' . Content::TYPE, Templates::SELECTION ) as $slug ) {
			$template = get_block_template( 'windrose-knotboard//' . $slug );
			$this->assertNotNull( $template, $slug );
			$names = array();
			$walk  = static function ( array $blocks ) use ( &$walk, &$names ): void {
				foreach ( $blocks as $block ) {
					if ( null !== $block['blockName'] ) {
						$names[] = $block['blockName'];
					}
					$walk( $block['innerBlocks'] );
				}
			};
			$walk( parse_blocks( $template->content ) );
			foreach ( array_unique( $names ) as $name ) {
				$this->assertTrue( WP_Block_Type_Registry::get_instance()->is_registered( $name ), "$slug: $name" );
			}
		}
		$archive = get_block_template( 'windrose-knotboard//archive-' . Content::TYPE )->content;
		$this->assertStringContainsString( '"taxonomy":"language"', $archive );
		$this->assertStringContainsString( '"inherit":true', $archive );
		$this->assertNotNull( WP_Block_Patterns_Registry::get_instance()->get_registered( 'windrose-knotboard/library-grid' ) );
	}

	public function test_filters_several_values_chips_and_one_value_hidden(): void {
		$a = $this->pattern( 'A', '', 'header', 'publish', 'en' );
		$b = $this->pattern( 'B', '', 'section', 'publish', 'en' );
		$c = $this->pattern( 'C', '', 'section', 'publish', 'en' );
		wp_set_object_terms( $b, array( 'Heroes' ), Content::CATEGORY );
		wp_set_object_terms( $c, array( 'Events' ), Content::CATEGORY );
		wp_set_object_terms( $b, array( 'restaurant' ), Content::TAG );
		wp_set_object_terms( $c, array( 'architect' ), Content::TAG );
		$this->go_to(
			add_query_arg(
				array(
					Content::CATEGORY => 'heroes,events',
					Content::SEARCH   => 'big',
					'paged'           => 2,
				),
				Content::library_url()
			)
		);
		$this->assertSame( array( 'heroes', 'events' ), Blocks::chosen( Content::CATEGORY ) );

		// Links: each adds its value to the chosen ones, or takes it away; the others and the search are kept.
		$kinds = Blocks::render_filter(
			array(
				'taxonomy' => 'kind',
				'display'  => 'links',
			)
		);
		$this->assertStringContainsString( 'aria-label="Filter by kind"', $kinds );
		$this->assertStringContainsString( '>Headers</a>', $kinds );
		$this->assertStringNotContainsString( 'Footers', $kinds, 'Only kinds in use.' );
		$this->assertStringContainsString( Content::CATEGORY . '=heroes%2Cevents', $kinds );
		$this->assertStringContainsString( Content::SEARCH . '=big', $kinds );
		$this->assertStringNotContainsString( 'paged', $kinds );
		$categories = Blocks::render_filter(
			array(
				'taxonomy' => 'category',
				'display'  => 'links',
			)
		);
		$this->assertMatchesRegularExpression( '#href="[^"]*' . Content::CATEGORY . '=events&[^"]*"[^>]*aria-current="true">Heroes</a>#', $categories, 'A chosen value’s link takes it away.' );

		// One value only: no filter. Languages (all English), then two languages.
		$this->assertSame( '', Blocks::render_filter( array( 'taxonomy' => 'language' ) ) );
		$this->pattern( 'D', '', 'section', 'publish', 'fr' );
		$this->assertStringContainsString(
			'lang="fr"',
			Blocks::render_filter(
				array(
					'taxonomy' => 'language',
					'display'  => 'links',
				)
			)
		);
		$this->assertSame( '', Blocks::render_filter( array( 'taxonomy' => 'nonsense' ) ) );

		// The default: a button opening checkboxes, several at once.
		$in   = static fn( string $name ): WP_Block => new WP_Block( array( 'blockName' => $name ), array( 'windrose-knotboard/form' => true ) );
		$menu = Blocks::render_filter( array( 'taxonomy' => 'category' ), '', $in( 'windrose/pattern-filter' ) );
		$this->assertStringContainsString( '<details class="windrose-knotboard-filter__menu" data-name="Category">', $menu );
		$this->assertMatchesRegularExpression( '#<summary[^>]*>Category \(2\)</summary>#', $menu );
		$this->assertStringContainsString( '<legend>Filter by category</legend>', $menu );
		$this->assertMatchesRegularExpression( '#name="' . Content::CATEGORY . '\[\]" value="heroes" checked#', $menu );
		$this->assertStringContainsString( '1 pattern', $menu );
		$this->assertStringNotContainsString( '<form', $menu );
		$tags = Blocks::render_filter( array( 'taxonomy' => 'tag' ) );
		$this->assertStringContainsString( '<form', $tags, 'Alone: a small form of its own.' );
		$this->assertStringContainsString( 'name="' . Content::CATEGORY . '[]" value="events"', $tags, 'It keeps the other filters.' );

		$field = Blocks::render_search( array(), '', $in( 'windrose/pattern-search' ) );
		$this->assertStringNotContainsString( '<form', $field );
		$this->assertStringContainsString( 'placeholder="Search patterns"', $field );
		$this->assertStringContainsString( 'class="windrose-knotboard-hidden">Search patterns</label>', $field );
		$search = Blocks::render_search();
		$this->assertStringContainsString( 'name="' . Content::SEARCH . '" value="big"', $search );
		$this->assertStringContainsString( 'type="hidden" name="' . Content::CATEGORY . '[]" value="heroes"', $search );

		// One form, one Filter button, the active choices as chips that take themselves away.
		$form = Blocks::render_filters( array(), $field . $menu );
		$this->assertSame( 1, substr_count( $form, '<form' ) );
		$this->assertStringContainsString( '>Filter</button>', $form );
		$this->assertStringContainsString( 'aria-label="Remove the filter Heroes"', $form );
		$this->assertMatchesRegularExpression( '#href="[^"]*' . Content::CATEGORY . '=events[^"]*" aria-label="Remove the filter Heroes"#', $form );
		$this->assertStringContainsString( 'aria-label="Remove the search big"', $form );
		$this->assertStringContainsString( 'Clear filters', $form );

		// A cloud: sizes by how many patterns use a term.
		wp_set_object_terms( $a, array( 'Heroes' ), Content::CATEGORY );
		$cloud = Blocks::render_filter(
			array(
				'taxonomy' => 'category',
				'display'  => 'cloud',
			)
		);
		$this->assertStringContainsString( 'is-cloud', $cloud );
		$this->assertStringContainsString( 'font-size:', $cloud );
	}

	public function test_several_values_or_inside_a_filter_and_between_filters(): void {
		$r  = $this->pattern( 'Restaurant', '', 'section', 'publish', 'en' );
		$ar = $this->pattern( 'Architect', '', 'section', 'publish', 'en' );
		$fr = $this->pattern( 'Restaurant FR', '', 'section', 'publish', 'fr' );
		$this->pattern( 'Shop', '', 'section', 'publish', 'en' );
		wp_set_object_terms( $r, array( 'restaurant' ), Content::TAG );
		wp_set_object_terms( $fr, array( 'restaurant' ), Content::TAG );
		wp_set_object_terms( $ar, array( 'architect' ), Content::TAG );
		$titles = function ( array $args ): array {
			$this->go_to( add_query_arg( $args, Content::library_url() ) );
			$list = wp_list_pluck( $GLOBALS['wp_query']->posts, 'post_title' );
			sort( $list );
			return $list;
		};
		$this->assertSame( array( 'Architect', 'Restaurant', 'Restaurant FR' ), $titles( array( Content::TAG => 'restaurant,architect' ) ), 'OR inside a filter.' );
		$this->assertSame(
			array( 'Architect', 'Restaurant', 'Restaurant FR' ),
			$titles(
				array(
					Content::TAG . '[0]' => 'restaurant',
					Content::TAG . '[1]' => 'architect',
				)
			),
			'Checkboxes send a list.'
		);
		$this->assertSame(
			array( 'Architect', 'Restaurant' ),
			$titles(
				array(
					Content::TAG      => 'restaurant,architect',
					Content::LANGUAGE => 'en',
				)
			),
			'AND between filters.'
		);
		$this->assertSame( array( 'Architect', 'Restaurant', 'Restaurant FR', 'Shop' ), $titles( array( Content::LANGUAGE => 'en,fr' ) ) );
	}

	public function test_actions_and_preview(): void {
		$img   = '<!-- wp:image {"id":3} --><figure class="wp-block-image"><img src="https://library.example/a.webp" alt="" class="wp-image-3"/></figure><!-- /wp:image --><!-- wp:paragraph --><p>Tom &amp; </script> Jerry</p><!-- /wp:paragraph -->';
		$id    = $this->pattern( 'Hours', $img, 'section', 'publish', 'ar' );
		$block = new WP_Block(
			array( 'blockName' => 'windrose/pattern-actions' ),
			array( 'postId' => $id )
		);
		$html  = Blocks::render_actions( array(), '', $block );
		$this->assertStringContainsString( 'data-windrose-knotboard-pattern="' . $id . '"', $html );
		$this->assertStringContainsString( 'data-kind="section"', $html );
		$this->assertStringContainsString( 'Copy pattern', $html );
		$this->assertStringContainsString( 'wp-element-button', $html, 'Copy pattern takes the site’s button style.' );
		// Pictures stay in copies, detached from this site's attachments.
		$this->assertStringContainsString( 'data-pictures="1"', $html );
		$this->assertStringContainsString( 'https:\\/\\/library.example\\/a.webp', $html );
		$this->assertStringNotContainsString( 'wp-image-3', $html );
		$this->assertSame( 1, substr_count( $html, '</script>' ), 'The markup cannot close its script element.' );
		preg_match( '#<script type="application/json" class="windrose-knotboard-actions__markup">(.*)</script>#s', $html, $m );
		$this->assertStringContainsString( 'Tom &amp; </script> Jerry', json_decode( $m[1] ) );
		$this->assertStringNotContainsString( 'View code', Blocks::render_actions( array( 'code' => false ), '', $block ) );

		$preview = Blocks::render_preview( array(), '', new WP_Block( array( 'blockName' => 'windrose/pattern-preview' ), array( 'postId' => $id ) ) );
		if ( \Windrose\Knotboard\Plugin::has_livery() ) {
			$this->assertStringContainsString( 'data-dir="rtl"', $preview );
			$this->assertStringContainsString( 'data-lang="ar"', $preview );
			$this->assertStringContainsString( 'is-thumb', $preview );
			$this->assertStringContainsString( 'aria-hidden="true"', $preview, 'The thumbnail’s link is a duplicate of the title’s.' );
			$this->assertStringContainsString( 'is-full', Blocks::render_preview( array( 'size' => 'full' ), '', new WP_Block( array( 'blockName' => 'windrose/pattern-preview' ), array( 'postId' => $id ) ) ) );
		} else {
			$this->assertSame( '', $preview );
		}

		$draft = $this->pattern( 'Draft', '', 'section', 'draft' );
		wp_set_current_user( 0 );
		$this->assertSame( '', Blocks::render_actions( array(), '', new WP_Block( array( 'blockName' => 'windrose/pattern-actions' ), array( 'postId' => $draft ) ) ), 'Visitors get nothing of a draft.' );
		$protected = $this->pattern( 'Protected', '', 'section' );
		wp_update_post(
			array(
				'ID'            => $protected,
				'post_password' => 'secret',
			)
		);
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );
		$this->assertSame( '', Blocks::render_actions( array(), '', new WP_Block( array( 'blockName' => 'windrose/pattern-actions' ), array( 'postId' => $protected ) ) ), 'Members without the password get nothing either.' );
		$this->assertFalse( Content::grid_query( array( 'post_type' => Content::TYPE ) )['has_password'], 'Grids leave protected patterns out.' );
		$this->assertArrayNotHasKey( 'has_password', Content::grid_query( array( 'post_type' => 'post' ) ) );
		wp_set_current_user( 0 );
		$other = self::factory()->post->create();
		$this->assertSame( '', Blocks::render_actions( array(), '', new WP_Block( array( 'blockName' => 'windrose/pattern-actions' ), array( 'postId' => $other ) ) ) );
	}

	public function test_style_picker_shows_colors(): void {
		$this->needs_livery();
		$recipes = \Windrose\Livery\Catalog::ready_recipes();
		$this->assertCount( 6, Blocks::swatches( $recipes[0]['recipe'] ) );
		$html = Blocks::render_style();
		$this->assertStringContainsString( 'aria-expanded="false"', $html );
		$this->assertStringContainsString( 'Preview style:', $html );
		$this->assertSame( count( $recipes ), substr_count( $html, 'type="radio"' ) );
		$this->assertStringContainsString( '<legend>Preview style</legend>', $html );
		$this->assertMatchesRegularExpression( '/data-colors="#[0-9a-fA-F]{3,8}(,#[0-9a-fA-F]{3,8}){5}"/', $html );
		$this->assertStringContainsString( 'aria-hidden="true"', $html );
		$this->assertSame(
			array(),
			Blocks::swatches(
				array(
					'settings' => array(
						'color' => array(
							'palette' => array(
								array(
									'slug'  => 'base',
									'color' => 'red;}',
								),
							),
						),
					),
				)
			),
			'Only color codes.'
		);
		$palette = array();
		foreach ( array(
			'accent-6' => '#666666',
			'base'     => '#ffffff',
			'extra-1'  => '#123456',
			'contrast' => '#222222',
			'accent-1' => '#111111',
			'accent-5' => '#555555',
			'accent-3' => '#333333',
		) as $slug => $color ) {
			$palette[] = array(
				'slug'  => $slug,
				'color' => $color,
			);
		}
		$this->assertSame(
			array( '#ffffff', '#222222', '#111111', '#333333', '#555555', '#666666' ),
			Blocks::swatches( array( 'settings' => array( 'color' => array( 'palette' => $palette ) ) ) ),
			"Ipsum's six colors in its order, no others."
		);
	}

	public function test_style_picker_links_to_the_style_mermaid_page(): void {
		$this->needs_livery();
		$page = self::factory()->post->create(
			array(
				'post_type'    => 'page',
				'post_title'   => 'Make a style',
				'post_content' => '<!-- wp:windrose/style-mermaid /-->',
			)
		);
		$this->assertSame( '', Blocks::mermaid_page(), 'Style Mermaid not public: no link.' );
		update_option( \Windrose\Livery\Mermaid::OPTION, array( 'public' => true ) );
		$this->assertSame( get_permalink( $page ), Blocks::mermaid_page() );
		$this->assertStringContainsString( 'Make your own style with Style Mermaid', Blocks::render_style() );
		wp_update_post(
			array(
				'ID'           => $page,
				'post_content' => '<!-- wp:paragraph --><p>Gone</p><!-- /wp:paragraph -->',
			)
		);
		$this->assertSame( '', Blocks::mermaid_page(), 'Saving a page looks again.' );
		delete_option( \Windrose\Livery\Mermaid::OPTION );
	}

	public function test_selection_proposes_template_parts_only_when_the_library_has_them(): void {
		$this->pattern( 'Section' );
		$values = Blocks::values( 'kind' );
		$this->assertArrayNotHasKey( 'header', $values );
		$this->pattern( 'Header', '', 'header' );
		$this->assertArrayHasKey( 'header', Blocks::values( 'kind' ) );
		$this->assertArrayNotHasKey( 'footer', Blocks::values( 'kind' ) );
	}

	public function test_selection_bar_selection_back(): void {
		$bar = Blocks::render_selection_bar();
		$this->assertStringContainsString( 'target="_blank"', $bar );
		$this->assertStringContainsString( '(opens in a new tab)', $bar );
		$this->assertStringContainsString( esc_url( Content::selection_url() ), $bar );
		$this->assertStringContainsString( ' hidden', $bar );
		$this->assertStringContainsString( 'data-windrose-knotboard-selection', Blocks::render_selection() );
		$this->assertStringContainsString( esc_url( Content::library_url() ), Blocks::render_back() );
	}
}
