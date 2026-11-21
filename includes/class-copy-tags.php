<?php
/**
 * The HTML API's tag processor with the two changes copies need (core extends it the same way for its Image block and
 * for KSES): an attribute goes with the space before it, so a tag reads as core saves it, and a link holding only a
 * picture can be taken away, the picture staying.
 *
 * @package Windrose\Knotboard
 */

declare( strict_types=1 );

namespace Windrose\Knotboard;

use WP_HTML_Span;
use WP_HTML_Tag_Processor;
use WP_HTML_Text_Replacement;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Tag processor for copies.
 */
final class Copy_Tags extends WP_HTML_Tag_Processor {

	/** HTML's whitespace. */
	private const SPACE = " \t\n\f\r";

	/**
	 * Removes an attribute of the current tag with the space before it (core's tag processor keeps that space).
	 *
	 * @param string $name Attribute name.
	 * @return bool Whether the attribute was removed.
	 */
	public function remove_attribute( $name ): bool {
		if ( ! parent::remove_attribute( $name ) ) {
			return false;
		}
		$update = $this->lexical_updates[ strtolower( $name ) ] ?? null;
		if ( $update instanceof WP_HTML_Text_Replacement ) {
			while ( $update->start > 0 && str_contains( self::SPACE, $this->html[ $update->start - 1 ] ) ) {
				--$update->start;
				++$update->length;
			}
		}
		return true;
	}

	/**
	 * Takes away a link holding only a picture (and spaces): on the link, or, with `$first_child`, on the element whose
	 * first child is that link (the spaces before the link go too). The picture stays; spaces after the link stay.
	 *
	 * @param bool $first_child Whether the processor is on the link's parent.
	 * @return bool Whether a link was taken away.
	 */
	public function unwrap_picture( bool $first_child = false ): bool {
		$from = $this->span();
		if ( null === $from || ( $first_child && ! $this->next_solid() ) ) {
			return false;
		}
		$start = $first_child ? $from->start + $from->length : $from->start;
		if ( 'A' !== $this->get_tag() || $this->is_tag_closer() || ! $this->next_solid() || 'IMG' !== $this->get_tag() ) {
			return false;
		}
		$picture = $this->span();
		if ( null === $picture || ! $this->next_solid() || 'A' !== $this->get_tag() || ! $this->is_tag_closer() ) {
			return false;
		}
		$closer = $this->span();
		if ( null === $closer ) {
			return false;
		}
		$end                     = $picture->start + $picture->length;
		$this->lexical_updates[] = new WP_HTML_Text_Replacement( $start, $picture->start - $start, '' );
		$this->lexical_updates[] = new WP_HTML_Text_Replacement( $end, $closer->start + $closer->length - $end, '' );
		return true;
	}

	/**
	 * Where the current token is in the HTML.
	 *
	 * @return WP_HTML_Span|null
	 */
	private function span(): ?WP_HTML_Span {
		return $this->set_bookmark( 'here' ) ? $this->bookmarks['here'] : null;
	}

	/**
	 * Moves to the next token that is not only whitespace.
	 *
	 * @phpstan-impure
	 * @return bool Whether there is one.
	 */
	private function next_solid(): bool {
		while ( $this->next_token() ) {
			if ( '#text' !== $this->get_token_type() || '' !== trim( $this->get_modifiable_text(), self::SPACE ) ) {
				return true;
			}
		}
		return false;
	}
}
