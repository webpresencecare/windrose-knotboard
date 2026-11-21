<?php
/**
 * Uninstall: removes Knotboard's settings on every site (the address base, the version mark) and the network switch.
 * The library patterns and their categories, tags, languages and kinds stay in the database as the site's content,
 * as WordPress keeps posts of any plugin; reinstalling Knotboard shows them again.
 *
 * @package Windrose\Knotboard
 */

declare( strict_types=1 );

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$windrose_knotboard_clean = static function (): void {
	delete_option( 'windrose_knotboard_base' );
	delete_option( 'windrose_knotboard_version' );
};

if ( is_multisite() ) {
	foreach ( get_sites(
		array(
			'fields' => 'ids',
			'number' => 0,
		)
	) as $windrose_knotboard_site ) {
		switch_to_blog( (int) $windrose_knotboard_site );
		$windrose_knotboard_clean();
		restore_current_blog();
	}
	delete_site_option( 'windrose_knotboard_network' );
} else {
	$windrose_knotboard_clean();
}
