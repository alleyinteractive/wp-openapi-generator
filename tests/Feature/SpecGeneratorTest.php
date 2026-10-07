<?php
/**
 * OpenAPI Generator Tests: Spec_Generator
 *
 * @package wp-openapi-generator
 */

namespace Alley\WP\OpenAPI_Generator\Tests\Feature;

use Alley\WP\OpenAPI_Generator\Spec_Generator;
use Alley\WP\OpenAPI_Generator\Tests\TestCase;

/**
 * Tests for generating an OpenAPI document from registered REST routes.
 */
class SpecGeneratorTest extends TestCase {
	protected function setUp(): void {
		parent::setUp();

		add_action( 'rest_api_init', [ $this, 'register_test_routes' ] );

		$GLOBALS['wp_rest_server'] = null; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited, WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Forces rest_api_init to run again with the test routes.
	}

	protected function tearDown(): void {
		$GLOBALS['wp_rest_server'] = null; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited, WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Forces rest_api_init to run again with the test routes.

		parent::tearDown();
	}

	public function register_test_routes(): void {
		register_rest_route(
			'test/v1',
			'/widgets/(?P<id>\d+)',
			[
				[
					'methods'             => 'GET',
					'callback'            => '__return_null',
					'permission_callback' => '__return_true',
					'args'                => [
						'id' => [
							'description' => 'Widget ID.',
							'type'        => 'integer',
						],
					],
				],
				[
					'methods'             => 'POST',
					'callback'            => '__return_null',
					'permission_callback' => '__return_true',
					'args'                => [
						'name' => [
							'type'     => 'string',
							'required' => true,
						],
					],
					'openapi'             => [
						'summary'    => 'Rename a widget',
						'deprecated' => true,
					],
				],
				'schema' => fn () => [
					'$schema'    => 'http://json-schema.org/draft-04/schema#',
					'title'      => 'widget',
					'type'       => 'object',
					'properties' => [
						'id' => [
							'type'     => 'integer',
							'readonly' => true,
							'context'  => [ 'view' ],
						],
					],
				],
			]
		);

		register_rest_route(
			'test/v1',
			'/widgets/(?P<slug>[a-z]+)',
			[
				'methods'             => 'DELETE',
				'callback'            => '__return_null',
				'permission_callback' => '__return_true',
			]
		);

		register_rest_route(
			'test/v1',
			'/internal',
			[
				'methods'             => 'GET',
				'callback'            => '__return_null',
				'permission_callback' => '__return_true',
				'openapi'             => false,
			]
		);

		register_rest_route(
			'test/v1',
			'/hidden',
			[
				'methods'             => 'GET',
				'callback'            => '__return_null',
				'permission_callback' => '__return_true',
				'show_in_index'       => false,
			]
		);
	}

	/**
	 * Generate a document.
	 *
	 * @param string[] $namespaces Namespaces.
	 * @return array<string, mixed>
	 */
	private function generate( array $namespaces = [] ): array {
		return ( new Spec_Generator( rest_get_server(), $namespaces ) )->generate();
	}

	public function test_document_shape(): void {
		$spec = $this->generate();

		$this->assertSame( '3.1.0', $spec['openapi'] );
		$this->assertSame( untrailingslashit( rest_url() ), $spec['servers'][0]['url'] );
		$this->assertArrayHasKey( 'WP_Error', $spec['components']['schemas'] );
		$this->assertArrayHasKey( 'applicationPassword', $spec['components']['securitySchemes'] );
		$this->assertArrayHasKey( '/', $spec['paths'] );
	}

	public function test_core_collection_route(): void {
		$spec       = $this->generate( [ 'wp/v2' ] );
		$collection = $spec['paths']['/wp/v2/posts'];

		$this->assertSame( [ 'get', 'post' ], array_keys( $collection ) );
		$this->assertSame( 'List posts', $collection['get']['summary'] );
		$this->assertSame( [ 'wp/v2/posts' ], $collection['get']['tags'] );

		$list = $collection['get']['responses'][200];
		$this->assertSame( 'array', $list['content']['application/json']['schema']['type'] );
		$this->assertSame( '#/components/schemas/post', $list['content']['application/json']['schema']['items']['$ref'] );
		$this->assertArrayHasKey( 'X-WP-Total', $list['headers'] );

		$per_page = $this->find_parameter( $collection['get']['parameters'], 'per_page' );
		$this->assertSame( 'query', $per_page['in'] );
		$this->assertSame( 'integer', $per_page['schema']['type'] );

		$this->assertArrayHasKey( 201, $collection['post']['responses'] );
		$this->assertArrayHasKey( 'title', $collection['post']['requestBody']['content']['application/json']['schema']['properties'] );
		$this->assertArrayHasKey( 'post', $spec['components']['schemas'] );
	}

