<?php
/**
 * The library's patterns: a post type of their own, never in the Site Editor's patterns or the
 * site's inserter, so a site's own patterns and the patterns it offers visitors stay apart.
 *
 * - Post type `windrose_knotboard` (menu "Pattern Library", items "Pattern"): title, block content, excerpt (the card's description),
 *   revisions. Published = offered. Its address base is `patterns` by default and can be changed under Settings,
 *   Permalinks (a site may call them "designs", or translate the word): `/<base>/` the library, `/<base>/<name>/` one
 *   pattern, `/<base>/selection/` the visitor's selection.
 * - Taxonomies: `windrose_knotboard_kind` (header, footer, section, page; exactly one, `section` when none),
 *   `windrose_knotboard_category`, `windrose_knotboard_tag`, `windrose_knotboard_language` (slug = language code, such
 *   as `fr` or `pt-br`; right to left for Arabic, Persian, Urdu, Hebrew and the others in `RTL`). They filter the
 *   library through query variables of the same names (no address of their own).
 * - The library page shows 24 patterns at a time; `windrose_knotboard_search` searches titles, descriptions and
 *   content without turning the page into a search page.
 * - Rights: capabilities of their own (`edit_windrose_knotboard_patterns`...), granted to people with
 *   `edit_theme_options` on the site; on a network only network administrators have them while the network switch is
 *   off (`Network`).
 *
 * @package Windrose\Knotboard
 */

declare( strict_types=1 );

namespace Windrose\Knotboard;

use WP_Post;
use WP_Query;
use WP_Term;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Post type, taxonomies, address.
 *
 * @phpstan-type Item array{id: int, title: string, kind: string, lang: string, dir: string}
 */
final class Content {

	public const TYPE     = 'windrose_knotboard';
	public const KIND     = 'windrose_knotboard_kind';
	public const CATEGORY = 'windrose_knotboard_category';
	public const TAG      = 'windrose_knotboard_tag';
	public const LANGUAGE = 'windrose_knotboard_language';
	public const SEARCH   = 'windrose_knotboard_search';
	public const VIEW     = 'windrose_knotboard_view';
	public const BASE     = 'windrose_knotboard_base';
	public const KINDS    = array( 'header', 'footer', 'section', 'page' );
	public const PER_PAGE = 24;

	/** The library's own capabilities start with this (granted by `grant()`). */
	public const CAP_PREFIX = 'windrose_knotboard_pattern';

	/** Managing the library's terms. */
	public const CAP = 'edit_windrose_knotboard_patterns';

	/** Language codes written right to left (by their first part). */
	public const RTL = array( 'ar', 'arc', 'ckb', 'dv', 'fa', 'ha', 'he', 'khw', 'ks', 'ps', 'sd', 'ug', 'ur', 'yi' );

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public static function init(): void {
		add_action( 'init', array( __CLASS__, 'register' ), 5 );
		add_action( 'wp_loaded', array( __CLASS__, 'upgrade' ) );
		add_action( 'pre_get_posts', array( __CLASS__, 'query' ) );
		add_filter( 'query_loop_block_query_vars', array( __CLASS__, 'grid_query' ) );
		add_filter( 'query_vars', array( __CLASS__, 'query_vars' ) );
		add_action( 'save_post_' . self::TYPE, array( __CLASS__, 'one_kind' ), 20 );
		add_action( 'load-options-permalink.php', array( __CLASS__, 'permalink_field' ) );
		add_filter( 'user_has_cap', array( __CLASS__, 'grant' ), 10, 2 );
		add_filter( 'manage_' . self::TYPE . '_posts_columns', array( __CLASS__, 'columns' ) );
	}

	/**
	 * The address base (`patterns` in the site's language unless changed under Settings, Permalinks).
	 *
	 * @return string
	 */
	public static function base(): string {
		$base = get_option( self::BASE, '' );
		$base = is_string( $base ) ? sanitize_title( $base ) : '';
		return '' !== $base ? $base : sanitize_title( _x( 'patterns', 'address base of the pattern library', 'windrose-knotboard' ) );
	}

	/**
	 * The library's address.
	 *
	 * @return string
	 */
	public static function library_url(): string {
		$url = get_post_type_archive_link( self::TYPE );
		return is_string( $url ) ? $url : home_url( '/' );
	}

