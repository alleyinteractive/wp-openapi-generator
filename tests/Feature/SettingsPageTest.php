<?php
/**
 * OpenAPI Generator Tests: Settings_Page
 *
 * @package wp-openapi-generator
 */

namespace Alley\WP\OpenAPI_Generator\Tests\Feature;

use Alley\WP\OpenAPI_Generator\Features\Settings_Page;
use Alley\WP\OpenAPI_Generator\Tests\TestCase;

/**
 * Tests for the settings page.
 */
class SettingsPageTest extends TestCase {
	public function test_lists_operations_with_missing_shapes(): void {
		require_once ABSPATH . 'wp-admin/includes/template.php';

		$this->acting_as( 'administrator' );

		ob_start();
		( new Settings_Page() )->render_page();
		$html = (string) ob_get_clean();

		$this->assertStringContainsString( '<details>', $html );
		$this->assertStringContainsString( '<code>/oembed/1.0/proxy</code>', $html );
		$this->assertStringNotContainsString( '<code>/openapi/v1/spec</code>', $html );
		$this->assertStringNotContainsString( '<code>/wp/v2</code>', $html );
	}
}
