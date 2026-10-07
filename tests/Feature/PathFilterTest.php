<?php
/**
 * OpenAPI Generator Tests: Path_Filter
 *
 * @package wp-openapi-generator
 */

namespace Alley\WP\OpenAPI_Generator\Tests\Feature;

use Alley\WP\OpenAPI_Generator\Settings;
use Alley\WP\OpenAPI_Generator\Spec_Generator;
use Alley\WP\OpenAPI_Generator\Tests\TestCase;

/**
 * Tests for limiting the document with the path patterns setting.
 */
class PathFilterTest extends TestCase {
	/**
	 * Generate the document's paths.
	 *
	 * @return string[]
	 */
	private function paths(): array {
		return array_keys( ( new Spec_Generator( rest_get_server() ) )->generate()['paths'] );
	}

	public function test_includes_everything_without_patterns(): void {
		update_option( Settings::OPTION, [ 'path_filter' => 'allow' ] );

		$this->assertContains( '/wp/v2/pages', $this->paths() );
	}

	public function test_allow_list(): void {
		update_option(
			Settings::OPTION,
			[
				'path_filter'   => 'allow',
				'path_patterns' => "wp/v2/posts*\noembed/1.0/embed",
			]
		);

		$paths = $this->paths();

		$this->assertContains( '/wp/v2/posts', $paths );
		$this->assertContains( '/wp/v2/posts/{id}', $paths );
		$this->assertContains( '/wp/v2/posts/{parent}/revisions', $paths );
		$this->assertContains( '/oembed/1.0/embed', $paths );
		$this->assertNotContains( '/wp/v2/pages', $paths );
		$this->assertNotContains( '/wp/v2', $paths );
	}

	public function test_deny_list(): void {
		update_option(
			Settings::OPTION,
			[
				'path_filter'   => 'deny',
				'path_patterns' => [ 'wp/v2/posts/*', 'oembed/*' ],
			]
		);

		$paths = $this->paths();

		$this->assertContains( '/wp/v2/posts', $paths );
		$this->assertNotContains( '/wp/v2/posts/{id}', $paths );
		$this->assertContains( '/wp/v2/pages/{id}', $paths );
		$this->assertEmpty( preg_grep( '#^/oembed/#', $paths ) );
	}

	public function test_docs_page_hides_empty_namespaces(): void {
		$this->set_permalink_structure( '/%postname%/' );
		update_option(
			Settings::OPTION,
			[
				'public'        => true,
				'path_filter'   => 'allow',
				'path_patterns' => 'wp/v2/posts*',
			]
		);

		$this->get( '/openapi/' )
			->assertOk()
			->assertSee( 'namespace=wp%2Fv2' )
			->assertDontSee( 'namespace=oembed' );
	}
}
