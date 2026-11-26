<?php
/**
 * The library's patterns: post type and taxonomies, one kind each, languages, rights (the network switch), the
 * address base, the library page's query.
 *
 * @package Windrose\Knotboard
 */

use Windrose\Knotboard\Content;
use Windrose\Knotboard\Network;
use Windrose\Knotboard\Plugin;

/**
 * @covers \Windrose\Knotboard\Content
 * @covers \Windrose\Knotboard\Network
 */
class ContentTest extends Knotboard_TestCase {

	public function tear_down(): void {
		delete_option( Content::BASE );
		parent::tear_down();
	}

	public function test_post_type_and_taxonomies_apart_from_the_sites_own_patterns(): void {
		$type = get_post_type_object( Content::TYPE );
		$this->assertNotNull( $type );
		$this->assertTrue( $type->public );
		$this->assertTrue( $type->has_archive );
		$this->assertSame( 'patterns', $type->rewrite['slug'] );
		// The menu icon is a core icon (drawn in the admin color scheme), not a Dashicon.
		$this->assertSame( 'core/symbol', $type->menu_icon );
		$this->assertTrue( WP_Icons_Registry::get_instance()->is_registered( $type->menu_icon ) );
		foreach ( array( Content::KIND, Content::CATEGORY, Content::TAG, Content::LANGUAGE ) as $taxonomy ) {
			$this->assertTrue( is_object_in_taxonomy( Content::TYPE, $taxonomy ), $taxonomy );
		}
		$this->assertSame( Content::KINDS, array_keys( Content::kind_labels() ) );
		foreach ( Content::KINDS as $kind ) {
			$this->assertNotNull( term_exists( $kind, Content::KIND ) );
		}
		// Library patterns never reach the site's inserter or its Site Editor patterns (core's wp_block).
		$id = $this->pattern( 'Hero' );
		$this->assertSame( Content::TYPE, get_post_type( $id ) );
		$this->assertSame( array(), get_posts( array( 'post_type' => 'wp_block' ) ) );
	}

	public function test_deactivation_removes_the_addresses_and_the_version_mark_on_every_site(): void {
		$sites = array( get_current_blog_id(), self::factory()->blog->create() );
		foreach ( $sites as $site ) {
			switch_to_blog( $site );
			update_option( 'windrose_knotboard_version', WINDROSE_KNOTBOARD_VERSION );
			update_option( 'rewrite_rules', array( '^patterns/selection/?$' => 'index.php' ) );
			restore_current_blog();
		}
		Plugin::deactivate( true );
		foreach ( $sites as $site ) {
			$this->assertFalse( get_blog_option( $site, 'windrose_knotboard_version' ), "Version mark on site $site." );
			$this->assertFalse( get_blog_option( $site, 'rewrite_rules' ), "Stored addresses on site $site." );
		}
		// Activating again rebuilds the addresses once.
		Content::upgrade();
		$this->assertSame( WINDROSE_KNOTBOARD_VERSION, get_option( 'windrose_knotboard_version' ) );
		wp_delete_site( $sites[1] );
	}

	public function test_exactly_one_kind(): void {
		$id = self::factory()->post->create( array( 'post_type' => Content::TYPE ) );
		$this->assertSame( 'section', Content::kind( $id ) );
		$this->assertSame( array( 'section' ), wp_get_object_terms( $id, Content::KIND, array( 'fields' => 'slugs' ) ) );
		wp_set_object_terms( $id, array( 'footer', 'header' ), Content::KIND );
		wp_update_post( array( 'ID' => $id ) );
		$this->assertCount( 1, wp_get_object_terms( $id, Content::KIND ) );
	}

	public function test_languages(): void {
		$this->assertSame( 'fr', Content::code( 'fr_FR' ) );
		$this->assertSame( 'en', Content::code( 'en_GB' ) );
		$this->assertSame( 'pt-br', Content::code( 'pt_BR' ) );
		$this->assertSame( 'zh-tw', Content::code( 'zh_TW' ) );
		$this->assertSame( 'ar', Content::code( 'ar' ) );
		foreach ( array( 'ar', 'fa', 'ur', 'he', 'ps' ) as $rtl ) {
			$this->assertTrue( Content::is_rtl( $rtl ), $rtl );
		}
		$this->assertFalse( Content::is_rtl( 'fr' ) );
		$id   = $this->pattern( 'Hours', '', 'section', 'publish', 'ar' );
		$item = Content::item( get_post( $id ) );
		$this->assertSame( 'ar', $item['lang'] );
		$this->assertSame( 'rtl', $item['dir'] );
		Content::seed_language();
		$this->assertNotNull( term_exists( Content::code( get_locale() ), Content::LANGUAGE ) );
		if ( class_exists( 'Locale' ) ) {
			$this->assertSame( 'Français', Content::language_name( 'fr' ) );
		}
	}

