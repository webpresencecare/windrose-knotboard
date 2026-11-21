<?php
/**
 * The import screen, through core's own importer list: Tools, Import, "Pattern library package"
 * (`register_importer()`), also linked as Pattern Library, Import. Three steps: upload one or more packages (kept as
 * core keeps import files: a private attachment, deleted after the import or by core a day later), the dry run in a
 * table with the choice for patterns no longer in their pack, then the import and its results.
 *
 * @package Windrose\Knotboard
 */

declare( strict_types=1 );

namespace Windrose\Knotboard;

use WP_Error;
use WP_Post;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Tools, Import, Pattern library package.
 *
 * @phpstan-import-type Pack from Package
 * @phpstan-import-type Row from Import
 */
final class Importer {

	public const ID    = 'windrose-knotboard';
	public const FIELD = 'windrose_knotboard_packages';
	public const NONCE = 'windrose_knotboard_import';

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public static function init(): void {
		add_action( 'admin_init', array( __CLASS__, 'register' ) );
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
	}

	/**
	 * The importer in core's list.
	 *
	 * @return void
	 */
	public static function register(): void {
		if ( ! function_exists( 'register_importer' ) ) {
			require_once ABSPATH . 'wp-admin/includes/import.php';
		}
		register_importer(
			self::ID,
			__( 'Pattern library package', 'windrose-knotboard' ),
			__( 'Patterns for the Pattern Library from a package (zip), with their pictures, kinds, categories, tags and language (Windrose Knotboard).', 'windrose-knotboard' ),
			array( __CLASS__, 'page' )
		);
	}

	/**
	 * Pattern Library, Import.
	 *
	 * @return void
	 */
	public static function menu(): void {
		add_submenu_page( 'edit.php?post_type=' . Content::TYPE, __( 'Import into the Pattern Library', 'windrose-knotboard' ), __( 'Import', 'windrose-knotboard' ), 'import', 'admin.php?import=' . self::ID );
	}

	/**
	 * This screen's address.
	 *
	 * @return string
	 */
	public static function url(): string {
		return admin_url( 'admin.php?import=' . self::ID );
	}

