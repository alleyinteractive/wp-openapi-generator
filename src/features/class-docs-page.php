<?php
/**
 * Docs_Page class file
 *
 * @package wp-openapi-generator
 */

declare(strict_types=1);

namespace Alley\WP\OpenAPI_Generator\Features;

use Alley\WP\OpenAPI_Generator\Settings;
use Alley\WP\Types\Feature;

/**
 * Renders Swagger UI for the OpenAPI document on the front end.
 */
final class Docs_Page implements Feature {
	/**
	 * Query var that identifies a documentation request.
	 *
	 * @var string
	 */
	public const QUERY_VAR = 'wp_openapi_generator_docs';

	/**
	 * Script and style handle.
	 *
	 * @var string
	 */
	public const HANDLE = 'wp-openapi-generator-docs';

	/**
	 * Version of swagger-ui-dist loaded from jsDelivr.
	 *
	 * @var string
	 */
	public const SWAGGER_UI_VERSION = '5.33.1';

	/**
	 * Boot the feature.
	 */
	public function boot(): void {
		add_action( 'init', [ $this, 'add_rewrite_rule' ] );
		add_filter( 'query_vars', [ $this, 'add_query_var' ] );
		add_filter( 'template_include', [ $this, 'template_include' ], 99 );
		add_action( 'add_option_' . Settings::OPTION, [ self::class, 'flush_rewrite_rules' ] );
		add_action( 'update_option_' . Settings::OPTION, [ self::class, 'flush_rewrite_rules' ] );
	}

	/**
	 * URL of the documentation page.
	 */
	public static function url(): string {
		if ( get_option( 'permalink_structure' ) ) {
			return home_url( user_trailingslashit( Settings::path() ) );
		}

		return add_query_arg( self::QUERY_VAR, '1', home_url( '/' ) );
	}

	/**
	 * Have WordPress rebuild its rewrite rules on the next request, once the new path is registered.
	 */
	public static function flush_rewrite_rules(): void {
		delete_option( 'rewrite_rules' );
	}

	/**
	 * Add the rewrite rule for the documentation page.
	 */
	public function add_rewrite_rule(): void {
		add_rewrite_rule( '^' . Settings::path() . '/?$', 'index.php?' . self::QUERY_VAR . '=1', 'top' );
	}

	/**
	 * Register the query var.
	 *
	 * @param mixed $vars Query vars.
	 * @return mixed
	 */
	public function add_query_var( $vars ) {
		if ( is_array( $vars ) ) {
			$vars[] = self::QUERY_VAR;
		}

		return $vars;
	}

	/**
	 * Swap in the documentation template.
	 *
	 * @param mixed $template Template path.
	 * @return mixed
	 */
	public function template_include( $template ) {
		if ( ! get_query_var( self::QUERY_VAR ) ) {
			return $template;
		}

		if ( ! Settings::current_user_can_view() ) {
			global $wp_query;

			if ( $wp_query instanceof \WP_Query ) {
				$wp_query->set_404();
			}
			status_header( 404 );
			nocache_headers();

			return get_404_template() ?: $template;
		}

		status_header( 200 );
		$this->register_assets();

		return dirname( __DIR__, 2 ) . '/templates/docs.php';
	}

	/**
	 * Register Swagger UI and the script that boots it.
	 */
	private function register_assets(): void {
		/**
		 * Filters the base URL that swagger-ui-dist assets are loaded from.
		 *
		 * @param string $base_url Base URL, without a trailing slash.
		 */
		$base_url = apply_filters(
			'wp_openapi_generator_swagger_ui_url',
			'https://cdn.jsdelivr.net/npm/swagger-ui-dist@' . self::SWAGGER_UI_VERSION
		);
		$base_url = untrailingslashit( $base_url );

		wp_register_style( 'swagger-ui', $base_url . '/swagger-ui.css', [], self::SWAGGER_UI_VERSION );
		wp_register_style( self::HANDLE, false, [ 'swagger-ui' ], self::SWAGGER_UI_VERSION );
		wp_add_inline_style( self::HANDLE, 'body { margin: 0; }' );

		wp_register_script( 'swagger-ui-bundle', $base_url . '/swagger-ui-bundle.js', [], self::SWAGGER_UI_VERSION, true );
		wp_register_script( 'swagger-ui-standalone-preset', $base_url . '/swagger-ui-standalone-preset.js', [], self::SWAGGER_UI_VERSION, true );
		wp_register_script( self::HANDLE, false, [ 'swagger-ui-bundle', 'swagger-ui-standalone-preset' ], self::SWAGGER_UI_VERSION, true );
		wp_add_inline_script(
			self::HANDLE,
			'window.wpOpenApiGenerator = ' . wp_json_encode( $this->config() ) . ';' . <<<'JS'
(function (config) {
	window.ui = SwaggerUIBundle({
		dom_id: '#swagger-ui',
		urls: config.urls,
		deepLinking: true,
		filter: true,
		presets: [SwaggerUIBundle.presets.apis, SwaggerUIStandalonePreset],
		layout: 'StandaloneLayout',
		requestInterceptor: function (request) {
			if (config.nonce && new URL(request.url, window.location.href).origin === window.location.origin) {
				request.headers['X-WP-Nonce'] = config.nonce;
			}
			return request;
		},
	});
})(window.wpOpenApiGenerator);
JS
		);
	}

	/**
	 * Configuration passed to the Swagger UI boot script.
	 *
	 * @return array{urls: list<array{url: string, name: string}>, nonce: string}
	 */
	private function config(): array {
		$urls = [
			[
				'url'  => Spec_Endpoint::url(),
				'name' => __( 'All namespaces', 'wp-openapi-generator' ),
			],
		];

		foreach ( rest_get_server()->get_namespaces() as $namespace ) {
			$urls[] = [
				'url'  => Spec_Endpoint::url( [ $namespace ] ),
				'name' => $namespace,
			];
		}

		return [
			'urls'  => $urls,
			'nonce' => is_user_logged_in() ? wp_create_nonce( 'wp_rest' ) : '',
		];
	}
}
