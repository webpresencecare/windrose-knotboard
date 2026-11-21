<?php
/**
 * Previews in a style: Windrose Livery draws them (0.7, `windrose_livery_pattern_items` and
 * `windrose_livery_visitor_previews`), Knotboard says which patterns. Source key `knotboard`, item keys are pattern IDs.
 *
 * Visitors get only offered (published) library patterns, and only where the site offers at least one; Livery adds its own rules (an
 * unsaved style only, sample content, previews per hour). People Livery treats as administrators (its nonce and
 * rights) may preview any pattern they can read, offered or not.
 *
 * @package Windrose\Knotboard
 */

declare( strict_types=1 );

namespace Windrose\Knotboard;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Livery's filters.
 */
final class Render {

	public const SOURCE = 'knotboard';

	/**
	 * Hooks Livery's filters.
	 *
	 * @return void
	 */
	public static function init(): void {
		add_filter( 'windrose_livery_pattern_items', array( __CLASS__, 'items' ), 10, 4 );
		add_filter( 'windrose_livery_visitor_previews', array( __CLASS__, 'visitors' ), 10, 2 );
		add_filter( 'windrose_livery_pattern_library', array( __CLASS__, 'library' ) );
	}

	/**
	 * Style Mermaid's "See the patterns in this style" opens this site's library, where it offers patterns.
	 *
	 * @param mixed $url Earlier answer.
	 * @return mixed
	 */
	public static function library( $url ) {
		return Content::has_offered() ? Content::library_url() : $url;
	}

	/**
	 * Visitors' previews: for this source, where the site offers patterns.
	 *
	 * @param mixed  $open   Earlier answer.
	 * @param string $source Source key.
	 * @return bool
	 */
	public static function visitors( $open, string $source ): bool {
		if ( self::SOURCE !== $source ) {
			return (bool) $open;
		}
		return Content::has_offered();
	}

	/**
	 * The patterns asked for, in order: what this person may see.
	 *
	 * @param mixed  $items   Earlier answer (another source's).
	 * @param string $source  Source key.
	 * @param array  $keys    Pattern IDs.
	 * @phpstan-param list<string> $keys
	 * @param bool   $visitor Whether Livery treats the person as a visitor.
	 * @return mixed
	 */
	public static function items( $items, string $source, array $keys, bool $visitor ) {
		if ( self::SOURCE !== $source ) {
			return $items;
		}
		$list = array();
		foreach ( $keys as $key ) {
			$post = ctype_digit( $key ) ? get_post( (int) $key ) : null;
			if ( null === $post || Content::TYPE !== $post->post_type ) {
				continue;
			}
			$allowed = $visitor ? Content::is_offered( $post ) : current_user_can( 'read_post', $post->ID );
			if ( ! $allowed ) {
				continue;
			}
			$list[] = array(
				'name'    => self::SOURCE . '/' . $post->ID,
				'title'   => '' !== $post->post_title ? $post->post_title : __( '(no title)', 'windrose-knotboard' ),
				'content' => $post->post_content,
			);
		}
		return $list;
	}
}