	/**
	 * The selection's address.
	 *
	 * @return string
	 */
	public static function selection_url(): string {
		global $wp_rewrite;
		if ( $wp_rewrite instanceof \WP_Rewrite && $wp_rewrite->using_permalinks() ) {
			return trailingslashit( home_url( user_trailingslashit( self::base() . '/selection' ) ) );
		}
		return add_query_arg(
			array(
				'post_type' => self::TYPE,
				self::VIEW  => 'selection',
			),
			home_url( '/' )
		);
	}

	/**
	 * Registers the post type, the taxonomies and the selection's address.
	 *
	 * @return void
	 */
	public static function register(): void {
		register_post_type(
			self::TYPE,
			array(
				'labels'          => array(
					'name'                   => __( 'Patterns', 'windrose-knotboard' ),
					'singular_name'          => __( 'Pattern', 'windrose-knotboard' ),
					'menu_name'              => __( 'Pattern Library', 'windrose-knotboard' ),
					'add_new'                => __( 'Add Pattern', 'windrose-knotboard' ),
					'add_new_item'           => __( 'Add Pattern', 'windrose-knotboard' ),
					'edit_item'              => __( 'Edit Pattern', 'windrose-knotboard' ),
					'new_item'               => __( 'New Pattern', 'windrose-knotboard' ),
					'view_item'              => __( 'View Pattern', 'windrose-knotboard' ),
					'view_items'             => __( 'View Patterns', 'windrose-knotboard' ),
					'search_items'           => __( 'Search Patterns', 'windrose-knotboard' ),
					'not_found'              => __( 'No patterns found.', 'windrose-knotboard' ),
					'not_found_in_trash'     => __( 'No patterns found in Trash.', 'windrose-knotboard' ),
					'all_items'              => __( 'All Patterns', 'windrose-knotboard' ),
					'archives'               => __( 'Pattern Library', 'windrose-knotboard' ),
					'item_published'         => __( 'Pattern published.', 'windrose-knotboard' ),
					'item_updated'           => __( 'Pattern updated.', 'windrose-knotboard' ),
					'item_reverted_to_draft' => __( 'Pattern reverted to draft.', 'windrose-knotboard' ),
					'item_scheduled'         => __( 'Pattern scheduled.', 'windrose-knotboard' ),
					'item_link'              => __( 'Pattern Link', 'windrose-knotboard' ),
				),
				'description'     => __( 'Patterns this site offers visitors in its pattern library, to copy into their own sites.', 'windrose-knotboard' ),
				'public'          => true,
				'show_in_rest'    => true,
				'menu_icon'       => 'core/symbol',
				'menu_position'   => 21,
				'supports'        => array( 'title', 'editor', 'excerpt', 'revisions' ),
				'has_archive'     => true,
				'rewrite'         => array(
					'slug'       => self::base(),
					'with_front' => false,
				),
				'capability_type' => array( 'windrose_knotboard_pattern', 'windrose_knotboard_patterns' ),
				'map_meta_cap'    => true,
			)
		);
		$common = array(
			'public'             => false,
			'publicly_queryable' => true,
			'show_ui'            => true,
			'show_in_rest'       => true,
			'show_admin_column'  => true,
			'rewrite'            => false,
			'capabilities'       => array(
				'manage_terms' => self::CAP,
				'edit_terms'   => self::CAP,
				'delete_terms' => self::CAP,
				'assign_terms' => self::CAP,
			),
		);
		register_taxonomy(
			self::KIND,
			self::TYPE,
			array(
				'labels'       => array(
					'name'          => __( 'Kinds', 'windrose-knotboard' ),
					'singular_name' => __( 'Kind', 'windrose-knotboard' ),
				),
				'hierarchical' => true,
				'query_var'    => self::KIND,
				'show_in_menu' => false,
				'capabilities' => array(
					'manage_terms' => 'do_not_allow',
					'edit_terms'   => 'do_not_allow',
					'delete_terms' => 'do_not_allow',
					'assign_terms' => self::CAP,
				),
			) + $common
		);
		register_taxonomy(
			self::CATEGORY,
			self::TYPE,
			array(
				'labels'       => array(
					'name'          => __( 'Pattern categories', 'windrose-knotboard' ),
					'singular_name' => __( 'Pattern category', 'windrose-knotboard' ),
					'menu_name'     => __( 'Categories', 'windrose-knotboard' ),
				),
				'hierarchical' => true,
				'query_var'    => self::CATEGORY,
			) + $common
		);
		register_taxonomy(
			self::TAG,
			self::TYPE,
			array(
				'labels'       => array(
					'name'          => __( 'Pattern tags', 'windrose-knotboard' ),
					'singular_name' => __( 'Pattern tag', 'windrose-knotboard' ),
					'menu_name'     => __( 'Tags', 'windrose-knotboard' ),
				),
				'hierarchical' => false,
				'query_var'    => self::TAG,
			) + $common
		);
		register_taxonomy(
			self::LANGUAGE,
			self::TYPE,
			array(
				'labels'       => array(
					'name'                   => __( 'Pattern languages', 'windrose-knotboard' ),
					'singular_name'          => __( 'Pattern language', 'windrose-knotboard' ),
					'menu_name'              => __( 'Languages', 'windrose-knotboard' ),
					'name_field_description' => __( 'The language’s own name, for example Français.', 'windrose-knotboard' ),
					'slug_field_description' => __( 'The language code, for example fr or pt-br.', 'windrose-knotboard' ),
				),
				'hierarchical' => true,
				'query_var'    => self::LANGUAGE,
			) + $common
		);
		add_rewrite_rule( '^' . preg_quote( self::base(), '#' ) . '/selection/?$', 'index.php?post_type=' . self::TYPE . '&' . self::VIEW . '=selection', 'top' );
	}

