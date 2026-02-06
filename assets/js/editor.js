/**
 * Block editor enhancements for Jetpack Blog Stats block:
 * - Remove block style options (Jetpack registers them in JS).
 * - Add a link to the 1990s Counter settings in the block sidebar.
 * - Show 1990s counter preview in the editor by fetching HTML and hiding default block output.
 */
(function () {
	'use strict';

	var blockName = 'jetpack/blog-stats';
	var createElement = wp.element.createElement;
	var useState = wp.element.useState;
	var useEffect = wp.element.useEffect;

	function fetchPreviewHtml() {
		var formData = new FormData();
		formData.append('action', 'nineties_counter_preview');
		formData.append('nonce', ninetiesCounterEditor.previewNonce);
		return fetch(ninetiesCounterEditor.ajaxUrl, {
			method: 'POST',
			body: formData,
			credentials: 'same-origin',
		})
			.then(function (response) {
				return response.json();
			})
			.then(function (data) {
				return data && data.success && data.data && data.data.html ? data.data.html : '';
			})
			.catch(function () {
				return '';
			});
	}

	// Add settings link and 1990s preview in the block sidebar when Blog Stats block is selected.
	wp.hooks.addFilter('editor.BlockEdit', 'ninetiesCounter/addSettingsLink', function (BlockEdit) {
		return function (props) {
			if (props.name !== blockName) {
				return createElement(BlockEdit, props);
			}

			var previewState = useState('');
			var previewHtml = previewState[0];
			var setPreviewHtml = previewState[1];

			useEffect(function () {
				fetchPreviewHtml().then(setPreviewHtml);
			}, []);

			return createElement(
				'div',
				{ className: 'nineties-counter-editor-wrap' },
				createElement('div', {
					className: 'nineties-counter-editor-preview',
					dangerouslySetInnerHTML: previewHtml ? { __html: previewHtml } : undefined,
				}),
				createElement(BlockEdit, props),
				createElement(
					wp.blockEditor.InspectorControls,
					null,
					createElement(
						wp.components.PanelBody,
						{
							title: '1990s Counter',
							initialOpen: true,
						},
						createElement(
							wp.components.Notice,
							{
								status: 'info',
								isDismissible: false,
								className: 'nineties-counter-editor-notice',
							},
							'Default block styles are being overwritten by the 1990s counter styles.'
						),
						createElement(
							'button',
							{
								type: 'button',
								className: 'components-button is-next-40px-default-size is-secondary',
								onClick: function () {
									window.location.href = ninetiesCounterEditor.settingsUrl;
								},
							},
							'Open 1990s Counter Settings'
						)
					)
				)
			);
		};
	});

	// Unregister block styles so the styles panel is empty.
	wp.domReady(function () {
		setTimeout(function () {
			var blockType = wp.blocks.getBlockType(blockName);
			if (!blockType || !blockType.styles || !blockType.styles.length) {
				return;
			}
			var styleNames = blockType.styles.map(function (s) {
				return s.name;
			});
			styleNames.forEach(function (name) {
				wp.blocks.unregisterBlockStyle(blockName, name);
			});
		}, 0);
	});
})();
