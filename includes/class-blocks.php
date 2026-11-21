<?php
/**
 * Knotboard's blocks (`blocks/<name>/block.json`, registered from `blocks/blocks-manifest.php`, rendered here; the
 * browser part is `src/view`, built `build/view.js`):
 *
 * | Block | What it shows |
 * |---|---|
 * | `windrose/pattern-preview` | The pattern drawn in the chosen style (in a Query Loop: a thumbnail; `size: full`: large, Wide or Phone) |
 * | `windrose/pattern-actions` | Copy pattern, Add to selection, View code |
 * | `windrose/pattern-filters` | One form around Pattern Search and Pattern Filter blocks, with one Filter button |
 * | `windrose/pattern-filter` | Kind, category, tag or language (`taxonomy`) as a dropdown (in Pattern Filters), links or a cloud (`display`) |
 * | `windrose/pattern-search` | A search field for the library (its own form outside Pattern Filters) |
 * | `windrose/pattern-style` | The style the previews use (Windrose Livery's ready styles) |
 * | `windrose/pattern-selection-bar` | The number of chosen patterns and "See selection" (opens in a new tab) |
 * | `windrose/pattern-selection` | The selection: order, remove, Copy header, Copy footer, Copy content, Download, preview |
 * | `windrose/pattern-back` | A link back to the library |
 *
 * Filters and search are plain links and a form (they work without JavaScript); previews, copies and the selection
 * need it. Previews are drawn by Windrose Livery on this site (one request for all the thumbnails of a page).
 *
 * @package Windrose\Knotboard
 */

declare( strict_types=1 );

namespace Windrose\Knotboard;

use WP_Block;
use WP_Post;
use WP_Term;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Blocks.
 */
final class Blocks {

	public const VIEW   = 'windrose-knotboard-view';
	public const EDITOR = 'windrose-knotboard-blocks';

	/**
	 * Ipsum's six colors, in its order (core r65224: Twenty Twenty-Five's slugs).
	 *
	 * @var list<string>
	 */
	public const ROLES = array( 'base', 'contrast', 'accent-1', 'accent-3', 'accent-5', 'accent-6' );

