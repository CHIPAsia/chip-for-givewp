<?php
use Give\Framework\PaymentGateways\Commands\PaymentRefunded;
use Give\Framework\PaymentGateways\PaymentGateway;
use Give\Donations\Models\Donation;
use Give\Framework\PaymentGateways\Commands\RedirectOffsite;
use Give\Framework\PaymentGateways\Exceptions\PaymentGatewayException;
use Give\Log\ValueObjects\LogType;

class ChipGateway extends PaymentGateway {
	private $debug;

	private static bool $script_loaded = false;

	public static function id(): string {
		return 'chip_block';
	}

	public function getId(): string {
		return self::id();
	}

	public function getName(): string {
		return __( 'CHIP', 'chip-for-givewp' );
	}

	public function getPaymentMethodLabel(): string {
		return __( 'CHIP', 'chip-for-givewp' );
	}

	/**
	 * Display gateway fields for v2 donation forms
	 */
	public function getLegacyFormFieldMarkup( $formId, $args ) {
		return "<div class=''>
            <p>You will be redirected to CHIP Payment Gateway</p>
        </div>";
	}

	/**
	 * Register a js file to display gateway fields for v3 donation forms
	 */
	public function enqueueScript( int $formId ) {

		// Ensure loaded once
		if ( self::$script_loaded ) {
			return;
		}

		// Get handle
		$handle = $this::id();

		// Set script_loaded to TRUE
		self::$script_loaded = true;

		wp_enqueue_script(
			$handle,
			plugin_dir_url( __FILE__ ) . 'js/chip-gateway.js',
			[ 'react', 'wp-element' ],
			'1.0.0',
			true );
	}

