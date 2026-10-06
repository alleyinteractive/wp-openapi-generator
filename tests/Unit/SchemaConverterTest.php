<?php
/**
 * OpenAPI Generator Tests: Schema_Converter
 *
 * @package wp-openapi-generator
 */

namespace Alley\WP\OpenAPI_Generator\Tests\Unit;

use Alley\WP\OpenAPI_Generator\Schema_Converter;
use PHPUnit\Framework\TestCase;
use stdClass;

/**
 * Tests for converting WordPress schemas into OpenAPI schemas.
 */
class SchemaConverterTest extends TestCase {
	public function test_drops_wordpress_only_keys(): void {
		$schema = Schema_Converter::convert(
			[
				'$schema'           => 'http://json-schema.org/draft-04/schema#',
				'type'              => 'string',
				'context'           => [ 'view', 'edit' ],
				'arg_options'       => [ 'sanitize_callback' => 'sanitize_text_field' ],
				'sanitize_callback' => 'absint',
				'validate_callback' => 'rest_validate_request_arg',
				'readonly'          => true,
				'x-custom'          => 'kept',
			]
		);

		$this->assertSame(
			[
				'type'     => 'string',
				'x-custom' => 'kept',
				'readOnly' => true,
			],
			$schema
		);
	}

	public function test_moves_property_required_flags_to_parent(): void {
		$schema = Schema_Converter::convert(
			[
				'type'       => 'object',
				'properties' => [
					'title' => [
						'type'     => 'string',
						'required' => true,
					],
					'slug'  => [
						'type'     => 'string',
						'required' => false,
					],
				],
			]
		);

		$this->assertIsArray( $schema );
		$this->assertSame( [ 'title' ], $schema['required'] );
		$this->assertSame( [ 'type' => 'string' ], $schema['properties']['title'] );
		$this->assertSame( [ 'type' => 'string' ], $schema['properties']['slug'] );
	}

	public function test_converts_draft_04_exclusive_bounds(): void {
		$schema = Schema_Converter::convert(
			[
				'type'             => 'integer',
				'minimum'          => 0,
				'exclusiveMinimum' => true,
				'maximum'          => 100,
				'exclusiveMaximum' => false,
			]
		);

		$this->assertSame(
			[
				'type'             => 'integer',
				'exclusiveMinimum' => 0,
				'maximum'          => 100,
			],
			$schema
		);
	}

	public function test_normalizes_types(): void {
		$this->assertSame( [ 'type' => [ 'string', 'null' ] ], Schema_Converter::convert( [ 'type' => [ 'string', 'null' ] ] ) );
		$this->assertSame( [ 'type' => 'integer' ], Schema_Converter::convert( [ 'type' => [ 'integer', 'bogus' ] ] ) );
		$this->assertEquals( new stdClass(), Schema_Converter::convert( [ 'type' => 'any' ] ) );
	}

	public function test_converts_nested_schemas(): void {
		$schema = Schema_Converter::convert(
			[
				'type'                 => 'object',
				'additionalProperties' => [
					'type'    => 'array',
					'items'   => [
						'type'     => 'integer',
						'readonly' => true,
					],
					'context' => [ 'edit' ],
				],
				'oneOf'                => [
					[ 'type' => 'object' ],
					'invalid',
				],
			]
		);

		$this->assertSame(
			[
				'type'                 => 'object',
				'additionalProperties' => [
					'type'  => 'array',
					'items' => [
						'type'     => 'integer',
						'readOnly' => true,
					],
				],
				'oneOf'                => [
					[ 'type' => 'object' ],
				],
			],
			$schema
		);
	}

	public function test_empty_object_default_is_an_object(): void {
		$schema = Schema_Converter::convert(
			[
				'type'    => 'object',
				'default' => [],
			]
		);

		$this->assertIsArray( $schema );
		$this->assertEquals( new stdClass(), $schema['default'] );
	}

	public function test_args_to_parameters(): void {
		$parameters = Schema_Converter::args_to_parameters(
			[
				'search'  => [
					'description' => 'Limit results to those matching a string.',
					'type'        => 'string',
				],
				'include' => [
					'type'     => 'array',
					'items'    => [ 'type' => 'integer' ],
					'required' => true,
				],
				'filter'  => [ 'type' => 'object' ],
			]
		);

		$this->assertSame(
			[
				[
					'name'        => 'search',
					'in'          => 'query',
					'required'    => false,
					'description' => 'Limit results to those matching a string.',
					'schema'      => [ 'type' => 'string' ],
				],
				[
					'name'     => 'include',
					'in'       => 'query',
					'required' => true,
					'style'    => 'form',
					'explode'  => false,
					'schema'   => [
						'type'  => 'array',
						'items' => [ 'type' => 'integer' ],
					],
				],
				[
					'name'     => 'filter',
					'in'       => 'query',
					'required' => false,
					'style'    => 'deepObject',
					'explode'  => true,
					'schema'   => [ 'type' => 'object' ],
				],
			],
			$parameters
		);
	}

	public function test_query_parameters_that_accept_an_array_or_object_use_the_array_form(): void {
		$parameters = Schema_Converter::args_to_parameters(
			[
				'categories' => [
					'description' => 'Limit result set to items with specific terms.',
					'type'        => [ 'object', 'array' ],
					'oneOf'       => [
						[
							'title' => 'Term ID List',
							'type'  => 'array',
							'items' => [ 'type' => 'integer' ],
						],
						[
							'title'      => 'Term ID Taxonomy Query',
							'type'       => 'object',
							'properties' => [
								'terms' => [ 'type' => 'array' ],
							],
						],
					],
				],
				'ids'        => [ 'type' => [ 'array', 'object' ] ],
			]
		);

		$this->assertEquals(
			[
				'title' => 'Term ID List',
				'type'  => 'array',
				'items' => [ 'type' => 'integer' ],
			],
			$parameters[0]['schema']
		);
		$this->assertSame( [ 'type' => 'array' ], $parameters[1]['schema'] );
		$this->assertFalse( $parameters[0]['explode'] );
	}

	public function test_args_to_object_schema(): void {
		$this->assertSame(
			[
				'type'       => 'object',
				'properties' => [
					'title'  => [ 'type' => 'string' ],
					'status' => [
						'type' => 'string',
						'enum' => [ 'publish', 'draft' ],
					],
				],
				'required'   => [ 'title' ],
			],
			Schema_Converter::args_to_object_schema(
				[
					'title'  => [
						'type'     => 'string',
						'required' => true,
					],
					'status' => [
						'type' => 'string',
						'enum' => [ 'publish', 'draft', 'draft' ],
					],
				]
			)
		);
	}
}
