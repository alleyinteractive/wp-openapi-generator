<?php
/**
 * CLI_Command class file
 *
 * @package wp-openapi-generator
 */

declare(strict_types=1);

namespace Alley\WP\OpenAPI_Generator\Features;

use Alley\WP\OpenAPI_Generator\Spec_Generator;
use Alley\WP\OpenAPI_Generator\Feature;
use WP_CLI;

/**
 * `wp openapi` WP-CLI command.
 */
final class CLI_Command implements Feature {
	/**
	 * Boot the feature.
	 */
	public function boot(): void {
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			WP_CLI::add_command( 'openapi generate', [ $this, 'generate' ] );
		}
	}

	/**
	 * Generate an OpenAPI document for the REST API.
	 *
	 * ## OPTIONS
	 *
	 * [--namespace=<namespace>]
	 * : Limit the document to one or more comma-separated REST namespaces.
	 *
	 * [--output=<file>]
	 * : Write the document to a file instead of STDOUT.
	 *
	 * [--compact]
	 * : Output minified JSON.
	 *
	 * ## EXAMPLES
	 *
	 *     wp openapi generate --namespace=wp/v2 --output=openapi.json
	 *
	 * @param string[]              $args       Positional arguments.
	 * @param array<string, string> $assoc_args Associative arguments.
	 */
	public function generate( array $args, array $assoc_args ): void {
		$namespaces = array_values( array_filter( array_map( 'trim', explode( ',', $assoc_args['namespace'] ?? '' ) ) ) );
		$spec       = ( new Spec_Generator( rest_get_server(), $namespaces ) )->generate();
		$flags      = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | ( isset( $assoc_args['compact'] ) ? 0 : JSON_PRETTY_PRINT );
		$json       = (string) wp_json_encode( $spec, $flags );

		if ( empty( $assoc_args['output'] ) ) {
			WP_CLI::line( $json );
			return;
		}

		// phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_file_put_contents
		if ( false === file_put_contents( $assoc_args['output'], $json . "\n" ) ) {
			WP_CLI::error( "Could not write to {$assoc_args['output']}." );
		}

		WP_CLI::success( "OpenAPI document written to {$assoc_args['output']}." );
	}
}
