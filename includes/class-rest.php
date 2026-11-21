<?php
/**
 * REST routes (`windrose-knotboard/v1`), all public and read only: what a library page shows anyone.
 *
 * - `GET /copy?ids=1,2,3`: the offered (published) library patterns among them, in that order, with their block markup
 *   ready to paste (`Copy::for_visitors()`: pictures kept, detached from this site's Media Library), for the selection's Copy and Download buttons.
 * - `GET /styles` and `GET /styles/<slug>`: Windrose Livery's ready styles, and one as the recipe Livery's preview
 *   takes (`\Windrose\Livery\Catalog::ready_recipes()`).
 *
 * @package Windrose\Knotboard
 */

declare( strict_types=1 );

namespace Windrose\Knotboard;

use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Routes.
 */
final class Rest {

	public const NAMESPACE = 'windrose-knotboard/v1';

	/** At most this many patterns in one copy. */
	public const MAX_COPY = 60;

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public static function init(): void {
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
	}

	/**
	 * Registers the routes.
	 *
	 * @return void
	 */
	public static function routes(): void {
		register_rest_route(
			self::NAMESPACE,
			'/copy',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'copy' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'ids' => array(
						'type'              => 'string',
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);
		register_rest_route(
			self::NAMESPACE,
			'/styles',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'styles' ),
				'permission_callback' => '__return_true',
			)
		);
		register_rest_route(
			self::NAMESPACE,
			'/styles/(?P<slug>[a-z0-9-]{1,60})',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'style' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * Pattern IDs from a comma-separated list: positive integers, at most MAX_COPY, order kept, duplicates removed.
	 *
	 * @param string $value List.
	 * @return list<int>
	 */
	public static function ids( string $value ): array {
		$ids = array();
		foreach ( explode( ',', $value ) as $part ) {
			$part = trim( $part );
			if ( '' !== $part && ctype_digit( $part ) && (int) $part > 0 ) {
				$ids[ (int) $part ] = true;
			}
		}
		return array_slice( array_keys( $ids ), 0, self::MAX_COPY );
	}

	/**
	 * GET /copy: offered patterns only, in the order asked, ready to paste.
	 *
	 * @param WP_REST_Request $request Request.
	 * @phpstan-param WP_REST_Request<array<string, mixed>> $request
	 * @return WP_REST_Response|WP_Error
	 */
	public static function copy( WP_REST_Request $request ) {
		$ids   = self::ids( (string) $request->get_param( 'ids' ) );
		$posts = array() === $ids ? array() : get_posts(
			array(
				'post_type'      => Content::TYPE,
				'post__in'       => $ids,
				'orderby'        => 'post__in',
				'posts_per_page' => self::MAX_COPY,
			)
		);
		$list  = array();
		foreach ( $posts as $post ) {
			if ( ! Content::is_offered( $post ) ) {
				continue;
			}
			$list[] = Content::item( $post ) + array(
				'content'  => Copy::for_visitors( $post->post_content ),
				'pictures' => Copy::has_pictures( $post->post_content ),
			);
		}
		if ( array() === $list ) {
			return new WP_Error( 'windrose_knotboard_none', __( 'These patterns are not offered here.', 'windrose-knotboard' ), array( 'status' => 404 ) );
		}
		$response = new WP_REST_Response( array( 'patterns' => $list ) );
		$response->header( 'Cache-Control', 'no-cache' );
		return $response;
	}

	/**
	 * GET /styles: Livery's ready styles (no recipes).
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public static function styles() {
		if ( ! Plugin::has_livery() ) {
			return self::no_livery();
		}
		$list = array();
		foreach ( \Windrose\Livery\Catalog::ready_recipes() as $item ) {
			$list[] = array(
				'slug'        => $item['slug'],
				'title'       => $item['title'],
				'description' => $item['description'],
			);
		}
		$response = new WP_REST_Response( array( 'styles' => $list ) );
		$response->header( 'Cache-Control', 'public, max-age=3600' );
		return $response;
	}

	/**
	 * GET /styles/<slug>: one ready style as Livery's recipe.
	 *
	 * @param WP_REST_Request $request Request.
	 * @phpstan-param WP_REST_Request<array<string, mixed>> $request
	 * @return WP_REST_Response|WP_Error
	 */
	public static function style( WP_REST_Request $request ) {
		if ( ! Plugin::has_livery() ) {
			return self::no_livery();
		}
		$slug = (string) $request->get_param( 'slug' );
		foreach ( \Windrose\Livery\Catalog::ready_recipes() as $item ) {
			if ( $item['slug'] === $slug ) {
				$response = new WP_REST_Response( $item );
				$response->header( 'Cache-Control', 'public, max-age=3600' );
				return $response;
			}
		}
		return new WP_Error( 'windrose_knotboard_style', __( 'This style does not exist.', 'windrose-knotboard' ), array( 'status' => 404 ) );
	}

	/**
	 * The error without Livery.
	 *
	 * @return WP_Error
	 */
	private static function no_livery(): WP_Error {
		return new WP_Error( 'windrose_knotboard_livery', __( 'The pattern library needs Windrose Livery 0.7 or later.', 'windrose-knotboard' ), array( 'status' => 503 ) );
	}
}
