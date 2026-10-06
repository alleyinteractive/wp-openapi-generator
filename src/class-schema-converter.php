<?php
/**
 * Schema_Converter class file
 *
 * @package wp-openapi-generator
 */

declare(strict_types=1);

namespace Alley\WP\OpenAPI_Generator;

use stdClass;

/**
 * Converts WordPress REST argument and resource schemas into OpenAPI 3.1 schema objects.
 *
 * WordPress schemas are loosely based on JSON Schema draft-04 and carry WordPress-only
 * keys (callbacks, `context`, boolean `required` on properties). OpenAPI 3.1 uses JSON
 * Schema 2020-12, so those keys are dropped or translated.
 */
final class Schema_Converter {
	/**
	 * JSON Schema keywords carried over to the OpenAPI document.
	 *
	 * @var string[]
	 */
	private const KEYWORDS = [
		'$ref',
		'additionalProperties',
		'allOf',
		'anyOf',
		'const',
		'default',
		'deprecated',
		'description',
		'enum',
		'examples',
		'exclusiveMaximum',
		'exclusiveMinimum',
		'format',
		'items',
		'maxItems',
		'maxLength',
		'maxProperties',
		'maximum',
		'minItems',
		'minLength',
		'minProperties',
		'minimum',
		'multipleOf',
		'not',
		'oneOf',
		'pattern',
		'patternProperties',
		'properties',
		'readOnly',
		'required',
		'title',
		'type',
		'uniqueItems',
		'writeOnly',
	];

	/**
	 * Valid JSON Schema types.
	 *
	 * @var string[]
	 */
	private const TYPES = [ 'array', 'boolean', 'integer', 'null', 'number', 'object', 'string' ];

	/**
	 * Convert a WordPress schema into an OpenAPI schema.
	 *
	 * @param array<mixed> $schema WordPress schema.
	 * @return array<string, mixed>|stdClass Schema, or an empty object for a schema that accepts anything.
	 */
	public static function convert( array $schema ): array|stdClass {
		if ( isset( $schema['readonly'] ) && ! isset( $schema['readOnly'] ) ) {
			$schema['readOnly'] = (bool) $schema['readonly'];
		}

		$output = [];

		foreach ( $schema as $key => $value ) {
			if ( ! is_string( $key ) ) {
				continue;
			}

			if ( str_starts_with( $key, 'x-' ) ) {
				$output[ $key ] = $value;
				continue;
			}

			if ( ! in_array( $key, self::KEYWORDS, true ) ) {
				continue;
			}

			$value = self::convert_keyword( $key, $value, $schema );

			if ( null !== $value ) {
				$output[ $key ] = $value;
			}
		}

		$required = self::required_properties( $schema );

		if ( $required ) {
			$output['required'] = $required;
		}

		if ( isset( $output['default'] ) && [] === $output['default'] && 'object' === ( $output['type'] ?? null ) ) {
			$output['default'] = new stdClass();
		}

		return $output ?: new stdClass();
	}

	/**
	 * Convert a map of WordPress REST arguments into an object schema.
	 *
	 * @param array<mixed> $args WordPress REST arguments keyed by name.
	 * @return array<string, mixed>
	 */
	public static function args_to_object_schema( array $args ): array {
		$properties = [];

		foreach ( $args as $name => $arg ) {
			if ( is_array( $arg ) ) {
				$properties[ $name ] = $arg;
			}
		}

		$schema = self::convert(
			[
				'type'       => 'object',
				'properties' => $properties,
			]
		);

		return is_array( $schema ) ? $schema : [];
	}

	/**
	 * Convert WordPress REST arguments into OpenAPI parameter objects.
	 *
	 * @param array<mixed> $args WordPress REST arguments keyed by name.
	 * @param string       $in   Parameter location: query, path, or header.
	 * @return list<array<string, mixed>>
	 */
	public static function args_to_parameters( array $args, string $in = 'query' ): array {
		$parameters = [];

		foreach ( $args as $name => $arg ) {
			if ( ! is_array( $arg ) ) {
				continue;
			}

			$schema    = self::convert( $arg );
			$parameter = [
				'name'     => (string) $name,
				'in'       => $in,
				'required' => 'path' === $in || ! empty( $arg['required'] ),
			];

			if ( is_array( $schema ) && isset( $schema['description'] ) ) {
				$parameter['description'] = $schema['description'];
				unset( $schema['description'] );
			}

			$type = is_array( $schema ) ? (array) ( $schema['type'] ?? [] ) : [];

			// PHP collapses repeated `name=a&name=b` query keys, so arrays are sent comma-separated, which WordPress accepts.
			if ( 'query' === $in && in_array( 'array', $type, true ) ) {
				// A comma-separated value can't carry an object, so only the array form is documented.
				if ( in_array( 'object', $type, true ) ) {
					$schema = self::array_form( $schema );
				}

				$parameter['style']   = 'form';
				$parameter['explode'] = false;
			} elseif ( 'query' === $in && in_array( 'object', $type, true ) ) {
				$parameter['style']   = 'deepObject';
				$parameter['explode'] = true;
			}

			$parameter['schema'] = $schema ?: new stdClass();
			$parameters[]        = $parameter;
		}

		return $parameters;
	}

