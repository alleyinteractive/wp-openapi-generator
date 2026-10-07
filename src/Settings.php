<?php
/**
 * Settings class file
 *
 * @package wp-openapi-generator
 */

declare(strict_types=1);

namespace Alley\WP\OpenAPI_Generator;

/**
 * Reads and sanitizes the plugin's settings.
 */
final class Settings {
	/**
	 * Option name.
	 *
	 * @var string
	 */
	public const OPTION = 'wp_openapi_generator_settings';

	/**
	 * Default settings.
	 *
	 * @var array{public: bool, path: string, path_filter: 'allow'|'deny', path_patterns: list<string>}
	 */
	public const DEFAULTS = [
		'public'        => false,
		'path'          => 'openapi',
		'path_filter'   => 'deny',
		'path_patterns' => [],
	];

	/**
	 * Get all settings.
	 *
	 * @return array{public: bool, path: string, path_filter: 'allow'|'deny', path_patterns: list<string>}
	 */
	public static function all(): array {
		return self::sanitize( get_option( self::OPTION, self::DEFAULTS ) );
	}

	/**
	 * Whether the spec and documentation UI are available to everyone.
	 */
	public static function is_public(): bool {
		/**
		 * Filters whether the OpenAPI spec and documentation UI are public.
		 *
		 * @param bool $is_public Whether they are public. Defaults to the plugin setting.
		 */
		return (bool) apply_filters( 'wp_openapi_generator_is_public', self::all()['public'] );
	}

	/**
	 * Path, relative to the home URL, of the documentation UI.
	 */
	public static function path(): string {
		return self::all()['path'];
	}

	/**
	 * Capability required to view the spec and documentation UI when they are not public.
	 */
	public static function capability(): string {
		/**
		 * Filters the capability required to view the OpenAPI spec and documentation UI when they are not public.
		 *
		 * @param string $capability Capability. Default 'manage_options'.
		 */
		return (string) apply_filters( 'wp_openapi_generator_capability', 'manage_options' );
	}

	/**
	 * Whether the current user can view the spec and documentation UI.
	 */
	public static function current_user_can_view(): bool {
		return self::is_public() || current_user_can( self::capability() );
	}

	/**
	 * Sanitize settings.
	 *
	 * @param mixed $value Raw settings.
	 * @return array{public: bool, path: string, path_filter: 'allow'|'deny', path_patterns: list<string>}
	 */
	public static function sanitize( mixed $value ): array {
		$value = is_array( $value ) ? $value : [];
		$path  = is_string( $value['path'] ?? null ) ? strtolower( $value['path'] ) : '';
		$path  = trim( (string) preg_replace( [ '#[^a-z0-9/_-]+#', '#/{2,}#' ], [ '', '/' ], $path ), '/' );

		$patterns = $value['path_patterns'] ?? [];
		$patterns = is_string( $patterns ) ? preg_split( '/[\r\n,]+/', $patterns ) : $patterns;
		$patterns = array_map(
			fn ( $pattern ) => trim( (string) preg_replace( '#[^A-Za-z0-9/_.*{}-]+#', '', $pattern ), '/' ),
			array_filter( is_array( $patterns ) ? $patterns : [], 'is_string' ),
		);

		return [
			'public'        => ! empty( $value['public'] ),
			'path'          => '' !== $path ? $path : self::DEFAULTS['path'],
			'path_filter'   => 'allow' === ( $value['path_filter'] ?? null ) ? 'allow' : 'deny',
			'path_patterns' => array_values( array_unique( array_filter( $patterns, fn ( $pattern ) => '' !== $pattern ) ) ),
		];
	}
}
