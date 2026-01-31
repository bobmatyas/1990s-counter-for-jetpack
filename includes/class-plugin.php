<?php
/**
 * Plugin Main Class
 *
 * Orchestrates the plugin components and manages initialization.
 *
 * @package NinetiesCounterForJetpack
 */

namespace Nineties_Counter;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Plugin
 *
 * Main plugin class that wires together all components.
 * Responsible for initialization and settings management.
 */
class Plugin {

	/**
	 * Block interceptor instance.
	 *
	 * @var Block_Interceptor
	 */
	private $interceptor;

	/**
	 * Stats extractor instance.
	 *
	 * @var Stats_Extractor
	 */
	private $extractor;

	/**
	 * Counter renderer instance.
	 *
	 * @var Counter_Renderer
	 */
	private $renderer;

	/**
	 * Initialize the plugin.
	 *
	 * Sets up all components and hooks.
	 *
	 * @return void
	 */
	public function init() {
		// Create component instances.
		$this->extractor = new Stats_Extractor();
		$this->renderer  = new Counter_Renderer();

		// Create interceptor with dependencies.
		$this->interceptor = new Block_Interceptor(
			$this->extractor,
			$this->renderer
		);

		// Register the render filter.
		$this->interceptor->register();

		// Register admin hooks if in admin context.
		if ( is_admin() ) {
			$this->init_admin();
		}
	}

	/**
	 * Initialize admin functionality.
	 *
	 * Registers settings page and admin hooks.
	 *
	 * @return void
	 */
	private function init_admin() {
		add_action( 'admin_menu', array( $this, 'add_settings_page' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );
		add_action( 'wp_ajax_nineties_counter_preview', array( $this, 'ajax_preview' ) );
	}

	/**
	 * Add the settings page to the admin menu.
	 *
	 * @return void
	 */
	public function add_settings_page() {
		add_options_page(
			__( '1990s Counter Settings', '1990s-counter-for-jetpack' ),
			__( '1990s Counter', '1990s-counter-for-jetpack' ),
			'manage_options',
			'nineties-counter',
			array( $this, 'render_settings_page' )
		);
	}

	/**
	 * Register plugin settings.
	 *
	 * @return void
	 */
	public function register_settings() {
		register_setting(
			'nineties_counter_settings_group',
			'nineties_counter_settings',
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize_settings' ),
				'default'           => array(
					'digit_count' => 6,
					'style'       => 'classic',
				),
			)
		);

		// Settings section.
		add_settings_section(
			'nineties_counter_main',
			__( 'Counter Settings', '1990s-counter-for-jetpack' ),
			array( $this, 'render_settings_section' ),
			'nineties-counter'
		);

		// Digit count field.
		add_settings_field(
			'digit_count',
			__( 'Digit Count', '1990s-counter-for-jetpack' ),
			array( $this, 'render_digit_count_field' ),
			'nineties-counter',
			'nineties_counter_main'
		);

		// Style field.
		add_settings_field(
			'style',
			__( 'Visual Style', '1990s-counter-for-jetpack' ),
			array( $this, 'render_style_field' ),
			'nineties-counter',
			'nineties_counter_main'
		);
	}

	/**
	 * Sanitize settings input.
	 *
	 * @param array $input Raw input from form.
	 * @return array Sanitized settings.
	 */
	public function sanitize_settings( $input ) {
		$sanitized = array();

		// Digit count: 1-12.
		$sanitized['digit_count'] = isset( $input['digit_count'] )
			? max( 1, min( 12, absint( $input['digit_count'] ) ) )
			: 6;

		// Style: must be a valid style key.
		$valid_styles        = array_keys( Counter_Renderer::get_available_styles() );
		$sanitized['style']  = isset( $input['style'] ) && in_array( $input['style'], $valid_styles, true )
			? $input['style']
			: 'classic';

		// Clear cache when settings change.
		$this->extractor->clear_cache();

		return $sanitized;
	}

	/**
	 * Render the settings page.
	 *
	 * @return void
	 */
	public function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

			<?php if ( ! nineties_counter_is_jetpack_active() ) : ?>
				<div class="notice notice-warning">
					<p>
						<?php esc_html_e( 'Jetpack is not currently active. This plugin transforms the Jetpack Blog Stats block and requires Jetpack to function.', '1990s-counter-for-jetpack' ); ?>
					</p>
				</div>
			<?php endif; ?>