	/**
	 * Once per site and version, after every plugin registered its addresses: the four kinds, the site's language,
	 * and the addresses rebuilt (never on every request).
	 *
	 * @return void
	 */
	public static function upgrade(): void {
		if ( WINDROSE_KNOTBOARD_VERSION === get_option( 'windrose_knotboard_version' ) ) {
			return;
		}
		self::seed_kinds();
		self::seed_language();
		flush_rewrite_rules( false );
		update_option( 'windrose_knotboard_version', WINDROSE_KNOTBOARD_VERSION );
	}

	/**
	 * The four kinds, once.
	 *
	 * @return void
	 */
	public static function seed_kinds(): void {
		foreach ( self::kind_labels() as $slug => $label ) {
			if ( ! term_exists( $slug, self::KIND ) ) {
				wp_insert_term( $label, self::KIND, array( 'slug' => $slug ) );
			}
		}
	}

	/**
	 * The site's own language as a language term, if none exists yet.
	 *
	 * @return void
	 */
	public static function seed_language(): void {
		$code = self::code( get_locale() );
		if ( ! term_exists( $code, self::LANGUAGE ) ) {
			wp_insert_term( self::language_name( $code ), self::LANGUAGE, array( 'slug' => $code ) );
		}
	}

	/**
	 * A language code from a WordPress locale: `fr_FR` gives `fr`, `pt_BR` gives `pt-br`, `en_US` gives `en`.
	 *
	 * @param string $locale Locale.
	 * @return string
	 */
	public static function code( string $locale ): string {
		$parts = explode( '_', strtolower( $locale ) );
		$keep  = array( 'pt_br', 'pt_pt', 'zh_cn', 'zh_tw', 'zh_hk' );
		$code  = in_array( implode( '_', array_slice( $parts, 0, 2 ) ), $keep, true ) ? $parts[0] . '-' . ( $parts[1] ?? '' ) : $parts[0];
		return '' !== $code ? sanitize_title( $code ) : 'en';
	}

	/**
	 * A language's own name (with PHP's intl extension), or its code.
	 *
	 * @param string $code Language code.
	 * @return string
	 */
	public static function language_name( string $code ): string {
		if ( class_exists( '\Locale' ) ) {
			$tag  = str_replace( '-', '_', $code );
			$name = \Locale::getDisplayName( $tag, $tag );
			if ( is_string( $name ) && '' !== $name && $name !== $tag ) {
				return mb_convert_case( mb_substr( $name, 0, 1 ), MB_CASE_UPPER ) . mb_substr( $name, 1 );
			}
		}
		return $code;
	}

