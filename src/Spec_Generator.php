<?php
/**
 * Spec_Generator class file
 *
 * @package wp-openapi-generator
 */

declare(strict_types=1);

namespace Alley\WP\OpenAPI_Generator;

use stdClass;
use WP_REST_Attachments_Controller;
use WP_REST_Server;

/**
 * Builds an OpenAPI 3.1 document from the routes registered with a REST server.
 */
final class Spec_Generator {
	/**
	 * OpenAPI version of the generated document.
	 *
	 * @var string
	 */
	public const OPENAPI_VERSION = '3.1.0';

	/**
	 * HTTP methods that become OpenAPI operations.
	 *
	 * @var string[]
	 */
	private const METHODS = [ 'get', 'post', 'put', 'patch', 'delete' ];

	/**
	 * Resource schemas collected while generating, keyed by component name.
	 *
	 * @var array<string, array<string, mixed>>
	 */
	private array $schemas = [];

	/**
	 * Operation IDs already in use.
	 *
	 * @var array<string, true>
	 */
	private array $operation_ids = [];

	/**
	 * Tag names already in use.
	 *
	 * @var array<string, true>
	 */
	private array $tags = [];

	/**
	 * Namespace of each path that has an operation, keyed by path.
	 *
	 * @var array<string, string>
	 */
	private array $path_namespaces = [];

	/**
	 * Constructor.
	 *
	 * @param WP_REST_Server $server     REST server to read routes from.
	 * @param string[]       $namespaces Namespaces to include, or empty for all.
	 */
	public function __construct(
		private readonly WP_REST_Server $server,
		private readonly array $namespaces = [],
	) {}

