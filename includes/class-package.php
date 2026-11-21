<?php
/**
 * A pattern library package (zip), read and checked in memory before anything is written.
 *
 * The format (`knotboard.json`, format_version 1), made by any build that writes this format:
 *
 *     knotboard.json            pack, release, language {code, name, dir}, terms {category, tag}, images, patterns
 *     patterns/<key>.html       block markup; pictures as {{url:<image>}} and {{id:<image>}}
 *     images/<image>.<ext>      WebP, JPEG, PNG or AVIF
 *
 * One package = one pack (a topic) in one language. A pattern's `key` is its identity across imports: with the
 * language, it finds the library pattern to update. Term names are a string or an object of names by language code;
 * the import names a new term in the site's language when the package has it.
 *
 * @package Windrose\Knotboard
 */

declare( strict_types=1 );

namespace Windrose\Knotboard;

use WP_Error;
use ZipArchive;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Reads and checks a package.
 *
 * @phpstan-type Language array{code: string, name: string, dir: string}
 * @phpstan-type Image array{file: string, sha256: string, type: string, alt: string, credit: array<string, string>, bytes: string, ext: string}
 * @phpstan-type Entry array{key: string, kind: string, title: string, description: string, categories: list<string>, tags: list<string>, reviewed: bool, content: string, sha256: string, images: list<string>}
 * @phpstan-type Pack array{pack: string, release: string, language: Language, terms: array{category: array<string, string|array<string, string>>, tag: array<string, string|array<string, string>>}, images: array<string, Image>, patterns: list<Entry>, file: string}
 */
final class Package {

	public const FORMAT  = 'windrose-knotboard-package';
	public const VERSION = 1;

	/** Limits: the zip, its entries, one pattern, one picture, everything unpacked. */
	public const MAX_ZIP      = 64 * MB_IN_BYTES;
	public const MAX_ENTRIES  = 600;
	public const MAX_PATTERN  = 512 * KB_IN_BYTES;
	public const MAX_IMAGE    = 5 * MB_IN_BYTES;
	public const MAX_UNPACKED = 128 * MB_IN_BYTES;

	/** Picture types by extension. */
	public const TYPES = array(
		'webp' => 'image/webp',
		'jpg'  => 'image/jpeg',
		'jpeg' => 'image/jpeg',
		'png'  => 'image/png',
		'avif' => 'image/avif',
	);

	private const KEY      = '/^[a-z0-9]+(?:-[a-z0-9]+)*$/';
	private const IMAGE    = '/^[a-z0-9]+(?:[.-][a-z0-9]+)*$/';
	private const LANGUAGE = '/^[a-z]{2,3}(?:-[a-z0-9]{2,8})*$/';

	/**
	 * Reads a package file.
	 *
	 * @param string $path     Zip on disk.
	 * @param string $filename Its name, for messages.
	 * @return array|WP_Error
	 * @phpstan-return Pack|WP_Error
	 */
	public static function read( string $path, string $filename = '' ) {
		$name = '' !== $filename ? $filename : wp_basename( $path );
		if ( ! class_exists( ZipArchive::class ) ) {
			return self::error( $name, __( 'PHP’s zip extension is needed to read packages.', 'windrose-knotboard' ) );
		}
		$size = is_readable( $path ) ? (int) filesize( $path ) : 0;
		if ( $size <= 0 || $size > self::MAX_ZIP ) {
			return self::error( $name, __( 'The file is empty, missing or larger than 64 MB.', 'windrose-knotboard' ) );
		}
		$zip = new ZipArchive();
		if ( true !== $zip->open( $path, ZipArchive::RDONLY ) ) {
			return self::error( $name, __( 'This is not a zip file.', 'windrose-knotboard' ) );
		}
		try {
			return self::from_zip( $zip, $name );
		} finally {
			$zip->close();
		}
	}

