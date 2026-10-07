<?php
/**
 * Remove the plugin's data when it is deleted.
 *
 * @package wp-openapi-generator
 */

declare(strict_types=1);

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'wp_openapi_generator_settings' );
