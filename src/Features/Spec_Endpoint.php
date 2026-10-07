<?php
/**
 * Spec_Endpoint class file
 *
 * @package wp-openapi-generator
 */

declare(strict_types=1);

namespace Alley\WP\OpenAPI_Generator\Features;

use Alley\WP\OpenAPI_Generator\Settings;
use Alley\WP\OpenAPI_Generator\Spec_Generator;
use Alley\WP\OpenAPI_Generator\Feature;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * Serves the OpenAPI document from the REST API.
 */
final class Spec_Endpoint implements Feature {
	/**
	 * REST namespace.
	 *
	 * @var string
	 */
	public const NAMESPACE = 'openapi/v1';

	/**
	 * REST route.
	 *
	 * @var string
	 */
	public const ROUTE = '/spec';

	/**
	 * Boot the feature.
	 */
	public function boot(): void {
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
	}

	/**
	 * URL of the spec endpoint.
	 *
	 * @param string[] $namespaces Namespaces to limit the document to.
	 */
	public static function url( array $namespaces = [] ): string {
		$url = rest_url( self::NAMESPACE . self::ROUTE );

		return $namespaces ? add_query_arg( 'namespace', rawurlencode( implode( ',', $namespaces ) ), $url ) : $url;
	}

	/**
	 * Register the REST route.
	 */
	public function register_routes(): void {
		register_rest_route(
			self::NAMESPACE,
			self::ROUTE,
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_spec' ],
					'permission_callback' => [ $this, 'permission_callback' ],
					'args'                => [
						'namespace' => [
							'description' => __( 'Limit the document to these REST namespaces.', 'wp-openapi-generator' ),
							'type'        => 'array',
							'items'       => [ 'type' => 'string' ],
							'default'     => [],
						],
					],
					'openapi'             => [
						'summary' => __( 'Retrieve the OpenAPI document', 'wp-openapi-generator' ),
					],
				],
				'schema' => [ $this, 'get_schema' ],
			]
		);
	}

	/**
	 * Schema of the spec endpoint's response.
	 *
	 * @return array<string, mixed>
	 */
	public function get_schema(): array {
		return [
			'$schema'     => 'http://json-schema.org/draft-04/schema#',
			'title'       => 'openapi-document',
			'description' => __( 'An OpenAPI 3.1 document.', 'wp-openapi-generator' ),
			'type'        => 'object',
			'properties'  => [
				'openapi'    => [ 'type' => 'string' ],
				'info'       => [ 'type' => 'object' ],
				'servers'    => [ 'type' => 'array' ],
				'tags'       => [ 'type' => 'array' ],
				'paths'      => [ 'type' => 'object' ],
				'components' => [ 'type' => 'object' ],
				'security'   => [ 'type' => 'array' ],
			],
		];
	}

	/**
	 * Check that the current user can view the spec.
	 *
	 * @return true|WP_Error
	 */
	public function permission_callback(): bool|WP_Error {
		if ( Settings::current_user_can_view() ) {
			return true;
		}

		return new WP_Error(
			'rest_forbidden',
			__( 'Sorry, you are not allowed to view the OpenAPI document.', 'wp-openapi-generator' ),
			[ 'status' => rest_authorization_required_code() ]
		);
	}

	/**
	 * Generate the spec.
	 *
	 * @param WP_REST_Request $request Request.
	 * @phpstan-param WP_REST_Request<array{namespace?: string[]}> $request
	 */
	public function get_spec( WP_REST_Request $request ): WP_REST_Response {
		$namespaces = array_values( array_filter( (array) $request->get_param( 'namespace' ), 'is_string' ) );

		return new WP_REST_Response( ( new Spec_Generator( rest_get_server(), $namespaces ) )->generate() );
	}
}
