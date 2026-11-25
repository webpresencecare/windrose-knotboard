<?php
/**
 * Imports pattern library packages (`Package`) into the library: one library pattern per key and language,
 * found again by its key on the next import and updated (the earlier version stays in its revisions).
 *
 * - **Dry run first** (`plan()`): every pattern is new, changed, unchanged, or **edited on this site** since the last
 *   import (it will be replaced); patterns of the same pack and language that the package no longer holds are listed,
 *   and the person importing chooses: they stay published, or move to drafts.
 * - The markup must come through the site's own content filter unchanged (`wp_kses_post()`, which also filters block attributes: the rules
 *   WordPress applies to people without `unfiltered_html`): otherwise the pattern is refused, never saved altered.
 * - Pictures go into the Media Library once, found again by their checksum; their alternative text and credit come
 *   from the package.
 * - Terms: the kind; categories and tags by key (slug), named in the site's language when the package has that name;
 *   an existing term keeps the name the site gave it. The language term (code as slug, the language's own name).
 *
 * @package Windrose\Knotboard
 */

declare( strict_types=1 );

namespace Windrose\Knotboard;

use WP_Error;
use WP_Post;
use WP_Query;
use Windrose\Common\Files;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Plans and runs imports.
 *
 * @phpstan-import-type Pack from Package
 * @phpstan-import-type Entry from Package
 * @phpstan-type Row array{index: int, file: string, pack: string, language: string, key: string, title: string, status: string, id: int, problem: string}
 * @phpstan-type Result array{rows: list<Row>, created: int, updated: int, unchanged: int, drafted: int, failed: int, pictures: int}
 */
final class Import {

	public const META_KEY        = '_windrose_knotboard_key';
	public const META_PACK       = '_windrose_knotboard_pack';
	public const META_SOURCE     = '_windrose_knotboard_source';
	public const META_SAVED      = '_windrose_knotboard_saved';
	public const META_UNREVIEWED = '_windrose_knotboard_unreviewed';
	public const META_CREDITS    = '_windrose_knotboard_credits';
	public const META_REMOVED    = '_windrose_knotboard_removed';
	public const META_IMAGE      = '_windrose_knotboard_image';

	/** What happens to patterns a package no longer holds. */
	public const KEEP  = 'published';
	public const DRAFT = 'draft';

	/**
	 * Who may import: manage the library and upload files.
	 *
	 * @return bool
	 */
	public static function allowed(): bool {
		return current_user_can( Content::CAP ) && current_user_can( 'upload_files' ) && current_user_can( 'publish_' . Content::CAP_PREFIX . 's' );
	}

	/**
	 * The dry run: what an import of these packages would do. Nothing is written.
	 *
	 * @param array<int, Pack> $packs Packages.
	 * @return list<Row>
	 */
	public static function plan( array $packs ): array {
		$rows  = array();
		$taken = array();
		foreach ( $packs as $index => $pack ) {
			$code = $pack['language']['code'];
			$keys = array();
			foreach ( $pack['patterns'] as $entry ) {
				$row = self::row( $index, $pack, $entry['key'], $entry['title'] );
				// Still in its pack even when refused below, or a second copy of the pack would list it as removed.
				$keys[ $entry['key'] ] = true;
				if ( isset( $taken[ $code . ':' . $entry['key'] ] ) ) {
					$row['status']  = 'failed';
					$row['problem'] = __( 'This pattern is in two of the packages chosen.', 'windrose-knotboard' );
					$rows[]         = $row;
					continue;
				}
				$taken[ $code . ':' . $entry['key'] ] = true;
				$post                                 = self::find( $entry['key'], $code );
				$row['id']                            = $post instanceof WP_Post ? $post->ID : 0;
				$problem                              = self::filtered( $pack, $entry );
				$owner                                = $post instanceof WP_Post ? (string) get_post_meta( $post->ID, self::META_PACK, true ) : '';
				if ( '' !== $owner && $owner !== $pack['pack'] ) {
					// Keys are unique within a source, not across sources: never take over another pack's pattern.
					$row['status']  = 'failed';
					$row['problem'] = sprintf(
						/* translators: %s: pack name. */
						__( 'This key belongs to the pack %s on this site.', 'windrose-knotboard' ),
						$owner
					);
				} elseif ( '' !== $problem ) {
					$row['status']  = 'failed';
					$row['problem'] = $problem;
				} elseif ( ! $post instanceof WP_Post ) {
					$row['status'] = 'new';
				} elseif ( md5( $post->post_content ) !== get_post_meta( $post->ID, self::META_SAVED, true ) ) {
					$row['status'] = 'edited';
				} elseif ( self::source( $pack, $entry ) !== get_post_meta( $post->ID, self::META_SOURCE, true ) || '' !== get_post_meta( $post->ID, self::META_REMOVED, true ) ) {
					$row['status'] = 'changed';
				} else {
					$row['status'] = 'unchanged';
				}
				$rows[] = $row;
			}//end foreach
			foreach ( self::in_pack( $pack['pack'], $code ) as $post ) {
				$key = (string) get_post_meta( $post->ID, self::META_KEY, true );
				if ( ! isset( $keys[ $key ] ) && 'publish' === $post->post_status ) {
					$row           = self::row( $index, $pack, $key, $post->post_title );
					$row['id']     = $post->ID;
					$row['status'] = 'removed';
					$rows[]        = $row;
				}
			}
		}//end foreach
		return $rows;
	}