	/**
	 * Whether a language code is written right to left.
	 *
	 * @param string $code Language code.
	 * @return bool
	 */
	public static function is_rtl( string $code ): bool {
		return in_array( explode( '-', strtolower( $code ) )[0], self::RTL, true );
	}

	/**
	 * Labels of the kinds (by slug, translated; the terms' names are only what the site saved when they were made).
	 *
	 * @return array<string, string>
	 */
	public static function kind_labels(): array {
		return array(
			'header'  => __( 'Header', 'windrose-knotboard' ),
			'footer'  => __( 'Footer', 'windrose-knotboard' ),
			'section' => __( 'Section', 'windrose-knotboard' ),
			'page'    => __( 'Page', 'windrose-knotboard' ),
		);
	}

	/**
	 * Plural labels of the kinds, for filters.
	 *
	 * @return array<string, string>
	 */
	public static function kind_plurals(): array {
		return array(
			'header'  => __( 'Headers', 'windrose-knotboard' ),
			'footer'  => __( 'Footers', 'windrose-knotboard' ),
			'section' => __( 'Sections', 'windrose-knotboard' ),
			'page'    => __( 'Pages', 'windrose-knotboard' ),
		);
	}

	/**
	 * A pattern's kind.
	 *
	 * @param int $id Pattern ID.
	 * @return string
	 */
	public static function kind( int $id ): string {
		$terms = get_the_terms( $id, self::KIND );
		if ( is_array( $terms ) ) {
			foreach ( $terms as $term ) {
				if ( in_array( $term->slug, self::KINDS, true ) ) {
					return $term->slug;
				}
			}
		}
		return 'section';
	}

	/**
	 * A pattern's language code ('' when none).
	 *
	 * @param int $id Pattern ID.
	 * @return string
	 */
	public static function language( int $id ): string {
		$terms = get_the_terms( $id, self::LANGUAGE );
		return is_array( $terms ) && isset( $terms[0] ) && $terms[0] instanceof WP_Term ? $terms[0]->slug : '';
	}

