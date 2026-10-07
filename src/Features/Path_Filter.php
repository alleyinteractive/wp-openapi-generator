<?php
/**
 * Path_Filter class file
 *
 * @package wp-openapi-generator
 */

declare(strict_types=1);

namespace Alley\WP\OpenAPI_Generator\Features;

use Alley\WP\OpenAPI_Generator\Settings;
use Alley\WP\OpenAPI_Generator\Feature;

/**
 * Includes or excludes paths from the OpenAPI document based on the path patterns setting.
 */
final class Path_Filter implements Feature {
	/**
	 * Boot the feature.
	 */
	public function boot(): void {
		add_filter( 'wp_openapi_generator_include_route', [ $this, 'filter_include_route' ], 10, 5 );
	}

	/**
	 * Apply the path patterns setting to a route.
	 *
	 * @param mixed $include  Whether to include the route.
	 * @param mixed $route    Route regex.
	 * @param mixed $handlers Route handlers.
	 * @param mixed $options  Route options.
	 * @param mixed $path     OpenAPI path.
	 * @return mixed
	 */
	public function filter_include_route( $include, $route, $handlers, $options, $path ) {
		$settings = Settings::all();

		if ( ! $include || ! $settings['path_patterns'] || ! is_string( $path ) ) {
			return $include;
		}

		$matches = self::matches( $path, $settings['path_patterns'] );

		return 'allow' === $settings['path_filter'] ? $matches : ! $matches;
	}

	/**
	 * Whether a path matches any of the patterns. `*` matches any characters, including slashes.
	 *
	 * @param string   $path     OpenAPI path, such as `/wp/v2/posts/{id}`.
	 * @param string[] $patterns Patterns, such as `wp/v2/posts/*`.
	 */
	public static function matches( string $path, array $patterns ): bool {
		$path = trim( $path, '/' );

		foreach ( $patterns as $pattern ) {
			$regex = '#^' . str_replace( '\*', '.*', preg_quote( trim( $pattern, '/' ), '#' ) ) . '$#i';

			if ( preg_match( $regex, $path ) ) {
				return true;
			}
		}

		return false;
	}
}
