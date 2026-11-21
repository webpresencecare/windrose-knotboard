<?php
/**
 * The library's pages as block templates of the plugin (core's `register_block_template()`): the site's Site Editor
 * shows them under Templates, where the owner moves, removes or adds blocks (a theme's own template with the same
 * name wins, as for any plugin template). Built from core blocks (Query Loop, Post Title, Post Excerpt, Post Terms,
 * Query Pagination, the theme's header and footer) and Knotboard's own blocks (`Blocks`).
 *
 * - `archive-windrose_knotboard`: the library (`/<base>/`), filters, the grid of patterns, the selection bar.
 * - `single-windrose_knotboard`: one pattern (`/<base>/<name>/`), large preview, actions.
 * - `windrose-knotboard-selection`: the visitor's selection (`/<base>/selection/`), opened in a new tab.
 * - A block pattern "Pattern Library grid" puts a grid of library patterns on any page (a Query Loop the owner can
 *   narrow to a category or a kind, as the old library's shortcode did); its filters lead to the library.
 *
 * @package Windrose\Knotboard
 */

declare( strict_types=1 );

namespace Windrose\Knotboard;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Templates.
 */
final class Templates {

	public const SELECTION = 'windrose-knotboard-selection';

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public static function init(): void {
		add_action( 'init', array( __CLASS__, 'register' ), 20 );
		add_filter( 'archive_template_hierarchy', array( __CLASS__, 'selection_hierarchy' ) );
	}

	/**
	 * Registers the templates and the block pattern.
	 *
	 * @return void
	 */
	public static function register(): void {
		if ( ! function_exists( 'register_block_template' ) ) {
			return;
		}
		register_block_template(
			'windrose-knotboard//archive-' . Content::TYPE,
			array(
				'title'       => __( 'Pattern Library', 'windrose-knotboard' ),
				'description' => __( 'The pattern library: filters, the patterns and the selection bar (Windrose Knotboard).', 'windrose-knotboard' ),
				'content'     => self::archive(),
			)
		);
		register_block_template(
			'windrose-knotboard//single-' . Content::TYPE,
			array(
				'title'       => __( 'Pattern Library: single pattern', 'windrose-knotboard' ),
				'description' => __( 'One pattern of the library, with its preview and its buttons (Windrose Knotboard).', 'windrose-knotboard' ),
				'content'     => self::single(),
				'post_types'  => array( Content::TYPE ),
			)
		);
		register_block_template(
			'windrose-knotboard//' . self::SELECTION,
			array(
				'title'       => __( 'Pattern Library: selection', 'windrose-knotboard' ),
				'description' => __( 'A visitor’s selection: the chosen patterns together, to copy or download (Windrose Knotboard).', 'windrose-knotboard' ),
				'content'     => self::selection(),
			)
		);
		register_block_pattern(
			'windrose-knotboard/library-grid',
			array(
				'title'       => __( 'Pattern Library grid', 'windrose-knotboard' ),
				'description' => __( 'A grid of the pattern library’s patterns, with their buttons.', 'windrose-knotboard' ),
				'categories'  => array( 'query' ),
				'blockTypes'  => array( 'core/query' ),
				'content'     => self::grid( false ),
			)
		);
	}

	/**
	 * The selection's address uses the selection template.
	 *
	 * @param array $templates Template names.
	 * @phpstan-param list<string> $templates
	 * @return list<string>
	 */
	public static function selection_hierarchy( array $templates ): array {
		if ( 'selection' === get_query_var( Content::VIEW ) && is_post_type_archive( Content::TYPE ) ) {
			array_unshift( $templates, self::SELECTION );
		}
		return $templates;
	}

	/**
	 * A block comment.
	 *
	 * @param string               $name  Block name.
	 * @param array<string, mixed> $attrs Attributes.
	 * @return string
	 */
	private static function block( string $name, array $attrs = array() ): string {
		return '<!-- wp:' . $name . ( array() === $attrs ? '' : ' ' . wp_json_encode( $attrs, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) ) . " /-->\n";
	}

	/**
	 * A heading.
	 *
	 * @param string $text  Text.
	 * @param int    $level Level.
	 * @return string
	 */
	private static function heading( string $text, int $level = 1 ): string {
		$attrs = 2 === $level ? '' : ' ' . wp_json_encode( array( 'level' => $level ) );
		return "<!-- wp:heading{$attrs} -->\n<h{$level} class=\"wp-block-heading\">" . esc_html( $text ) . "</h{$level}>\n<!-- /wp:heading -->\n";
	}

	/**
	 * A paragraph.
	 *
	 * @param string $text Text.
	 * @return string
	 */
	private static function paragraph( string $text ): string {
		return "<!-- wp:paragraph -->\n<p>" . esc_html( $text ) . "</p>\n<!-- /wp:paragraph -->\n";
	}

	/**
	 * The page's frame: the theme's header and footer around a main group.
	 *
	 * @param string $inner Main content.
	 * @return string
	 */
	private static function page( string $inner ): string {
		return self::block(
			'template-part',
			array(
				'slug'    => 'header',
				'tagName' => 'header',
			)
		)
			. '<!-- wp:group {"tagName":"main","style":{"spacing":{"padding":{"top":"var:preset|spacing|50","bottom":"var:preset|spacing|50"}}},"layout":{"type":"constrained"}} -->' . "\n"
			. '<main class="wp-block-group" style="padding-top:var(--wp--preset--spacing--50);padding-bottom:var(--wp--preset--spacing--50)">' . "\n"
			. $inner
			. "</main>\n<!-- /wp:group -->\n"
			. self::block(
				'template-part',
				array(
					'slug'    => 'footer',
					'tagName' => 'footer',
				)
			);
	}

