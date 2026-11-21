<?php
/**
 * Packages and their import: the package is checked in memory (only its own kinds of files, checksums, picture
 * types, placeholders); the dry run writes nothing; the import makes one library pattern per key and language with
 * its terms, pictures (once, by checksum, with alternative text and credit) and notes; a second import finds them
 * again by key; edits on the site are reported and replaced; patterns no longer in their pack need a choice; the
 * site's content filter must leave the markup unchanged.
 *
 * @package Windrose\Knotboard
 */

use Windrose\Knotboard\Blocks;
use Windrose\Knotboard\Content;
use Windrose\Knotboard\Import;
use Windrose\Knotboard\Package;

/**
 * @covers \Windrose\Knotboard\Package
 * @covers \Windrose\Knotboard\Import
 * @covers \Windrose\Knotboard\Blocks::render_notes
 */
class ImportTest extends Knotboard_TestCase {

	/**
	 * Reads a package that must be fine.
	 *
	 * @param string $path Zip.
	 * @return array
	 */
	private function read( string $path ): array {
		$pack = Package::read( $path );
		$this->assertIsArray( $pack, $pack instanceof WP_Error ? $pack->get_error_message() : '' );
		return $pack;
	}

	/**
	 * Statuses of a plan or result by key.
	 *
	 * @param list<array> $rows Rows.
	 * @return array<string, string>
	 */
	private function statuses( array $rows ): array {
		return array_column( $rows, 'status', 'key' );
	}

	public function test_package_is_read_and_checked(): void {
		$pack = $this->read( $this->package_file() );
		$this->assertSame( 'musician', $pack['pack'] );
		$this->assertSame( 'en', $pack['language']['code'] );
		$this->assertSame( array( 'hello-band', 'tour-dates' ), array_column( $pack['patterns'], 'key' ) );
		$this->assertSame( 'image/png', $pack['images']['hello-band.photo']['type'] );

		$bad = array(
			'an unexpected file'             => $this->package_file( array(), array( 'patterns/run.php' => '<?php echo 1;' ) ),
			'a path out of the package'      => $this->package_file( array(), array( '../evil.html' => 'x' ) ),
			'no manifest'                    => $this->package_file( array(), array( 'knotboard.json' => '' ) ),
			'another format'                 => $this->package_file( array( 'format' => 'something-else' ) ),
			'a newer format'                 => $this->package_file( array( 'format_version' => 2 ) ),
			'a bad language code'            => $this->package_file(
				array(
					'language' => array(
						'code' => 'French!',
						'dir'  => 'ltr',
					),
				)
			),
			'a changed pattern file'         => $this->package_file( array(), array( 'patterns/tour-dates.html' => '<!-- wp:paragraph --><p>Changed</p><!-- /wp:paragraph -->' ) ),
			'a picture that is not one'      => $this->package_file( array(), array( 'images/hello-band.photo.png' => 'not a picture' ) ),
			'an unknown tag'                 => $this->package_file(
				array(),
				array(),
				array(
					array(
						'key'     => 'x',
						'kind'    => 'section',
						'title'   => 'X',
						'tags'    => array( 'nope' ),
						'content' => '<!-- wp:paragraph --><p>x</p><!-- /wp:paragraph -->',
					),
				)
			),
			'a placeholder it does not list' => $this->package_file(
				array(),
				array(),
				array(
					array(
						'key'     => 'x',
						'kind'    => 'section',
						'title'   => 'X',
						'content' => '<!-- wp:image {"id":{{id:other}}} --><figure class="wp-block-image"><img src="{{url:other}}" alt=""/></figure><!-- /wp:image -->',
					),
				)
			),
			'a wrong kind'                   => $this->package_file(
				array(),
				array(),
				array(
					array(
						'key'     => 'x',
						'kind'    => 'sidebar',
						'title'   => 'X',
						'content' => '<!-- wp:paragraph --><p>x</p><!-- /wp:paragraph -->',
					),
				)
			),
		);
		foreach ( $bad as $why => $path ) {
			$this->assertInstanceOf( WP_Error::class, Package::read( $path ), $why );
		}
		$this->assertInstanceOf( WP_Error::class, Package::read( '/nowhere/package.zip' ) );
	}

