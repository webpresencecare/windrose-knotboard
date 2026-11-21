<?php
/**
 * WP-CLI: `wp windrose-knotboard import <file>... [--run] [--removed=<published|draft>]`. A dry run unless
 * `--run` is given (as Windrose Livery's commands). On a network, `--url` names the library's site.
 *
 * @package Windrose\Knotboard
 */

declare( strict_types=1 );

namespace Windrose\Knotboard;

use WP_CLI;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The command.
 *
 * @phpstan-import-type Pack from Package
 * @phpstan-import-type Row from Import
 */
final class Cli {

	/**
	 * Registers the command when WP-CLI runs.
	 *
	 * @return void
	 */
	public static function init(): void {
		if ( defined( 'WP_CLI' ) && WP_CLI && class_exists( WP_CLI::class ) ) {
			WP_CLI::add_command( 'windrose-knotboard import', array( __CLASS__, 'import' ) );
		}
	}

	/**
	 * Imports pattern library packages (zip) into this site's library. Shows what would change unless --run is given.
	 *
	 * ## OPTIONS
	 *
	 * <file>...
	 * : One or more packages, for example musician-fr.zip.
	 *
	 * [--run]
	 * : Import. Without it, nothing is written (dry run).
	 *
	 * [--removed=<what>]
	 * : Patterns of the same pack and language no longer in the package: published (they stay) or draft.
	 * ---
	 * options:
	 *   - published
	 *   - draft
	 * ---
	 *
	 * [--format=<format>]
	 * : Output.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - json
	 *   - csv
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     wp windrose-knotboard import musician-en.zip --url=example.com/patterns/
	 *     wp windrose-knotboard import *.zip --run --removed=draft --url=example.com/patterns/
	 *
	 * @param array                $args  Files.
	 * @phpstan-param list<string> $args
	 * @param array<string, mixed> $assoc Options.
	 * @return void
	 */
	public static function import( array $args, array $assoc ): void {
		$packs = array();
		foreach ( $args as $file ) {
			$pack = Package::read( $file );
			if ( $pack instanceof WP_Error ) {
				WP_CLI::error( $pack->get_error_message() );
			}
			$packs[] = $pack;
		}
		$format = is_string( $assoc['format'] ?? null ) ? $assoc['format'] : 'table';
		if ( empty( $assoc['run'] ) ) {
			$rows = Import::plan( $packs );
			self::table( $rows, $format );
			if ( 'table' === $format ) {
				WP_CLI::log( 'Dry run: nothing was written. Add --run to import.' );
				if ( array() !== array_filter( $rows, static fn( array $row ): bool => 'removed' === $row['status'] ) ) {
					WP_CLI::log( 'Some patterns are no longer in their pack: add --removed=published or --removed=draft.' );
				}
			}
			return;
		}
		$removed = is_string( $assoc['removed'] ?? null ) ? $assoc['removed'] : '';
		$result  = Import::run( $packs, $removed );
		if ( $result instanceof WP_Error ) {
			WP_CLI::error( $result->get_error_message() );
		}
		self::table( $result['rows'], $format );
		$summary = sprintf(
			'%d created, %d updated, %d unchanged, %d moved to drafts, %d refused; %d pictures added to the Media Library.',
			$result['created'],
			$result['updated'],
			$result['unchanged'],
			$result['drafted'],
			$result['failed'],
			$result['pictures']
		);
		$result['failed'] > 0 ? WP_CLI::warning( $summary ) : WP_CLI::success( $summary );
	}

	/**
	 * Prints rows.
	 *
	 * @param array  $rows   Rows.
	 * @phpstan-param list<Row> $rows
	 * @param string $format Format.
	 * @return void
	 */
	private static function table( array $rows, string $format ): void {
		$labels = Import::labels();
		$items  = array();
		foreach ( $rows as $row ) {
			$items[] = array(
				'pack'     => $row['pack'],
				'language' => $row['language'],
				'key'      => $row['key'],
				'title'    => $row['title'],
				'status'   => 'table' === $format ? ( $labels[ $row['status'] ] ?? $row['status'] ) : $row['status'],
				'id'       => $row['id'],
				'problem'  => $row['problem'],
			);
		}
		WP_CLI\Utils\format_items( $format, $items, array( 'pack', 'language', 'key', 'title', 'status', 'id', 'problem' ) );
	}
}
