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
				'public' => true,
				'path'   => 'api/docs',
			],
			Settings::sanitize(
				[
					'public' => '1',
					'path'   => '//API//Docs?/',
				]
			)
		);
		$this->assertSame( 'openapi', Settings::sanitize( [ 'path' => '///' ] )['path'] );
	}
}
