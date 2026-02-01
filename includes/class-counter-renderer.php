<?php
/**
 * Counter Renderer
 *
 * Handles rendering of the 1990s-style hit counter.
 * This is the presentation layer - it only knows how to display an integer.
 *
 * @package NinetiesCounterForJetpack
 */

namespace Nineties_Counter;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Counter_Renderer
 *
 * Presentation layer: transforms an integer into a nostalgic hit counter.
 *
 * Design principles:
 * - Input is always a sanitized integer
 * - Output is static HTML (no JS, no animation)
 * - Zero Jetpack dependencies
 * - Accessible markup
 * - Fixed-width digits with zero padding
 */
class Counter_Renderer {

	/**
	 * Available visual styles.
	 *
	 * @var array
	 */
	const STYLES = array(
		'classic'  => 'Classic LCD (green on black)',
		'retro'    => 'Retro LED (red on black)',
		'vintage'  => 'Vintage Odometer (flip digits)',
		'terminal' => 'Terminal (green phosphor)',
	);

	/**
	 * Plugin settings.
	 *
	 * @var array
	 */
	private $settings;

	/**
	 * Constructor.
	 *
	 * @param array|null $settings Optional settings override.
	 */
	public function __construct( $settings = null ) {
		$this->settings = $settings ?? $this->get_settings();
	}

	/**
	 * Get plugin settings with defaults.
	 *
	 * @return array Settings array.
	 */
	private function get_settings() {
		$defaults = array(
			'digit_count' => 6,
			'style'       => 'classic',
		);

		$saved = get_option( 'nineties_counter_settings', array() );

		return wp_parse_args( $saved, $defaults );
	}

	/**
	 * Render the hit counter HTML.
	 *
	 * @param int $value The stats value to display.
	 * @return string The rendered HTML.
	 */
	public function render( $value ) {
		// Ensure non-negative.
		$display_value = max( 0, $value );

		// Get digit count (minimum 1, maximum 12).
		$digit_count = max( 1, min( 12, (int) $this->settings['digit_count'] ) );

		// Format with zero padding.
		$formatted = $this->format_number( $display_value, $digit_count );

		// Get style class.
		$style = $this->sanitize_style( $this->settings['style'] );

		// Ensure styles are enqueued.
		$this->enqueue_styles();

		// Build the HTML.
		return $this->build_html( $formatted, $value, $style );
	}

	/**
	 * Format the number with zero padding.
	 *
	 * If the number exceeds digit_count, it shows all digits (no truncation).
	 *
	 * @param int $value       The value to format.
	 * @param int $digit_count Minimum digit count for padding.
	 * @return string Formatted number string.
	 */
	private function format_number( $value, $digit_count ) {
		// Convert to string.
		$str = (string) $value;

		// Left-pad with zeros if needed.
		if ( strlen( $str ) < $digit_count ) {
			$str = str_pad( $str, $digit_count, '0', STR_PAD_LEFT );
		}

		return $str;
	}

	/**
	 * Sanitize the style setting.
	 *
	 * @param string $style The requested style.
	 * @return string A valid style name.
	 */
	private function sanitize_style( $style ) {
		if ( array_key_exists( $style, self::STYLES ) ) {
			return $style;
		}

		// Default to classic.
		return 'classic';
	}

	/**
	 * Build the counter HTML structure.
	 *
	 * Structure:
	 * - Outer wrapper with role and label for accessibility
	 * - Inner container for styling
	 * - Individual digit spans for styling control
	 *
	 * @param string $formatted   The formatted digit string.
	 * @param int    $actual      The actual stats value (for screen readers).
	 * @param string $style       The style class name.
	 * @return string Complete HTML.
	 */
	private function build_html( $formatted, $actual, $style ) {
		$digits_html = '';

		// Create individual digit elements for styling.
		$chars = str_split( $formatted );
		foreach ( $chars as $digit ) {
			$digits_html .= sprintf(
				'<span class="nineties-counter__digit" aria-hidden="true">%s</span>',
				esc_html( $digit )
			);
		}

		// Build accessible label.
		$label = sprintf(
			/* translators: %s: number of page views */
			__( '%s page views', '1990s-counter-for-jetpack' ),
			number_format_i18n( $actual )
		);

		// Assemble the complete counter.
		$html = sprintf(
			'<div class="nineties-counter nineties-counter--%s" role="img" aria-label="%s">
				<div class="nineties-counter__display">%s</div>
			</div>',
			esc_attr( $style ),
			esc_attr( $label ),
			$digits_html
		);

		return $html;
	}

	/**
	 * Enqueue counter styles.
	 *
	 * Uses inline styles for simplicity and to avoid additional HTTP requests.
	 * The CSS is minimal and self-contained.
	 *
	 * @return void
	 */
	private function enqueue_styles() {
		// Only enqueue once.
		static $enqueued = false;
		if ( $enqueued ) {
			return;
		}
		$enqueued = true;

		$css_file = NINETIES_COUNTER_PLUGIN_DIR . 'assets/css/counter.css';
		$version  = file_exists( $css_file ) ? (string) filemtime( $css_file ) : NINETIES_COUNTER_VERSION;

		// Register and enqueue the stylesheet.
		wp_register_style(
			'nineties-counter',
			NINETIES_COUNTER_PLUGIN_URL . 'assets/css/counter.css',
			array(),
			$version
		);
		wp_enqueue_style( 'nineties-counter' );
	}

	/**
	 * Get available styles for settings UI.
	 *
	 * @return array Style options.
	 */
	public static function get_available_styles() {
		return self::STYLES;
	}
}
