<?php
/**
 * Plugin Name: Swagger API Documentation Generator
 * Plugin URI: https://github.com/alleyinteractive/wp-swagger-generator
 * Description: Plugin/package to generate Swagger documentation for the WordPress REST API.
 * Version: 0.0.0
 * Author: Sean Fisher
 * Author URI: https://github.com/alleyinteractive/wp-swagger-generator
 * Requires at least: 5.9
 * Tested up to: 7.1.2
 *
 * Text Domain: wp-swagger-generator
 * Domain Path: /languages/
 *
 * @package wp-swagger-generator
 */

namespace Alley\WP\Swagger_Generator;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Root directory to this plugin.
 */
define( 'WP_SWAGGER_GENERATOR_DIR', __DIR__ );

// Check if Composer is installed (remove if Composer is not required for your plugin).
if ( ! file_exists( __DIR__ . '/vendor/wordpress-autoload.php' ) ) {
	// Will also check for the presence of an already loaded Composer autoloader
	// to see if the Composer dependencies have been installed in a parent
	// folder. This is useful for when the plugin is loaded as a Composer
	// dependency in a larger project.
	if ( ! class_exists( \Composer\InstalledVersions::class ) ) {
		\add_action(
			'admin_notices',
			function () {
				?>
				<div class="notice notice-error">
					<p><?php esc_html_e( 'Composer is not installed and wp-swagger-generator cannot load. Try using a `*-built` branch if the plugin is being loaded as a submodule.', 'wp-swagger-generator' ); ?></p>
				</div>
				<?php
			}
		);

		return;
	}
} else {
	// Load Composer dependencies.
	require_once __DIR__ . '/vendor/wordpress-autoload.php';
}

// Load the plugin's main files.
require_once __DIR__ . '/src/meta.php';
require_once __DIR__ . '/src/main.php';

load_scripts();
register_post_meta_from_defs();
main();