	public function test_dry_run_writes_nothing(): void {
		$before = wp_count_posts( Content::TYPE )->publish ?? 0;
		$rows   = Import::plan( array( $this->read( $this->package_file() ) ) );
		$this->assertSame(
			array(
				'hello-band' => 'new',
				'tour-dates' => 'new',
			),
			$this->statuses( $rows )
		);
		$this->assertSame( $before, wp_count_posts( Content::TYPE )->publish ?? 0 );
		$this->assertSame( array(), get_posts( array( 'post_type' => 'attachment' ) ) );
	}

	public function test_import_makes_library_patterns_with_terms_pictures_and_notes(): void {
		$pack   = $this->read( $this->package_file() );
		$result = Import::run( array( $pack ) );
		$this->assertIsArray( $result );
		$this->assertSame( 2, $result['created'] );
		$this->assertSame( 1, $result['pictures'] );

		$post = Import::find( 'hello-band', 'en' );
		$this->assertInstanceOf( WP_Post::class, $post );
		$this->assertSame( 'publish', $post->post_status );
		$this->assertSame( 'Hello band', $post->post_title );
		$this->assertSame( 'About Hello band', $post->post_excerpt );
		$this->assertSame( 'hello-band-en', $post->post_name );
		$this->assertSame( 'section', Content::kind( $post->ID ) );
		$this->assertSame( 'en', Content::language( $post->ID ) );
		$this->assertSame( array( 'musician' ), wp_get_object_terms( $post->ID, Content::CATEGORY, array( 'fields' => 'slugs' ) ) );
		$this->assertSame( 'Musician', get_term_by( 'slug', 'musician', Content::CATEGORY )->name );
		$this->assertSame( array( 'about' ), wp_get_object_terms( $post->ID, Content::TAG, array( 'fields' => 'slugs' ) ) );

		// The picture: in the Media Library once, its address and ID in the markup, alt text and credit.
		$media = get_posts(
			array(
				'post_type'   => 'attachment',
				'meta_key'    => Import::META_IMAGE, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- test.
				'fields'      => 'ids',
				'post_status' => 'inherit',
			)
		);
		$this->assertCount( 1, $media );
		$this->assertSame( 'A stage with a drum kit', get_post_meta( $media[0], '_wp_attachment_image_alt', true ) );
		$this->assertStringContainsString( 'Nilo Velez', get_post( $media[0] )->post_content );
		$this->assertStringContainsString( '"id":' . $media[0], $post->post_content );
		$this->assertStringContainsString( 'src="' . wp_get_attachment_url( $media[0] ) . '"', $post->post_content );
		$this->assertStringContainsString( 'wp-image-' . $media[0], $post->post_content );
		$this->assertStringNotContainsString( '{{', $post->post_content );
		$this->assertSame( 'core/group', parse_blocks( $post->post_content )[0]['blockName'] );

		// Notes: credits on the pattern's page, no "not reviewed" for a reviewed text.
		$notes = Blocks::render_notes( array(), '', new WP_Block( array( 'blockName' => 'windrose/pattern-notes' ), array( 'postId' => $post->ID ) ) );
		$this->assertStringContainsString( 'Nilo Velez', $notes );
		$this->assertStringContainsString( 'https://wordpress.org/photos/photo/7086659866/', $notes );
		$this->assertStringNotContainsString( 'not reviewed', $notes );
		$this->assertStringNotContainsString( 'Nilo', Blocks::render_notes( array( 'credits' => false ), '', new WP_Block( array( 'blockName' => 'windrose/pattern-notes' ), array( 'postId' => $post->ID ) ) ) );

		// A second import: unchanged, the picture not added again.
		$again = Import::run( array( $this->read( $this->package_file() ) ) );
		$this->assertSame( 2, $again['unchanged'] );
		$this->assertSame( 0, $again['pictures'] );
	}