	/**
	 * Narrow a schema that accepts an array or an object to just the array.
	 *
	 * @param array<string, mixed> $schema Converted schema.
	 * @return array<string, mixed>
	 */
	private static function array_form( array $schema ): array {
		foreach ( [ 'oneOf', 'anyOf' ] as $keyword ) {
			foreach ( (array) ( $schema[ $keyword ] ?? [] ) as $branch ) {
				if ( is_array( $branch ) && 'array' === ( $branch['type'] ?? null ) ) {
					unset( $schema['oneOf'], $schema['anyOf'] );

					return array_merge( $schema, $branch );
				}
			}
		}

		unset( $schema['properties'], $schema['additionalProperties'], $schema['patternProperties'] );
		$schema['type'] = 'array';

		return $schema;
	}

	/**
	 * Convert a single keyword's value.
	 *
	 * @param string       $key    Keyword.
	 * @param mixed        $value  Value.
	 * @param array<mixed> $schema The schema the keyword belongs to.
	 * @return mixed Converted value, or null to drop the keyword.
	 */
	private static function convert_keyword( string $key, mixed $value, array $schema ): mixed {
		switch ( $key ) {
			case 'type':
				return self::convert_type( $value );

			case 'properties':
			case 'patternProperties':
				return self::convert_schema_map( $value );

			case 'items':
				if ( ! is_array( $value ) ) {
					return null;
				}

				// A list of schemas is a draft-04 tuple; the first schema is the closest single-schema equivalent.
				if ( array_is_list( $value ) ) {
					return isset( $value[0] ) && is_array( $value[0] ) ? self::convert( $value[0] ) : null;
				}

				return self::convert( $value );

			case 'additionalProperties':
			case 'not':
				if ( is_bool( $value ) ) {
					return $value;
				}

				return is_array( $value ) ? self::convert( $value ) : null;

			case 'allOf':
			case 'anyOf':
			case 'oneOf':
				if ( ! is_array( $value ) ) {
					return null;
				}

				$schemas = array_map(
					fn ( $item ) => self::convert( $item ),
					array_values( array_filter( $value, 'is_array' ) ),
				);

				return $schemas ?: null;

			case 'enum':
				if ( ! is_array( $value ) || ! $value ) {
					return null;
				}

				return array_values( array_unique( $value, SORT_REGULAR ) );

			case 'required':
				// Handled by required_properties() once all properties are known.
				return null;

			case 'exclusiveMinimum':
			case 'exclusiveMaximum':
				if ( ! is_bool( $value ) ) {
					return is_int( $value ) || is_float( $value ) ? $value : null;
				}

				// Draft-04 booleans modify minimum/maximum; 2020-12 expects the bound itself.
				$bound = 'exclusiveMinimum' === $key ? 'minimum' : 'maximum';

				return $value && isset( $schema[ $bound ] ) ? $schema[ $bound ] : null;

			case 'minimum':
			case 'maximum':
				$exclusive = 'minimum' === $key ? 'exclusiveMinimum' : 'exclusiveMaximum';

				if ( true === ( $schema[ $exclusive ] ?? null ) ) {
					return null;
				}

				return is_int( $value ) || is_float( $value ) ? $value : null;

			case 'default':
			case 'const':
			case 'examples':
				return is_object( $value ) && ! $value instanceof stdClass ? null : $value;

			case 'description':
			case 'title':
			case 'format':
			case 'pattern':
			case '$ref':
				return is_string( $value ) && '' !== $value ? $value : null;

			default:
				return $value;
		}
	}

	/**
	 * Normalize a type declaration.
	 *
	 * @param mixed $value Type or list of types.
	 * @return string|list<string>|null
	 */
	private static function convert_type( mixed $value ): string|array|null {
		$types = array_values( array_intersect( array_filter( (array) $value, 'is_string' ), self::TYPES ) );

		return match ( count( $types ) ) {
			0       => null,
			1       => $types[0],
			default => $types,
		};
	}

	/**
	 * Convert a map of property names to schemas.
	 *
	 * @param mixed $value Map of schemas.
	 * @return array<string, mixed>|null
	 */
	private static function convert_schema_map( mixed $value ): ?array {
		if ( ! is_array( $value ) ) {
			return null;
		}

		$output = [];

		foreach ( $value as $name => $schema ) {
			if ( is_array( $schema ) ) {
				$output[ (string) $name ] = self::convert( $schema );
			}
		}

		return $output ?: null;
	}

	/**
	 * Collect required property names, from both a 2020-12 `required` list and
	 * WordPress's per-property `required: true`.
	 *
	 * @param array<mixed> $schema Schema.
	 * @return list<string>
	 */
	private static function required_properties( array $schema ): array {
		$required = [];

		if ( isset( $schema['required'] ) && is_array( $schema['required'] ) ) {
			$required = array_values( array_filter( $schema['required'], 'is_string' ) );
		}

		if ( isset( $schema['properties'] ) && is_array( $schema['properties'] ) ) {
			foreach ( $schema['properties'] as $name => $property ) {
				if ( is_array( $property ) && true === ( $property['required'] ?? null ) ) {
					$required[] = (string) $name;
				}
			}
		}

		return array_values( array_unique( $required ) );
	}
}
