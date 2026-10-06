<?php
/**
 * Settings_Page class file
 *
 * @package wp-openapi-generator
 */

declare(strict_types=1);

namespace Alley\WP\OpenAPI_Generator\Features;

use Alley\WP\OpenAPI_Generator\Settings;
use Alley\WP\Types\Feature;

/**
 * Settings > OpenAPI admin screen.
 */
final class Settings_Page implements Feature {
	/**
	 * Admin page slug and settings group.
	 *
	 * @var string
	 */
	public const SLUG = 'wp-openapi-generator';

	/**
	 * Boot the feature.
	 */
	public function boot(): void {
		add_action( 'admin_init', [ $this, 'register_settings' ] );
		add_action( 'admin_menu', [ $this, 'add_page' ] );
		add_filter( 'plugin_action_links_' . plugin_basename( WP_OPENAPI_GENERATOR_DIR . '/wp-openapi-generator.php' ), [ $this, 'add_action_link' ] );
	}

	/**
	 * Link to the settings page from the Plugins screen.
	 *
	 * @param mixed $links Plugin action links.
	 * @return mixed
	 */
	public function add_action_link( $links ) {
		if ( is_array( $links ) ) {
			array_unshift(
				$links,
				sprintf(
					'<a href="%s">%s</a>',
					esc_url( admin_url( 'options-general.php?page=' . self::SLUG ) ),
					esc_html__( 'Settings', 'wp-openapi-generator' )
				)
			);
		}

		return $links;
	}

	/**
	 * Register the setting and its fields.
	 */
	public function register_settings(): void {
		register_setting(
			self::SLUG,
			Settings::OPTION,
			[
				'type'              => 'object',
				'default'           => Settings::DEFAULTS,
				'sanitize_callback' => [ Settings::class, 'sanitize' ],
			]
		);

		add_settings_section( 'access', __( 'Access', 'wp-openapi-generator' ), '__return_null', self::SLUG );

		add_settings_field(
			'public',
			__( 'Public documentation', 'wp-openapi-generator' ),
			[ $this, 'render_public_field' ],
			self::SLUG,
			'access',
			[ 'label_for' => 'wp-openapi-generator-public' ]
		);

		add_settings_field(
			'path',
			__( 'Documentation path', 'wp-openapi-generator' ),
			[ $this, 'render_path_field' ],
			self::SLUG,
			'access',
			[ 'label_for' => 'wp-openapi-generator-path' ]
		);
	}

	/**
	 * Add the page under Settings.
	 */
	public function add_page(): void {
		add_options_page(
			__( 'OpenAPI', 'wp-openapi-generator' ),
			__( 'OpenAPI', 'wp-openapi-generator' ),
			'manage_options',
			self::SLUG,
			[ $this, 'render_page' ]
		);
	}

	/**
	 * Render the page.
	 */
	public function render_page(): void {
		$spec_url = add_query_arg( '_wpnonce', wp_create_nonce( 'wp_rest' ), Spec_Endpoint::url() );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'OpenAPI', 'wp-openapi-generator' ); ?></h1>
			<p>
				<a class="button button-primary" href="<?php echo esc_url( Docs_Page::url() ); ?>" target="_blank" rel="noopener">
					<?php esc_html_e( 'View documentation', 'wp-openapi-generator' ); ?>
				</a>
				<a class="button" href="<?php echo esc_url( $spec_url ); ?>" target="_blank" rel="noopener">
					<?php esc_html_e( 'View OpenAPI JSON', 'wp-openapi-generator' ); ?>
				</a>
			</p>
			<form action="options.php" method="post">
				<?php
				settings_fields( self::SLUG );
				do_settings_sections( self::SLUG );
				submit_button();
				?>
			</form>
		</div>
		<?php
	}

	/**
	 * Render the public access checkbox.
	 */
	public function render_public_field(): void {
		?>
		<label>
			<input
				type="checkbox"
				id="wp-openapi-generator-public"
				name="<?php echo esc_attr( Settings::OPTION ); ?>[public]"
				value="1"
				<?php checked( Settings::all()['public'] ); ?>
			/>
			<?php esc_html_e( 'Let anyone view the documentation page and the OpenAPI JSON.', 'wp-openapi-generator' ); ?>
		</label>
		<p class="description">
			<?php esc_html_e( 'When unchecked, only administrators can view them. Endpoints still enforce their own permissions either way.', 'wp-openapi-generator' ); ?>
		</p>
		<?php
	}

	/**
	 * Render the documentation path field.
	 */
	public function render_path_field(): void {
		?>
		<code><?php echo esc_html( trailingslashit( home_url() ) ); ?></code>
		<input
			type="text"
			class="regular-text code"
			id="wp-openapi-generator-path"
			name="<?php echo esc_attr( Settings::OPTION ); ?>[path]"
			value="<?php echo esc_attr( Settings::path() ); ?>"
		/>
		<?php
	}
}