	/**
	 * The taxonomies a filter block can use.
	 *
	 * @return array<string, string>
	 */
	public static function filters(): array {
		return array(
			'kind'     => Content::KIND,
			'category' => Content::CATEGORY,
			'tag'      => Content::TAG,
			'language' => Content::LANGUAGE,
		);
	}

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public static function init(): void {
		add_action( 'init', array( __CLASS__, 'register' ), 15 );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'config' ), 100 );
		add_action( 'save_post_page', array( __CLASS__, 'forget_mermaid_page' ) );
		add_action( 'save_post_post', array( __CLASS__, 'forget_mermaid_page' ) );
	}

	/**
	 * Registers a built script (and its style when there is one).
	 *
	 * @param string $handle Handle.
	 * @phpstan-param non-empty-string $handle
	 * @param string $name   File name in build/.
	 * @return void
	 */
	private static function script( string $handle, string $name ): void {
		$asset_file = WINDROSE_KNOTBOARD_DIR . '/build/' . $name . '.asset.php';
		if ( ! is_readable( $asset_file ) ) {
			return;
		}
		$asset = require $asset_file;
		$deps  = is_array( $asset ) && is_array( $asset['dependencies'] ?? null ) ? $asset['dependencies'] : array();
		$ver   = is_array( $asset ) && is_string( $asset['version'] ?? null ) ? $asset['version'] : WINDROSE_KNOTBOARD_VERSION;
		$src   = plugins_url( 'build/' . $name . '.js', WINDROSE_KNOTBOARD_FILE );
		if ( '' === $src ) {
			return;
		}
		wp_register_script( $handle, $src, $deps, $ver, array( 'in_footer' => true ) );
		wp_set_script_translations( $handle, 'windrose-knotboard', WINDROSE_KNOTBOARD_DIR . '/languages' );
		if ( is_readable( WINDROSE_KNOTBOARD_DIR . '/build/' . $name . '.css' ) ) {
			wp_register_style( $handle, plugins_url( 'build/' . $name . '.css', WINDROSE_KNOTBOARD_FILE ), array(), $ver );
			wp_style_add_data( $handle, 'rtl', 'replace' );
		}
	}

	/**
	 * Registers the scripts and the blocks with their render callbacks. The blocks' metadata comes from core's
	 * metadata collection, `blocks/blocks-manifest.php` (written by `wp-scripts build-blocks-manifest` from the
	 * blocks' block.json files, `npm run build`): once it is registered, `register_block_type()` reads the manifest,
	 * not each block.json (as core registers its own dynamic blocks).
	 *
	 * @return void
	 */
	public static function register(): void {
		self::script( self::VIEW, 'view' );
		self::script( self::EDITOR, 'blocks' );
		wp_register_block_metadata_collection( WINDROSE_KNOTBOARD_DIR . '/blocks', WINDROSE_KNOTBOARD_DIR . '/blocks/blocks-manifest.php' );
		$callbacks = array(
			'pattern-preview'       => array( __CLASS__, 'render_preview' ),
			'pattern-actions'       => array( __CLASS__, 'render_actions' ),
			'pattern-filters'       => array( __CLASS__, 'render_filters' ),
			'pattern-filter'        => array( __CLASS__, 'render_filter' ),
			'pattern-search'        => array( __CLASS__, 'render_search' ),
			'pattern-style'         => array( __CLASS__, 'render_style' ),
			'pattern-selection-bar' => array( __CLASS__, 'render_selection_bar' ),
			'pattern-selection'     => array( __CLASS__, 'render_selection' ),
			'pattern-back'          => array( __CLASS__, 'render_back' ),
			'pattern-notes'         => array( __CLASS__, 'render_notes' ),
		);
		foreach ( $callbacks as $name => $callback ) {
			register_block_type( WINDROSE_KNOTBOARD_DIR . '/blocks/' . $name, array( 'render_callback' => $callback ) );
		}
		if ( wp_script_is( self::EDITOR, 'registered' ) ) {
			wp_add_inline_script(
				self::EDITOR,
				'window.windroseKnotboardBlocks = ' . wp_json_encode(
					array(
						'livery'  => Plugin::has_livery(),
						'filters' => array_keys( self::filters() ),
					)
				) . ';',
				'before'
			);
		}
	}

	/**
	 * What the browser part needs, on pages that show a Knotboard block.
	 *
	 * @return void
	 */
	public static function config(): void {
		if ( ! wp_script_is( self::VIEW, 'enqueued' ) ) {
			return;
		}
		$data = array(
			'site'      => get_current_blog_id(),
			'rest'      => esc_url_raw( get_rest_url( null, Rest::NAMESPACE ) ),
			'render'    => Plugin::has_livery() ? esc_url_raw( \Windrose\Livery\Patterns::render_url() ) : '',
			'source'    => Render::SOURCE,
			'style'     => 'ipsum',
			'library'   => esc_url_raw( Content::library_url() ),
			'selection' => esc_url_raw( Content::selection_url() ),
			'kinds'     => Content::kind_labels(),
			// Template parts the library offers: the selection page proposes a header or a footer only when there is one.
			'parts'     => array(
				'header' => isset( self::values( 'kind' )['header'] ),
				'footer' => isset( self::values( 'kind' )['footer'] ),
			),
		);
		wp_add_inline_script( self::VIEW, 'window.windroseKnotboard = ' . wp_json_encode( $data ) . ';', 'before' );
	}

	/**
	 * The pattern of a block's context (a Query Loop's, or the page's own).
	 *
	 * @param WP_Block|null $block Block.
	 * @return WP_Post|null
	 */
	private static function post( ?WP_Block $block ): ?WP_Post {
		$id   = $block instanceof WP_Block && isset( $block->context['postId'] ) ? (int) $block->context['postId'] : (int) get_the_ID();
		$post = $id > 0 ? get_post( $id ) : null;
		if ( ! $post instanceof WP_Post || Content::TYPE !== $post->post_type || post_password_required( $post ) ) {
			return null;
		}
		return Content::is_offered( $post ) || current_user_can( 'read_post', $post->ID ) ? $post : null;
	}

	/**
	 * A block's wrapper attributes.
	 *
	 * @param string                $classes Classes.
	 * @param array<string, string> $extra   Other attributes.
	 * @return string
	 */
	private static function wrapper( string $classes, array $extra = array() ): string {
		return get_block_wrapper_attributes( array( 'class' => $classes ) + $extra );
	}

	/**
	 * `windrose/pattern-preview`.
	 *
	 * @param array<string, mixed> $attributes Attributes.
	 * @param string               $content    Inner content.
	 * @param WP_Block|null        $block      Block.
	 * @return string
	 */
	public static function render_preview( array $attributes, string $content = '', ?WP_Block $block = null ): string {
		$post = self::post( $block );
		if ( null === $post || ! Plugin::has_livery() ) {
			return '';
		}
		$item  = Content::item( $post );
		$full  = 'full' === ( $attributes['size'] ?? '' );
		$link  = $full ? '' : sprintf(
			'<a class="windrose-knotboard-preview__link" href="%1$s" tabindex="-1" aria-hidden="true"></a>',
			esc_url( (string) get_permalink( $post ) )
		);
		$sizes = $full ? sprintf(
			'<div class="windrose-knotboard-preview__sizes" role="group" aria-label="%1$s"><button type="button" data-size="wide" aria-pressed="true">%2$s</button><button type="button" data-size="phone" aria-pressed="false">%3$s</button></div>',
			esc_attr__( 'Preview width', 'windrose-knotboard' ),
			esc_html__( 'Wide', 'windrose-knotboard' ),
			esc_html__( 'Phone', 'windrose-knotboard' )
		) : '';
		return sprintf(
			'<div %1$s>%2$s<div class="windrose-knotboard-preview__frame"><p class="windrose-knotboard-preview__wait">%3$s</p></div>%4$s</div>',
			self::wrapper(
				'windrose-knotboard-preview ' . ( $full ? 'is-full' : 'is-thumb' ),
				array(
					'data-windrose-knotboard-preview' => (string) $item['id'],
					'data-title'                      => $item['title'],
					'data-lang'                       => $item['lang'],
					'data-dir'                        => $item['dir'],
				)
			),
			$sizes,
			esc_html__( 'Drawing the preview…', 'windrose-knotboard' ),
			$link
		);
	}

	/**
	 * `windrose/pattern-actions`.
	 *
	 * @param array<string, mixed> $attributes Attributes.
	 * @param string               $content    Inner content.
	 * @param WP_Block|null        $block      Block.
	 * @return string
	 */
	public static function render_actions( array $attributes, string $content = '', ?WP_Block $block = null ): string {
		$post = self::post( $block );
		if ( null === $post ) {
			return '';
		}
		$item    = Content::item( $post );
		$buttons = '';
		if ( false !== ( $attributes['copy'] ?? true ) ) {
			$buttons .= sprintf( '<button type="button" class="windrose-knotboard-actions__copy wp-element-button" data-action="copy">%s</button>', esc_html__( 'Copy pattern', 'windrose-knotboard' ) );
		}
		if ( false !== ( $attributes['select'] ?? true ) ) {
			$buttons .= sprintf( '<button type="button" class="windrose-knotboard-actions__select" data-action="select" aria-pressed="false">%s</button>', esc_html__( 'Add to selection', 'windrose-knotboard' ) );
		}
		if ( false !== ( $attributes['code'] ?? true ) ) {
			$buttons .= sprintf( '<button type="button" class="windrose-knotboard-actions__code" data-action="code">%s</button>', esc_html__( 'View code', 'windrose-knotboard' ) );
		}
		if ( '' === $buttons ) {
			return '';
		}
		$code = (string) wp_json_encode( Copy::for_visitors( $post->post_content ), JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE );
		return sprintf(
			'<div %1$s>%2$s<script type="application/json" class="windrose-knotboard-actions__markup">%3$s</script></div>',
			self::wrapper(
				'windrose-knotboard-actions',
				array(
					'data-windrose-knotboard-pattern' => (string) $item['id'],
					'data-kind'                       => $item['kind'],
					'data-title'                      => $item['title'],
					'data-lang'                       => $item['lang'],
					'data-pictures'                   => Copy::has_pictures( $post->post_content ) ? '1' : '0',
				)
			),
			$buttons,
			$code
		);
	}

	/**
	 * The values chosen for a filter (several allowed: OR inside one filter), from the address: `a,b` or `x[]=a&x[]=b`.
	 *
	 * @param string $query_var Query variable.
	 * @return list<string>
	 */
	public static function chosen( string $query_var ): array {
		$value = get_query_var( $query_var );
		$list  = is_array( $value ) ? $value : explode( ',', is_string( $value ) ? $value : '' );
		$out   = array();
		foreach ( $list as $item ) {
			$item = is_scalar( $item ) ? sanitize_title( (string) $item ) : '';
			if ( '' !== $item && ! in_array( $item, $out, true ) ) {
				$out[] = $item;
			}
		}
		return array_slice( $out, 0, 50 );
	}

	/**
	 * The active filters and search, as query arguments (several values joined by commas).
	 *
	 * @return array<string, string>
	 */
	private static function active(): array {
		$args = array();
		foreach ( self::filters() as $taxonomy ) {
			$values = self::chosen( $taxonomy );
			if ( array() !== $values ) {
				$args[ $taxonomy ] = implode( ',', $values );
			}
		}
		$search = get_query_var( Content::SEARCH );
		if ( is_string( $search ) && '' !== trim( $search ) ) {
			$args[ Content::SEARCH ] = $search;
		}
		return $args;
	}

	/**
	 * The library's address with these filters (others kept, page 1).
	 *
	 * @param array<string, list<string>|string|null> $change Query variables to set (a list, a value; null removes).
	 * @return string
	 */
	public static function filter_url( array $change ): string {
		$args = self::active();
		foreach ( $change as $var => $value ) {
			$value = is_array( $value ) ? implode( ',', $value ) : $value;
			if ( null === $value || '' === $value ) {
				unset( $args[ $var ] );
			} else {
				$args[ $var ] = $value;
			}
		}
		return add_query_arg( array_map( 'rawurlencode', $args ), Content::library_url() );
	}

	/**
	 * Whether a block sits in Pattern Filters (one form for all).
	 *
	 * @param WP_Block|null $block Block.
	 * @return bool
	 */
	private static function in_form( ?WP_Block $block ): bool {
		return $block instanceof WP_Block && ! empty( $block->context['windrose-knotboard/form'] );
	}

	/**
	 * Hidden fields for the active filters and search, except some (for the small forms outside Pattern Filters).
	 *
	 * @param array $except Query variables left out.
	 * @phpstan-param list<string> $except
	 * @return string
	 */
	private static function hidden( array $except ): string {
		$html = '';
		foreach ( self::active() as $var => $value ) {
			if ( in_array( $var, $except, true ) ) {
				continue;
			}
			$values = Content::SEARCH === $var ? array( $value ) : explode( ',', $value );
			foreach ( $values as $one ) {
				$html .= sprintf(
					'<input type="hidden" name="%1$s" value="%2$s" />',
					esc_attr( Content::SEARCH === $var ? $var : $var . '[]' ),
					esc_attr( $one )
				);
			}
		}
		return $html;
	}

	/**
	 * A filter's values in use: slug => label and number of patterns, kinds in their own order with plural labels.
	 *
	 * @param string $key Filter key (kind, category, tag, language).
	 * @return array<string, array{label: string, count: int}>
	 */
	public static function values( string $key ): array {
		$taxonomy = self::filters()[ $key ] ?? null;
		if ( null === $taxonomy ) {
			return array();
		}
		$terms = get_terms(
			array(
				'taxonomy'   => $taxonomy,
				'hide_empty' => true,
			)
		);
		if ( ! is_array( $terms ) ) {
			return array();
		}
		$values = array();
		foreach ( $terms as $term ) {
			if ( $term instanceof WP_Term ) {
				$values[ $term->slug ] = array(
					'label' => $term->name,
					'count' => (int) $term->count,
				);
			}
		}
		if ( 'kind' !== $key ) {
			return $values;
		}
		$plural = Content::kind_plurals();
		$kinds  = array();
		foreach ( Content::KINDS as $slug ) {
			if ( isset( $values[ $slug ] ) ) {
				$kinds[ $slug ] = array(
					'label' => $plural[ $slug ],
					'count' => $values[ $slug ]['count'],
				);
			}
		}
		return $kinds;
	}

	/**
	 * Labels of a filter.
	 *
	 * @param string $key Filter key.
	 * @return array{name: string, nav: string, all: string}
	 */
	private static function labels( string $key ): array {
		$labels = array(
			'kind'     => array( __( 'Kind', 'windrose-knotboard' ), __( 'Filter by kind', 'windrose-knotboard' ), __( 'All kinds', 'windrose-knotboard' ) ),
			'category' => array( __( 'Category', 'windrose-knotboard' ), __( 'Filter by category', 'windrose-knotboard' ), __( 'All categories', 'windrose-knotboard' ) ),
			'tag'      => array( __( 'Tags', 'windrose-knotboard' ), __( 'Filter by tag', 'windrose-knotboard' ), __( 'All tags', 'windrose-knotboard' ) ),
			'language' => array( __( 'Language', 'windrose-knotboard' ), __( 'Filter by language', 'windrose-knotboard' ), __( 'All languages', 'windrose-knotboard' ) ),
		);
		$one    = $labels[ $key ] ?? $labels['kind'];
		return array(
			'name' => $one[0],
			'nav'  => $one[1],
			'all'  => $one[2],
		);
	}

	/**
	 * `windrose/pattern-filters`: one form, one Filter button, the active choices as chips that remove themselves, and
	 * Clear filters (no JavaScript needed; nothing changes before the visitor presses Filter).
	 *
	 * @param array<string, mixed> $attributes Attributes.
	 * @param string               $content    Inner blocks, rendered.
	 * @return string
	 */
	public static function render_filters( array $attributes, string $content = '' ): string {
		$chips = '';
		foreach ( self::filters() as $key => $taxonomy ) {
			$values = self::values( $key );
			$chosen = self::chosen( $taxonomy );
			foreach ( $chosen as $slug ) {
				$label  = $values[ $slug ]['label'] ?? $slug;
				$chips .= sprintf(
					'<li><a href="%1$s" aria-label="%2$s"%3$s>%4$s <span aria-hidden="true">×</span></a></li>',
					esc_url( self::filter_url( array( $taxonomy => array_values( array_diff( $chosen, array( $slug ) ) ) ) ) ),
					esc_attr(
						sprintf(
							/* translators: %s: a filter's value, for example restaurant */
							__( 'Remove the filter %s', 'windrose-knotboard' ),
							$label
						)
					),
					'language' === $key ? ' lang="' . esc_attr( $slug ) . '"' : '',
					esc_html( $label )
				);
			}
		}
		$search = get_query_var( Content::SEARCH );
		if ( is_string( $search ) && '' !== trim( $search ) ) {
			$chips .= sprintf(
				'<li><a href="%1$s" aria-label="%2$s">“%3$s” <span aria-hidden="true">×</span></a></li>',
				esc_url( self::filter_url( array( Content::SEARCH => null ) ) ),
				esc_attr(
					sprintf(
						/* translators: %s: the words searched */
						__( 'Remove the search %s', 'windrose-knotboard' ),
						$search
					)
				),
				esc_html( $search )
			);
		}
		$active = '' === $chips ? '' : sprintf(
			'<ul class="windrose-knotboard-filters__active" aria-label="%1$s">%2$s<li><a class="windrose-knotboard-filters__clear" href="%3$s">%4$s</a></li></ul>',
			esc_attr__( 'Active filters', 'windrose-knotboard' ),
			$chips,
			esc_url( Content::library_url() ),
			esc_html__( 'Clear filters', 'windrose-knotboard' )
		);
		return sprintf(
			'<form %1$s role="search" method="get" action="%2$s"><div class="windrose-knotboard-filters__row">%3$s<div class="windrose-knotboard-filters__submit"><button type="submit" class="wp-element-button">%4$s</button></div></div>%5$s</form>',
			self::wrapper( 'windrose-knotboard-filters' ),
			esc_url( Content::library_url() ),
			$content,
			esc_html__( 'Filter', 'windrose-knotboard' ),
			$active
		);
	}

	/**
	 * `windrose/pattern-filter`: several values at once (OR inside the filter), shown as a button opening checkboxes
	 * (the default; in Pattern Filters, or with its own small form), links or a cloud. Only with two values or more.
	 *
	 * @param array<string, mixed> $attributes Attributes.
	 * @param string               $content    Inner content.
	 * @param WP_Block|null        $block      Block.
	 * @return string
	 */
	public static function render_filter( array $attributes, string $content = '', ?WP_Block $block = null ): string {
		$key      = is_string( $attributes['taxonomy'] ?? null ) ? $attributes['taxonomy'] : 'kind';
		$display  = is_string( $attributes['display'] ?? null ) && in_array( $attributes['display'], array( 'dropdown', 'links', 'cloud' ), true ) ? $attributes['display'] : 'dropdown';
		$taxonomy = self::filters()[ $key ] ?? null;
		$values   = self::values( $key );
		// One value only (every pattern is a section, every pattern is in English...): nothing to choose.
		if ( null === $taxonomy || count( $values ) < 2 ) {
			return '';
		}
		$labels = self::labels( $key );
		$chosen = self::chosen( $taxonomy );

		if ( 'dropdown' === $display ) {
			$id    = wp_unique_id( 'windrose-knotboard-filter-' );
			$boxes = '';
			foreach ( $values as $slug => $value ) {
				$boxes .= sprintf(
					'<label class="windrose-knotboard-filter__option"><input type="checkbox" name="%1$s[]" value="%2$s"%3$s /><span%4$s>%5$s</span> <span class="windrose-knotboard-filter__count">%6$s</span></label>',
					esc_attr( $taxonomy ),
					esc_attr( (string) $slug ),
					checked( in_array( (string) $slug, $chosen, true ), true, false ),
					'language' === $key ? ' lang="' . esc_attr( (string) $slug ) . '"' : '',
					esc_html( $value['label'] ),
					esc_html(
						sprintf(
							/* translators: %d: number of patterns */
							_n( '%d pattern', '%d patterns', $value['count'], 'windrose-knotboard' ),
							$value['count']
						)
					)
				);
			}
			$summary = array() === $chosen ? $labels['name'] : sprintf(
				/* translators: 1: a filter's name (Tags), 2: how many values are chosen */
				__( '%1$s (%2$d)', 'windrose-knotboard' ),
				$labels['name'],
				count( $chosen )
			);
			$field = sprintf(
				'<details class="windrose-knotboard-filter__menu" data-name="%1$s"><summary id="%2$s">%3$s</summary><fieldset class="windrose-knotboard-filter__list"><legend>%4$s</legend>%5$s</fieldset></details>',
				esc_attr( $labels['name'] ),
				esc_attr( $id ),
				esc_html( $summary ),
				esc_html( $labels['nav'] ),
				$boxes
			);
			if ( self::in_form( $block ) ) {
				return sprintf( '<div %1$s>%2$s</div>', self::wrapper( 'windrose-knotboard-filter is-dropdown is-' . $key ), $field );
			}
			return sprintf(
				'<form %1$s method="get" action="%2$s">%3$s%4$s<button type="submit">%5$s</button></form>',
				self::wrapper( 'windrose-knotboard-filter is-dropdown is-' . $key ),
				esc_url( Content::library_url() ),
				$field,
				self::hidden( array( $taxonomy ) ),
				esc_html__( 'Filter', 'windrose-knotboard' )
			);
		}//end if

		$counts = array_column( $values, 'count' );
		$max    = array() === $counts ? 0 : max( $counts );
		$min    = array() === $counts ? 0 : min( $counts );
		$items  = sprintf(
			'<li><a href="%1$s"%2$s>%3$s</a></li>',
			esc_url( self::filter_url( array( $taxonomy => null ) ) ),
			array() === $chosen ? ' aria-current="page"' : '',
			esc_html( $labels['all'] )
		);
		foreach ( $values as $slug => $value ) {
			$slug = (string) $slug;
			$on   = in_array( $slug, $chosen, true );
			// A link adds its value to the chosen ones, or takes it away (several at once, OR).
			$next = $on ? array_values( array_diff( $chosen, array( $slug ) ) ) : array_merge( $chosen, array( $slug ) );
			// A cloud: from 0.85 to 1.5 times the text size, by how many patterns use the term.
			$size   = 'cloud' === $display ? 0.85 + ( $max > $min ? ( $value['count'] - $min ) / ( $max - $min ) : 0.5 ) * 0.65 : 0;
			$items .= sprintf(
				'<li><a href="%1$s"%2$s%3$s%4$s>%5$s</a></li>',
				esc_url( self::filter_url( array( $taxonomy => $next ) ) ),
				$on ? ' aria-current="true"' : '',
				'language' === $key ? ' lang="' . esc_attr( $slug ) . '"' : '',
				$size > 0 ? ' style="font-size:' . esc_attr( (string) round( $size, 2 ) ) . 'em"' : '',
				esc_html( $value['label'] )
			);
		}
		return sprintf(
			'<nav %1$s aria-label="%2$s"><ul>%3$s</ul></nav>',
			self::wrapper( 'windrose-knotboard-filter is-' . $display . ' is-' . $key ),
			esc_attr( $labels['nav'] ),
			$items
		);
	}

	/**
	 * `windrose/pattern-search`: a field in Pattern Filters, or a small form of its own (no JavaScript needed).
	 *
	 * @param array<string, mixed> $attributes Attributes.
	 * @param string               $content    Inner content.
	 * @param WP_Block|null        $block      Block.
	 * @return string
	 */
	public static function render_search( array $attributes = array(), string $content = '', ?WP_Block $block = null ): string {
		$value = get_query_var( Content::SEARCH );
		$id    = wp_unique_id( 'windrose-knotboard-search-' );
		// Its name shows inside the field, as the filters' names show in their buttons; screen readers get the label.
		$field = sprintf(
			'<label for="%1$s" class="windrose-knotboard-hidden">%2$s</label><input type="search" id="%1$s" name="%3$s" value="%4$s" placeholder="%2$s" />',
			esc_attr( $id ),
			esc_html__( 'Search patterns', 'windrose-knotboard' ),
			esc_attr( Content::SEARCH ),
			esc_attr( is_string( $value ) ? $value : '' )
		);
		if ( self::in_form( $block ) ) {
			return sprintf( '<div %1$s>%2$s</div>', self::wrapper( 'windrose-knotboard-search' ), $field );
		}
		return sprintf(
			'<form %1$s role="search" method="get" action="%2$s">%3$s%4$s<button type="submit">%5$s</button></form>',
			self::wrapper( 'windrose-knotboard-search' ),
			esc_url( Content::library_url() ),
			$field,
			self::hidden( array( Content::SEARCH ) ),
			esc_html__( 'Search', 'windrose-knotboard' )
		);
	}

	/**
	 * A ready style's six colors, in Ipsum's order (Base, Contrast, Accent, Strong Contrast, Surface, Border; core
	 * r65224 gave them Twenty Twenty-Five's slugs).
	 *
	 * @param array<string, mixed> $recipe Recipe.
	 * @return list<string>
	 */
	public static function swatches( array $recipe ): array {
		$palette = $recipe['settings']['color']['palette'] ?? array();
		$colors  = array();
		if ( is_array( $palette ) ) {
			foreach ( $palette as $entry ) {
				if ( is_array( $entry ) && is_string( $entry['slug'] ?? null ) && in_array( $entry['slug'], self::ROLES, true ) && is_string( $entry['color'] ?? null ) && 1 === preg_match( '/^#[0-9a-fA-F]{3,8}$/', $entry['color'] ) ) {
					$colors[ $entry['slug'] ] = $entry['color'];
				}
			}
		}
		return array_values( array_filter( array_map( static fn( string $slug ): string => $colors[ $slug ] ?? '', self::ROLES ) ) );
	}

	/**
	 * Color dots (shown, not read: the style's name says it).
	 *
	 * @param array $colors Colors.
	 * @phpstan-param list<string> $colors
	 * @return string
	 */
	private static function dots( array $colors ): string {
		$html = '';
		foreach ( $colors as $color ) {
			$html .= sprintf( '<span style="background:%s"></span>', esc_attr( $color ) );
		}
		return '<span class="windrose-knotboard-style__dots" aria-hidden="true">' . $html . '</span>';
	}

	/**
	 * `windrose/pattern-style`: a button showing the style, opening a list of the ready styles with their colors
	 * (radio buttons, so keyboards and screen readers use it as any choice).
	 *
	 * @return string
	 */
	public static function render_style(): string {
		if ( ! Plugin::has_livery() ) {
			return '';
		}
		$id      = wp_unique_id( 'windrose-knotboard-styles-' );
		$options = '';
		$first   = null;
		foreach ( \Windrose\Livery\Catalog::ready_recipes() as $item ) {
			$colors   = self::swatches( $item['recipe'] );
			$first    = $first ?? array( $item['title'], $colors );
			$options .= sprintf(
				'<label class="windrose-knotboard-style__option"><input type="radio" name="%1$s" value="%2$s" data-title="%3$s" data-colors="%4$s" /><span class="windrose-knotboard-style__name">%5$s</span>%6$s</label>',
				esc_attr( $id ),
				esc_attr( $item['slug'] ),
				esc_attr( $item['title'] ),
				esc_attr( implode( ',', $colors ) ),
				esc_html( $item['title'] ),
				self::dots( $colors )
			);
		}
		if ( null === $first ) {
			return '';
		}
		$make = self::mermaid_page();
		if ( '' !== $make ) {
			$options .= sprintf(
				'<a class="windrose-knotboard-style__make" href="%1$s">%2$s</a>',
				esc_url( $make ),
				esc_html__( 'Make your own style with Style Mermaid', 'windrose-knotboard' )
			);
		}
		return sprintf(
			'<div %1$s data-windrose-knotboard-style><button type="button" class="windrose-knotboard-style__toggle" aria-expanded="false" aria-controls="%2$s"><span class="windrose-knotboard-style__label">%3$s</span> <span class="windrose-knotboard-style__current">%4$s</span>%5$s</button><fieldset class="windrose-knotboard-style__list" id="%2$s" hidden><legend>%6$s</legend>%7$s</fieldset></div>',
			self::wrapper( 'windrose-knotboard-style' ),
			esc_attr( $id ),
			esc_html__( 'Preview style:', 'windrose-knotboard' ),
			esc_html( $first[0] ),
			self::dots( $first[1] ),
			esc_html__( 'Preview style', 'windrose-knotboard' ),
			$options
		);
	}

	/**
	 * The page of this site that offers Style Mermaid to visitors (Windrose Livery's block), where its public switch is
	 * on; '' otherwise. Looked up once a day, and again when a page is saved.
	 *
	 * @return string
	 */
	public static function mermaid_page(): string {
		if ( ! Plugin::has_livery() || ! \Windrose\Livery\Mermaid::settings()['public'] ) {
			return '';
		}
		$id = get_transient( 'windrose_knotboard_mermaid_page' );
		if ( false === $id ) {
			$id    = 0;
			$pages = get_posts(
				array(
					'post_type'        => array( 'page', 'post' ),
					'post_status'      => 'publish',
					'numberposts'      => 100,
					'orderby'          => 'menu_order date',
					'order'            => 'ASC',
					'suppress_filters' => false,
				)
			);
			foreach ( $pages as $page ) {
				if ( $page instanceof WP_Post && has_block( 'windrose/style-mermaid', $page ) ) {
					$id = $page->ID;
					break;
				}
			}
			set_transient( 'windrose_knotboard_mermaid_page', $id, DAY_IN_SECONDS );
		}
		$url = (int) $id > 0 ? get_permalink( (int) $id ) : false;
		return is_string( $url ) ? $url : '';
	}

	/**
	 * Forgets which page offers Style Mermaid.
	 *
	 * @return void
	 */
	public static function forget_mermaid_page(): void {
		delete_transient( 'windrose_knotboard_mermaid_page' );
	}

	/**
	 * `windrose/pattern-selection-bar`.
	 *
	 * @return string
	 */
	public static function render_selection_bar(): string {
		return sprintf(
			'<div %1$s hidden><p><span class="windrose-knotboard-bar__count" aria-live="polite"></span> <a href="%2$s" target="_blank" rel="noopener">%3$s <span class="windrose-knotboard-bar__new">%4$s</span></a> <button type="button" data-action="clear">%5$s</button></p></div>',
			self::wrapper( 'windrose-knotboard-bar' ),
			esc_url( Content::selection_url() ),
			esc_html__( 'See selection', 'windrose-knotboard' ),
			esc_html__( '(opens in a new tab)', 'windrose-knotboard' ),
			esc_html__( 'Clear', 'windrose-knotboard' )
		);
	}

	/**
	 * `windrose/pattern-selection`: the browser fills it.
	 *
	 * @return string
	 */
	public static function render_selection(): string {
		return sprintf(
			'<div %1$s data-windrose-knotboard-selection><p>%2$s</p><noscript><p>%3$s</p></noscript></div>',
			self::wrapper( 'windrose-knotboard-selection' ),
			esc_html__( 'Loading your selection…', 'windrose-knotboard' ),
			esc_html__( 'Your selection needs JavaScript.', 'windrose-knotboard' )
		);
	}

	/**
	 * `windrose/pattern-back`.
	 *
	 * @return string
	 */
	public static function render_back(): string {
		return sprintf(
			'<p %1$s><a href="%2$s">%3$s</a></p>',
			self::wrapper( 'windrose-knotboard-back' ),
			esc_url( Content::library_url() ),
			esc_html__( 'Back to all patterns', 'windrose-knotboard' )
		);
	}

	/**
	 * `windrose/pattern-notes`: "Translation not reviewed yet" for imported translations nobody reviewed, and the
	 * credits of the pattern's photos (from the package).
	 *
	 * @param array<string, mixed> $attributes Attributes.
	 * @param string               $content    Inner content.
	 * @param WP_Block|null        $block      Block.
	 * @return string
	 */
	public static function render_notes( array $attributes, string $content = '', ?WP_Block $block = null ): string {
		$post = self::post( $block );
		if ( null === $post ) {
			return '';
		}
		$parts = array();
		if ( '' !== get_post_meta( $post->ID, Import::META_UNREVIEWED, true ) ) {
			$parts[] = '<span class="windrose-knotboard-notes__unreviewed">' . esc_html__( 'Translation not reviewed yet', 'windrose-knotboard' ) . '</span>';
		}
		$credits = false !== ( $attributes['credits'] ?? true ) ? get_post_meta( $post->ID, Import::META_CREDITS, true ) : array();
		foreach ( is_array( $credits ) ? $credits : array() as $credit ) {
			if ( ! is_array( $credit ) || '' === (string) ( $credit['author'] ?? '' ) ) {
				continue;
			}
			$author  = esc_html( (string) $credit['author'] );
			$url     = (string) ( $credit['author_url'] ?? '' );
			$author  = '' !== $url ? '<a href="' . esc_url( $url ) . '">' . $author . '</a>' : $author;
			$licence = esc_html( (string) ( $credit['licence'] ?? '' ) );
			$source  = (string) ( $credit['source'] ?? '' );
			$licence = '' !== $source && '' !== $licence ? '<a href="' . esc_url( $source ) . '">' . $licence . '</a>' : $licence;
			$parts[] = '<span class="windrose-knotboard-notes__credit">' . ( '' !== $licence
				/* translators: 1: photographer's name, 2: licence, for example CC0 1.0 */
				? sprintf( __( 'Photo: %1$s, %2$s', 'windrose-knotboard' ), $author, $licence )
				/* translators: %s: photographer's name */
				: sprintf( __( 'Photo: %s', 'windrose-knotboard' ), $author ) ) . '</span>';
		}
		if ( array() === $parts ) {
			return '';
		}
		return sprintf( '<p %1$s>%2$s</p>', self::wrapper( 'windrose-knotboard-notes' ), implode( ' ', $parts ) );
	}
}