	/**
	 * Generate the OpenAPI document.
	 *
	 * @return array<string, mixed>
	 */
	public function generate(): array {
		$this->schemas         = [];
		$this->operation_ids   = [];
		$this->tags            = [];
		$this->path_namespaces = [];

		$paths     = [];
		$templates = [];

		foreach ( $this->server->get_routes() as $route => $handlers ) {
			$handlers  = array_values( array_filter( (array) $handlers, 'is_array' ) );
			$options   = $this->server->get_route_options( $route ) ?? [];
			$namespace = is_string( $options['namespace'] ?? null ) ? $options['namespace'] : '';

			if ( $this->namespaces && ! in_array( $namespace, $this->namespaces, true ) ) {
				continue;
			}

			$route_path = Route_Path::from_route( $route );

			/**
			 * Filters whether a route is included in the OpenAPI document.
			 *
			 * @param bool                $include  Whether to include the route. Defaults to whether it is shown in the REST index.
			 * @param string              $route    Route regex.
			 * @param array<array<mixed>> $handlers Route handlers.
			 * @param array<mixed>        $options  Route options.
			 * @param string              $path     OpenAPI path, such as `/wp/v2/posts/{id}`.
			 */
			if ( ! apply_filters( 'wp_openapi_generator_include_route', $this->is_shown_in_index( $handlers, $options ), $route, $handlers, $options, $route_path->path ) ) {
				continue;
			}

			$template = (string) preg_replace( '/\{[^}]+\}/', '{}', $route_path->path );

			// OpenAPI cannot hold two paths that differ only by parameter names, so the first route registered wins.
			if ( isset( $templates[ $template ] ) && $templates[ $template ] !== $route_path->path ) {
				continue;
			}

			$templates[ $template ] = $route_path->path;

			$schema_ref       = $this->register_route_schema( $route, $options );
			$has_list_handler = (bool) array_filter( $handlers, [ $this, 'is_list_handler' ] );

			foreach ( $handlers as $handler ) {
				if ( false === ( $handler['openapi'] ?? null ) ) {
					continue;
				}

				$methods = is_array( $handler['methods'] ?? null ) ? $handler['methods'] : [];

				foreach ( array_keys( $methods ) as $method ) {
					$method = strtolower( (string) $method );

					if ( ! in_array( $method, self::METHODS, true ) || isset( $paths[ $route_path->path ][ $method ] ) ) {
						continue;
					}

					$operation = $this->build_operation( $method, $route, $namespace, $route_path, $handler, $schema_ref, $has_list_handler );

					/**
					 * Filters a single OpenAPI operation.
					 *
					 * @param array<string, mixed> $operation OpenAPI operation object. Return an empty array to omit the operation.
					 * @param string               $method    Lowercase HTTP method.
					 * @param string               $route     Route regex.
					 * @param array<mixed>         $handler   Route handler.
					 */
					$operation = apply_filters( 'wp_openapi_generator_operation', $operation, $method, $route, $handler );

					if ( $operation ) {
						$paths[ $route_path->path ][ $method ] = $operation;

						$this->path_namespaces[ $route_path->path ] = $namespace;
					}
				}
			}
		}

		ksort( $paths );
		ksort( $this->tags );

		$security_schemes = $this->security_schemes();

		$spec = [
			'openapi'    => self::OPENAPI_VERSION,
			'info'       => $this->info(),
			'servers'    => $this->servers(),
			'tags'       => array_map( fn ( $tag ) => [ 'name' => $tag ], array_keys( $this->tags ) ),
			'paths'      => $paths ?: new stdClass(),
			'components' => [
				'schemas'         => array_merge( [ 'WP_Error' => self::error_schema() ], $this->schemas ),
				'responses'       => [
					'Error' => [
						'description' => __( 'Error response.', 'wp-openapi-generator' ),
						'content'     => [
							'application/json' => [
								'schema' => [ '$ref' => '#/components/schemas/WP_Error' ],
							],
						],
					],
				],
				'securitySchemes' => $security_schemes,
			],
			'security'   => $this->security( array_keys( $security_schemes ) ),
		];

		if ( ! $spec['components']['securitySchemes'] ) {
			unset( $spec['components']['securitySchemes'] );
		}

		if ( ! $spec['security'] ) {
			unset( $spec['security'] );
		}

		/**
		 * Filters the generated OpenAPI document.
		 *
		 * @param array<string, mixed> $spec       OpenAPI document.
		 * @param string[]             $namespaces Namespaces the document was limited to, or empty for all.
		 */
		return apply_filters( 'wp_openapi_generator_spec', $spec, $this->namespaces );
	}

	/**
	 * Namespace of each path in the most recently generated document, keyed by path.
	 *
	 * @return array<string, string>
	 */
	public function path_namespaces(): array {
		return $this->path_namespaces;
	}

