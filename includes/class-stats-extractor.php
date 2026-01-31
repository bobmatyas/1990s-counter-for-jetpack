<?php
/**
 * Stats Extractor
 *
 * Handles extraction of numeric values from Jetpack Blog Stats block HTML.
 * This is the extraction layer - it treats Jetpack output as authoritative.
 *
 * @package NinetiesCounterForJetpack
 */

namespace Nineties_Counter;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Stats_Extractor
 *
 * Extraction layer: parses Jetpack-rendered HTML to extract the stats integer.
 *
 * Design principles:
 * - Jetpack output is the single source of truth
 * - No direct API calls to Jetpack
 * - Defensive parsing that fails safely
 * - Only extract numeric values, ignore labels/icons
 */
class Stats_Extractor {

	/**
	 * Cache key for storing extracted stats.
	 *
	 * @var string
	 */
	const CACHE_KEY = 'nineties_counter_cached_stats';

	/**
	 * Cache duration in seconds (1 hour).
	 *
	 * We cache generously since Jetpack already manages freshness.
	 * Stale data is acceptable per spec.
	 *
	 * @var int
	 */
	const CACHE_DURATION = HOUR_IN_SECONDS;

	/**
	 * Extract the numeric stats value from Jetpack block HTML.
	 *
	 * Strips to text (e.g. "1,142 hits"), takes the first number, with a fallback
	 * to find-all-numbers-then-max. Returns null on failure.
	 *
	 * @param string $html The rendered Jetpack blog stats block HTML.
	 * @return int|null The extracted stats value, or null on failure.
	 */
	public function extract( $html ) {
		// Sanitize input.
		if ( ! is_string( $html ) || empty( trim( $html ) ) ) {
			return null;
		}

		// Try cache first (per-block key so multiple blocks show correct values).
		$cached = $this->get_cached_value( $html );
		if ( null !== $cached ) {
			return $cached;
		}

		// Attempt extraction.
		$value = $this->extract_from_html( $html );

		// Cache successful extraction.
		if ( null !== $value ) {
			$this->cache_value( $html, $value );
		}

		return $value;
	}

	/**
	 * Perform the actual HTML extraction.
	 *
	 * Jetpack Blog Stats block renders as "NUMBER label" (e.g. "1,142 hits", "588 visitors").
	 * We match the first number in the text content, with a fallback to find-all-numbers-then-max.
	 *
	 * @param string $html The block HTML.
	 * @return int|null Extracted value or null.
	 */
	private function extract_from_html( $html ) {
		$text_content = $this->extract_text_content( $html );

		// Primary: first number in content (matches "1,142 hits" / "588 visitors").
		$value = $this->extract_first_number( $text_content );
		if ( null !== $value ) {
			return $value;
		}

		// Fallback: find all numbers and take max (legacy/heuristic).
		return $this->find_stats_number( $text_content );
	}

	/**
	 * Extract the first number (with optional thousands separators) from text.
	 *
	 * Matches Jetpack output like "1,142 hits" or "588 visitors".
	 *
	 * @param string $text Plain text content.
	 * @return int|null Extracted value or null.
	 */
	private function extract_first_number( $text ) {
		if ( preg_match( '/\d{1,3}(?:[,.\s]\d{3})*|\d+/', $text, $matches ) ) {
			$normalized = $this->normalize_number( $matches[0] );
			if ( null !== $normalized ) {
				return $this->sanitize_stats_value( $normalized );
			}
		}
		return null;
	}

	/**
	 * Extract text content from HTML, preserving only text nodes.
	 *
	 * @param string $html The HTML content.
	 * @return string Plain text content.
	 */
	private function extract_text_content( $html ) {
		// Remove script and style contents.
		$html = preg_replace( '/<script[^>]*>.*?<\/script>/is', '', $html );
		$html = preg_replace( '/<style[^>]*>.*?<\/style>/is', '', $html );

		// Strip HTML tags.
		$text = wp_strip_all_tags( $html );

		// Normalize whitespace.
		$text = preg_replace( '/\s+/', ' ', $text );

		return trim( $text );
	}

