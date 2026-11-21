<?php
/**
 * Plugin Name:       Windrose Knotboard - Pattern Library
 * Plugin URI:        https://www.webpresencecare.com/plugins
 * Description:       A pattern library on your site: visitors filter and search your library patterns, see them in a style of their choice, copy one, or build a selection (header, footer, content) and copy or download it for their own site. Built from templates you can change in the Site Editor.
 * Version:           0.2.4
 * Requires at least: 7.2
 * Requires PHP:      8.4
 * Requires Plugins:  windrose-livery
 * Author:            Patricia BT
 * Author URI:        https://patriciabt.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       windrose-knotboard
 * Domain Path:       /languages
 * Network:           true
 * Update URI:        https://github.com/webpresencecare/windrose-knotboard
 *
 * @package Windrose\Knotboard
 */

declare( strict_types=1 );

namespace Windrose\Knotboard;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'WINDROSE_KNOTBOARD_VERSION', '0.2.4' );
define( 'WINDROSE_KNOTBOARD_FILE', __FILE__ );
define( 'WINDROSE_KNOTBOARD_DIR', __DIR__ );

spl_autoload_register(
	static function ( string $class_name ): void {
		$prefix = __NAMESPACE__ . '\\';
		if ( ! str_starts_with( $class_name, $prefix ) ) {
			return;
		}
		$name = substr( $class_name, strlen( $prefix ) );
		$file = __DIR__ . '/includes/class-' . strtolower( str_replace( '_', '-', $name ) ) . '.php';
		if ( ! str_contains( $name, '\\' ) && is_readable( $file ) ) {
			require_once $file;
		}
	}
);

require_once __DIR__ . '/common/bootstrap.php';

add_action( 'plugins_loaded', array( Plugin::class, 'boot' ) );
