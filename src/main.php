<?php
/**
 * The main plugin function
 *
 * @package wp-openapi-generator
 */

declare(strict_types=1);

namespace Alley\WP\OpenAPI_Generator;

use Alley\WP\Features\Group;

/**
 * Instantiate the plugin.
 */
function main(): void {
	$plugin = new Group(
		new Features\Spec_Endpoint(),
		new Features\Docs_Page(),
		new Features\Settings_Page(),
		new Features\CLI_Command(),
	);

	$plugin->boot();
}