	/**
	 * Find the stats number from text content.
	 *
	 * The stats value is typically the largest number in the content,
	 * since other numbers (like dates) are usually smaller.
	 *
	 * We also apply heuristics to filter out obvious non-stats numbers.
	 *
	 * @param string $text Plain text content.
	 * @return int|null The likely stats value.
	 */
	private function find_stats_number( $text ) {
		// Find all number sequences (including those with thousands separators).
		// This handles formats like: 1234, 1,234, 1.234 (European format).
		preg_match_all( '/\d{1,3}(?:[,.\s]\d{3})*|\d+/', $text, $matches );

		if ( empty( $matches[0] ) ) {
			return null;
		}

		$candidates = array();

		foreach ( $matches[0] as $match ) {
			$normalized = $this->normalize_number( $match );

			// Skip obviously invalid values.
			if ( null === $normalized ) {
				continue;
			}

			// Skip values that look like years (1900-2099).
			if ( $normalized >= 1900 && $normalized <= 2099 && strlen( (string) $normalized ) === 4 ) {
				continue;
			}

			$candidates[] = $normalized;
		}

		if ( empty( $candidates ) ) {
			return null;
		}

		// Return the largest value as the most likely stats count.
		$value = max( $candidates );

		return $this->sanitize_stats_value( $value );
	}

	/**
	 * Normalize a number string to an integer.
	 *
	 * Handles thousands separators (comma, period, space).
	 *
	 * @param string $number_string The raw number string.
	 * @return int|null Normalized integer or null.
	 */
	private function normalize_number( $number_string ) {
		// Remove thousands separators.
		$cleaned = preg_replace( '/[,.\s]/', '', $number_string );

		// Validate it's purely numeric.
		if ( ! ctype_digit( $cleaned ) ) {
			return null;
		}

		return (int) $cleaned;
	}

	/**
	 * Sanitize and validate the extracted stats value.
	 *
	 * @param mixed $value The raw extracted value.
	 * @return int|null Sanitized integer or null if invalid.
	 */
	private function sanitize_stats_value( $value ) {
		// Convert to integer.
		$int_value = (int) $value;

		// Stats must be non-negative.
		if ( $int_value < 0 ) {
			return null;
		}

		// Sanity check: reject impossibly large values.
		// A trillion views seems like a reasonable upper bound.
		if ( $int_value > 1000000000000 ) {
			return null;
		}

		return $int_value;
	}

	/**
	 * Get cache key for a block's HTML (per-block so multiple blocks keep correct values).
	 *
	 * @param string $html The block HTML.
	 * @return string Transient key.
	 */
	private function get_cache_key( $html ) {
		return self::CACHE_KEY . '_' . md5( $html );
	}

	/**
	 * Get cached stats value for this block's HTML.
	 *
	 * @param string $html The block HTML (used to key the cache).
	 * @return int|null Cached value or null.
	 */
	private function get_cached_value( $html ) {
		$key = $this->get_cache_key( $html );
		$cached = get_transient( $key );

		// Transient returns false if not set or expired.
		if ( false === $cached ) {
			return null;
		}

		// Validate cached value is still a valid integer.
		if ( ! is_numeric( $cached ) ) {
			delete_transient( $key );
			return null;
		}

		return (int) $cached;
	}

	/**
	 * Cache the extracted stats value for this block's HTML.
	 *
	 * Uses WordPress transients for native caching support.
	 * Gracefully handles cache failures - extraction still works.
	 *
	 * @param string $html  The block HTML (used to key the cache).
	 * @param int    $value The value to cache.
	 * @return void
	 */
	private function cache_value( $html, $value ) {
		$key = $this->get_cache_key( $html );
		// Intentionally ignore return value.
		// Cache failure is not a critical error.
		set_transient( $key, $value, self::CACHE_DURATION );
	}

	/**
	 * Clear all cached stats values (all block-specific transients).
	 *
	 * Useful for testing or manual cache invalidation.
	 *
	 * @return void
	 */
	public function clear_cache() {
		global $wpdb;
		$value_prefix = $wpdb->esc_like( '_transient_' . self::CACHE_KEY . '_' ) . '%';
		$timeout_prefix = $wpdb->esc_like( '_transient_timeout_' . self::CACHE_KEY . '_' ) . '%';
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
				$value_prefix,
				$timeout_prefix
			)
		);
	}
}