	/**
	 * Build an OpenAPI operation for one method of a route handler.
	 *
	 * @param string       $method           Lowercase HTTP method.
	 * @param string       $route            Route regex.
	 * @param string       $namespace        Route namespace.
	 * @param Route_Path   $route_path       Parsed route.
	 * @param array<mixed> $handler          Route handler.
	 * @param string|null  $schema_ref       Reference to the route's resource schema.
	 * @param bool         $has_list_handler Whether the route lists a collection of resources.
	 * @return array<string, mixed>
	 */
	private function build_operation(
		string $method,
		string $route,
		string $namespace,
		Route_Path $route_path,
		array $handler,
		?string $schema_ref,
		bool $has_list_handler,
	): array {
		$args = is_array( $handler['args'] ?? null ) ? $handler['args'] : [];

		/**
		 * Filters an endpoint's arguments, in WordPress format, before they become parameters or a request body.
		 *
		 * @param array<mixed> $args    Arguments keyed by name, as passed to register_rest_route().
		 * @param string       $method  Lowercase HTTP method.
		 * @param string       $route   Route regex.
		 * @param array<mixed> $handler Route handler.
		 */
		$args       = apply_filters( 'wp_openapi_generator_endpoint_args', $args, $method, $route, $handler );
		$other_args = array_diff_key( $args, $route_path->parameters );
		$is_list    = 'get' === $method && $this->is_list_handler( $handler );
		$is_create  = 'post' === $method && $has_list_handler && ! str_ends_with( $route_path->path, '}' );

		$operation = [
			'operationId' => $this->operation_id( $method, $route_path->path ),
			'summary'     => $this->summary( $method, $namespace, $route_path, $schema_ref, $is_list, $is_create ),
			'tags'        => [ $this->tag( $namespace, $route_path->path ) ],
		];

		$parameters = $this->path_parameters( $route_path, $args );

		if ( in_array( $method, [ 'get', 'delete' ], true ) ) {
			$parameters = array_merge( $parameters, Schema_Converter::args_to_parameters( $other_args ) );
		} elseif ( $other_args ) {
			$body_schema = Schema_Converter::args_to_object_schema( $other_args );

			$operation['requestBody'] = [
				'required' => ! empty( $body_schema['required'] ),
				'content'  => [
					'application/json' => [ 'schema' => $body_schema ],
				],
			];

			// Creating media requires a file, and Swagger UI fills every optional form field with placeholder values that fail validation, so only the file is documented.
			if ( $is_create && $this->is_attachment_handler( $handler ) ) {
				$operation['requestBody'] = [
					'required' => true,
					'content'  => [ 'multipart/form-data' => [ 'schema' => self::file_upload_schema() ] ],
				];
			}
		}

		if ( $parameters ) {
			$operation['parameters'] = $parameters;
		}

		$responses = [
			$is_create ? '201' : '200' => $this->success_response( $schema_ref, $is_list, $is_create, 'delete' === $method && isset( $args['force'] ) ),
			'default'                  => [ '$ref' => '#/components/responses/Error' ],
		];

		/**
		 * Filters an operation's responses, keyed by HTTP status code.
		 *
		 * @param array<int|string, mixed> $responses  OpenAPI responses object.
		 * @param string                   $method     Lowercase HTTP method.
		 * @param string                   $route      Route regex.
		 * @param array<mixed>             $handler    Route handler.
		 * @param string|null              $schema_ref Reference to the route's resource schema, such as `#/components/schemas/post`.
		 */
		$operation['responses'] = apply_filters( 'wp_openapi_generator_responses', $responses, $method, $route, $handler, $schema_ref );

		if ( isset( $handler['openapi'] ) && is_array( $handler['openapi'] ) ) {
			foreach ( $handler['openapi'] as $key => $value ) {
				$operation[ (string) $key ] = $value;
			}
		}

		return $operation;
	}

	/**
	 * Build path parameter objects for a route.
	 *
	 * @param Route_Path   $route_path Parsed route.
	 * @param array<mixed> $args       Handler arguments.
	 * @return list<array<string, mixed>>
	 */
	private function path_parameters( Route_Path $route_path, array $args ): array {
		$path_args = [];

		foreach ( $route_path->parameters as $name => $pattern ) {
			$arg = is_array( $args[ $name ] ?? null ) ? $args[ $name ] : [];

			if ( ! isset( $arg['type'] ) ) {
				$arg['type'] = 'string';
			}

			if ( 'string' === $arg['type'] && ! isset( $arg['pattern'] ) && ! isset( $arg['enum'] ) && '' !== $pattern ) {
				$arg['pattern'] = '^' . str_replace( '(?P<', '(?<', $pattern ) . '$';
			}

			$path_args[ $name ] = $arg;
		}

		return Schema_Converter::args_to_parameters( $path_args, 'path' );
	}