	public function test_rights_follow_theme_options_and_the_network_switch(): void {
		$id = $this->pattern( 'Hero' );
		wp_set_current_user( self::$admin );
		$this->assertTrue( current_user_can( 'edit_post', $id ) );
		$this->assertTrue( current_user_can( get_post_type_object( Content::TYPE )->cap->create_posts ) );
		$this->assertTrue( current_user_can( Content::CAP ) );

		update_site_option( Network::OPTION, array( 'site_admins' => false ) );
		$this->assertFalse( current_user_can( 'edit_post', $id ) );
		$this->assertFalse( current_user_can( get_post_type_object( Content::TYPE )->cap->create_posts ) );
		$this->assertFalse( current_user_can( Content::CAP ) );
		$this->assertTrue( current_user_can( 'edit_theme_options' ), 'Only the library is concerned: theme options stay.' );

		wp_set_current_user( self::$super );
		$this->assertTrue( current_user_can( 'edit_post', $id ) );

		$editor = self::factory()->user->create( array( 'role' => 'editor' ) );
		wp_set_current_user( $editor );
		delete_site_option( Network::OPTION );
		$this->assertFalse( current_user_can( 'edit_post', $id ), 'Editors never manage the library.' );
		wp_set_current_user( 0 );
		$this->assertTrue( Content::is_offered( get_post( $id ) ), 'Visitors see published patterns (what the blocks check).' );
	}

	public function test_network_settings_field(): void {
		ob_start();
		Network::field();
		$html = (string) ob_get_clean();
		$this->assertStringContainsString( 'name="' . Network::FIELD . '"', $html );
		$this->assertStringContainsString( 'checked', $html );

		$_POST = array();
		Network::save();
		$this->assertFalse( Network::site_admins() );
		$_POST = array( Network::FIELD => '1' );
		Network::save();
		$this->assertTrue( Network::site_admins() );

		wp_set_current_user( self::$admin );
		$_POST = array();
		Network::save();
		$this->assertTrue( Network::site_admins(), 'Only people who manage network options change it.' );
		$_POST = array();
	}

	public function test_address_base_library_page_and_selection(): void {
		$this->set_permalink_structure( '/%postname%/' );
		update_option( Content::BASE, 'Designs' );
		Content::register();
		flush_rewrite_rules( false );
		$this->assertSame( 'designs', Content::base() );
		$this->assertStringEndsWith( '/designs/', Content::library_url() );
		$this->assertStringEndsWith( '/designs/selection/', Content::selection_url() );

		for ( $i = 0; $i < 26; $i++ ) {
			$this->pattern( 'Pattern ' . $i );
		}
		$this->pattern( 'Opening hours with a table' );
		$this->go_to( Content::library_url() );
		$this->assertTrue( is_post_type_archive( Content::TYPE ) );
		$this->assertCount( Content::PER_PAGE, $GLOBALS['wp_query']->posts );

		$this->go_to( add_query_arg( Content::SEARCH, 'table', Content::library_url() ) );
		$this->assertFalse( is_search(), 'The library keeps its own page while searching.' );
		$this->assertSame( array( 'Opening hours with a table' ), wp_list_pluck( $GLOBALS['wp_query']->posts, 'post_title' ) );

		$this->go_to( Content::selection_url() );
		$this->assertSame( 'selection', get_query_var( Content::VIEW ) );
		$this->assertSame( array( 'windrose-knotboard-selection', 'archive' ), \Windrose\Knotboard\Templates::selection_hierarchy( array( 'archive' ) ) );

		delete_option( Content::BASE );
		Content::register();
		$this->assertSame( 'patterns', Content::base() );
	}
}