	/**
	 * Imports. `$removed` says what happens to patterns no longer in their pack (needed when there are any).
	 *
	 * @param array<int, Pack> $packs   Packages.
	 * @param string           $removed `published` (they stay) or `draft`.
	 * @return array|WP_Error
	 * @phpstan-return Result|WP_Error
	 */
	public static function run( array $packs, string $removed = '' ) {
		$rows = self::plan( $packs );
		if ( '' === $removed && array() !== array_filter( $rows, static fn( array $row ): bool => 'removed' === $row['status'] ) ) {
			return new WP_Error( 'windrose_knotboard_removed', __( 'Some patterns are no longer in their pack: choose whether they stay published or move to drafts.', 'windrose-knotboard' ) );
		}
		if ( '' !== $removed && ! in_array( $removed, array( self::KEEP, self::DRAFT ), true ) ) {
			return new WP_Error( 'windrose_knotboard_removed', __( 'Patterns no longer in their pack either stay published or move to drafts.', 'windrose-knotboard' ) );
		}
		self::media_functions();
		Content::seed_kinds();
		$result = array(
			'rows'      => array(),
			'created'   => 0,
			'updated'   => 0,
			'unchanged' => 0,
			'drafted'   => 0,
			'failed'    => 0,
			'pictures'  => 0,
		);
		foreach ( $rows as $row ) {
			$pack = $packs[ $row['index'] ];
			if ( 'removed' === $row['status'] ) {
				if ( self::DRAFT === $removed ) {
					wp_update_post(
						array(
							'ID'          => $row['id'],
							'post_status' => 'draft',
						)
					);
					update_post_meta( $row['id'], self::META_REMOVED, '1' );
					$row['status'] = 'drafted';
					++$result['drafted'];
				} else {
					$row['status'] = 'kept';
				}
			} elseif ( 'unchanged' === $row['status'] ) {
				++$result['unchanged'];
			} elseif ( 'failed' !== $row['status'] ) {
				$entry = current( array_filter( $pack['patterns'], static fn( array $item ): bool => $item['key'] === $row['key'] ) );
				$saved = false !== $entry ? self::save( $pack, $entry, $row['id'], $result['pictures'] ) : new WP_Error( 'windrose_knotboard_missing', '' );
				if ( $saved instanceof WP_Error ) {
					$row['status']  = 'failed';
					$row['problem'] = $saved->get_error_message();
				} else {
					'new' === $row['status'] ? ++$result['created'] : ++$result['updated'];
					$row['id'] = $saved;
				}
			}//end if
			if ( 'failed' === $row['status'] ) {
				++$result['failed'];
			}
			$result['rows'][] = $row;
		}//end foreach
		return $result;
	}

	/**
	 * A row of the plan.
	 *
	 * @param int    $index The package's place in the list.
	 * @param array  $pack  Package.
	 * @phpstan-param Pack $pack
	 * @param string $key   Pattern key.
	 * @param string $title Title.
	 * @return Row
	 */
	private static function row( int $index, array $pack, string $key, string $title ): array {
		return array(
			'index'    => $index,
			'file'     => $pack['file'],
			'pack'     => $pack['pack'],
			'language' => $pack['language']['code'],
			'key'      => $key,
			'title'    => $title,
			'status'   => '',
			'id'       => 0,
			'problem'  => '',
		);
	}