	/**
	 * Checks every entry, then the manifest, the patterns and the pictures.
	 *
	 * @param ZipArchive $zip  Open zip.
	 * @param string     $name File name for messages.
	 * @return array|WP_Error
	 * @phpstan-return Pack|WP_Error
	 */
	private static function from_zip( ZipArchive $zip, string $name ) {
		if ( $zip->count() < 1 || $zip->count() > self::MAX_ENTRIES ) {
			return self::error( $name, __( 'The package has no files, or too many.', 'windrose-knotboard' ) );
		}
		$entries  = array();
		$unpacked = 0;
		$count    = $zip->count();
		for ( $i = 0; $i < $count; $i++ ) {
			$stat = $zip->statIndex( $i );
			if ( false === $stat ) {
				return self::error( $name, __( 'The zip file is damaged.', 'windrose-knotboard' ) );
			}
			$entry = (string) $stat['name'];
			if ( str_ends_with( $entry, '/' ) && in_array( $entry, array( 'patterns/', 'images/' ), true ) ) {
				continue;
			}
			$known = 'knotboard.json' === $entry
				|| 1 === preg_match( '#^patterns/[a-z0-9]+(?:-[a-z0-9]+)*\.html$#', $entry )
				|| 1 === preg_match( '#^images/[a-z0-9]+(?:[.-][a-z0-9]+)*\.(?:webp|jpe?g|png|avif)$#', $entry );
			if ( ! $known ) {
				/* translators: %s: a file name inside the zip */
				return self::error( $name, sprintf( __( 'Unexpected file in the package: %s. Packages hold knotboard.json, patterns/*.html and images only.', 'windrose-knotboard' ), $entry ) );
			}
			$limit     = str_starts_with( $entry, 'images/' ) ? self::MAX_IMAGE : self::MAX_PATTERN;
			$unpacked += (int) $stat['size'];
			if ( (int) $stat['size'] > $limit || $unpacked > self::MAX_UNPACKED ) {
				/* translators: %s: a file name inside the zip */
				return self::error( $name, sprintf( __( '%s is too large.', 'windrose-knotboard' ), $entry ) );
			}
			$entries[ $entry ] = true;
		}//end for
		if ( ! isset( $entries['knotboard.json'] ) ) {
			return self::error( $name, __( 'knotboard.json is missing: this is not a pattern library package.', 'windrose-knotboard' ) );
		}
		$manifest = json_decode( (string) $zip->getFromName( 'knotboard.json' ), true );
		if ( ! is_array( $manifest ) || self::FORMAT !== ( $manifest['format'] ?? null ) ) {
			return self::error( $name, __( 'knotboard.json is not a pattern library package.', 'windrose-knotboard' ) );
		}
		if ( self::VERSION !== ( $manifest['format_version'] ?? null ) ) {
			return self::error( $name, __( 'This package was made for another version of Windrose Knotboard: update the plugin.', 'windrose-knotboard' ) );
		}
		$pack     = is_string( $manifest['pack'] ?? null ) ? $manifest['pack'] : '';
		$language = is_array( $manifest['language'] ?? null ) ? $manifest['language'] : array();
		$code     = is_string( $language['code'] ?? null ) ? $language['code'] : '';
		if ( 1 !== preg_match( self::KEY, $pack ) || 1 !== preg_match( self::LANGUAGE, $code ) || ! in_array( $language['dir'] ?? '', array( 'ltr', 'rtl' ), true ) ) {
			return self::error( $name, __( 'knotboard.json needs a pack name and a language (code and direction).', 'windrose-knotboard' ) );
		}
		$terms = array(
			'category' => self::terms( $manifest['terms']['category'] ?? array() ),
			'tag'      => self::terms( $manifest['terms']['tag'] ?? array() ),
		);
		if ( null === $terms['category'] || null === $terms['tag'] ) {
			return self::error( $name, __( 'knotboard.json: categories and tags need keys and names.', 'windrose-knotboard' ) );
		}
		$images = self::images( $zip, $manifest['images'] ?? array(), $entries, $name );
		if ( $images instanceof WP_Error ) {
			return $images;
		}
		$patterns = self::patterns( $zip, $manifest['patterns'] ?? null, $entries, $images, $terms, $name );
		if ( $patterns instanceof WP_Error ) {
			return $patterns;
		}
		return array(
			'pack'     => $pack,
			'release'  => is_string( $manifest['release'] ?? null ) ? sanitize_text_field( $manifest['release'] ) : '',
			'language' => array(
				'code' => $code,
				'name' => is_string( $language['name'] ?? null ) && '' !== trim( $language['name'] ) ? sanitize_text_field( $language['name'] ) : $code,
				'dir'  => (string) $language['dir'],
			),
			'terms'    => $terms,
			'images'   => $images,
			'patterns' => $patterns,
			'file'     => $name,
		);
	}