	/**
	 * Build the success response object.
	 *
	 * @param string|null $schema_ref Reference to the route's resource schema.
	 * @param bool        $is_list    Whether the response is a list of resources.
	 * @param bool        $is_create  Whether the operation creates a resource.
	 * @param bool        $can_force  Whether the operation is a delete that accepts `force`.
	 * @return array<string, mixed>
	 */
	private function success_response( ?string $schema_ref, bool $is_list, bool $is_create, bool $can_force ): array {
		$schema = $schema_ref ? [ '$ref' => $schema_ref ] : new stdClass();

		// Core controllers return the trashed item, or `{deleted, previous}` when the item is permanently deleted.
		if ( $can_force && $schema_ref ) {
			$schema = [
				'anyOf' => [
					$schema,
					[
						'type'       => 'object',
						'properties' => [
							'deleted'  => [ 'type' => 'boolean' ],
							'previous' => [ '$ref' => $schema_ref ],
						],
					],
				],
			];
		}

		if ( $is_list ) {
			$schema = [
				'type'  => 'array',
				'items' => $schema,
			];
		}

		$response = [
			'description' => $is_create ? __( 'Created.', 'wp-openapi-generator' ) : __( 'Successful response.', 'wp-openapi-generator' ),
			'content'     => [
				'application/json' => [ 'schema' => $schema ],
			],
		];

		if ( $is_list ) {
			$response['headers'] = [
				'X-WP-Total'      => [
					'description' => __( 'Total number of items.', 'wp-openapi-generator' ),
					'schema'      => [ 'type' => 'integer' ],
				],
				'X-WP-TotalPages' => [
					'description' => __( 'Total number of pages.', 'wp-openapi-generator' ),
					'schema'      => [ 'type' => 'integer' ],
				],
			];
		}

		return $response;
	}

	/**
	 * Register a route's resource schema as a reusable component.
	 *
	 * @param string       $route   Route regex.
	 * @param array<mixed> $options Route options.
	 * @return string|null Reference to the component, or null when the route has no schema.
	 */
	private function register_route_schema( string $route, array $options ): ?string {
		$schema = isset( $options['schema'] ) && is_callable( $options['schema'] ) ? call_user_func( $options['schema'] ) : null;

		/**
		 * Filters a route's resource schema, in WordPress format, before it is converted. Return null to leave the route without one.
		 *
		 * @param array<mixed>|null $schema  Schema returned by the route's `schema` callback, or null if it has none.
		 * @param string            $route   Route regex.
		 * @param array<mixed>      $options Route options.
		 */
		$schema = apply_filters( 'wp_openapi_generator_route_schema', is_array( $schema ) ? $schema : null, $route, $options );

		if ( ! is_array( $schema ) || ! $schema ) {
			return null;
		}

		$converted = Schema_Converter::convert( $schema );

		if ( ! is_array( $converted ) ) {
			return null;
		}

		$title = is_string( $schema['title'] ?? null ) ? $schema['title'] : 'schema';
		$base  = trim( (string) preg_replace( '/[^A-Za-z0-9._-]+/', '-', $title ), '-' ) ?: 'schema';
		$name  = $base;

		// phpcs:ignore Universal.Operators.StrictComparisons.LooseNotEqual -- Loose comparison so equal stdClass instances match.
		for ( $i = 2; isset( $this->schemas[ $name ] ) && $this->schemas[ $name ] != $converted; $i++ ) {
			$name = "{$base}-{$i}";
		}

		$this->schemas[ $name ] = $converted;

		return '#/components/schemas/' . $name;
	}

