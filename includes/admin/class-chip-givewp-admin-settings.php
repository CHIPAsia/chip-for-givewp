<?php
/**
 * Base settings field definitions.
 *
 * @package GiveWPCHIP
 */

defined( 'ABSPATH' ) || exit;

/**
 * Base admin settings fields (shared by global and per-form settings).
 */
abstract class Chip_Givewp_Admin_Settings {

	/**
	 * Returns the CHIP settings fields array.
	 *
	 * @param string $prefix Optional meta prefix for per-form settings.
	 * @return array
	 */
	public function setting_fields( $prefix = '' ) {
		$array = array(
			// Section: Credentials.
			array(
				'id'   => 'give_title_chip_credentials',
				'type' => 'title',
			),
			array(
				'name' => __( 'Secret Key', 'chip-for-givewp' ),
				'desc' => __( 'Enter your Secret Key, found in your CHIP Dashboard.', 'chip-for-givewp' ),
				'id'   => $prefix . 'chip-secret-key',
				'type' => 'text',
			),
			array(
				'name' => __( 'Test Secret Key', 'chip-for-givewp' ),
				'desc' => __( 'Enter your Test Secret Key, found in your CHIP Dashboard. When you enabled test mode in GiveWP, this key will be used.', 'chip-for-givewp' ),
				'id'   => $prefix . 'chip-test-secret-key',
				'type' => 'text',
			),
			array(
				'name' => __( 'Brand ID', 'chip-for-givewp' ),
				'desc' => __( 'Enter your Brand ID, found in your CHIP Dashboard.', 'chip-for-givewp' ),
				'id'   => $prefix . 'chip-brand-id',
				'type' => 'text',
			),
			array(
				'id'   => 'give_title_chip_credentials',
				'type' => 'sectionend',
			),

			// Section: Donation Display.
			array(
				'id'   => 'give_title_chip_display',
				'type' => 'title',
			),
			array(
				'name'    => __( 'Donation Instructions', 'chip-for-givewp' ),
				'desc'    => __( 'The Donation Instructions are a chance for you to educate the donor on how to best submit donations. These instructions appear directly on the form, and after submission of the form. Note: You may also customize the instructions on individual forms as needed.', 'chip-for-givewp' ),
				'id'      => $prefix . 'chip-content',
				'default' => 'Complete your donation securely. You will be redirected to CHIP\'s payment page to finalize your transaction.',
				'type'    => 'wysiwyg',
				'options' => array(
					'textarea_rows' => 6,
				),
			),
			array(
				'name'    => __( 'Collect Billing Details', 'chip-for-givewp' ),
				'desc'    => __( 'If enabled, required billing address fields are added to Donation forms. These fields are not required to process the transaction, but you may have a need to collect the data. Billing address details are added to both the donation and donor record in GiveWP. ', 'chip-for-givewp' ),
				'id'      => $prefix . 'chip-enable-billing-fields',
				'type'    => 'radio_inline',
				'default' => 'disabled',
				'options' => array(
					'enabled'  => __( 'Enabled', 'chip-for-givewp' ),
					'disabled' => __( 'Disabled', 'chip-for-givewp' ),
				),
			),
			array(
				'id'   => 'give_title_chip_display',
				'type' => 'sectionend',
			),

			// Section: Payment Timing.
			array(
				'id'   => 'give_title_chip_timing',
				'type' => 'title',
			),
			array(
				'name'    => __( 'Due Strict', 'chip-for-givewp' ),
				'desc'    => __( "Whether to permit payments when Purchase's due has passed.", 'chip-for-givewp' ),
				'id'      => $prefix . 'chip-due-strict',
				'type'    => 'radio_inline',
				'default' => 'disabled',
				'options' => array(
					'enabled'  => __( 'Enabled', 'chip-for-givewp' ),
					'disabled' => __( 'Disabled', 'chip-for-givewp' ),
				),
			),
			array(
				'name'    => __( 'Due Strict Timing (minutes)', 'chip-for-givewp' ),
				'desc'    => __( 'Set timeframe allowed for a payment to be made.', 'chip-for-givewp' ),
				'id'      => $prefix . 'chip-due-strict-timing',
				'default' => '60',
				'type'    => 'number',
			),
			array(
				'id'   => 'give_title_chip_timing',
				'type' => 'sectionend',
			),

			// Section: Redirects.
			array(
				'id'   => 'give_title_chip_redirects',
				'type' => 'title',
			),
			array(
				'name' => __( 'Success URL', 'chip-for-givewp' ),
				'desc' => __( 'Redirect to a custom URL when the payment is successful. Leaving this blank will redirect to the default donation confirmation page.', 'chip-for-givewp' ),
				'id'   => $prefix . 'chip-success-url',
				'type' => 'text',
			),
			array(
				'name' => __( 'Cancel URL', 'chip-for-givewp' ),
				'desc' => __( 'Redirect to a custom URL when the customer cancels. Leaving this blank will redirect back to the donation form.', 'chip-for-givewp' ),
				'id'   => $prefix . 'chip-cancel-url',
				'type' => 'text',
			),
			array(
				'id'   => 'give_title_chip_redirects',
				'type' => 'sectionend',
			),

			// Section: Payment Methods.
			array(
				'id'   => 'give_title_chip_payment_methods',
				'type' => 'title',
			),
			array(
				'name'    => __( 'Payment Method Whitelist', 'chip-for-givewp' ),
				'desc'    => __( 'Restrict the available payment methods on CHIP checkout. Leave all unchecked to allow all methods supported by your brand.', 'chip-for-givewp' ),
				'id'      => $prefix . 'chip-payment-method-whitelist',
				'type'    => 'multicheck',
				'options' => array(
					'fpx'             => __( 'FPX', 'chip-for-givewp' ),
					'fpx_b2b1'        => __( 'FPX B2B1', 'chip-for-givewp' ),
					'crypto_coin'     => __( 'Crypto', 'chip-for-givewp' ),
					'dnqr'            => __( 'DuitNow QR', 'chip-for-givewp' ),
					'duitnow_qr'      => __( 'DuitNow QR (Legacy)', 'chip-for-givewp' ),
					'cards'           => __( 'Cards (Visa, Mastercard, Maestro)', 'chip-for-givewp' ),
					'mpgs_apple_pay'  => __( 'Apple Pay', 'chip-for-givewp' ),
					'mpgs_google_pay' => __( 'Google Pay', 'chip-for-givewp' ),
					'razer_atome'     => __( 'Atome', 'chip-for-givewp' ),
					'razer_grabpay'   => __( 'GrabPay', 'chip-for-givewp' ),
					'razer_maybankqr' => __( 'Maybank QR', 'chip-for-givewp' ),
					'razer_shopeepay' => __( 'ShopeePay', 'chip-for-givewp' ),
					'razer_tng'       => __( 'Touch \'n Go', 'chip-for-givewp' ),
					'shopee_pay'      => __( 'Shopee Pay', 'chip-for-givewp' ),
				),
			),
			array(
				'id'   => 'give_title_chip_payment_methods',
				'type' => 'sectionend',
			),
		);

		if ( ! empty( $prefix ) ) {
			$count = count( $array );
			for ( $i = 0; $i < $count; $i++ ) {
				$array[ $i ]['row_classes'] = 'give-subfield give-hidden';
			}
		}

		return apply_filters( 'gwp_chip_setting_fields', $array );
	}
}