	public function createPayment( Donation $donation, $gatewayData ): RedirectOffsite {

		$form_id = $donation->formId;

		$customization = give_get_meta( $form_id, '_give_customize_chip_donations', true );

		$prefix = '';
		if ( give_is_setting_enabled( $customization ) ) {
			$prefix = '_give_';
		}

		// Assign data
		$secret_key = give_is_test_mode() ? Chip_Givewp_Helper::get_fields( $form_id, 'chip-test-secret-key', $prefix ) : Chip_Givewp_Helper::get_fields( $form_id, 'chip-secret-key', $prefix );
		$due_strict = Chip_Givewp_Helper::get_fields( $form_id, 'chip-due-strict', $prefix );
		$due_strict_timing = Chip_Givewp_Helper::get_fields( $form_id, 'chip-due-strict-timing', $prefix );
		$send_receipt = Chip_Givewp_Helper::get_fields( $form_id, 'chip-send-receipt', $prefix );
		$brand_id = Chip_Givewp_Helper::get_fields( $form_id, 'chip-brand-id', $prefix );
		$billing_fields = Chip_Givewp_Helper::get_fields( $form_id, 'chip-enable-billing-fields', $prefix );
		$currency = give_get_currency( $form_id );

		// Instantiate Chip_Givewp_API
		$chip = Chip_Givewp_API::get_instance( $secret_key, $brand_id );

		// Instantiate listener 
		$listener = Chip_Givewp_Listener::get_instance();

		// Assign parameter
		$params = array(
			'success_callback' => $listener->get_callback_url( array( 'donation_id' => $donation->id, 'status' => 'paid' ) ),
			'success_redirect' => $listener->get_redirect_url( array( 'donation_id' => $donation->id ) ),
			'failure_redirect' => $listener->get_redirect_url( array( 'donation_id' => $donation->id, 'status' => 'error' ) ),
			'creator_agent' => 'GiveWP: ' . GWP_CHIP_MODULE_VERSION,
			'reference' => substr( $donation->id, 0, 128 ),
			'platform' => 'givewp',
			'send_receipt' => give_is_setting_enabled( $send_receipt ),
			'due' => time() + ( absint( $due_strict_timing ) * 60 ),
			'brand_id' => $brand_id,
			'client' => [ 
				'email' => $donation->email,
				'full_name' => substr( $donation->firstName . ' ' . $donation->lastName, 0, 30 ),
			],
			'purchase' => array(
				'timezone' => apply_filters( 'gwp_chip_purchase_timezone', $this->get_timezone() ),
				'currency' => $currency,
				'due_strict' => give_is_setting_enabled( $due_strict ),
				'products' => array( [ 
					'name' => substr( $donation->formTitle, 0, 256 ), //substr(give_payment_gateway_item_title($payment_data), 0, 256),
					'price' => round( $donation->amount->getAmount() ),
					'quantity' => '1',
				] ),
			),
		);

		// Try and catch response from CHIP
		try {
			$payment = $chip->create_payment( $params );

			if ( ! array_key_exists( 'id', $payment ) ) {
				/* translators: Response from CHIP */
				throw new Exception(sprintf(__('CHIP: Something went wrong, please contact the merchant %s', 'chip-for-givewp'), wp_json_encode($payment)));
			}

			/* translators: 1: Donation ID */
			Chip_Givewp_Helper::log( $form_id, LogType::HTTP, sprintf( __( 'Create purchases success for donation id %1$s', 'chip-for-givewp' ), $donation->id ), $payment );

			give_update_meta( $donation->id, '_chip_purchase_id', $payment['id'], '', 'donation' );

			if ( give_is_test_mode() ) {
				give_insert_payment_note( $donation->id, __( 'This is test environment where payment status is simulated.', 'chip-for-givewp' ) );
			}
			/* translators: 1: CHIP Checkout URL */
			give_insert_payment_note( $donation->id, sprintf( __( 'URL: %1$s', 'chip-for-givewp' ), $payment['checkout_url'] ) );

			return new RedirectOffsite( $payment['checkout_url'] );
		} catch (\Exception $e) {
			// When debug mode, display details
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				$status_message = $e->getMessage();
			} else {
				$status_message = esc_html__('CHIP: Something went wrong, please contact the merchant', 'chip-for-givewp' );
			}

			throw new PaymentGatewayException( $status_message );
		}
	}

	public function refundDonation( Donation $donation ): PaymentRefunded {

		// Set donation_id and payment_id
		$donation_id = $donation->id;
		$payment_id = $donation->gatewayTransactionId;

		// Refund initiated note
		/* translators: 1: CHIP Transaction ID */
		give_insert_payment_note( $donation_id, sprintf( __( 'Refund initiated for CHIP transaction ID: %1$s', 'chip-for-givewp' ), $payment_id ) );

		try {
			// Get meta key
			$chip_is_refunded = give_get_payment_meta( $donation_id, 'chip_is_refunded' . true );

			// Get settings for CHIP
			$give_settings = give_get_settings();

			$secret_key = give_is_test_mode() ? $give_settings['chip-test-secret-key'] : $give_settings['chip-secret-key'];
			$brand_id = $give_settings['chip-brand-id'];

			//  If already refunded
			if ( $chip_is_refunded == 1 ) {
				throw new Exception( __( 'Donation already refunded in CHIP.', 'chip-for-givewp' ) );
			}

			// Instantiate Chip_Givewp_API
			$chip = Chip_Givewp_API::get_instance( $secret_key, $brand_id );

			// Refund in CHIP
			$payment = $chip->refund_payment( $payment_id );

			// CHIP refund unsucessful
			if ( ! is_array( $payment ) || ! array_key_exists( 'id', $payment ) ) {
				/* translators: CHIP refund_payment API response */
				$msg = sprintf( __( 'There was an error while refunding the payment. Details: %s', 'chip-for-givewp' ), wp_json_encode( $payment, true ) );
				Chip_Givewp_Helper::log( $donation_id, LogType::ERROR, $msg );
				wp_die( esc_html($msg), esc_html__( 'Error', 'chip-for-givewp' ), array( 'response' => 403 ) );
			}

			Chip_Givewp_Helper::log( $donation_id, LogType::HTTP, __( 'Payment refunded.', 'chip-for-givewp' ), $payment );

			give_update_payment_status( $donation_id, 'refunded' );

			$note_id = Give()->comment->db->add(
				array(
					'comment_parent' => $donation_id,
					'user_id' => get_current_user_id(),
					/* translators: CHIP Refund Transaction ID */
					'comment_content' => sprintf( __( 'Donation has been refunded with ID: %s', 'chip-for-givewp' ), $payment['id'] ),
					'comment_type' => 'donation',
				)
			);

			do_action( 'give_donor-note_email_notification', $note_id, $donation_id );

		} catch (\Exception $e) {
			$message = $e->getMessage();
			throw new Exception( esc_html($message) );
		}

		give_get_payment_note_html( $note_id );

		// Return PaymentRefunded with new CHIP refund transaction ID
		return new PaymentRefunded( $payment['id'] );
	}

	/**
	 * Get Timezone
	 * @return string
	 */
	private function get_timezone() {
		if ( preg_match( '/^[A-z]+\/[A-z\_\/\-]+$/', wp_timezone_string() ) ) {
			return wp_timezone_string();
		}

		return 'UTC';
	}
}