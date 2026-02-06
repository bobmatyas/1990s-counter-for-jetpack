/**
 * Block editor enhancements for Jetpack Blog Stats block:
 * - Remove block style options (Jetpack registers them in JS).
 * - Add a link to the 1990s Counter settings in the block sidebar.
 */
(function () {
	'use strict';

	var blockName = 'jetpack/blog-stats';

	// Add settings link in the block sidebar when Blog Stats block is selected.
	wp.hooks.addFilter('editor.BlockEdit', 'ninetiesCounter/addSettingsLink', function (BlockEdit) {
		return function (props) {
			if (props.name !== blockName) {
				return wp.element.createElement(BlockEdit, props);
			}
			return wp.element.createElement(
				wp.element.Fragment,
				null,
				wp.element.createElement(BlockEdit, props),
				wp.element.createElement(
					wp.blockEditor.InspectorControls,
					null,
					wp.element.createElement(
						wp.components.PanelBody,
						{
							title: '1990s Counter',
							initialOpen: true,
						},
						wp.element.createElement(
							wp.components.Notice,
							{
								status: 'info',
								isDismissible: false,
								className: 'nineties-counter-editor-notice',
							},
							'Default block styles are being overwritten by the 1990s counter styles.'
						),
						wp.element.createElement(
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