	public function test_core_single_route(): void {
		$single = $this->generate( [ 'wp/v2' ] )['paths']['/wp/v2/posts/{id}'];

		$this->assertSame( [ 'get', 'post', 'put', 'patch', 'delete' ], array_keys( $single ) );
		$this->assertSame( 'Retrieve post', $single['get']['summary'] );

		$id = $this->find_parameter( $single['get']['parameters'], 'id' );
		$this->assertSame( 'path', $id['in'] );
		$this->assertTrue( $id['required'] );
		$this->assertSame( 'integer', $id['schema']['type'] );

		$this->assertSame( '#/components/schemas/post', $single['get']['responses'][200]['content']['application/json']['schema']['$ref'] );
		$this->assertArrayNotHasKey( 'id', $single['post']['requestBody']['content']['application/json']['schema']['properties'] );
		$this->assertNotNull( $this->find_parameter( $single['delete']['parameters'], 'force' ) );
	}

	public function test_media_upload_accepts_a_file(): void {
		$content = $this->generate( [ 'wp/v2' ] )['paths']['/wp/v2/media']['post']['requestBody']['content'];

		$this->assertSame( [ 'multipart/form-data' ], array_keys( $content ) );
		$this->assertSame( [ 'file' ], array_keys( $content['multipart/form-data']['schema']['properties'] ) );
		$this->assertSame( 'binary', $content['multipart/form-data']['schema']['properties']['file']['format'] );
		$this->assertSame( [ 'file' ], $content['multipart/form-data']['schema']['required'] );
	}

	public function test_delete_with_force_returns_the_item_or_a_deletion_result(): void {
		$schema = $this->generate( [ 'wp/v2' ] )['paths']['/wp/v2/posts/{id}']['delete']['responses'][200]['content']['application/json']['schema'];

		$this->assertSame( [ '$ref' => '#/components/schemas/post' ], $schema['anyOf'][0] );
		$this->assertSame( [ '$ref' => '#/components/schemas/post' ], $schema['anyOf'][1]['properties']['previous'] );
	}

	public function test_custom_route(): void {
		$spec  = $this->generate( [ 'test/v1' ] );
		$route = $spec['paths']['/test/v1/widgets/{id}'];

		$id = $this->find_parameter( $route['get']['parameters'], 'id' );
		$this->assertSame( 'Widget ID.', $id['description'] );
		$this->assertSame( [ 'type' => 'integer' ], $id['schema'] );

		$this->assertSame( 'Rename a widget', $route['post']['summary'] );
		$this->assertTrue( $route['post']['deprecated'] );
		$this->assertTrue( $route['post']['requestBody']['required'] );
		$this->assertSame( [ 'name' ], $route['post']['requestBody']['content']['application/json']['schema']['required'] );

		$this->assertSame(
			[
				'title'      => 'widget',
				'type'       => 'object',
				'properties' => [
					'id' => [
						'type'     => 'integer',
						'readOnly' => true,
					],
				],
			],
			$spec['components']['schemas']['widget']
		);
	}

	public function test_limits_to_namespaces(): void {
		$paths = array_keys( $this->generate( [ 'test/v1' ] )['paths'] );

		$this->assertSame( [ '/test/v1', '/test/v1/widgets/{id}' ], $paths );
	}

	public function test_skips_routes_that_differ_only_by_parameter_name(): void {
		$paths = $this->generate( [ 'test/v1' ] )['paths'];

		$this->assertArrayNotHasKey( '/test/v1/widgets/{slug}', $paths );
		$this->assertArrayNotHasKey( 'delete', $paths['/test/v1/widgets/{id}'] );
	}

	public function test_excludes_routes_hidden_from_the_index(): void {
		$this->assertArrayNotHasKey( '/test/v1/hidden', $this->generate( [ 'test/v1' ] )['paths'] );

		add_filter( 'wp_openapi_generator_include_route', '__return_true' );

		$this->assertArrayHasKey( '/test/v1/hidden', $this->generate( [ 'test/v1' ] )['paths'] );
	}

	public function test_operation_filter_can_remove_operations(): void {
		add_filter(
			'wp_openapi_generator_operation',
			fn ( $operation, $method ) => 'post' === $method ? [] : $operation,
			10,
			2
		);

		$this->assertSame( [ 'get' ], array_keys( $this->generate( [ 'test/v1' ] )['paths']['/test/v1/widgets/{id}'] ) );
	}

