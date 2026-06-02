/**
 * CHIP Form Builder sidebar panel.
 * Adds a link to the existing per-form CHIP settings tab.
 *
 * @package GiveWPCHIP
 */

( function() {
	var addFilter    = wp.hooks.addFilter;
	var el           = wp.element.createElement;
	var Fragment     = wp.element.Fragment;
	var InspectorControls = wp.blockEditor.InspectorControls;
	var PanelBody    = wp.components.PanelBody;
	var Button       = wp.components.Button;
	var __           = wp.i18n.__;

	/**
	 * Wraps the Payment Gateways block edit to add a CHIP settings link.
	 */
	function withChipInspectorControls( BlockEdit ) {
		return function( props ) {
			if ( props.name !== 'givewp/payment-gateways' ) {
				return el( BlockEdit, props );
			}

			return el(
				Fragment,
				null,
				el( BlockEdit, props ),
				el(
					InspectorControls,
					null,
					el(
						PanelBody,
						{ title: __( 'CHIP', 'chip-for-givewp' ) },
						el(
							'p',
							{ className: 'description' },
							__( 'Configure Brand ID, Secret Key, Donation Instructions and Cancel URL for this form.', 'chip-for-givewp' )
						),
						el(
							Button,
							{
								isSecondary: true,
								href: window.gwp_chip_form_builder.settings_url,
								target: '_blank',
							},
							__( 'Open CHIP Settings', 'chip-for-givewp' )
						)
					)
				)
			);
		};
	}

	addFilter(
		'editor.BlockEdit',
		'chip-for-givewp/inspector-controls',
		withChipInspectorControls
	);
} )();
