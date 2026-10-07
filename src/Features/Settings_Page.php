<?php
/**
 * Settings_Page class file
 *
 * @package wp-openapi-generator
 */

declare(strict_types=1);

namespace Alley\WP\OpenAPI_Generator\Features;

use Alley\WP\OpenAPI_Generator\Settings;
use Alley\WP\OpenAPI_Generator\Shape_Audit;
use Alley\WP\OpenAPI_Generator\Spec_Generator;
use Alley\WP\OpenAPI_Generator\Feature;

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

		add_settings_section( 'paths', __( 'Paths', 'wp-openapi-generator' ), '__return_null', self::SLUG );

		add_settings_field(
			'path_filter',
			__( 'Filter mode', 'wp-openapi-generator' ),
			[ $this, 'render_path_filter_field' ],
			self::SLUG,
			'paths'
		);

		add_settings_field(
			'path_patterns',
			__( 'Path patterns', 'wp-openapi-generator' ),
			[ $this, 'render_path_patterns_field' ],
			self::SLUG,
			'paths',
			[ 'label_for' => 'wp-openapi-generator-path-patterns' ]
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
			<?php $this->render_shape_audit(); ?>
		</div>
		<?php
	}

	/**
	 * Render the list of operations with missing request or response shapes.
	 */
	private function render_shape_audit(): void {
		$server     = rest_get_server();
		$skip_paths = array_merge( [ '/' ], array_map( fn ( $namespace ) => '/' . $namespace, $server->get_namespaces() ) );
		$issues     = ( new Shape_Audit( ( new Spec_Generator( $server ) )->generate(), $skip_paths ) )->issues();
		?>
		<h2><?php esc_html_e( 'Missing shapes', 'wp-openapi-generator' ); ?></h2>
		<details>
			<summary>
				<?php
				echo esc_html(
					sprintf(
						/* translators: %d: number of operations */
						_n( '%d operation has an incomplete request or response shape.', '%d operations have an incomplete request or response shape.', count( $issues ), 'wp-openapi-generator' ),
						count( $issues )
					)
				);
				?>
			</summary>
			<p class="description">
				<?php esc_html_e( 'Give routes a schema callback to describe their responses, and give each argument a type and description. You can also fill gaps with the openapi endpoint option or the plugin\'s filters; fixed operations drop off this list.', 'wp-openapi-generator' ); ?>
			</p>
			<?php if ( $issues ) : ?>
				<table class="widefat striped">
					<thead>
						<tr>
							<th scope="col"><?php esc_html_e( 'Method', 'wp-openapi-generator' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Path', 'wp-openapi-generator' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Response schema', 'wp-openapi-generator' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Arguments without a type', 'wp-openapi-generator' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Arguments without a description', 'wp-openapi-generator' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $issues as $issue ) : ?>
							<tr>
								<td><code><?php echo esc_html( $issue['method'] ); ?></code></td>
								<td><code><?php echo esc_html( $issue['path'] ); ?></code></td>
								<td><?php echo $issue['no_response_schema'] ? esc_html__( 'Missing', 'wp-openapi-generator' ) : ''; ?></td>
								<?php foreach ( [ $issue['untyped'], $issue['undescribed'] ] as $names ) : ?>
									<td>
										<?php if ( $names ) : ?>
											<code><?php echo esc_html( implode( ', ', $names ) ); ?></code>
										<?php endif; ?>
									</td>
								<?php endforeach; ?>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</details>
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
	 * Render the allow/deny radio buttons.
	 */
	public function render_path_filter_field(): void {
		$options = [
			'deny'  => __( 'Exclude paths that match a pattern', 'wp-openapi-generator' ),
			'allow' => __( 'Only include paths that match a pattern', 'wp-openapi-generator' ),
		];
		?>
		<fieldset>
			<?php foreach ( $options as $value => $label ) : ?>
				<label>
					<input
						type="radio"
						name="<?php echo esc_attr( Settings::OPTION ); ?>[path_filter]"
						value="<?php echo esc_attr( $value ); ?>"
						<?php checked( Settings::all()['path_filter'], $value ); ?>
					/>
					<?php echo esc_html( $label ); ?>
				</label>
				<br />
			<?php endforeach; ?>
		</fieldset>
		<?php
	}

	/**
	 * Render the path patterns textarea.
	 */
	public function render_path_patterns_field(): void {
		?>
		<textarea
			id="wp-openapi-generator-path-patterns"
			class="large-text code"
			rows="6"
			name="<?php echo esc_attr( Settings::OPTION ); ?>[path_patterns]"
			placeholder="wp/v2/posts*&#10;my-plugin/v1/*"
		><?php echo esc_textarea( implode( "\n", Settings::all()['path_patterns'] ) ); ?></textarea>
		<p class="description">
			<?php esc_html_e( 'One per line. Patterns match the documented path, such as wp/v2/posts/{id}. Use * to match anything, including slashes: wp/v2/posts/* matches every path under wp/v2/posts, and wp/v2/posts* also matches wp/v2/posts itself. Leave empty to include every path.', 'wp-openapi-generator' ); ?>
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
