<?php
/**
 * Block Interceptor
 *
 * Handles interception of block rendering at the WordPress render pipeline level.
 * Responsible for detecting the Jetpack Blog Stats block and triggering transformation.
 *
 * @package NinetiesCounterForJetpack
 */

namespace Nineties_Counter;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Block_Interceptor
 *
 * Detection layer: intercepts block rendering and identifies the target block.
 * This class owns the decision of whether to transform, not how to transform.
 */
class Block_Interceptor {

	/**
	 * The block name we're targeting.
	 *
	 * This is the only identifier we use for detection.
	 * We never rely on CSS classes, markup structure, or other heuristics.
	 *
	 * @var string
	 */
	const TARGET_BLOCK = 'jetpack/blog-stats';

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
	 * Constructor.
	 *
	 * @param Stats_Extractor  $extractor The stats extraction handler.
	 * @param Counter_Renderer $renderer  The counter rendering handler.
	 */
	public function __construct( Stats_Extractor $extractor, Counter_Renderer $renderer ) {
		$this->extractor = $extractor;
		$this->renderer  = $renderer;
	}

	/**
	 * Register the render filter.
	 *
	 * Uses the block-specific render filter for precise targeting.
	 * This avoids processing every block on the page.
	 *
	 * @return void
	 */
	public function register() {
		// Use the block-specific filter for efficiency.
		// Format: render_block_{block_name}
		// This filter only fires for our target block.
		add_filter(
			'render_block_' . self::TARGET_BLOCK,
			array( $this, 'intercept_render' ),
			10,
			3
		);
	}

	/**
	 * Intercept the block render.
	 *
	 * This is called only for jetpack/blog-stats blocks.
	 * We receive the already-rendered HTML from Jetpack.
	 *
	 * Decision flow:
	 * 1. Verify we're on frontend (not editor/REST)
	 * 2. Attempt extraction
	 * 3. If extraction succeeds, transform
	 * 4. If extraction fails, return original HTML
	 *
	 * @param string   $block_content The rendered block HTML from Jetpack.
	 * @param array    $block         The parsed block array.
	 * @param WP_Block $instance      The block instance.
	 * @return string The original or transformed HTML.
	 */
	public function intercept_render( $block_content, $block, $instance ) {
		// Guard: Only transform on frontend.
		// Do not affect editor previews, REST API responses, or admin contexts.
		if ( $this->is_editor_context() ) {
			return $block_content;
		}

		// Guard: Empty content means nothing to transform.
		if ( empty( $block_content ) ) {
			return $block_content;
		}

		// Attempt to extract the stats value.
		$stats_value = $this->extractor->extract( $block_content );

		// If extraction failed, return original content unchanged.
		// This is the safe failure mode - Jetpack's output remains intact.
		if ( null === $stats_value ) {
			return $block_content;
		}

		// Transform successful - render the hit counter.
		return $this->renderer->render( $stats_value );
	}

	/**
	 * Determine if we're in an editor or non-frontend context.
	 *
	 * We want to transform only on actual page/post views.
	 * Editor previews, REST API, and admin should see original Jetpack output.
	 *
	 * @return bool True if in editor/admin context, false if frontend.
	 */
	private function is_editor_context() {
		// REST API request (includes editor preview requests).
		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return true;
		}

		// Admin context (not frontend).
		if ( is_admin() ) {
			return true;
		}

		// AJAX requests from admin.
		if ( wp_doing_ajax() ) {
			return true;
		}

		// We're on the frontend.
		return false;
	}
}
