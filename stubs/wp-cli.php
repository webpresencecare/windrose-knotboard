<?php
/**
 * The WP-CLI methods Windrose Knotboard calls (class-cli.php), for PHPStan.
 *
 * @package Windrose\Knotboard
 */

namespace {
	class WP_CLI {
		/**
		 * @param string               $name     Command name.
		 * @param callable|string      $callable Command class.
		 * @param array<string, mixed> $args     Options.
		 */
		public static function add_command( $name, $callable, $args = array() ): bool {}
		public static function success( string $message ): void {}
		public static function warning( string $message ): void {}
		public static function log( string $message ): void {}
		public static function line( string $message = '' ): void {}
		/**
		 * @return never
		 */
		public static function error( string $message, bool $exit = true ): void {}
	}
}

namespace WP_CLI\Utils {
	/**
	 * @param string                    $format Format.
	 * @param array<int, array<string, mixed>> $items  Rows.
	 * @param array<int, string>|string $fields Fields.
	 */
	function format_items( $format, $items, $fields ): void {}
}
