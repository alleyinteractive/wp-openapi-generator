<?php
/**
 * Route_Path class file
 *
 * @package wp-openapi-generator
 */

declare(strict_types=1);

namespace Alley\WP\OpenAPI_Generator;

/**
 * Converts a WordPress REST route regex into an OpenAPI path template.
 *
 * For example, `/wp/v2/posts/(?P<id>[\d]+)` becomes `/wp/v2/posts/{id}` with an
 * `id` path parameter whose pattern is `[\d]+`.
 */
final class Route_Path {
	/**
	 * Constructor.
	 *
	 * @param string                $path       OpenAPI path template.
	 * @param array<string, string> $parameters Path parameter names mapped to their regex patterns.
	 */
	public function __construct(
		public readonly string $path,
		public readonly array $parameters,
	) {}

	/**
	 * Parse a WordPress REST route.
	 *
	 * @param string $route Route regex as registered with register_rest_route().
	 */
	public static function from_route( string $route ): self {
		$parameters = [];
		$path       = self::convert( $route, $parameters );
		$path       = (string) preg_replace( '#/{2,}#', '/', $path );

		if ( '' === $path || '/' !== $path[0] ) {
			$path = '/' . $path;
		}

		return new self( $path, $parameters );
	}

	/**
	 * Convert a regex fragment into a path template, collecting named groups.
	 *
	 * @param string                $regex      Regex fragment.
	 * @param array<string, string> $parameters Collected parameters, by reference.
	 */
	private static function convert( string $regex, array &$parameters ): string {
		$output = '';
		$length = strlen( $regex );
		$i      = 0;

		while ( $i < $length ) {
			$char = $regex[ $i ];

			if ( '\\' === $char ) {
				$output .= $regex[ $i + 1 ] ?? '';
				$i      += 2;
				continue;
			}

			if ( '^' === $char || '$' === $char ) {
				++$i;
				continue;
			}

			if ( '(' !== $char ) {
				$output .= $char;
				++$i;
				continue;
			}

			$close = self::find_closing_paren( $regex, $i );

			if ( -1 === $close ) {
				$output .= substr( $regex, $i );
				break;
			}

			$group = substr( $regex, $i + 1, $close - $i - 1 );

			if ( preg_match( '/^\?P?<([A-Za-z_][A-Za-z0-9_]*)>/', $group, $matches ) ) {
				$parameters[ $matches[1] ] = substr( $group, strlen( $matches[0] ) );
				$output                   .= '{' . $matches[1] . '}';
			} else {
				// Non-capturing and unnamed groups are flattened so any named groups inside them are still found.
				$output .= self::convert( (string) preg_replace( '/^\?(?:[:=!]|<[=!])/', '', $group ), $parameters );
			}

			$i = self::skip_quantifier( $regex, $close + 1 );
		}

		return $output;
	}

	/**
	 * Find the index of the parenthesis that closes the group opened at $open.
	 *
	 * @param string $regex Regex.
	 * @param int    $open  Index of the opening parenthesis.
	 * @return int Index of the closing parenthesis, or -1 if unbalanced.
	 */
	private static function find_closing_paren( string $regex, int $open ): int {
		$depth    = 0;
		$in_class = false;
		$length   = strlen( $regex );

		for ( $i = $open; $i < $length; $i++ ) {
			$char = $regex[ $i ];

			if ( '\\' === $char ) {
				++$i;
				continue;
			}

			if ( $in_class ) {
				$in_class = ']' !== $char;
				continue;
			}

			if ( '[' === $char ) {
				$in_class = true;
			} elseif ( '(' === $char ) {
				++$depth;
			} elseif ( ')' === $char && 0 === --$depth ) {
				return $i;
			}
		}

		return -1;
	}

	/**
	 * Skip a quantifier (`?`, `*`, `+`, `{n,m}`) that follows a group.
	 *
	 * @param string $regex Regex.
	 * @param int    $index Index just after the group.
	 * @return int Index after the quantifier.
	 */
	private static function skip_quantifier( string $regex, int $index ): int {
		if ( preg_match( '/\G(?:[?*+]|\{\d*,?\d*\})[?+]?/', $regex, $matches, 0, $index ) ) {
			return $index + strlen( $matches[0] );
		}

		return $index;
	}
}