	/**
	 * The library pattern of a key and language (not in the trash).
	 *
	 * @param string $key  Pattern key.
	 * @param string $code Language code.
	 * @return WP_Post|null
	 */
	public static function find( string $key, string $code ): ?WP_Post {
		$query = new WP_Query(
			array(
				'post_type'              => Content::TYPE,
				'post_status'            => array( 'publish', 'draft', 'pending', 'private', 'future' ),
				'posts_per_page'         => 1,
				'orderby'                => 'ID',
				'order'                  => 'ASC',
				'no_found_rows'          => true,
				'update_post_term_cache' => false,
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- one indexed meta key, on import only.
				'meta_query'             => array(
					array(
						'key'   => self::META_KEY,
						'value' => $key,
					),
				),
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- on import only.
				'tax_query'              => array(
					array(
						'taxonomy' => Content::LANGUAGE,
						'field'    => 'slug',
						'terms'    => $code,
					),
				),
			)
		);
		$post = $query->posts[0] ?? null;
		return $post instanceof WP_Post ? $post : null;
	}

	/**
	 * The library patterns an earlier import of this pack and language made.
	 *
	 * @param string $pack Pack name.
	 * @param string $code Language code.
	 * @return list<WP_Post>
	 */
	private static function in_pack( string $pack, string $code ): array {
		$query = new WP_Query(
			array(
				'post_type'              => Content::TYPE,
				'post_status'            => array( 'publish', 'draft', 'pending', 'private', 'future' ),
				'posts_per_page'         => 500, // phpcs:ignore WordPress.WP.PostsPerPage.posts_per_page_posts_per_page -- one pack's patterns, on import only.
				'no_found_rows'          => true,
				'update_post_term_cache' => false,
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- on import only.
				'meta_query'             => array(
					array(
						'key'   => self::META_PACK,
						'value' => $pack,
					),
				),
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- on import only.
				'tax_query'              => array(
					array(
						'taxonomy' => Content::LANGUAGE,
						'field'    => 'slug',
						'terms'    => $code,
					),
				),
			)
		);
		return array_values( array_filter( is_array( $query->posts ) ? $query->posts : array(), static fn( $post ): bool => $post instanceof WP_Post ) );
	}

	/**
	 * What was imported, as one checksum: the entry, its pictures, its language.
	 *
	 * @param array $pack  Package.
	 * @phpstan-param Pack $pack
	 * @param array $entry Pattern.
	 * @phpstan-param Entry $entry
	 * @return string
	 */
	private static function source( array $pack, array $entry ): string {
		$images = array();
		foreach ( $entry['images'] as $ident ) {
			$images[ $ident ] = array( $pack['images'][ $ident ]['sha256'], $pack['images'][ $ident ]['alt'] );
		}
		unset( $entry['content'] );
		return hash( 'sha256', (string) wp_json_encode( array( $pack['pack'], $pack['language']['code'], $entry, $images ) ) );
	}

	/**
	 * Why the site's content filter would change a pattern ('' when it passes unchanged).
	 *
	 * @param array $pack  Package.
	 * @phpstan-param Pack $pack
	 * @param array $entry Pattern.
	 * @phpstan-param Entry $entry
	 * @return string
	 */
	private static function filtered( array $pack, array $entry ): string {
		$content = $entry['content'];
		foreach ( $entry['images'] as $ident ) {
			$content = str_replace(
				array( '{{url:' . $ident . '}}', '{{id:' . $ident . '}}' ),
				array( trailingslashit( wp_get_upload_dir()['baseurl'] ) . sanitize_file_name( str_replace( '.', '-', $ident ) . '.' . $pack['images'][ $ident ]['ext'] ), '1' ),
				$content
			);
		}
		$blocks = parse_blocks( $content );
		foreach ( $blocks as $block ) {
			if ( null === $block['blockName'] && '' !== trim( (string) $block['innerHTML'] ) ) {
				return __( 'Content outside blocks: it would become a Classic block.', 'windrose-knotboard' );
			}
		}
		if ( ! self::passes_filter( $content ) ) {
			return __( 'The site’s content filter would change this pattern, so it is not imported.', 'windrose-knotboard' );
		}
		return '';
	}

	/**
	 * Whether the site's content filter (`wp_kses_post()`, block attributes included) leaves the markup as it is.
	 * KSES may write tags differently (attribute order, `<img>` without its slash): the HTML is compared token by
	 * token (tags with their attributes, text, block comments), not character by character.
	 *
	 * @param string $content Block markup.
	 * @return bool
	 */
	public static function passes_filter( string $content ): bool {
		return self::tokens( wp_kses_post( $content ) ) === self::tokens( $content );
	}

