/**
 * 1990s Counter Admin JavaScript
 *
 * Handles live preview updates on the settings page.
 *
 * @package NinetiesCounterForJetpack
 */

( function() {
	'use strict';

	/**
	 * Initialize the live preview functionality.
	 */
	function init() {
		var styleSelect = document.getElementById( 'style' );
		var digitCountInput = document.getElementById( 'digit_count' );
		var previewContainer = document.querySelector( '.nineties-counter-preview' );

		if ( ! styleSelect || ! previewContainer ) {
			return;
		}

		// Debounce timer for text inputs.
		var debounceTimer;

		/**
		 * Update the preview via AJAX.
		 */
		function updatePreview() {
			var formData = new FormData();
			formData.append( 'action', 'nineties_counter_preview' );
			formData.append( 'nonce', ninetiesCounterAdmin.nonce );
			formData.append( 'style', styleSelect.value );
			formData.append( 'digit_count', digitCountInput ? digitCountInput.value : 6 );

			fetch( ninetiesCounterAdmin.ajaxUrl, {
				method: 'POST',
				body: formData,
				credentials: 'same-origin'
			} )
			.then( function( response ) {
				return response.json();
			} )
			.then( function( data ) {
				if ( data.success && data.data && data.data.html ) {
					// Find the counter element and replace it.
					var existingCounter = previewContainer.querySelector( '.nineties-counter' );
					if ( existingCounter ) {
						existingCounter.outerHTML = data.data.html;
					}
				}
			} )
			.catch( function( error ) {
				console.error( 'Preview update failed:', error );
			} );
		}

		/**
		 * Debounced update for text inputs.
		 */
		function debouncedUpdate() {
			clearTimeout( debounceTimer );
			debounceTimer = setTimeout( updatePreview, 300 );
		}

		// Listen for style dropdown changes.
		styleSelect.addEventListener( 'change', updatePreview );

		// Listen for digit count changes.
		if ( digitCountInput ) {
			digitCountInput.addEventListener( 'input', debouncedUpdate );
		}

	}

	// Initialize when DOM is ready.
	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
} )();