	/**
	 * Exactly one kind per pattern: the first one chosen, or `section`.
	 *
	 * @param int $post_id Pattern ID.
	 * @return void
	 */
	public static function one_kind( int $post_id ): void {
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}
		$kind  = self::kind( $post_id );
		$terms = get_the_terms( $post_id, self::KIND );
		if ( is_array( $terms ) && 1 === count( $terms ) && $terms[0]->slug === $kind ) {
			return;
		}
		wp_set_object_terms( $post_id, $kind, self::KIND );
	}

	/**
	 * Whether a post is an offered pattern: a published library pattern.
	 *
	 * @param WP_Post|null $post Post.
	 * @return bool
	 */
	public static function is_offered( ?WP_Post $post ): bool {
		return $post instanceof WP_Post && self::TYPE === $post->post_type && 'publish' === $post->post_status && '' === $post->post_password;
	}

	/**
	 * Whether the site offers any pattern.
	 *
	 * @return bool
	 */
	public static function has_offered(): bool {
		$counts = wp_count_posts( self::TYPE );
		return isset( $counts->publish ) && (int) $counts->publish > 0;
	}

	/**
	 * What the browser needs about one pattern.
	 *
	 * @param WP_Post $post Pattern.
	 * @return Item
	 */
	public static function item( WP_Post $post ): array {
		$lang = self::language( $post->ID );
		return array(
			'id'    => $post->ID,
			'title' => '' !== $post->post_title ? $post->post_title : __( '(no title)', 'windrose-knotboard' ),
			'kind'  => self::kind( $post->ID ),
			'lang'  => $lang,
			'dir'   => '' !== $lang && self::is_rtl( $lang ) ? 'rtl' : '',
		);
	}

	/**
	 * Query variables of the library's filters and view.
	 *
	 * @param array $vars Public query variables.
	 * @phpstan-param list<string> $vars
	 * @return list<string>
	 */
	public static function query_vars( array $vars ): array {
		$vars[] = self::SEARCH;
		$vars[] = self::VIEW;
		return $vars;
	}

	/**
	 * The library page: 24 at a time, newest first, and the library's own search.
	 *
	 * @param WP_Query $query Query.
	 * @return void
	 */
	public static function query( WP_Query $query ): void {
		if ( is_admin() || ! $query->is_main_query() || ! $query->is_post_type_archive( self::TYPE ) ) {
			return;
		}
		$query->set( 'posts_per_page', self::PER_PAGE );
		// Only offered patterns (is_offered()): not the password-protected ones.
		$query->set( 'has_password', false );
		$search = $query->get( self::SEARCH );
		if ( is_string( $search ) && '' !== trim( $search ) ) {
			$query->set( 's', sanitize_text_field( $search ) );
		}
		if ( 'selection' === $query->get( self::VIEW ) ) {
			$query->set( 'posts_per_page', 1 );
		}
	}

	/**
	 * Grids with their own query (the block pattern, the front page): no password-protected patterns either
	 * (`query_loop_block_query_vars`). Only Query Loops, so Import::find() still finds them.
	 *
	 * @param array<string, mixed> $vars Query variables.
	 * @return array<string, mixed>
	 */
	public static function grid_query( array $vars ): array {
		if ( self::TYPE === ( $vars['post_type'] ?? '' ) ) {
			$vars['has_password'] = false;
		}
		return $vars;
	}

	/**
	 * The address base on Settings, Permalinks (saved with core's form, which then rebuilds the addresses).
	 *
	 * @return void
	 */
	public static function permalink_field(): void {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- Core's Permalinks form: check_admin_referer( 'update-permalink' ) ran before this screen saves.
		if ( isset( $_POST[ self::BASE ] ) && current_user_can( 'manage_options' ) && check_admin_referer( 'update-permalink' ) ) {
			$value = sanitize_title( wp_unslash( (string) $_POST[ self::BASE ] ) );
			update_option( self::BASE, $value );
			self::register();
		}
		// phpcs:enable
		add_settings_field(
			self::BASE,
			__( 'Pattern library base', 'windrose-knotboard' ),
			array( __CLASS__, 'permalink_input' ),
			'permalink',
			'optional',
			array( 'label_for' => self::BASE )
		);
	}

	/**
	 * The field.
	 *
	 * @return void
	 */
	public static function permalink_input(): void {
		printf(
			'<input name="%1$s" id="%1$s" type="text" value="%2$s" class="regular-text code" placeholder="%3$s" /><p class="description">%4$s</p>',
			esc_attr( self::BASE ),
			esc_attr( (string) get_option( self::BASE, '' ) ),
			esc_attr( sanitize_title( _x( 'patterns', 'address base of the pattern library', 'windrose-knotboard' ) ) ),
			esc_html__( 'The library’s address, for example designs. Empty: patterns, in the site’s language (Windrose Knotboard).', 'windrose-knotboard' )
		);
	}

	/**
	 * The library's own capabilities (`edit_windrose_knotboard_patterns` and the others of the post type): granted to
	 * people who may edit theme options on the site; on a network with the switch off, to network administrators only
	 * (they hold every capability anyway).
	 *
	 * @param array<string, bool> $allcaps The user's capabilities.
	 * @param array               $caps    Primitive capabilities asked for.
	 * @phpstan-param list<string> $caps
	 * @return array<string, bool>
	 */
	public static function grant( array $allcaps, array $caps ): array {
		if ( empty( $allcaps['edit_theme_options'] ) ) {
			return $allcaps;
		}
		if ( is_multisite() && ! Network::site_admins() ) {
			return $allcaps;
		}
		foreach ( $caps as $cap ) {
			if ( str_contains( $cap, self::CAP_PREFIX ) ) {
				$allcaps[ $cap ] = true;
			}
		}
		return $allcaps;
	}

	/**
	 * The list's columns: the kind first after the title.
	 *
	 * @param array<string, string> $columns Columns.
	 * @return array<string, string>
	 */
	public static function columns( array $columns ): array {
		$kind = 'taxonomy-' . self::KIND;
		if ( ! isset( $columns[ $kind ] ) ) {
			return $columns;
		}
		$label = $columns[ $kind ];
		unset( $columns[ $kind ] );
		$out = array();
		foreach ( $columns as $key => $value ) {
			$out[ $key ] = $value;
			if ( 'title' === $key ) {
				$out[ $kind ] = $label;
			}
		}
		return $out;
	}
}
