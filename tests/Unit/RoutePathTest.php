<?php
/**
 * OpenAPI Generator Tests: Route_Path
 *
 * @package wp-openapi-generator
 */

namespace Alley\WP\OpenAPI_Generator\Tests\Unit;

use Alley\WP\OpenAPI_Generator\Route_Path;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Tests for converting WordPress route regexes into OpenAPI paths.
 */
class RoutePathTest extends TestCase {
	/**
	 * Routes and the paths and parameters they should produce.
	 *
	 * @return array<string, array{string, string, array<string, string>}>
	 */
	public static function routes(): array {
		return [
			'root'                 => [ '/', '/', [] ],
			'static'               => [ '/wp/v2/posts', '/wp/v2/posts', [] ],
			'named group'          => [ '/wp/v2/posts/(?P<id>[\d]+)', '/wp/v2/posts/{id}', [ 'id' => '[\d]+' ] ],
			'multiple groups'      => [
				'/wp/v2/posts/(?P<parent>[\d]+)/revisions/(?P<id>[\d]+)',
				'/wp/v2/posts/{parent}/revisions/{id}',
				[
					'parent' => '[\d]+',
					'id'     => '[\d]+',
				],
			],
			'nested group'         => [
				'/wp/v2/plugins/(?P<plugin>[^.\/]+(?:\/[^.\/]+)?)',
				'/wp/v2/plugins/{plugin}',
				[ 'plugin' => '[^.\/]+(?:\/[^.\/]+)?' ],
			],
			'parenthesis in class' => [ '/ns/v1/(?P<slug>[a-z()]+)', '/ns/v1/{slug}', [ 'slug' => '[a-z()]+' ] ],
			'optional segment'     => [ '/ns/v1/items(?:/(?P<id>\d+))?', '/ns/v1/items/{id}', [ 'id' => '\d+' ] ],
			'perl style name'      => [ '/ns/v1/(?<id>\d+)', '/ns/v1/{id}', [ 'id' => '\d+' ] ],
			'escaped characters'   => [ '/ns/v1/file\.json', '/ns/v1/file.json', [] ],
			'anchors'              => [ '^/ns/v1/items$', '/ns/v1/items', [] ],
		];
	}

	/**
	 * Test route conversion.
	 *
	 * @param string                $route      Route regex.
	 * @param string                $path       Expected path.
	 * @param array<string, string> $parameters Expected parameters.
	 */
	#[DataProvider( 'routes' )]
	public function test_from_route( string $route, string $path, array $parameters ): void {
		$route_path = Route_Path::from_route( $route );

		$this->assertSame( $path, $route_path->path );
		$this->assertSame( $parameters, $route_path->parameters );
	}
}