	/**
	 * The filters and the style picker.
	 *
	 * @return string
	 */
	private static function filters(): string {
		$filter = static fn( string $taxonomy ): string => self::block( 'windrose/pattern-filter', array( 'taxonomy' => $taxonomy ) );
		return '<!-- wp:group {"align":"wide","className":"windrose-knotboard-toolbar","layout":{"type":"flex","flexWrap":"wrap","justifyContent":"space-between","verticalAlignment":"top"}} -->' . "\n"
			. '<div class="wp-block-group alignwide windrose-knotboard-toolbar">' . "\n"
			. "<!-- wp:windrose/pattern-filters -->\n"
			. self::block( 'windrose/pattern-search' )
			. $filter( 'kind' )
			. $filter( 'category' )
			. $filter( 'tag' )
			. $filter( 'language' )
			. "<!-- /wp:windrose/pattern-filters -->\n"
			. self::block( 'windrose/pattern-style' )
			. "</div>\n<!-- /wp:group -->\n";
	}

	/**
	 * The grid: a Query Loop of library patterns, a card per pattern.
	 *
	 * @param bool $inherit The library page's own query (with its filters and pages), or a grid of its own.
	 * @return string
	 */
	public static function grid( bool $inherit ): string {
		$query = array(
			'queryId' => 0,
			'query'   => array(
				'perPage'  => Content::PER_PAGE,
				'pages'    => 0,
				'offset'   => 0,
				'postType' => Content::TYPE,
				'order'    => 'desc',
				'orderBy'  => 'date',
				'inherit'  => $inherit,
			),
			'align'   => 'wide',
		);
		$card  = '<!-- wp:group {"className":"windrose-knotboard-card","layout":{"type":"flex","orientation":"vertical","justifyContent":"stretch"}} -->' . "\n"
			. '<div class="wp-block-group windrose-knotboard-card">' . "\n"
			. self::block( 'windrose/pattern-preview' )
			. self::block(
				'post-terms',
				array(
					'term'     => Content::CATEGORY,
					'fontSize' => 'small',
				)
			)
			. self::block(
				'post-title',
				array(
					'level'  => 3,
					'isLink' => true,
				)
			)
			. self::block( 'windrose/pattern-notes', array( 'credits' => false ) )
			. self::block(
				'post-excerpt',
				array(
					'moreText'      => '',
					'excerptLength' => 30,
				)
			)
			. self::block( 'windrose/pattern-actions' )
			. "</div>\n<!-- /wp:group -->\n";
		return '<!-- wp:query ' . wp_json_encode( $query, JSON_UNESCAPED_SLASHES ) . " -->\n"
			. '<div class="wp-block-query alignwide">' . "\n"
			. '<!-- wp:post-template {"layout":{"type":"grid","minimumColumnWidth":"18rem"}} -->' . "\n"
			. $card
			. "<!-- /wp:post-template -->\n"
			. '<!-- wp:query-pagination {"layout":{"type":"flex","justifyContent":"center"}} -->' . "\n"
			. self::block( 'query-pagination-previous' )
			. self::block( 'query-pagination-numbers' )
			. self::block( 'query-pagination-next' )
			. "<!-- /wp:query-pagination -->\n"
			. "<!-- wp:query-no-results -->\n"
			. self::paragraph( __( 'No pattern matches these filters.', 'windrose-knotboard' ) )
			. "<!-- /wp:query-no-results -->\n"
			. "</div>\n<!-- /wp:query -->\n";
	}

	/**
	 * The library page.
	 *
	 * @return string
	 */
	public static function archive(): string {
		return self::page(
			self::heading( __( 'Patterns', 'windrose-knotboard' ) )
			. self::paragraph( __( 'Choose patterns, see them in the style you like, and copy them into your own site: they take your site’s colors and fonts.', 'windrose-knotboard' ) )
			. self::filters()
			. self::grid( true )
			. self::block( 'windrose/pattern-selection-bar' )
		);
	}

	/**
	 * One pattern's page.
	 *
	 * @return string
	 */
	public static function single(): string {
		return self::page(
			self::block( 'windrose/pattern-back' )
			. self::block( 'post-title', array( 'level' => 1 ) )
			. self::block( 'post-terms', array( 'term' => Content::CATEGORY ) )
			. self::block( 'post-excerpt', array( 'moreText' => '' ) )
			. '<!-- wp:group {"align":"wide","layout":{"type":"flex","flexWrap":"wrap","justifyContent":"space-between","verticalAlignment":"center"}} -->' . "\n"
			. '<div class="wp-block-group alignwide">' . "\n"
			. self::block( 'windrose/pattern-style' )
			. self::block( 'windrose/pattern-actions' )
			. "</div>\n<!-- /wp:group -->\n"
			. self::block(
				'windrose/pattern-preview',
				array(
					'size'  => 'full',
					'align' => 'wide',
				)
			)
			. self::block( 'windrose/pattern-notes' )
			. self::block( 'post-terms', array( 'term' => Content::TAG ) )
			. self::block( 'windrose/pattern-selection-bar' )
		);
	}

	/**
	 * The selection's page.
	 *
	 * @return string
	 */
	public static function selection(): string {
		return self::page(
			self::block( 'windrose/pattern-back' )
			. self::heading( __( 'Your selection', 'windrose-knotboard' ) )
			. self::paragraph( __( 'Your chosen patterns together, in the style you like. Change their order, remove some, then copy the header, the footer and the content into your own site.', 'windrose-knotboard' ) )
			. self::block( 'windrose/pattern-style' )
			. self::block( 'windrose/pattern-selection', array( 'align' => 'wide' ) )
		);
	}
}
