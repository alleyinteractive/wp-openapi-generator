<?php
/**
 * Plugin Name: OpenAPI Generator
 * Plugin URI: https://github.com/alleyinteractive/wp-openapi-generator
 * Description: Generate an OpenAPI document for the WordPress REST API and browse it with Swagger UI.
 * Version: 0.1.0
 * Author: Sean Fisher
 * Author URI: https://github.com/alleyinteractive/wp-openapi-generator
 * Requires at least: 6.5
 * Requires PHP: 8.1
 * Tested up to: 7.1
 * License: GPL-2.0-or-later
 *
 * Text Domain: wp-openapi-generator
 * Domain Path: /languages/
 *
 * @package wp-openapi-generator
 */

declare(strict_types=1);

namespace Alley\WP\OpenAPI_Generator;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Root directory to this plugin.
 */
define( 'WP_OPENAPI_GENERATOR_DIR', __DIR__ );

if ( file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
	require_once __DIR__ . '/vendor/autoload.php';
}

// With no runtime dependencies, the plugin can load its own classes when Composer hasn't been run.
if ( ! class_exists( Spec_Generator::class ) ) {
	spl_autoload_register(
		function ( string $class_name ): void {
			$prefix = __NAMESPACE__ . '\\';

			if ( str_starts_with( $class_name, $prefix ) ) {
				$file = __DIR__ . '/src/' . str_replace( '\\', '/', substr( $class_name, strlen( $prefix ) ) ) . '.php';

				if ( file_exists( $file ) ) {
					require_once $file; // phpcs:ignore WordPressVIPMinimum.Files.IncludingFile.UsingVariable -- Built from this plugin's own src directory.
				}
			}
		}
	);
}

require_once __DIR__ . '/src/main.php';

register_activation_hook( __FILE__, [ Features\Docs_Page::class, 'flush_rewrite_rules' ] );

main();
