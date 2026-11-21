<?php
/**
 * Windrose Livery's classes Knotboard calls (Livery 0.7), for PHPStan: Livery is a separate plugin.
 *
 * @package Windrose\Knotboard
 */

namespace Windrose\Livery;

final class Patterns {
	/**
	 * The address that renders patterns on the current site.
	 */
	public static function render_url(): string {
		return '';
	}
}

final class Catalog {
	/**
	 * The ready styles as recipes.
	 *
	 * @return list<array{slug: string, title: string, description: string, recipe: array<string, mixed>}>
	 */
	public static function ready_recipes(): array {
		return array();
	}
}

final class Mermaid {
	/**
	 * The site's Style Mermaid switches.
	 *
	 * @return array{public: bool, remote: bool}
	 */
	public static function settings(): array {
		return array(
			'public' => false,
			'remote' => false,
		);
	}
}
