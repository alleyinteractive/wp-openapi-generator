<?php
/**
 * Feature interface file
 *
 * @package wp-openapi-generator
 */

declare(strict_types=1);

namespace Alley\WP\OpenAPI_Generator;

/**
 * A self-contained part of the plugin that registers its own hooks.
 */
interface Feature {
	/**
	 * Register hooks.
	 */
	public function boot(): void;
}
