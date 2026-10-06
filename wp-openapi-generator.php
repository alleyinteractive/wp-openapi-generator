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

if ( ! file_exists( __DIR__ . '/vendor/wordpress-autoload.php' ) ) {
	// The dependencies may have been installed by a parent project that loads this plugin as a Composer dependency.
	if ( ! class_exists( \Composer\InstalledVersions::class ) ) {
		\add_action(
			'admin_notices',
			function () {
				?>
				<div class="notice notice-error">
					<p><?php esc_html_e( 'Composer is not installed and wp-openapi-generator cannot load. Try using a `*-built` branch if the plugin is being loaded as a submodule.', 'wp-openapi-generator' ); ?></p>
				</div>
				<?php
			}
		);

		return;
	}
} else {
	require_once __DIR__ . '/vendor/wordpress-autoload.php';
}

require_once __DIR__ . '/src/main.php';

register_activation_hook( __FILE__, [ Features\Docs_Page::class, 'flush_rewrite_rules' ] );

main();
