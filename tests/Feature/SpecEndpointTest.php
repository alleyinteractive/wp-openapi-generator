<?php
/**
 * OpenAPI Generator Tests: Spec_Endpoint
 *
 * @package wp-openapi-generator
 */

namespace Alley\WP\OpenAPI_Generator\Tests\Feature;

use Alley\WP\OpenAPI_Generator\Settings;
use Alley\WP\OpenAPI_Generator\Tests\TestCase;

/**
 * Tests for the REST endpoint that serves the OpenAPI document.
 */
class SpecEndpointTest extends TestCase {
	public function test_requires_authorization_by_default(): void {
		$this->get( rest_url( 'openapi/v1/spec' ) )->assertUnauthorized();
	}

	public function test_forbidden_for_users_without_the_capability(): void {
		$this->acting_as( 'subscriber' );

		$this->get( rest_url( 'openapi/v1/spec' ) )->assertForbidden();
	}

	public function test_available_to_administrators(): void {
		$this->acting_as( 'administrator' );

		$this->get( rest_url( 'openapi/v1/spec' ) )
			->assertOk()
			->assertJsonPath( 'openapi', '3.1.0' );
	}

	public function test_public_setting(): void {
		update_option( Settings::OPTION, [ 'public' => true ] );

		$this->get( rest_url( 'openapi/v1/spec' ) )->assertOk();
	}

	public function test_limits_to_namespace(): void {
		$this->acting_as( 'administrator' );

		$paths = array_keys( (array) $this->get( add_query_arg( 'namespace', 'oembed/1.0', rest_url( 'openapi/v1/spec' ) ) )->json( 'paths' ) );

		$this->assertNotEmpty( $paths );

		foreach ( $paths as $path ) {
			$this->assertStringStartsWith( '/oembed/1.0', $path );
		}
	}
}
