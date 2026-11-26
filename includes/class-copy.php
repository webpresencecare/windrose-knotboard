<?php
/**
 * What visitors copy or download: the pattern's block markup with its pictures **kept** but
 * detached from this site's Media Library. Image, Cover and Media & Text blocks lose the attachment ID (and the
 * classes and link that come with it) and keep the picture's full address, so on the visitor's site WordPress treats
 * the picture as an outside one: the publish panel's "External media" Upload button, the Image toolbar's "Upload to
 * Media Library", or Replace, put it into their own Media Library (WordPress 7.2 downloads it on their server).
 * Everything else stays as saved. Copies keep Ipsum's names (Ipsum, Windrose Prow for Ipsum; the colors are Twenty Twenty-Five's too); other themes' names later.
 *
 * @package Windrose\Knotboard
 */

declare( strict_types=1 );

namespace Windrose\Knotboard;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Copies with outside pictures.
 */
final class Copy {

	/** Blocks whose picture WordPress offers to upload when it is not in the Media Library. */
	private const PICTURES = array( 'core/image', 'core/cover', 'core/media-text' );

	/**
	 * Block markup ready to paste on another site: pictures detached from this site's attachments.
	 *
	 * @param string $content Block markup.
	 * @return string
	 */
	public static function for_visitors( string $content ): string {
		if ( '' === $content ) {
			return '';
		}
		$blocks = parse_blocks( $content );
		foreach ( $blocks as $i => $block ) {
			$blocks[ $i ] = self::block( $block );
		}
		// @phpstan-ignore argument.type (the blocks keep parse_blocks()'s shape: only attributes and HTML change.)
		return serialize_blocks( $blocks );
	}