	/**
	 * HTML as a list of tokens: tags (name, closer, sorted attributes), text, comments.
	 *
	 * @param string $html HTML.
	 * @return list<string>
	 */
	private static function tokens( string $html ): array {
		$out  = array();
		$html = new \WP_HTML_Tag_Processor( $html );
		while ( $html->next_token() ) {
			$type = $html->get_token_type();
			if ( '#tag' === $type ) {
				$attributes = array();
				foreach ( $html->get_attribute_names_with_prefix( '' ) ?? array() as $name ) {
					$value               = $html->get_attribute( $name );
					$attributes[ $name ] = is_string( $value ) ? $value : (bool) $value;
				}
				ksort( $attributes );
				$out[] = ( $html->is_tag_closer() ? '/' : '' ) . $html->get_tag() . (string) wp_json_encode( $attributes );
			} else {
				$out[] = $type . ':' . $html->get_modifiable_text();
			}
		}
		return $out;
	}

	/**
	 * Saves one pattern: pictures, markup, title, description, terms, notes.
	 *
	 * @param array $pack     Package.
	 * @phpstan-param Pack $pack
	 * @param array $entry    Pattern.
	 * @phpstan-param Entry $entry
	 * @param int   $id       Existing library pattern, or 0.
	 * @param int   $pictures Pictures added so far (counted up).
	 * @return int|WP_Error
	 */
	private static function save( array $pack, array $entry, int $id, int &$pictures ) {
		$content = $entry['content'];
		$credits = array();
		foreach ( $entry['images'] as $ident ) {
			$image = $pack['images'][ $ident ];
			$added = false;
			$media = self::picture( $pack, $ident, $added );
			if ( $media instanceof WP_Error ) {
				return $media;
			}
			$pictures += $added ? 1 : 0;
			$url       = wp_get_attachment_url( $media );
			$content   = str_replace(
				array( '{{url:' . $ident . '}}', '{{id:' . $ident . '}}' ),
				array( is_string( $url ) ? $url : '', (string) $media ),
				$content
			);
			if ( array() !== $image['credit'] ) {
				$credits[] = $image['credit'];
			}
		}
		if ( ! self::passes_filter( $content ) ) {
			return new WP_Error( 'windrose_knotboard_filtered', __( 'The site’s content filter would change this pattern, so it is not imported.', 'windrose-knotboard' ) );
		}
		$code   = $pack['language']['code'];
		$post   = $id > 0 ? get_post( $id ) : null;
		$status = $post instanceof WP_Post && '' === get_post_meta( $id, self::META_REMOVED, true ) ? $post->post_status : 'publish';
		$data   = array(
			'post_type'    => Content::TYPE,
			'post_status'  => $status,
			'post_title'   => $entry['title'],
			'post_excerpt' => $entry['description'],
			'post_content' => $content,
		);
		if ( $post instanceof WP_Post ) {
			$data['ID'] = $id;
		} else {
			$data['post_name'] = sanitize_title( $entry['key'] . '-' . $code );
		}
		$saved = wp_insert_post( wp_slash( $data ), true );
		if ( $saved instanceof WP_Error ) {
			return $saved;
		}
		$language = self::term( Content::LANGUAGE, $code, $pack['language']['name'] );
		wp_set_object_terms( $saved, $entry['kind'], Content::KIND );
		wp_set_object_terms( $saved, $language > 0 ? array( $language ) : array(), Content::LANGUAGE );
		foreach ( array(
			'categories' => array( Content::CATEGORY, 'category' ),
			'tags'       => array( Content::TAG, 'tag' ),
		) as $field => [ $taxonomy, $part ] ) {
			$ids = array();
			foreach ( $entry[ $field ] as $key ) {
				$ids[] = self::term( $taxonomy, $key, Package::term_name( $pack['terms'][ $part ][ $key ], $code, $key ) );
			}
			wp_set_object_terms( $saved, array_values( array_filter( $ids ) ), $taxonomy );
		}
		$stored = get_post( $saved );
		update_post_meta( $saved, self::META_KEY, $entry['key'] );
		update_post_meta( $saved, self::META_PACK, $pack['pack'] );
		update_post_meta( $saved, self::META_SOURCE, self::source( $pack, $entry ) );
		update_post_meta( $saved, self::META_SAVED, md5( $stored instanceof WP_Post ? $stored->post_content : $content ) );
		delete_post_meta( $saved, self::META_REMOVED );
		$entry['reviewed'] ? delete_post_meta( $saved, self::META_UNREVIEWED ) : update_post_meta( $saved, self::META_UNREVIEWED, '1' );
		array() !== $credits ? update_post_meta( $saved, self::META_CREDITS, $credits ) : delete_post_meta( $saved, self::META_CREDITS );
		return $saved;
	}