	public function test_updates_by_key_edits_on_the_site_and_revisions(): void {
		Import::run( array( $this->read( $this->package_file() ) ) );
		$post = Import::find( 'tour-dates', 'en' );
		$this->assertInstanceOf( WP_Post::class, $post );

		// Edited on the site: reported, then replaced, the edit kept in the revisions.
		wp_update_post(
			array(
				'ID'           => $post->ID,
				'post_content' => '<!-- wp:paragraph --><p>Edited here</p><!-- /wp:paragraph -->',
			)
		);
		$this->assertSame( 'edited', $this->statuses( Import::plan( array( $this->read( $this->package_file() ) ) ) )['tour-dates'] );
		$result = Import::run( array( $this->read( $this->package_file() ) ) );
		$this->assertSame( 1, $result['updated'] );
		$this->assertStringContainsString( 'On tour', get_post( $post->ID )->post_content );
		$revisions = wp_get_post_revisions( $post->ID );
		$this->assertNotEmpty( array_filter( $revisions, static fn( $r ) => str_contains( $r->post_content, 'Edited here' ) ) );

		// Changed in the package: same post, new title, not reviewed.
		$changed = $this->package_file(
			array(),
			array(),
			array(
				array(
					'key'      => 'tour-dates',
					'kind'     => 'section',
					'title'    => 'Concert dates',
					'tags'     => array( 'events' ),
					'reviewed' => false,
					'content'  => '<!-- wp:heading -->' . "\n" . '<h2 class="wp-block-heading">On tour again</h2>' . "\n" . '<!-- /wp:heading -->',
				),
				array(
					'key'     => 'hello-band',
					'kind'    => 'section',
					'title'   => 'Hello band',
					'tags'    => array( 'about' ),
					'images'  => array( 'hello-band.photo' ),
					'content' => self::HELLO_BAND,
				),
			)
		);
		$this->assertSame(
			array(
				'tour-dates' => 'changed',
				'hello-band' => 'unchanged',
			),
			$this->statuses( Import::plan( array( $this->read( $changed ) ) ) )
		);
		Import::run( array( $this->read( $changed ) ) );
		$same = Import::find( 'tour-dates', 'en' );
		$this->assertSame( $post->ID, $same->ID );
		$this->assertSame( 'Concert dates', $same->post_title );
		$this->assertStringContainsString( 'Translation not reviewed yet', Blocks::render_notes( array(), '', new WP_Block( array( 'blockName' => 'windrose/pattern-notes' ), array( 'postId' => $post->ID ) ) ) );

		// Another language is another library pattern.
		$french = $this->package_file(
			array(
				'language' => array(
					'code' => 'fr',
					'name' => 'Français',
					'dir'  => 'ltr',
				),
			)
		);
		Import::run( array( $this->read( $french ) ) );
		$fr = Import::find( 'tour-dates', 'fr' );
		$this->assertNotSame( $post->ID, $fr->ID );
		$this->assertSame( 'Français', get_term_by( 'slug', 'fr', Content::LANGUAGE )->name );
		// An existing category keeps its name (the site's language is English here).
		$this->assertSame( 'Musician', get_term_by( 'slug', 'musician', Content::CATEGORY )->name );
	}

	public function test_patterns_no_longer_in_their_pack_need_a_choice(): void {
		Import::run( array( $this->read( $this->package_file() ) ) );
		$smaller = $this->package_file(
			array(),
			array(),
			array(
				array(
					'key'     => 'tour-dates',
					'kind'    => 'section',
					'title'   => 'Tour dates',
					'tags'    => array( 'events' ),
					'content' => '<!-- wp:heading -->' . "\n" . '<h2 class="wp-block-heading">On tour</h2>' . "\n" . '<!-- /wp:heading -->',
				),
			),
		);
		$pack    = $this->read( $smaller );
		$this->assertSame( 'removed', $this->statuses( Import::plan( array( $pack ) ) )['hello-band'] );
		$this->assertInstanceOf( WP_Error::class, Import::run( array( $pack ) ) );
		$this->assertInstanceOf( WP_Error::class, Import::run( array( $pack ), 'delete' ) );

		$kept = Import::run( array( $pack ), Import::KEEP );
		$this->assertSame( 'kept', $this->statuses( $kept['rows'] )['hello-band'] );
		$this->assertSame( 'publish', Import::find( 'hello-band', 'en' )->post_status );

		$drafted = Import::run( array( $pack ), Import::DRAFT );
		$this->assertSame( 1, $drafted['drafted'] );
		$this->assertSame( 'draft', Import::find( 'hello-band', 'en' )->post_status );
		// Drafts are not asked about again; back in the pack, the pattern is published again.
		$this->assertArrayNotHasKey( 'hello-band', $this->statuses( Import::plan( array( $pack ) ) ) );
		$this->assertSame( 'changed', $this->statuses( Import::plan( array( $this->read( $this->package_file() ) ) ) )['hello-band'] );
		Import::run( array( $this->read( $this->package_file() ) ) );
		$this->assertSame( 'publish', Import::find( 'hello-band', 'en' )->post_status );
	}