	/**
	 * The screen.
	 *
	 * @return void
	 */
	public static function page(): void {
		echo '<div class="wrap"><h1>' . esc_html__( 'Import into the Pattern Library', 'windrose-knotboard' ) . '</h1>';
		if ( ! Import::allowed() ) {
			echo '<p>' . esc_html__( 'You are not allowed to import patterns into the Pattern Library on this site.', 'windrose-knotboard' ) . '</p></div>';
			return;
		}
		$step = isset( $_POST['windrose_knotboard_step'] ) ? sanitize_key( wp_unslash( $_POST['windrose_knotboard_step'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- checked in each step.
		if ( 'check' === $step ) {
			check_admin_referer( self::NONCE );
			self::check();
		} elseif ( 'import' === $step ) {
			check_admin_referer( self::NONCE );
			self::import();
		} else {
			self::upload_form();
		}
		echo '</div>';
	}

	/**
	 * Step 1: the upload form.
	 *
	 * @return void
	 */
	private static function upload_form(): void {
		echo '<p>' . esc_html__( 'Choose one or more pattern library packages (zip files, one pack in one language each). Nothing changes yet: the next step shows what the import would do.', 'windrose-knotboard' ) . '</p>';
		printf( '<form method="post" enctype="multipart/form-data" action="%s">', esc_url( self::url() ) );
		wp_nonce_field( self::NONCE );
		echo '<input type="hidden" name="windrose_knotboard_step" value="check">';
		printf(
			'<p><label for="windrose-knotboard-packages">%1$s</label><br><input type="file" id="windrose-knotboard-packages" name="%2$s[]" accept=".zip,application/zip" multiple required></p>',
			esc_html__( 'Packages', 'windrose-knotboard' ),
			esc_attr( self::FIELD )
		);
		/* translators: %s: largest file size, for example 64 MB */
		echo '<p class="description">' . esc_html( sprintf( __( 'At most %s per package.', 'windrose-knotboard' ), size_format( min( Package::MAX_ZIP, wp_max_upload_size() ) ) ) ) . '</p>';
		submit_button( __( 'Upload and check', 'windrose-knotboard' ) );
		echo '</form>';
	}

	/**
	 * Step 2: keeps the uploads, reads them, shows the dry run.
	 *
	 * @return void
	 */
	private static function check(): void {
		$ids    = array();
		$errors = array();
		foreach ( self::files() as $file ) {
			$kept                                 = self::keep( $file );
			$kept instanceof WP_Error ? $errors[] = $kept->get_error_message() : $ids[] = $kept;
		}
		[ $packs, $read_errors, $ids ] = self::read( $ids );
		$errors                        = array_merge( $errors, $read_errors );
		foreach ( $errors as $error ) {
			echo '<div class="notice notice-error"><p>' . esc_html( $error ) . '</p></div>';
		}
		if ( array() === $packs ) {
			self::forget( $ids );
			echo '<p><a href="' . esc_url( self::url() ) . '">' . esc_html__( 'Choose other packages', 'windrose-knotboard' ) . '</a></p>';
			return;
		}
		$rows = Import::plan( $packs );
		echo '<p>' . esc_html__( 'Dry run: nothing has changed yet. This is what the import will do.', 'windrose-knotboard' ) . '</p>';
		self::table( $rows );
		printf( '<form method="post" action="%s">', esc_url( self::url() ) );
		wp_nonce_field( self::NONCE );
		echo '<input type="hidden" name="windrose_knotboard_step" value="import">';
		foreach ( $ids as $id ) {
			printf( '<input type="hidden" name="windrose_knotboard_files[]" value="%d">', (int) $id );
		}
		$removed = count( array_filter( $rows, static fn( array $row ): bool => 'removed' === $row['status'] ) );
		if ( $removed > 0 ) {
			echo '<fieldset class="windrose-knotboard-removed"><legend><strong>';
			/* translators: %d: number of patterns */
			echo esc_html( sprintf( _n( '%d published pattern is no longer in its pack. What should happen to it?', '%d published patterns are no longer in their pack. What should happen to them?', $removed, 'windrose-knotboard' ), $removed ) );
			echo '</strong></legend>';
			printf( '<p><label><input type="radio" name="windrose_knotboard_removed" value="%1$s" required> %2$s</label></p>', esc_attr( Import::KEEP ), esc_html__( 'They stay published', 'windrose-knotboard' ) );
			printf( '<p><label><input type="radio" name="windrose_knotboard_removed" value="%1$s" required> %2$s</label></p>', esc_attr( Import::DRAFT ), esc_html__( 'They move to drafts (no longer offered; their revisions are kept)', 'windrose-knotboard' ) );
			echo '</fieldset>';
		}
		submit_button( __( 'Import', 'windrose-knotboard' ) );
		echo '</form>';
		echo '<p><a href="' . esc_url( self::url() ) . '">' . esc_html__( 'Cancel and choose other packages', 'windrose-knotboard' ) . '</a></p>';
	}

	/**
	 * Step 3: the import, its results, the uploads deleted.
	 *
	 * @return void
	 */
	private static function import(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- checked in page().
		$ids                      = isset( $_POST['windrose_knotboard_files'] ) && is_array( $_POST['windrose_knotboard_files'] ) ? array_values( array_map( 'intval', wp_unslash( $_POST['windrose_knotboard_files'] ) ) ) : array();
		$removed                  = isset( $_POST['windrose_knotboard_removed'] ) ? sanitize_key( wp_unslash( $_POST['windrose_knotboard_removed'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- checked in page().
		[ $packs, $errors, $ids ] = self::read( $ids );
		foreach ( $errors as $error ) {
			echo '<div class="notice notice-error"><p>' . esc_html( $error ) . '</p></div>';
		}
		$result = array() !== $packs ? Import::run( $packs, $removed ) : new WP_Error( 'windrose_knotboard_none', __( 'No package to import: choose them again.', 'windrose-knotboard' ) );
		self::forget( $ids );
		if ( $result instanceof WP_Error ) {
			echo '<div class="notice notice-error"><p>' . esc_html( $result->get_error_message() ) . '</p></div>';
			echo '<p><a href="' . esc_url( self::url() ) . '">' . esc_html__( 'Choose the packages again', 'windrose-knotboard' ) . '</a></p>';
			return;
		}
		$summary = sprintf(
			/* translators: 1: created, 2: updated, 3: unchanged, 4: moved to drafts, 5: refused, 6: pictures added */
			__( 'Done: %1$d created, %2$d updated, %3$d unchanged, %4$d moved to drafts, %5$d refused; %6$d pictures added to the Media Library.', 'windrose-knotboard' ),
			$result['created'],
			$result['updated'],
			$result['unchanged'],
			$result['drafted'],
			$result['failed'],
			$result['pictures']
		);
		printf( '<div class="notice notice-%1$s"><p>%2$s</p></div>', $result['failed'] > 0 ? 'warning' : 'success', esc_html( $summary ) );
		self::table( $result['rows'] );
		printf(
			'<p><a class="button button-primary" href="%1$s">%2$s</a> <a class="button" href="%3$s">%4$s</a></p>',
			esc_url( admin_url( 'edit.php?post_type=' . Content::TYPE ) ),
			esc_html__( 'Open the Pattern Library', 'windrose-knotboard' ),
			esc_url( Content::library_url() ),
			esc_html__( 'View the library', 'windrose-knotboard' )
		);
	}

	/**
	 * The uploaded files, one array each (PHP gives a list of fields).
	 *
	 * @return list<array{name: string, type: string, tmp_name: string, error: int, size: int}>
	 */
	private static function files(): array {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- nonce checked in page(); each file is checked by core's upload and by Package::read().
		$field = isset( $_FILES[ self::FIELD ] ) && is_array( $_FILES[ self::FIELD ] ) ? $_FILES[ self::FIELD ] : array();
		$out   = array();
		if ( ! is_array( $field['name'] ?? null ) ) {
			return $out;
		}
		foreach ( array_keys( $field['name'] ) as $i ) {
			$out[] = array(
				'name'     => sanitize_file_name( (string) ( $field['name'][ $i ] ?? '' ) ),
				'type'     => sanitize_mime_type( (string) ( $field['type'][ $i ] ?? '' ) ),
				'tmp_name' => (string) ( $field['tmp_name'][ $i ] ?? '' ),
				'error'    => (int) ( $field['error'][ $i ] ?? UPLOAD_ERR_NO_FILE ),
				'size'     => (int) ( $field['size'][ $i ] ?? 0 ),
			);
		}
		return $out;
	}

	/**
	 * Keeps one upload as core keeps import files: in the uploads folder, as a private attachment marked as an import,
	 * deleted by core a day later if the import is not finished.
	 *
	 * @param array{name: string, type: string, tmp_name: string, error: int, size: int} $file Upload.
	 * @return int|WP_Error Attachment ID.
	 */
	private static function keep( array $file ) {
		require_once ABSPATH . 'wp-admin/includes/file.php';
		if ( UPLOAD_ERR_OK !== $file['error'] || $file['size'] > Package::MAX_ZIP || ! str_ends_with( strtolower( $file['name'] ), '.zip' ) ) {
			/* translators: %s: file name */
			return new WP_Error( 'windrose_knotboard_upload', sprintf( __( '%s: not uploaded (a zip file of at most 64 MB is needed).', 'windrose-knotboard' ), $file['name'] ) );
		}
		// Core's upload takes the array by reference: give it a copy, keep the name for messages.
		$upload = $file;
		$moved  = wp_handle_upload(
			$upload,
			array(
				'test_form' => false,
				'mimes'     => array( 'zip' => 'application/zip' ),
			)
		);
		if ( isset( $moved['error'] ) || ! isset( $moved['file'], $moved['url'], $moved['type'] ) ) {
			/* translators: 1: file name, 2: reason */
			return new WP_Error( 'windrose_knotboard_upload', sprintf( __( '%1$s: %2$s', 'windrose-knotboard' ), $file['name'], (string) ( $moved['error'] ?? '' ) ) );
		}
		$id = wp_insert_attachment(
			array(
				'post_title'     => wp_basename( $moved['file'] ),
				'post_content'   => $moved['url'],
				'post_mime_type' => $moved['type'],
				'guid'           => $moved['url'],
				'context'        => 'import',
				'post_status'    => 'private',
			),
			$moved['file'],
			0,
			true
		);
		if ( $id instanceof WP_Error ) {
			wp_delete_file( $moved['file'] );
			return $id;
		}
		wp_schedule_single_event( time() + DAY_IN_SECONDS, 'importer_scheduled_cleanup', array( $id ) );
		return $id;
	}

	/**
	 * Reads kept uploads: only this person's import files.
	 *
	 * @param array $ids Attachment IDs.
	 * @phpstan-param list<int> $ids
	 * @return array{0: list<Pack>, 1: list<string>, 2: list<int>} Packages, errors, the IDs that are import files.
	 */
	private static function read( array $ids ): array {
		$packs  = array();
		$errors = array();
		$kept   = array();
		foreach ( array_unique( $ids ) as $id ) {
			$post = get_post( $id );
			if ( ! $post instanceof WP_Post || 'attachment' !== $post->post_type || 'import' !== get_post_meta( $id, '_wp_attachment_context', true ) || get_current_user_id() !== (int) $post->post_author ) {
				continue;
			}
			$kept[]                               = $id;
			$path                                 = get_attached_file( $id );
			$pack                                 = is_string( $path ) ? Package::read( $path, $post->post_title ) : new WP_Error( 'windrose_knotboard_package', __( 'An uploaded package is missing.', 'windrose-knotboard' ) );
			$pack instanceof WP_Error ? $errors[] = $pack->get_error_message() : $packs[] = $pack;
		}
		return array( $packs, $errors, $kept );
	}

	/**
	 * Deletes kept uploads (core's own clean-up).
	 *
	 * @param array $ids Attachment IDs.
	 * @phpstan-param list<int> $ids
	 * @return void
	 */
	private static function forget( array $ids ): void {
		require_once ABSPATH . 'wp-admin/includes/import.php';
		foreach ( $ids as $id ) {
			wp_import_cleanup( (string) $id );
		}
	}

	/**
	 * The plan or the results as a table.
	 *
	 * @param array $rows Rows.
	 * @phpstan-param list<Row> $rows
	 * @return void
	 */
	private static function table( array $rows ): void {
		$labels = Import::labels();
		echo '<table class="widefat striped windrose-knotboard-import"><thead><tr>';
		foreach ( array( __( 'Pattern', 'windrose-knotboard' ), __( 'Language', 'windrose-knotboard' ), __( 'Pack', 'windrose-knotboard' ), __( 'What happens', 'windrose-knotboard' ) ) as $head ) {
			echo '<th scope="col">' . esc_html( $head ) . '</th>';
		}
		echo '</tr></thead><tbody>';
		foreach ( $rows as $row ) {
			$title = esc_html( $row['title'] );
			if ( $row['id'] > 0 && current_user_can( 'edit_post', $row['id'] ) ) {
				$link  = get_edit_post_link( $row['id'] );
				$title = is_string( $link ) ? '<a href="' . esc_url( $link ) . '">' . $title . '</a>' : $title;
			}
			printf(
				'<tr><td>%1$s<br><code>%2$s</code></td><td>%3$s</td><td>%4$s</td><td>%5$s%6$s</td></tr>',
				$title, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above.
				esc_html( $row['key'] ),
				esc_html( $row['language'] ),
				esc_html( $row['pack'] ),
				esc_html( $labels[ $row['status'] ] ?? $row['status'] ),
				'' !== $row['problem'] ? '<br>' . esc_html( $row['problem'] ) : ''
			);
		}
		echo '</tbody></table>';
	}
}