	public function test_endpoints_can_opt_out(): void {
		$this->assertArrayNotHasKey( '/test/v1/internal', $this->generate( [ 'test/v1' ] )['paths'] );
	}

	public function test_endpoint_args_filter(): void {
		add_filter(
			'wp_openapi_generator_endpoint_args',
			function ( $args, $method, $route ) {
				if ( 'post' === $method && '/test/v1/widgets/(?P<id>\d+)' === $route ) {
					$args['color'] = [
						'type' => 'string',
						'enum' => [ 'red', 'blue' ],
					];
				}

				return $args;
			},
			10,
			3
		);

		$schema = $this->generate( [ 'test/v1' ] )['paths']['/test/v1/widgets/{id}']['post']['requestBody']['content']['application/json']['schema'];

		$this->assertSame( [ 'red', 'blue' ], $schema['properties']['color']['enum'] );
	}

	public function test_route_schema_filter(): void {
		add_filter(
			'wp_openapi_generator_route_schema',
			function ( $schema, $route ) {
				if ( '/test/v1/widgets/(?P<id>\d+)' === $route ) {
					$schema['properties']['label'] = [ 'type' => 'string' ];
				}

				return $schema;
			},
			10,
			2
		);

		$this->assertSame( [ 'type' => 'string' ], $this->generate( [ 'test/v1' ] )['components']['schemas']['widget']['properties']['label'] );
	}

	public function test_responses_filter(): void {
		add_filter(
			'wp_openapi_generator_responses',
			function ( $responses ) {
				$responses['404'] = [ 'description' => 'Widget not found.' ];

				return $responses;
			}
		);

		$this->assertSame(
			[ 'description' => 'Widget not found.' ],
			$this->generate( [ 'test/v1' ] )['paths']['/test/v1/widgets/{id}']['get']['responses'][404]
		);
	}

	public function test_info_and_servers_filters(): void {
		add_filter( 'wp_openapi_generator_info', fn ( $info ) => [
			...$info,
			'version' => '2.0.0',
		] );
		add_filter( 'wp_openapi_generator_servers', fn () => [ [ 'url' => 'https://api.example.com/wp-json' ] ] );

		$spec = $this->generate( [ 'test/v1' ] );

		$this->assertSame( '2.0.0', $spec['info']['version'] );
		$this->assertSame( [ [ 'url' => 'https://api.example.com/wp-json' ] ], $spec['servers'] );
	}

	public function test_security_filters(): void {
		add_filter(
			'wp_openapi_generator_security_schemes',
			fn () => [
				'bearer' => [
					'type'   => 'http',
					'scheme' => 'bearer',
				],
			]
		);

		$spec = $this->generate( [ 'test/v1' ] );

		$this->assertSame( [ 'bearer' ], array_keys( $spec['components']['securitySchemes'] ) );
		$this->assertEquals( [ new \stdClass(), [ 'bearer' => [] ] ], $spec['security'] );

		add_filter( 'wp_openapi_generator_security_schemes', '__return_empty_array', 20 );
		add_filter( 'wp_openapi_generator_security', '__return_empty_array' );

		$spec = $this->generate( [ 'test/v1' ] );

		$this->assertArrayNotHasKey( 'securitySchemes', $spec['components'] );
		$this->assertArrayNotHasKey( 'security', $spec );
	}

	public function test_operation_ids_are_unique(): void {
		$ids = [];

		foreach ( $this->generate()['paths'] as $operations ) {
			foreach ( $operations as $operation ) {
				$ids[] = $operation['operationId'];
			}
		}

		$this->assertNotEmpty( $ids );
		$this->assertSame( $ids, array_unique( $ids ) );
	}

	public function test_encodes_empty_schemas_as_objects(): void {
		$json = (string) wp_json_encode( $this->generate( [ 'test/v1' ] ) );

		$this->assertStringNotContainsString( '"schema":[]', $json );
		$this->assertStringContainsString( '"security":[{},', $json );
	}

	/**
	 * Find a parameter by name.
	 *
	 * @param array<array<string, mixed>> $parameters Parameters.
	 * @param string                      $name       Name.
	 * @return array<string, mixed>|null
	 */
	private function find_parameter( array $parameters, string $name ): ?array {
		foreach ( $parameters as $parameter ) {
			if ( $name === $parameter['name'] ) {
				return $parameter;
			}
		}

		return null;
	}
}
