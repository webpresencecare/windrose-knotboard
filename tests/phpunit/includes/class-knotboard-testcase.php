<?php
/**
 * Base for Windrose Knotboard tests: a site administrator (not a super admin), a super admin, a helper that makes
 * library patterns. The database rolls back after each test.
 *
 * @package Windrose\Knotboard
 */

use Windrose\Knotboard\Content;

/**
 * Test case with users and patterns.
 */
abstract class Knotboard_TestCase extends WP_UnitTestCase {

	protected static int $super = 0;
	protected static int $admin = 0;

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ): void {
		self::$super = $factory->user->create( array( 'role' => 'administrator' ) );
		self::$admin = $factory->user->create( array( 'role' => 'administrator' ) );
	}

	public static function wpTearDownAfterClass(): void {
		self::delete_user( self::$super );
		self::delete_user( self::$admin );
	}

	public function set_up(): void {
		parent::set_up();
		if ( is_multisite() ) {
			grant_super_admin( self::$super );
		}
		wp_set_current_user( self::$super );
		delete_site_option( 'windrose_knotboard_network' );
		Content::seed_kinds();
	}

	/**
	 * Makes a library pattern.
	 *
	 * @param string $title    Title.
	 * @param string $content  Block markup.
	 * @param string $kind     Kind.
	 * @param string $status   Post status.
	 * @param string $language Language code ('' for none).
	 * @return int Pattern ID.
	 */
	protected function pattern( string $title, string $content = '', string $kind = 'section', string $status = 'publish', string $language = '' ): int {
		$id = self::factory()->post->create(
			array(
				'post_type'    => Content::TYPE,
				'post_status'  => $status,
				'post_title'   => $title,
				'post_content' => '' !== $content ? $content : '<!-- wp:paragraph --><p>' . esc_html( $title ) . '</p><!-- /wp:paragraph -->',
			)
		);
		wp_set_object_terms( $id, $kind, Content::KIND );
		if ( '' !== $language ) {
			if ( ! term_exists( $language, Content::LANGUAGE ) ) {
				wp_insert_term( Content::language_name( $language ), Content::LANGUAGE, array( 'slug' => $language ) );
			}
			wp_set_object_terms( $id, $language, Content::LANGUAGE );
		}
		return $id;
	}

	/**
	 * Skips a test that needs Windrose Livery 0.7 (not on GitHub, where Livery's repository is not checked out next to this one).
	 *
	 * @return void
	 */
	protected function needs_livery(): void {
		if ( ! \Windrose\Knotboard\Plugin::has_livery() ) {
			$this->markTestSkipped( 'Windrose Livery 0.7 is not loaded (its repository sits next to this one locally).' );
		}
	}

	/** The first default pattern's markup (an Image with the package's picture, a paragraph). */
	protected const HELLO_BAND = '<!-- wp:group {"layout":{"type":"constrained"}} -->' . "\n" . '<div class="wp-block-group"><!-- wp:image {"id":{{id:hello-band.photo}},"sizeSlug":"full"} -->' . "\n" . '<figure class="wp-block-image size-full"><img src="{{url:hello-band.photo}}" alt="A stage" class="wp-image-{{id:hello-band.photo}}"/></figure>' . "\n" . '<!-- /wp:image -->' . "\n\n" . '<!-- wp:paragraph -->' . "\n" . '<p>We play loud.</p>' . "\n" . '<!-- /wp:paragraph --></div>' . "\n" . '<!-- /wp:group -->';

	/** A 2×1 PNG picture. */
	protected const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAIAAAABCAIAAAB7QOjdAAAAC0lEQVR4nGNoAAMACocDAcTGH2YAAAAASUVORK5CYII=';

	/** Files made by package_file(), deleted after each test. */
	protected array $package_files = array();

	/**
	 * A pattern library package as a pattern build writes it.
	 *
	 * @param array<string, mixed>  $change   Manifest values to change.
	 * @param array<string, string> $files    Files to add or replace (name => bytes; '' removes one).
	 * @param list<array>|null      $patterns Pattern entries (with 'content'), or the default two.
	 * @return string Path of the zip.
	 */
	protected function package_file( array $change = array(), array $files = array(), ?array $patterns = null ): string {
		$png       = (string) base64_decode( self::PNG ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- a test picture.
		$patterns  = $patterns ?? array(
			array(
				'key'     => 'hello-band',
				'kind'    => 'section',
				'title'   => 'Hello band',
				'tags'    => array( 'about' ),
				'images'  => array( 'hello-band.photo' ),
				'content' => self::HELLO_BAND,
			),
			array(
				'key'     => 'tour-dates',
				'kind'    => 'section',
				'title'   => 'Tour dates',
				'tags'    => array( 'events' ),
				'images'  => array(),
				'content' => '<!-- wp:heading -->' . "\n" . '<h2 class="wp-block-heading">On tour</h2>' . "\n" . '<!-- /wp:heading -->',
			),
		);
		$zip_files = array( 'images/hello-band.photo.png' => $png );
		$entries   = array();
		foreach ( $patterns as $pattern ) {
			$content = $pattern['content'];
			$zip_files[ 'patterns/' . $pattern['key'] . '.html' ] = $content;
			unset( $pattern['content'] );
			$entries[] = $pattern + array(
				'description' => 'About ' . $pattern['title'],
				'categories'  => array( 'musician' ),
				'reviewed'    => true,
				'file'        => 'patterns/' . $pattern['key'] . '.html',
				'sha256'      => hash( 'sha256', $content ),
			);
		}
		$manifest                    = array_replace(
			array(
				'format'         => 'windrose-knotboard-package',
				'format_version' => 1,
				'generator'      => 'Example build',
				'pack'           => 'musician',
				'release'        => '0.1.0',
				'language'       => array(
					'code' => 'en',
					'name' => 'English',
					'dir'  => 'ltr',
				),
				'terms'          => array(
					'category' => array(
						'musician' => array(
							'en' => 'Musician',
							'fr' => 'Musicien',
						),
					),
					'tag'      => array(
						'about'  => array( 'en' => 'About' ),
						'events' => 'Events',
					),
				),
				'images'         => array(
					'hello-band.photo' => array(
						'file'   => 'images/hello-band.photo.png',
						'sha256' => hash( 'sha256', $png ),
						'type'   => 'image/png',
						'alt'    => 'A stage with a drum kit',
						'credit' => array(
							'author'     => 'Nilo Velez',
							'author_url' => 'https://wordpress.org/photos/author/nilovelez/',
							'source'     => 'https://wordpress.org/photos/photo/7086659866/',
							'licence'    => 'CC0 1.0',
						),
					),
				),
				'patterns'       => $entries,
			),
			$change
		);
		$zip_files['knotboard.json'] = (string) wp_json_encode( $manifest );
		foreach ( $files as $name => $bytes ) {
			if ( '' === $bytes ) {
				unset( $zip_files[ $name ] );
			} else {
				$zip_files[ $name ] = $bytes;
			}
		}
		$path = wp_tempnam( 'knotboard-package' ) . '.zip';
		$zip  = new ZipArchive();
		$zip->open( $path, ZipArchive::CREATE | ZipArchive::OVERWRITE );
		foreach ( $zip_files as $name => $bytes ) {
			$zip->addFromString( $name, $bytes );
		}
		$zip->close();
		$this->package_files[] = $path;
		return $path;
	}

	public function tear_down(): void {
		foreach ( $this->package_files as $file ) {
			if ( file_exists( $file ) ) {
				wp_delete_file( $file );
			}
		}
		$this->package_files = array();
		parent::tear_down();
	}
}