	/**
	 * Term keys and names (a name, or names by language code).
	 *
	 * @param mixed $data Manifest part.
	 * @return array<string, string|array<string, string>>|null
	 */
	private static function terms( $data ): ?array {
		if ( ! is_array( $data ) ) {
			return null;
		}
		$out = array();
		foreach ( $data as $key => $names ) {
			if ( ! is_string( $key ) || 1 !== preg_match( self::KEY, $key ) ) {
				return null;
			}
			if ( is_string( $names ) && '' !== trim( $names ) ) {
				$out[ $key ] = sanitize_text_field( $names );
				continue;
			}
			if ( ! is_array( $names ) || array() === $names ) {
				return null;
			}
			$clean = array();
			foreach ( $names as $code => $label ) {
				if ( is_string( $code ) && 1 === preg_match( self::LANGUAGE, $code ) && is_string( $label ) && '' !== trim( $label ) ) {
					$clean[ $code ] = sanitize_text_field( $label );
				}
			}
			if ( array() === $clean ) {
				return null;
			}
			$out[ $key ] = $clean;
		}//end foreach
		return $out;
	}

	/**
	 * The pictures: listed, present, the checksum and the type (read from the bytes) as said.
	 *
	 * @param ZipArchive          $zip     Open zip.
	 * @param mixed               $data    Manifest part.
	 * @param array<string, bool> $entries Files in the zip.
	 * @param string              $name    File name for messages.
	 * @return array<string, Image>|WP_Error
	 */
	private static function images( ZipArchive $zip, $data, array $entries, string $name ) {
		if ( ! is_array( $data ) ) {
			return self::error( $name, __( 'knotboard.json: images must be an object.', 'windrose-knotboard' ) );
		}
		$out = array();
		foreach ( $data as $ident => $info ) {
			$file = is_array( $info ) && is_string( $info['file'] ?? null ) ? $info['file'] : '';
			$ext  = strtolower( pathinfo( $file, PATHINFO_EXTENSION ) );
			if ( ! is_string( $ident ) || 1 !== preg_match( self::IMAGE, $ident ) || "images/{$ident}.{$ext}" !== $file || ! isset( $entries[ $file ], self::TYPES[ $ext ] ) || ! is_array( $info ) ) {
				/* translators: %s: picture name */
				return self::error( $name, sprintf( __( 'Picture %s: its file is missing or misnamed.', 'windrose-knotboard' ), is_string( $ident ) ? $ident : '?' ) );
			}
			$bytes = (string) $zip->getFromName( $file );
			$size  = '' !== $bytes ? getimagesizefromstring( $bytes ) : false;
			if ( ! hash_equals( (string) ( $info['sha256'] ?? '' ), hash( 'sha256', $bytes ) ) || false === $size || self::TYPES[ $ext ] !== $size['mime'] ) {
				/* translators: %s: picture name */
				return self::error( $name, sprintf( __( 'Picture %s is damaged or not the type its name says.', 'windrose-knotboard' ), $ident ) );
			}
			$credit = array();
			foreach ( array( 'title', 'author', 'author_url', 'source', 'licence' ) as $field ) {
				$value = is_array( $info['credit'] ?? null ) && is_string( $info['credit'][ $field ] ?? null ) ? $info['credit'][ $field ] : '';
				if ( '' === $value ) {
					continue;
				}
				$credit[ $field ] = str_ends_with( $field, 'url' ) || 'source' === $field ? esc_url_raw( $value, array( 'https', 'http' ) ) : sanitize_text_field( $value );
			}
			$out[ $ident ] = array(
				'file'   => $file,
				'sha256' => (string) $info['sha256'],
				'type'   => self::TYPES[ $ext ],
				'alt'    => is_string( $info['alt'] ?? null ) ? sanitize_text_field( $info['alt'] ) : '',
				'credit' => array_filter( $credit ),
				'bytes'  => $bytes,
				'ext'    => $ext,
			);
		}//end foreach
		return $out;
	}

