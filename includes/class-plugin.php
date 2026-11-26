<?php
/**
 * Windrose Knotboard: start-up.
 *
 * @package Windrose\Knotboard
 */

declare( strict_types=1 );

namespace Windrose\Knotboard;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Boots the plugin.
 */
final class Plugin {

	/**
	 * Hooks everything (on plugins_loaded). Works on a single site and on a network.
	 *
	 * @return void
	 */
	public static function boot(): void {
		add_action( 'init', array( __CLASS__, 'load_textdomain' ) );
		add_action( 'admin_init', array( __CLASS__, 'privacy_policy_content' ) );
		Network::init();
		Content::init();
		Templates::init();
		Blocks::init();
		Render::init();
		Rest::init();
		Cli::init();
		if ( is_admin() ) {
			Importer::init();
		}
	}

	/**
	 * On deactivation, on every site (or this one on a single site): the library's addresses go from the rewrite rules
	 * (WordPress rebuilds them on the next request, without Knotboard's), and the version mark goes, so activating
	 * again rebuilds the addresses (Content::upgrade()). Deleting the stored rules, not flushing them: the post type is
	 * still registered during deactivation, and a flush would write its rules back.
	 *
	 * @param bool $network_wide Whether the plugin is deactivated for the whole network.
	 * @return void
	 */
	public static function deactivate( bool $network_wide = false ): void {
		$sites = is_multisite() && $network_wide ? get_sites(
			array(
				'fields' => 'ids',
				'number' => 0,
			)
		) : array( get_current_blog_id() );
		foreach ( $sites as $site ) {
			if ( is_multisite() ) {
				switch_to_blog( (int) $site );
			}
			delete_option( 'rewrite_rules' );
			delete_option( 'windrose_knotboard_version' );
			if ( is_multisite() ) {
				restore_current_blog();
			}
		}
	}

	/**
	 * Whether Windrose Livery (which draws the previews) is there.
	 *
	 * @return bool
	 */
	public static function has_livery(): bool {
		// Livery 0.7 or later: its preview takes other plugins' patterns, its catalog has the ready styles as recipes.
		return defined( 'WINDROSE_LIVERY_VERSION' ) && version_compare( (string) constant( 'WINDROSE_LIVERY_VERSION' ), '0.7.0', '>=' );
	}

	/**
	 * Loads translations.
	 *
	 * @return void
	 */
	public static function load_textdomain(): void {
		load_plugin_textdomain( 'windrose-knotboard', false, dirname( plugin_basename( WINDROSE_KNOTBOARD_FILE ) ) . '/languages' );
	}

	/**
	 * Suggested text for each site's privacy policy (Settings, Privacy, Policy guide).
	 *
	 * @return void
	 */
	public static function privacy_policy_content(): void {
		if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) {
			return;
		}
		$text  = '<p>' . esc_html__( 'Where this website offers its pattern library, the patterns a visitor chooses and the style they pick for the previews are kept in their own browser (local storage), so they are still there on their next visit. This is not a cookie and it is never stored on this website.', 'windrose-knotboard' ) . '</p>';
		$text .= '<p>' . esc_html__( 'To show the chosen patterns in a style, their list and the chosen style are sent to this website, which draws the preview with sample text and stores nothing. To limit how often one connection can ask for previews, a keyed hash of the visitor’s IP address (not the address itself) and a counter are kept for one hour (Windrose Livery). Copying and downloading happen in the visitor’s browser.', 'windrose-knotboard' ) . '</p>';
		$text .= '<p>' . esc_html__( 'Copied patterns keep their pictures at this website’s address until the visitor uploads them into their own site’s Media Library: until then, their site and the browsers showing their pages load these pictures from this website, which receives the usual request details (IP address, browser).', 'windrose-knotboard' ) . '</p>';
		wp_add_privacy_policy_content( __( 'Windrose Knotboard', 'windrose-knotboard' ), wp_kses_post( $text ) );
	}
}
