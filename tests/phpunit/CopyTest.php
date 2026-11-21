<?php
/**
 * Copies keep their pictures, detached from this site's attachments: Image, Cover and Media &
 * Text lose the attachment ID, its classes and the link to this site, keep the picture's full address; the rest is
 * kept as saved, also inside groups.
 *
 * @package Windrose\Knotboard
 */

use Windrose\Knotboard\Copy;

/**
 * @covers \Windrose\Knotboard\Copy
 */
class CopyTest extends WP_UnitTestCase {

	private const IMG = 'https://library.example/wp-content/uploads/2026/10/photo.webp';

	public function test_image_keeps_its_picture_without_the_attachment(): void {
		$in  = '<!-- wp:group {"layout":{"type":"constrained"}} --><div class="wp-block-group">'
			. '<!-- wp:image {"id":12,"sizeSlug":"large","linkDestination":"media","style":{"border":{"radius":"12px"}}} -->'
			. '<figure class="wp-block-image size-large has-custom-border"><a href="' . self::IMG . '"><img src="' . self::IMG . '" alt="A band on stage" class="wp-image-12" style="border-radius:12px"/></a></figure>'
			. '<!-- /wp:image --></div><!-- /wp:group -->';
		$out = Copy::for_visitors( $in );
		$this->assertStringNotContainsString( 'wp-image-12', $out );
		$this->assertStringNotContainsString( '"id":12', $out );
		$this->assertStringNotContainsString( 'linkDestination', $out );
		$this->assertStringNotContainsString( '<a href', $out );
		$this->assertStringContainsString( '<!-- wp:image {"sizeSlug":"large","style":{"border":{"radius":"12px"}}} -->', $out );
		$this->assertStringContainsString( '<figure class="wp-block-image size-large has-custom-border"><img src="' . self::IMG . '" alt="A band on stage" style="border-radius:12px"/></figure>', $out );
		$this->assertTrue( Copy::has_pictures( $in ) );
	}

	public function test_cover_keeps_its_picture_overlay_and_content(): void {
		$in  = '<!-- wp:cover {"url":"' . self::IMG . '","id":7,"dimRatio":80,"overlayColor":"accent-3","isDark":false,"layout":{"type":"constrained"}} -->'
			. '<div class="wp-block-cover is-light"><img class="wp-block-cover__image-background wp-image-7" alt="" src="' . self::IMG . '" data-object-fit="cover"/><span aria-hidden="true" class="wp-block-cover__background has-accent-3-background-color has-background-dim-80 has-background-dim"></span><div class="wp-block-cover__inner-container">'
			. '<!-- wp:paragraph --><p>On stage</p><!-- /wp:paragraph --></div></div><!-- /wp:cover -->';
		$out = Copy::for_visitors( $in );
		$this->assertStringNotContainsString( '"id":7', $out );
		$this->assertStringNotContainsString( 'wp-image-7', $out );
		$this->assertStringContainsString( '"url":"' . self::IMG . '"', $out );
		$this->assertStringContainsString( '<img class="wp-block-cover__image-background" alt="" src="' . self::IMG . '" data-object-fit="cover"/>', $out );
		$this->assertStringContainsString( '<p>On stage</p>', $out );
	}

	public function test_media_and_text_keeps_its_picture_without_the_attachment_classes(): void {
		$in  = '<!-- wp:media-text {"mediaId":9,"mediaLink":"https://library.example/?attachment_id=9","mediaType":"image","backgroundColor":"accent-5"} -->'
			. '<div class="wp-block-media-text is-stacked-on-mobile has-accent-5-background-color has-background"><figure class="wp-block-media-text__media"><img src="' . self::IMG . '" alt="Stage" class="wp-image-9 size-full"/></figure><div class="wp-block-media-text__content">'
			. '<!-- wp:paragraph --><p>Text</p><!-- /wp:paragraph --></div></div><!-- /wp:media-text -->';
		$out = Copy::for_visitors( $in );
		$this->assertStringNotContainsString( 'mediaId', $out );
		$this->assertStringNotContainsString( 'attachment_id', $out );
		$this->assertStringContainsString( '<figure class="wp-block-media-text__media"><img src="' . self::IMG . '" alt="Stage"/></figure>', $out );
		$this->assertStringContainsString( '"backgroundColor":"accent-5"', $out );
	}

	public function test_image_link_with_attributes_and_spaces_goes_other_links_stay(): void {
		$in  = '<!-- wp:image {"id":12,"sizeSlug":"full","linkDestination":"attachment","className":"is-style-rounded"} -->'
			. '<figure class="wp-block-image size-full is-style-rounded"><a href="https://library.example/?attachment_id=12" target="_blank" rel="noreferrer noopener" class="photo-link">' . "\n\t"
			. '<img src="' . self::IMG . '" alt="" class="wp-image-12"/>' . "\n"
			. '</a><figcaption class="wp-element-caption">Taken by <a href="https://example.org/">Ana</a></figcaption></figure><!-- /wp:image -->';
		$out = Copy::for_visitors( $in );
		$this->assertStringContainsString( '<!-- wp:image {"sizeSlug":"full","className":"is-style-rounded"} -->', $out );
		$this->assertStringContainsString(
			'<figure class="wp-block-image size-full is-style-rounded"><img src="' . self::IMG . '" alt=""/><figcaption class="wp-element-caption">Taken by <a href="https://example.org/">Ana</a></figcaption></figure>',
			$out
		);
	}

