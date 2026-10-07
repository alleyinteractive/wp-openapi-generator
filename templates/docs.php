<?php
/**
 * Swagger UI documentation page.
 *
 * @package wp-openapi-generator
 */

declare(strict_types=1);

use Alley\WP\OpenAPI_Generator\Features\Docs_Page;

?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>
		<?php
		/* translators: %s: site name */
		echo esc_html( sprintf( __( '%s REST API', 'wp-openapi-generator' ), get_bloginfo( 'name' ) ) );
		?>
	</title>
	<?php wp_print_styles( [ Docs_Page::HANDLE ] ); ?>
</head>
<body>
	<div id="swagger-ui"></div>
	<?php wp_print_scripts( [ Docs_Page::HANDLE ] ); ?>
</body>
</html>