			<form action="options.php" method="post">
				<?php
				settings_fields( 'nineties_counter_settings_group' );
				do_settings_sections( 'nineties-counter' );
				submit_button();
				?>
			</form>

			<div class="nineties-counter-preview">
				<h2><?php esc_html_e( 'Preview', '1990s-counter-for-jetpack' ); ?></h2>
				<p><?php esc_html_e( 'This is how your counter will appear:', '1990s-counter-for-jetpack' ); ?></p>
				<?php
				$preview_renderer = new Counter_Renderer();
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer output is escaped internally.
				echo $preview_renderer->render( 12345 );
				?>
			</div>
		</div>
		<?php
	}

	/**
	 * Render the settings section description.
	 *
	 * @return void
	 */
	public function render_settings_section() {
		?>
		<p>
			<?php esc_html_e( 'Configure how the 1990s hit counter appears on your site.', '1990s-counter-for-jetpack' ); ?>
		</p>
		<?php
	}

	/**
	 * Render the digit count field.
	 *
	 * @return void
	 */
	public function render_digit_count_field() {
		$settings = get_option( 'nineties_counter_settings', array() );
		$value    = isset( $settings['digit_count'] ) ? $settings['digit_count'] : 6;
		?>
		<input
			type="number"
			id="digit_count"
			name="nineties_counter_settings[digit_count]"
			value="<?php echo esc_attr( $value ); ?>"
			min="1"
			max="12"
			class="small-text"
		>
		<p class="description">
			<?php esc_html_e( 'Minimum number of digits to display (1-12). Numbers are left-padded with zeros.', '1990s-counter-for-jetpack' ); ?>
		</p>
		<?php
	}

	/**
	 * Render the style field.
	 *
	 * @return void
	 */
	public function render_style_field() {
		$settings = get_option( 'nineties_counter_settings', array() );
		$current  = isset( $settings['style'] ) ? $settings['style'] : 'classic';
		$styles   = Counter_Renderer::get_available_styles();
		?>
		<select
			id="style"
			name="nineties_counter_settings[style]"
		>
			<?php foreach ( $styles as $key => $label ) : ?>
				<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $current, $key ); ?>>
					<?php echo esc_html( $label ); ?>
				</option>
			<?php endforeach; ?>
		</select>
		<p class="description">
			<?php esc_html_e( 'Choose the visual style for your hit counter.', '1990s-counter-for-jetpack' ); ?>
		</p>
		<?php
	}

	/**
	 * Enqueue admin scripts for the settings page.
	 *
	 * @param string $hook_suffix The current admin page hook suffix.
	 * @return void
	 */
	public function enqueue_admin_scripts( $hook_suffix ) {
		// Only load on our settings page.
		if ( 'settings_page_nineties-counter' !== $hook_suffix ) {
			return;
		}

		wp_enqueue_script(
			'nineties-counter-admin',
			NINETIES_COUNTER_PLUGIN_URL . 'assets/js/admin.js',
			array(),
			NINETIES_COUNTER_VERSION,
			true
		);

		wp_localize_script(
			'nineties-counter-admin',
			'ninetiesCounterAdmin',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'nineties_counter_preview' ),
			)
		);
	}

	/**
	 * Handle AJAX request for live preview.
	 *
	 * @return void
	 */
	public function ajax_preview() {
		// Verify nonce.
		if ( ! check_ajax_referer( 'nineties_counter_preview', 'nonce', false ) ) {
			wp_send_json_error( 'Invalid nonce' );
		}

		// Verify capabilities.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Insufficient permissions' );
		}

		// Get and sanitize parameters.
		$style       = isset( $_POST['style'] ) ? sanitize_text_field( wp_unslash( $_POST['style'] ) ) : 'classic';
		$digit_count = isset( $_POST['digit_count'] ) ? absint( $_POST['digit_count'] ) : 6;

		// Validate style.
		$valid_styles = array_keys( Counter_Renderer::get_available_styles() );
		if ( ! in_array( $style, $valid_styles, true ) ) {
			$style = 'classic';
		}

		// Validate digit count.
		$digit_count = max( 1, min( 12, $digit_count ) );

		// Create renderer with preview settings.
		$preview_settings = array(
			'style'       => $style,
			'digit_count' => $digit_count,
		);

		$renderer = new Counter_Renderer( $preview_settings );
		$html     = $renderer->render( 12345 );

		wp_send_json_success( array( 'html' => $html ) );
	}
}
