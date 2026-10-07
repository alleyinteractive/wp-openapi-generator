<?php
/**
 * OpenAPI Generator Tests: Shape_Audit
 *
 * @package wp-openapi-generator
 */

namespace Alley\WP\OpenAPI_Generator\Tests\Unit;

use Alley\WP\OpenAPI_Generator\Shape_Audit;
use PHPUnit\Framework\TestCase;
use stdClass;

/**
 * Tests for finding operations with incomplete shapes.
 */
class ShapeAuditTest extends TestCase {
	/**
	 * A success response with the given schema.
	 *
	 * @param mixed $schema Schema.
	 * @return array<string, mixed>
	 */
	private static function response( mixed $schema ): array {
		return [
			'200' => [
				'description' => 'OK',
				'content'     => [ 'application/json' => [ 'schema' => $schema ] ],
			],
		];
	}

	public function test_reports_missing_shapes(): void {
		$spec = [
			'paths' => [
				'/ns/v1'            => [
					'get' => [ 'responses' => self::response( new stdClass() ) ],
				],
				'/ns/v1/complete'   => [
					'get'  => [
						'parameters' => [
							[
								'name'        => 'page',
								'in'          => 'query',
								'description' => 'Page.',
								'schema'      => [ 'type' => 'integer' ],
							],
						],
						'responses'  => self::response(
							[
								'type'  => 'array',
								'items' => [ '$ref' => '#/components/schemas/thing' ],
							]
						),
					],
					'post' => [
						'requestBody' => [
							'content' => [
								'application/json' => [
									'schema' => [
										'type'       => 'object',
										'properties' => [
											'status' => [
												'enum' => [ 'a', 'b' ],
												'description' => 'Status.',
											],
										],
									],
								],
							],
						],
						'responses'   => self::response( [ '$ref' => '#/components/schemas/thing' ] ),
					],
				],
				'/ns/v1/incomplete' => [
					'get'  => [
						'parameters' => [
							[
								'name'   => 'search',
								'in'     => 'query',
								'schema' => new stdClass(),
							],
						],
						'responses'  => self::response(
							[
								'type'  => 'array',
								'items' => new stdClass(),
							]
						),
					],
					'post' => [
						'requestBody' => [
							'content' => [
								'application/json' => [
									'schema' => [
										'type'       => 'object',
										'properties' => [
											'name' => [ 'type' => 'string' ],
										],
									],
								],
							],
						],
						'responses'   => self::response( new stdClass() ),
					],
				],
			],
		];

		$this->assertSame(
			[
				[
					'method'             => 'GET',
					'path'               => '/ns/v1/incomplete',
					'no_response_schema' => true,
					'untyped'            => [ 'search' ],
					'undescribed'        => [ 'search' ],
				],
				[
					'method'             => 'POST',
					'path'               => '/ns/v1/incomplete',
					'no_response_schema' => true,
					'untyped'            => [],
					'undescribed'        => [ 'name' ],
				],
			],
			( new Shape_Audit( $spec, [ '/ns/v1' ] ) )->issues()
		);
	}
}