	/**
	 * Whether a route appears in the REST index.
	 *
	 * @param array<array<mixed>> $handlers Route handlers.
	 * @param array<mixed>        $options  Route options.
	 */
	private function is_shown_in_index( array $handlers, array $options ): bool {
		if ( isset( $options['show_in_index'] ) && ! $options['show_in_index'] ) {
			return false;
		}

		foreach ( $handlers as $handler ) {
			if ( ! empty( $handler['show_in_index'] ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Whether a handler is served by the core media controller, which accepts file uploads.
	 *
	 * @param array<mixed> $handler Route handler.
	 */
	private function is_attachment_handler( array $handler ): bool {
		$callback = $handler['callback'] ?? null;

		return is_array( $callback ) && ( $callback[0] ?? null ) instanceof WP_REST_Attachments_Controller;
	}

	/**
	 * Request body schema for uploading a file.
	 *
	 * @return array<string, mixed>
	 */
	private static function file_upload_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'file' => [
					'type'             => 'string',
					'format'           => 'binary',
					'contentMediaType' => 'application/octet-stream',
					'description'      => __( 'The file to upload. Set other fields, such as the title or alt text, with a follow-up update request.', 'wp-openapi-generator' ),
				],
			],
			'required'   => [ 'file' ],
		];
	}

	/**
	 * Whether a handler lists a paginated collection.
	 *
	 * @param array<mixed> $handler Route handler.
	 */
	private function is_list_handler( array $handler ): bool {
		$methods = is_array( $handler['methods'] ?? null ) ? $handler['methods'] : [];
		$args    = is_array( $handler['args'] ?? null ) ? $handler['args'] : [];

		return ! empty( $methods['GET'] ) && ( isset( $args['page'] ) || isset( $args['per_page'] ) );
	}

	/**
	 * Build a unique operation ID.
	 *
	 * @param string $method Lowercase HTTP method.
	 * @param string $path   OpenAPI path.
	 */
	private function operation_id( string $method, string $path ): string {
		$base = $method . '_' . ( trim( (string) preg_replace( '/[^A-Za-z0-9]+/', '_', $path ), '_' ) ?: 'index' );
		$id   = $base;

		for ( $i = 2; isset( $this->operation_ids[ $id ] ); $i++ ) {
			$id = "{$base}_{$i}";
		}

		$this->operation_ids[ $id ] = true;

		return $id;
	}

	/**
	 * Build a tag that groups operations by namespace and resource, such as `wp/v2/posts`.
	 *
	 * @param string $namespace Route namespace.
	 * @param string $path      OpenAPI path.
	 */
	private function tag( string $namespace, string $path ): string {
		if ( '' === $namespace ) {
			$tag = 'index';
		} else {
			$remainder = trim( substr( $path, strlen( '/' . $namespace ) ), '/' );
			$segment   = explode( '/', $remainder )[0];
			$tag       = '' === $segment || str_starts_with( $segment, '{' ) ? $namespace : "{$namespace}/{$segment}";
		}

		$this->tags[ $tag ] = true;

		return $tag;
	}

	/**
	 * Build a short human-readable summary for an operation.
	 *
	 * @param string      $method     Lowercase HTTP method.
	 * @param string      $namespace  Route namespace.
	 * @param Route_Path  $route_path Parsed route.
	 * @param string|null $schema_ref Reference to the route's resource schema.
	 * @param bool        $is_list    Whether the operation lists resources.
	 * @param bool        $is_create  Whether the operation creates a resource.
	 */
	private function summary( string $method, string $namespace, Route_Path $route_path, ?string $schema_ref, bool $is_list, bool $is_create ): string {
		if ( '/' === $route_path->path ) {
			return __( 'Retrieve the API index', 'wp-openapi-generator' );
		}

		if ( '/' . $namespace === $route_path->path ) {
			/* translators: %s: REST namespace */
			return sprintf( __( 'Retrieve the %s namespace index', 'wp-openapi-generator' ), $namespace );
		}

		$segments = array_filter(
			explode( '/', $route_path->path ),
			fn ( $segment ) => '' !== $segment && ! str_starts_with( $segment, '{' ) && ! preg_match( '/^v?\d+(\.\d+)*$/', $segment ),
		);
		$segment  = str_replace( [ '-', '_' ], ' ', (string) end( $segments ) );

		if ( $is_list ) {
			/* translators: %s: resource name */
			return sprintf( __( 'List %s', 'wp-openapi-generator' ), $segment );
		}

		$component = $schema_ref ? substr( $schema_ref, strlen( '#/components/schemas/' ) ) : '';
		$title     = $this->schemas[ $component ]['title'] ?? null;
		$noun      = is_string( $title ) ? str_replace( [ '-', '_' ], ' ', $title ) : $segment;

		return match ( true ) {
			/* translators: %s: resource name */
			$is_create         => sprintf( __( 'Create %s', 'wp-openapi-generator' ), $noun ),
			/* translators: %s: resource name */
			'get' === $method    => sprintf( __( 'Retrieve %s', 'wp-openapi-generator' ), $noun ),
			/* translators: %s: resource name */
			'delete' === $method => sprintf( __( 'Delete %s', 'wp-openapi-generator' ), $noun ),
			/* translators: %s: resource name */
			default              => sprintf( __( 'Update %s', 'wp-openapi-generator' ), $noun ),
		};
	}

	/**
	 * Build the document's server list.
	 *
	 * @return list<array<string, mixed>>
	 */
	private function servers(): array {
		/**
		 * Filters the servers the API is served from.
		 *
		 * @param list<array<string, mixed>> $servers OpenAPI server objects. Defaults to the site's REST URL.
		 */
		return apply_filters( 'wp_openapi_generator_servers', [ [ 'url' => untrailingslashit( rest_url() ) ] ] );
	}

	/**
	 * Build the document's security schemes.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	private function security_schemes(): array {
		$schemes = [
			'applicationPassword' => [
				'type'        => 'http',
				'scheme'      => 'basic',
				'description' => __( 'A WordPress username and application password.', 'wp-openapi-generator' ),
			],
			'cookieNonce'         => [
				'type'        => 'apiKey',
				'in'          => 'header',
				'name'        => 'X-WP-Nonce',
				'description' => __( 'A "wp_rest" nonce, used alongside the logged-in user\'s cookies.', 'wp-openapi-generator' ),
			],
		];

		/**
		 * Filters the security schemes, keyed by name.
		 *
		 * @param array<string, array<string, mixed>> $schemes OpenAPI security scheme objects.
		 */
		return apply_filters( 'wp_openapi_generator_security_schemes', $schemes );
	}

