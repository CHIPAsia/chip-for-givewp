<?php
/**
 * Enqueues the CHIP form builder sidebar script.
 *
 * @package GiveWPCHIP
 */

defined( 'ABSPATH' ) || exit;

/**
 * Adds a CHIP settings link in the Visual Form Builder sidebar.
 */
class Chip_Givewp_Enqueue_Form_Builder_Scripts {

	/**
	 * Enqueues the form builder script on the GiveWP Form Builder screen.
	 */
	public function __invoke() {
		// Build the URL to the per-form CHIP settings tab.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Only reading donationFormID for URL construction.
		$form_id = isset( $_GET['donationFormID'] ) ? absint( $_GET['donationFormID'] ) : 0;

		if ( ! $form_id ) {
			return;
		}

		$settings_url = admin_url(
			sprintf(
				'edit.php?post_type=give_forms&page=give-settings&tab=gateways&section=chip-settings&form_id=%d',
				$form_id
			)
		);

		$handle = 'chip-for-givewp-form-builder';

		wp_enqueue_script(
			$handle,
			plugins_url( 'includes/block/resources/form-builder/chip-gateway-form-builder.js', GWP_CHIP_FILE ),
			array( 'wp-hooks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-i18n' ),
			GWP_CHIP_MODULE_VERSION,
			true
		);

		wp_localize_script(
			$handle,
			'gwp_chip_form_builder',
			array(
				'settings_url' => $settings_url,
			)
		);
	}
}