	public function test_refused_when_the_content_filter_would_change_it_or_twice_in_one_import(): void {
		$unsafe = $this->package_file(
			array(),
			array(),
			array(
				array(
					'key'     => 'unsafe',
					'kind'    => 'section',
					'title'   => 'Unsafe',
					'content' => '<!-- wp:paragraph -->' . "\n" . '<p onclick="alert(1)">Hi</p>' . "\n" . '<!-- /wp:paragraph -->',
				),
				array(
					'key'     => 'outside',
					'kind'    => 'section',
					'title'   => 'Outside',
					'content' => '<p>No block</p>',
				),
			)
		);
		$result = Import::run( array( $this->read( $unsafe ) ) );
		$this->assertSame( 2, $result['failed'] );
		$this->assertNull( Import::find( 'unsafe', 'en' ) );
		$this->assertNull( Import::find( 'outside', 'en' ) );

		$twice = Import::plan( array( $this->read( $this->package_file() ), $this->read( $this->package_file() ) ) );
		$this->assertSame( array( 'new', 'new', 'failed', 'failed' ), array_column( $twice, 'status' ) );
	}

	public function test_the_same_pack_chosen_twice_removes_nothing(): void {
		Import::run( array( $this->read( $this->package_file() ) ) );
		$plan = Import::plan( array( $this->read( $this->package_file() ), $this->read( $this->package_file() ) ) );
		$this->assertNotContains( 'removed', array_column( $plan, 'status' ), 'The second copy is refused, not read as an empty pack.' );
	}

	public function test_another_packs_pattern_with_the_same_key_is_not_taken_over(): void {
		Import::run( array( $this->read( $this->package_file() ) ) );
		$plan = $this->statuses( Import::plan( array( $this->read( $this->package_file( array( 'pack' => 'designer' ) ) ) ) ) );
		$this->assertSame( 'failed', $plan['hello-band'] );
		$result = Import::run( array( $this->read( $this->package_file( array( 'pack' => 'designer' ) ) ) ) );
		$this->assertSame( 'musician', get_post_meta( Import::find( 'hello-band', 'en' )->ID, Import::META_PACK, true ) );
		$this->assertSame( 2, $result['failed'] );
	}

	public function test_who_may_import(): void {
		$this->assertTrue( Import::allowed() );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );
		$this->assertFalse( Import::allowed() );
		if ( is_multisite() ) {
			// A site administrator, while the network switch lets site administrators manage their library.
			$admin = self::factory()->user->create( array( 'role' => 'administrator' ) );
			wp_set_current_user( $admin );
			$this->assertTrue( Import::allowed() );
		}
	}

	public function test_term_names_follow_the_site_language(): void {
		$this->assertSame(
			'Musician',
			Package::term_name(
				array(
					'en' => 'Musician',
					'fr' => 'Musicien',
				),
				'fr',
				'musician'
			)
		);
		add_filter( 'locale', static fn() => 'fr_FR' );
		$this->assertSame(
			'Musicien',
			Package::term_name(
				array(
					'en' => 'Musician',
					'fr' => 'Musicien',
				),
				'en',
				'musician'
			)
		);
		$this->assertSame( 'Música', Package::term_name( array( 'es' => 'Música' ), 'es', 'musician' ) );
		$this->assertSame( 'Events', Package::term_name( 'Events', 'fr', 'events' ) );
	}
}