	/**
	 * Build the document-wide security requirements.
	 *
	 * @param string[] $scheme_names Names of the declared security schemes.
	 * @return list<array<string, list<string>>|stdClass>
	 */
	private function security( array $scheme_names ): array {
		$security = [ new stdClass() ];

		foreach ( $scheme_names as $name ) {
			$security[] = [ $name => [] ];
		}

		/**
		 * Filters the document-wide security requirements. Any one requirement is enough; the empty object allows anonymous requests.
		 *
		 * @param list<array<string, list<string>>|stdClass> $security OpenAPI security requirement objects.
		 */
		return apply_filters( 'wp_openapi_generator_security', $security );
	}

	/**
	 * Build the document's info object.
	 *
	 * @return array<string, mixed>
	 */
	private function info(): array {
		$info = [
			/* translators: %s: site name */
			'title'   => sprintf( __( '%s REST API', 'wp-openapi-generator' ), get_bloginfo( 'name' ) ?: 'WordPress' ),
			'version' => (string) get_bloginfo( 'version' ),
		];

		$description = (string) get_bloginfo( 'description' );

		if ( '' !== $description ) {
			$info['description'] = $description;
		}

		/**
		 * Filters the document's info object: title, version, description, contact, license, and so on.
		 *
		 * @param array<string, mixed> $info OpenAPI info object.
		 */
		return apply_filters( 'wp_openapi_generator_info', $info );
	}

	/**
	 * Schema of the error body WordPress returns for a WP_Error.
	 *
	 * @return array<string, mixed>
	 */
	private static function error_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'code'    => [ 'type' => 'string' ],
				'message' => [ 'type' => 'string' ],
				'data'    => [
					'type'       => 'object',
					'properties' => [
						'status' => [ 'type' => 'integer' ],
					],
				],
			],
			'required'   => [ 'code', 'message' ],
		];
	}
}
