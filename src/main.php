<?php
/**
 * The main plugin function
 *
 * @package wp-openapi-generator
 */

declare(strict_types=1);

namespace Alley\WP\OpenAPI_Generator;

/**
 * Instantiate the plugin.
 */
function main(): void {
	$features = [
		new Features\Spec_Endpoint(),
		new Features\Path_Filter(),
		new Features\Docs_Page(),
		new Features\Settings_Page(),
		new Features\CLI_Command(),
	];

	foreach ( $features as $feature ) {
		$feature->boot();
	}
}
