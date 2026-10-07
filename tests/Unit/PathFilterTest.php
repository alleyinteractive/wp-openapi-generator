<?php
/**
 * OpenAPI Generator Tests: Path_Filter matching
 *
 * @package wp-openapi-generator
 */

namespace Alley\WP\OpenAPI_Generator\Tests\Unit;

use Alley\WP\OpenAPI_Generator\Features\Path_Filter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Tests for matching paths against wildcard patterns.
 */
class PathFilterTest extends TestCase {
	/**
	 * Paths, patterns, and whether they should match.
	 *
	 * @return array<string, array{string, string[], bool}>
	 */
	public static function cases(): array {
		return [
			'exact'                        => [ '/wp/v2/posts', [ 'wp/v2/posts' ], true ],
			'slashes are optional'         => [ '/wp/v2/posts', [ '/wp/v2/posts/' ], true ],
			'wildcard child'               => [ '/wp/v2/posts/{id}', [ 'wp/v2/posts/*' ], true ],
			'wildcard crosses slashes'     => [ '/wp/v2/posts/{parent}/revisions', [ 'wp/v2/posts/*' ], true ],
			'wildcard needs the slash'     => [ '/wp/v2/posts', [ 'wp/v2/posts/*' ], false ],
			'wildcard without slash'       => [ '/wp/v2/posts', [ 'wp/v2/posts*' ], true ],
			'prefix is not enough'         => [ '/wp/v2/posts-archive', [ 'wp/v2/posts' ], false ],
			'middle wildcard'              => [ '/wp/v2/pages/{id}/revisions', [ 'wp/v2/*/revisions' ], true ],
			'case insensitive'             => [ '/wp/v2/Posts', [ 'wp/v2/posts' ], true ],
			'regex characters are literal' => [ '/wp/v2/posts/{id}', [ 'wp/v2/posts/.+' ], false ],
			'any pattern'                  => [ '/oembed/1.0/embed', [ 'wp/v2/*', 'oembed/*' ], true ],
			'no patterns'                  => [ '/wp/v2/posts', [], false ],
		];
	}

	/**
	 * Test matching.
	 *
	 * @param string   $path     Path.
	 * @param string[] $patterns Patterns.
	 * @param bool     $expected Whether the path should match.
	 */
	#[DataProvider( 'cases' )]
	public function test_matches( string $path, array $patterns, bool $expected ): void {
		$this->assertSame( $expected, Path_Filter::matches( $path, $patterns ) );
	}
}