	/**
	 * The patterns: keys, kinds, texts, terms, markup with its checksum, pictures it names.
	 *
	 * @param ZipArchive                                                                                                     $zip     Open zip.
	 * @param mixed                                                                                                          $data    Manifest part.
	 * @param array<string, bool>                                                                                            $entries Files in the zip.
	 * @param array<string, Image>                                                                                           $images  Pictures.
	 * @param array{category: array<string, string|array<string, string>>, tag: array<string, string|array<string, string>>} $terms   Terms.
	 * @param string                                                                                                         $name    File name for messages.
	 * @return list<Entry>|WP_Error
	 */
	private static function patterns( ZipArchive $zip, $data, array $entries, array $images, array $terms, string $name ) {
		if ( ! is_array( $data ) || array() === $data ) {
			return self::error( $name, __( 'knotboard.json lists no patterns.', 'windrose-knotboard' ) );
		}
		$out  = array();
		$seen = array();
		foreach ( $data as $item ) {
			$key = is_array( $item ) && is_string( $item['key'] ?? null ) ? $item['key'] : '';
			if ( 1 !== preg_match( self::KEY, $key ) || isset( $seen[ $key ] ) ) {
				/* translators: %s: pattern key */
				return self::error( $name, sprintf( __( 'Pattern %s: its key is missing, misnamed or used twice.', 'windrose-knotboard' ), '' !== $key ? $key : '?' ) );
			}
			$seen[ $key ] = true;
			$file         = "patterns/{$key}.html";
			$kind         = is_string( $item['kind'] ?? null ) ? $item['kind'] : '';
			$title        = is_string( $item['title'] ?? null ) ? trim( $item['title'] ) : '';
			if ( ! in_array( $kind, Content::KINDS, true ) || '' === $title || ( $item['file'] ?? '' ) !== $file || ! isset( $entries[ $file ] ) || ! is_bool( $item['reviewed'] ?? null ) ) {
				/* translators: %s: pattern key */
				return self::error( $name, sprintf( __( 'Pattern %s needs a kind (header, footer, section or page), a title, its file and reviewed (true or false).', 'windrose-knotboard' ), $key ) );
			}
			$content = (string) $zip->getFromName( $file );
			if ( ! hash_equals( (string) ( $item['sha256'] ?? '' ), hash( 'sha256', $content ) ) || ! wp_check_invalid_utf8( $content ) ) {
				/* translators: %s: pattern key */
				return self::error( $name, sprintf( __( 'Pattern %s: its markup is damaged.', 'windrose-knotboard' ), $key ) );
			}
			$lists = array();
			foreach ( array(
				'categories' => 'category',
				'tags'       => 'tag',
				'images'     => '',
			) as $field => $taxonomy ) {
				$values = $item[ $field ] ?? array();
				if ( ! is_array( $values ) || array_filter( $values, 'is_string' ) !== $values ) {
					/* translators: 1: pattern key, 2: field name */
					return self::error( $name, sprintf( __( 'Pattern %1$s: %2$s must be a list.', 'windrose-knotboard' ), $key, $field ) );
				}
				$known = '' === $taxonomy ? $images : $terms[ $taxonomy ];
				foreach ( $values as $value ) {
					if ( ! isset( $known[ $value ] ) ) {
						/* translators: 1: pattern key, 2: a category, tag or picture name */
						return self::error( $name, sprintf( __( 'Pattern %1$s names %2$s, which the package does not describe.', 'windrose-knotboard' ), $key, $value ) );
					}
				}
				$lists[ $field ] = array_values( $values );
			}
			preg_match_all( '/\{\{([a-z]+):([a-z0-9.-]+)\}\}|\{\{/', $content, $found, PREG_SET_ORDER );
			foreach ( $found as $match ) {
				if ( ! isset( $match[1], $match[2] ) || ! in_array( $match[2], $lists['images'], true ) ) {
					/* translators: %s: pattern key */
					return self::error( $name, sprintf( __( 'Pattern %s holds a placeholder for a picture it does not list.', 'windrose-knotboard' ), $key ) );
				}
			}
			$out[] = array(
				'key'         => $key,
				'kind'        => $kind,
				'title'       => sanitize_text_field( $title ),
				'description' => is_string( $item['description'] ?? null ) ? sanitize_textarea_field( $item['description'] ) : '',
				'categories'  => $lists['categories'],
				'tags'        => $lists['tags'],
				'reviewed'    => (bool) $item['reviewed'],
				'content'     => $content,
				'sha256'      => (string) $item['sha256'],
				'images'      => $lists['images'],
			);
		}//end foreach
		return $out;
	}

	/**
	 * A package error, naming the file.
	 *
	 * @param string $name    File name.
	 * @param string $message Message.
	 * @return WP_Error
	 */
	private static function error( string $name, string $message ): WP_Error {
		/* translators: 1: file name, 2: what is wrong */
		return new WP_Error( 'windrose_knotboard_package', sprintf( __( '%1$s: %2$s', 'windrose-knotboard' ), $name, $message ) );
	}

	/**
	 * A term's name for this site: the site's language, the package's, English, the first one, or the key.
	 *
	 * @param string|array<string, string> $names Names.
	 * @param string                       $pack  The package's language code.
	 * @param string                       $key   Term key.
	 * @return string
	 */
	public static function term_name( $names, string $pack, string $key ): string {
		if ( is_string( $names ) ) {
			return $names;
		}
		foreach ( array( Content::code( get_locale() ), $pack, 'en' ) as $code ) {
			if ( isset( $names[ $code ] ) ) {
				return $names[ $code ];
			}
		}
		$first = reset( $names );
		return is_string( $first ) ? $first : $key;
	}
}
