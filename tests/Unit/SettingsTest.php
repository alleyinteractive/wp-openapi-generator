<?php
/**
 * OpenAPI Generator Tests: Settings
 *
 * @package wp-openapi-generator
 */

namespace Alley\WP\OpenAPI_Generator\Tests\Unit;

use Alley\WP\OpenAPI_Generator\Settings;
use PHPUnit\Framework\TestCase;

/**
 * Tests for sanitizing settings.
 */
class SettingsTest extends TestCase {
	public function test_sanitize(): void {
		$this->assertSame( Settings::DEFAULTS, Settings::sanitize( 'invalid' ) );
		$this->assertSame(
			[
				'public'        => true,
				'path'          => 'api/docs',
				'path_filter'   => 'allow',
				'path_patterns' => [ 'wp/v2/posts/*', 'oembed/1.0/{url}' ],
			],
			Settings::sanitize(
				[
					'public'        => '1',
					'path'          => '//API//Docs?/',
					'path_filter'   => 'allow',
					'path_patterns' => "/wp/v2/posts/*/\r\n\n oembed/1.0/{url} \nwp/v2/posts/*,<>",
				]
			)
		);
		$this->assertSame( 'deny', Settings::sanitize( [ 'path_filter' => 'bogus' ] )['path_filter'] );
		$this->assertSame( 'openapi', Settings::sanitize( [ 'path' => '///' ] )['path'] );
	}
}