	/**
	 * Whether the markup holds a picture the visitor will want to upload (Image, Cover or Media & Text with one).
	 *
	 * @param string $content Block markup.
	 * @return bool
	 */
	public static function has_pictures( string $content ): bool {
		foreach ( self::names( parse_blocks( $content ) ) as $name ) {
			if ( in_array( $name, self::PICTURES, true ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Names of blocks with a picture address, at any depth.
	 *
	 * @param array<mixed> $blocks Parsed blocks.
	 * @return list<string>
	 */
	private static function names( array $blocks ): array {
		$names = array();
		foreach ( $blocks as $block ) {
			if ( ! is_array( $block ) ) {
				continue;
			}
			$name = is_string( $block['blockName'] ?? null ) ? $block['blockName'] : '';
			$html = is_string( $block['innerHTML'] ?? null ) ? $block['innerHTML'] : '';
			if ( '' !== $name && ( ! empty( $block['attrs']['url'] ) || ( '' !== $html && ( new \WP_HTML_Tag_Processor( $html ) )->next_tag( array( 'tag_name' => 'IMG' ) ) ) ) ) {
				$names[] = $name;
			}
			if ( is_array( $block['innerBlocks'] ?? null ) ) {
				$names = array_merge( $names, self::names( $block['innerBlocks'] ) );
			}
		}
		return $names;
	}

	/**
	 * One block and its inner blocks.
	 *
	 * @param array<string, mixed> $block Parsed block.
	 * @return array<string, mixed>
	 */
	private static function block( array $block ): array {
		if ( is_array( $block['innerBlocks'] ?? null ) ) {
			foreach ( $block['innerBlocks'] as $i => $inner ) {
				if ( is_array( $inner ) ) {
					$block['innerBlocks'][ $i ] = self::block( $inner );
				}
			}
		}
		$name  = $block['blockName'] ?? null;
		$attrs = is_array( $block['attrs'] ?? null ) ? $block['attrs'] : array();
		switch ( $name ) {
			case 'core/image':
				// The link to this site's file or attachment page goes too: it would lead back here.
				unset( $attrs['id'], $attrs['href'], $attrs['linkDestination'], $attrs['linkTarget'], $attrs['rel'], $attrs['linkClass'] );
				$block['attrs'] = $attrs;
				$block          = self::html( $block, array( __CLASS__, 'image_html' ) );
				break;
			case 'core/cover':
				unset( $attrs['id'] );
				if ( is_string( $attrs['url'] ?? null ) ) {
					$attrs['url'] = self::absolute( $attrs['url'] );
				}
				$block['attrs'] = $attrs;
				$block          = self::html( $block, array( __CLASS__, 'cover_html' ) );
				break;
			case 'core/media-text':
				unset( $attrs['mediaId'], $attrs['mediaLink'], $attrs['href'], $attrs['linkDestination'], $attrs['linkTarget'], $attrs['rel'], $attrs['linkClass'] );
				$block['attrs'] = $attrs;
				$block          = self::html( $block, array( __CLASS__, 'media_text_html' ) );
				break;
			case 'core/gallery':
				unset( $attrs['ids'] );
				$block['attrs'] = $attrs;
				break;
		}//end switch
		return $block;
	}

	/**
	 * Applies a change to a block's own HTML pieces (not its inner blocks').
	 *
	 * @param array<string, mixed> $block  Parsed block.
	 * @param callable             $change HTML piece => HTML piece.
	 * @return array<string, mixed>
	 */
	private static function html( array $block, callable $change ): array {
		$block['innerHTML'] = (string) $change( is_string( $block['innerHTML'] ?? null ) ? $block['innerHTML'] : '' );
		if ( is_array( $block['innerContent'] ?? null ) ) {
			foreach ( $block['innerContent'] as $i => $piece ) {
				if ( is_string( $piece ) ) {
					$block['innerContent'][ $i ] = (string) $change( $piece );
				}
			}
		}
		return $block;
	}

	/**
	 * A full address for a picture saved with a relative one.
	 *
	 * @param string $url Address.
	 * @return string
	 */
	public static function absolute( string $url ): string {
		return str_starts_with( $url, '/' ) && ! str_starts_with( $url, '//' ) ? \WP_Http::make_absolute_url( $url, home_url( '/' ) ) : $url;
	}

	/**
	 * Detaches every img of a piece: no attachment class or `data-id`, a full address. Removed attributes take the
	 * space before them, so the tag reads as core saves it.
	 *
	 * @param string   $html    HTML piece.
	 * @param string[] $classes Patterns of whole class names to remove.
	 * @return string
	 */
	private static function detach( string $html, array $classes ): string {
		$tags = new Copy_Tags( $html );
		while ( $tags->next_tag( array( 'tag_name' => 'IMG' ) ) ) {
			$tags->remove_attribute( 'data-id' );
			$src = $tags->get_attribute( 'src' );
			if ( is_string( $src ) ) {
				$tags->set_attribute( 'src', self::absolute( $src ) );
			}
			foreach ( $tags->class_list() as $class ) {
				foreach ( $classes as $pattern ) {
					if ( 1 === preg_match( $pattern, $class ) ) {
						$tags->remove_class( $class );
					}
				}
			}
		}
		return $tags->get_updated_html();
	}

	/**
	 * An Image block's HTML as core saves it without an attachment: no link around the picture, no `wp-image-N`.
	 *
	 * @param string $html HTML piece.
	 * @return string
	 */
	public static function image_html( string $html ): string {
		$tags = new Copy_Tags( $html );
		while ( $tags->next_tag( array( 'tag_name' => 'A' ) ) ) {
			$tags->unwrap_picture();
		}
		return self::detach( $tags->get_updated_html(), array( '/^wp-image-\d+$/' ) );
	}

	/**
	 * A Cover block's HTML without an attachment: the background picture keeps its address, loses `wp-image-N`.
	 *
	 * @param string $html HTML piece.
	 * @return string
	 */
	public static function cover_html( string $html ): string {
		return self::detach( $html, array( '/^wp-image-\d+$/' ) );
	}

	/**
	 * A Media & Text block's HTML without an attachment: no link around the picture, no `wp-image-N` and size class
	 * (core writes both only with an attachment ID).
	 *
	 * @param string $html HTML piece.
	 * @return string
	 */
	public static function media_text_html( string $html ): string {
		$tags = new Copy_Tags( $html );
		while ( $tags->next_tag(
			array(
				'tag_name'   => 'FIGURE',
				'class_name' => 'wp-block-media-text__media',
			)
		) ) {
			$tags->unwrap_picture( true );
		}
		return self::detach( $tags->get_updated_html(), array( '/^wp-image-\d+$/', '/^size-[a-z0-9_-]+$/' ) );
	}
}