	/**
	 * A term by slug, made with this name when it does not exist (an existing one keeps its name).
	 *
	 * @param string $taxonomy Taxonomy.
	 * @param string $slug     Slug.
	 * @param string $name     Name for a new term.
	 * @return int Term ID, 0 on failure.
	 */
	private static function term( string $taxonomy, string $slug, string $name ): int {
		$term = get_term_by( 'slug', $slug, $taxonomy );
		if ( $term instanceof \WP_Term ) {
			return $term->term_id;
		}
		$made = wp_insert_term( $name, $taxonomy, array( 'slug' => $slug ) );
		return is_array( $made ) ? (int) $made['term_id'] : 0;
	}

	/**
	 * A picture in the Media Library: the one with the same checksum, or a new one.
	 *
	 * @param array  $pack  Package.
	 * @phpstan-param Pack $pack
	 * @param string $ident Picture name.
	 * @param bool   $added Set when a picture was added.
	 * @return int|WP_Error Attachment ID.
	 */
	private static function picture( array $pack, string $ident, bool &$added ) {
		$image = $pack['images'][ $ident ];
		$found = get_posts(
			array(
				'post_type'      => 'attachment',
				'post_status'    => 'inherit',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key, WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- on import only.
				'meta_key'       => self::META_IMAGE,
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- on import only.
				'meta_value'     => $image['sha256'],
			)
		);
		if ( isset( $found[0] ) && is_int( $found[0] ) && is_string( wp_get_attachment_url( $found[0] ) ) ) {
			return $found[0];
		}
		$tmp = wp_tempnam( $ident );
		if ( '' === $tmp || ! Files::write( $tmp, $image['bytes'] ) ) {
			return new WP_Error( 'windrose_knotboard_picture', __( 'A picture could not be written to a temporary file.', 'windrose-knotboard' ) );
		}
		$credit = $image['credit'];
		$id     = media_handle_sideload(
			array(
				'name'     => sanitize_file_name( str_replace( '.', '-', $ident ) . '.' . $image['ext'] ),
				'tmp_name' => $tmp,
			),
			0,
			null,
			array(
				'post_title'   => '' !== ( $credit['title'] ?? '' ) ? wp_trim_words( $credit['title'], 12 ) : $ident,
				'post_content' => self::credit_text( $credit ),
			)
		);
		if ( file_exists( $tmp ) ) {
			wp_delete_file( $tmp );
		}
		if ( $id instanceof WP_Error ) {
			return $id;
		}
		update_post_meta( $id, self::META_IMAGE, $image['sha256'] );
		update_post_meta( $id, '_wp_attachment_image_alt', $image['alt'] );
		$added = true;
		return $id;
	}

	/**
	 * A credit line for the Media Library (author, licence, source).
	 *
	 * @param array<string, string> $credit Credit.
	 * @return string
	 */
	public static function credit_text( array $credit ): string {
		$parts = array_filter( array( $credit['author'] ?? '', $credit['licence'] ?? '', $credit['source'] ?? '' ) );
		return implode( ' · ', $parts );
	}

	/**
	 * Core's media functions, also outside the admin (WP-CLI).
	 *
	 * @return void
	 */
	private static function media_functions(): void {
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
	}

	/**
	 * Labels of the statuses, for screens and WP-CLI.
	 *
	 * @return array<string, string>
	 */
	public static function labels(): array {
		return array(
			'new'       => __( 'New', 'windrose-knotboard' ),
			'changed'   => __( 'Changed', 'windrose-knotboard' ),
			'unchanged' => __( 'Unchanged', 'windrose-knotboard' ),
			'edited'    => __( 'Edited on this site, will be replaced', 'windrose-knotboard' ),
			'removed'   => __( 'No longer in its pack', 'windrose-knotboard' ),
			'failed'    => __( 'Refused', 'windrose-knotboard' ),
			'drafted'   => __( 'Moved to drafts', 'windrose-knotboard' ),
			'kept'      => __( 'Stays published', 'windrose-knotboard' ),
		);
	}
}