	public function test_image_without_link_and_link_holding_more_than_the_picture(): void {
		$in  = '<!-- wp:image {"id":5} --><figure class="wp-block-image"><img src="' . self::IMG . '" alt="Hall" class="wp-image-5"/></figure><!-- /wp:image -->';
		$out = Copy::for_visitors( $in );
		$this->assertStringContainsString( '<figure class="wp-block-image"><img src="' . self::IMG . '" alt="Hall"/></figure>', $out );
		// Not a link around the picture only: kept (the picture is still detached).
		$in  = '<!-- wp:image {"id":5} --><figure class="wp-block-image"><a href="https://example.org/"><img src="' . self::IMG . '" alt="" class="wp-image-5"/> and more</a></figure><!-- /wp:image -->';
		$out = Copy::for_visitors( $in );
		$this->assertStringContainsString( '<a href="https://example.org/"><img src="' . self::IMG . '" alt=""/> and more</a>', $out );
	}

	public function test_gallery_images_lose_their_data_id(): void {
		$in  = '<!-- wp:gallery {"ids":[12],"linkTo":"none"} --><figure class="wp-block-gallery has-nested-images columns-default is-cropped">'
			. '<!-- wp:image {"id":12,"sizeSlug":"large","linkDestination":"none"} --><figure class="wp-block-image size-large"><img src="' . self::IMG . '" alt="" data-id="12" class="wp-image-12"/></figure><!-- /wp:image -->'
			. '</figure><!-- /wp:gallery -->';
		$out = Copy::for_visitors( $in );
		$this->assertStringNotContainsString( '"ids"', $out );
		$this->assertStringNotContainsString( 'data-id', $out );
		$this->assertStringContainsString( '<figure class="wp-block-image size-large"><img src="' . self::IMG . '" alt=""/></figure>', $out );
	}

	public function test_media_and_text_link_goes_text_links_stay(): void {
		$in  = '<!-- wp:media-text {"mediaId":9,"mediaLink":"https://library.example/?attachment_id=9","linkDestination":"media","mediaType":"image"} -->'
			. '<div class="wp-block-media-text is-stacked-on-mobile"><figure class="wp-block-media-text__media">' . "\n"
			. '<a href="' . self::IMG . '" target="_blank" rel="noreferrer noopener" class="photo-link">' . "\n"
			. '<img src="' . self::IMG . '" alt="Stage" class="wp-image-9 size-full"/>' . "\n"
			. '</a>' . "\n"
			. '</figure><div class="wp-block-media-text__content"><!-- wp:paragraph --><p>See <a href="https://example.org/">more</a></p><!-- /wp:paragraph --></div></div><!-- /wp:media-text -->';
		$out = Copy::for_visitors( $in );
		$this->assertStringNotContainsString( 'linkDestination', $out );
		$this->assertStringNotContainsString( 'photo-link', $out );
		// As the regular expressions did before the HTML API: spaces before the picture go, the one after the link stays.
		$this->assertStringContainsString( '<figure class="wp-block-media-text__media"><img src="' . self::IMG . '" alt="Stage"/>' . "\n" . '</figure>', $out );
		$this->assertStringContainsString( '<p>See <a href="https://example.org/">more</a></p>', $out );
	}

	public function test_relative_addresses_become_full_ones(): void {
		$in  = '<!-- wp:image {"id":3} --><figure class="wp-block-image"><img src="/wp-content/uploads/a.webp" alt="" class="wp-image-3"/></figure><!-- /wp:image -->';
		$out = Copy::for_visitors( $in );
		$this->assertStringContainsString( 'src="' . home_url( '/wp-content/uploads/a.webp' ) . '"', $out );
	}

	public function test_other_blocks_unchanged(): void {
		$samples = array(
			'<!-- wp:paragraph --><p>Plain <a href="https://example.org/">link</a></p><!-- /wp:paragraph -->',
			'<!-- wp:site-logo {"width":40} /-->',
			'<!-- wp:navigation {"overlayMenu":"mobile"} /-->',
			'<!-- wp:buttons --><div class="wp-block-buttons"><!-- wp:button --><div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="#x">Go</a></div><!-- /wp:button --></div><!-- /wp:buttons -->',
		);
		foreach ( $samples as $sample ) {
			$this->assertSame( serialize_blocks( parse_blocks( $sample ) ), Copy::for_visitors( $sample ) );
			$this->assertFalse( Copy::has_pictures( $sample ) );
		}
		$this->assertSame( '', Copy::for_visitors( '' ) );
	}
}
