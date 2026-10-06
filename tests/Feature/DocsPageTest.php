<?php
/**
 * OpenAPI Generator Tests: Docs_Page
 *
 * @package wp-openapi-generator
 */

namespace Alley\WP\OpenAPI_Generator\Tests\Feature;

use Alley\WP\OpenAPI_Generator\Features\Docs_Page;
use Alley\WP\OpenAPI_Generator\Settings;
use Alley\WP\OpenAPI_Generator\Tests\TestCase;

/**
 * Tests for the Swagger UI documentation page.
 */
class DocsPageTest extends TestCase {
	protected function setUp(): void {
		parent::setUp();

		$this->set_permalink_structure( '/%postname%/' );
	}

	public function test_hidden_from_anonymous_users_by_default(): void {
		$this->get( '/openapi/' )->assertNotFound();
	}

	public function test_renders_swagger_ui_for_administrators(): void {
		$this->acting_as( 'administrator' );

		$this->get( '/openapi/' )
			->assertOk()
			->assertSee( '<div id="swagger-ui"></div>' )
			->assertSee( 'swagger-ui-bundle.js' )
			->assertSee( 'openapi\/v1\/spec' )
			->assertSee( 'wpOpenApiGenerator' );
	}

	public function test_public_setting(): void {
		update_option( Settings::OPTION, [ 'public' => true ] );

		$this->get( '/openapi/' )->assertOk()->assertSee( 'swagger-ui' );
	}

	public function test_custom_path(): void {
		update_option(
			Settings::OPTION,
			[
				'public' => true,
				'path'   => 'api/docs',
			]
		);

		// Rewrite rules are rebuilt on the request after the setting changes, once init registers the new path.
		( new Docs_Page() )->add_rewrite_rule();
		$this->set_permalink_structure( '/%postname%/' );

		$this->assertSame( home_url( '/api/docs/' ), Docs_Page::url() );
		$this->get( '/api/docs/' )->assertOk()->assertSee( 'swagger-ui' );
	}

	public function test_url_without_pretty_permalinks(): void {
		$this->set_permalink_structure( '' );

		$this->assertSame( home_url( '/?' . Docs_Page::QUERY_VAR . '=1' ), Docs_Page::url() );
	}
}
