<?php
/**
 * Shape_Audit class file
 *
 * @package wp-openapi-generator
 */

declare(strict_types=1);

namespace Alley\WP\OpenAPI_Generator;

use stdClass;

/**
 * Finds operations in an OpenAPI document whose request or response shapes are incomplete.
 */
final class Shape_Audit {
	/**
	 * Keywords that describe a value well enough without a `type`.
	 *
	 * @var string[]
	 */
	private const TYPE_KEYWORDS = [ 'type', '$ref', 'enum', 'const', 'oneOf', 'anyOf', 'allOf' ];

	/**
	 * Constructor.
	 *
	 * @param array<string, mixed> $spec       OpenAPI document.
	 * @param string[]             $skip_paths Paths to leave out, such as namespace index routes.
	 */
	public function __construct(
		private readonly array $spec,
		private readonly array $skip_paths = [],
	) {}

	/**
	 * Operations with missing shapes.
	 *
	 * @return list<array{method: string, path: string, no_response_schema: bool, untyped: list<string>, undescribed: list<string>}>
	 */
	public function issues(): array {
		$issues = [];
		$paths  = is_array( $this->spec['paths'] ?? null ) ? $this->spec['paths'] : [];

		foreach ( $paths as $path => $operations ) {
			if ( ! is_array( $operations ) || in_array( $path, $this->skip_paths, true ) ) {
				continue;
			}

			foreach ( $operations as $method => $operation ) {
				if ( ! is_array( $operation ) ) {
					continue;
				}

				$fields      = $this->fields( $operation );
				$untyped     = array_keys( array_filter( $fields, fn ( $schema ) => ! array_intersect( self::TYPE_KEYWORDS, array_keys( $schema ) ) ) );
				$undescribed = array_keys( array_filter( $fields, fn ( $schema ) => empty( $schema['description'] ) ) );
				$issue       = [
					'method'             => strtoupper( (string) $method ),
					'path'               => (string) $path,
					'no_response_schema' => ! $this->has_response_schema( $operation ),
					'untyped'            => array_map( 'strval', $untyped ),
					'undescribed'        => array_map( 'strval', $undescribed ),
				];

				if ( $issue['no_response_schema'] || $issue['untyped'] || $issue['undescribed'] ) {
					$issues[] = $issue;
				}
			}
		}

		return $issues;
	}

	/**
	 * Parameters and top-level request body properties, keyed by name, with descriptions merged into each schema.
	 *
	 * @param array<mixed> $operation OpenAPI operation.
	 * @return array<string, array<mixed>>
	 */
	private function fields( array $operation ): array {
		$fields = [];

		foreach ( (array) ( $operation['parameters'] ?? [] ) as $parameter ) {
			if ( is_array( $parameter ) && is_string( $parameter['name'] ?? null ) ) {
				$schema = is_array( $parameter['schema'] ?? null ) ? $parameter['schema'] : [];

				$fields[ $parameter['name'] ] = $schema + [ 'description' => $parameter['description'] ?? null ];
			}
		}

		$request_body = is_array( $operation['requestBody'] ?? null ) ? $operation['requestBody'] : [];
		$content      = is_array( $request_body['content'] ?? null ) ? $request_body['content'] : [];
		$body         = reset( $content );
		$body_schema  = is_array( $body ) && is_array( $body['schema'] ?? null ) ? $body['schema'] : [];

		if ( is_array( $body_schema['properties'] ?? null ) ) {
			foreach ( $body_schema['properties'] as $name => $schema ) {
				$fields[ (string) $name ] = is_array( $schema ) ? $schema : [];
			}
		}

		return $fields;
	}

	/**
	 * Whether the operation's success response has a non-empty schema.
	 *
	 * @param array<mixed> $operation OpenAPI operation.
	 */
	private function has_response_schema( array $operation ): bool {
		foreach ( (array) ( $operation['responses'] ?? [] ) as $status => $response ) {
			if ( ! is_array( $response ) || ! preg_match( '/^2\d\d$/', (string) $status ) ) {
				continue;
			}

			if ( ! isset( $response['content'] ) ) {
				return true;
			}

			foreach ( (array) $response['content'] as $media ) {
				$schema = is_array( $media ) ? ( $media['schema'] ?? null ) : null;

				if ( is_array( $schema ) && $schema && ! ( 'array' === ( $schema['type'] ?? null ) && ( $schema['items'] ?? null ) instanceof stdClass ) ) {
					return true;
				}
			}

			return false;
		}

		return false;
	}
}
