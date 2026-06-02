<?php
/**
 * GiveWP 3.0 block-form gateway implementation.
 *
 * @package GiveWPCHIP
 */

defined( 'ABSPATH' ) || exit;

use Give\Donations\Models\Donation;
use Give\Donations\Models\DonationNote;
use Give\Framework\PaymentGateways\Commands\GatewayCommand;
use Give\Framework\PaymentGateways\Commands\PaymentRefunded;
use Give\Framework\PaymentGateways\Commands\RedirectOffsite;
use Give\Framework\PaymentGateways\Contracts\PaymentGatewayRefundable;
use Give\Framework\PaymentGateways\Exceptions\PaymentGatewayException;
use Give\Framework\PaymentGateways\PaymentGateway;
use Give\Log\ValueObjects\LogType;

/**
 * CHIP gateway for GiveWP 3.0 Visual Form Builder.
 */
class ChipGateway extends PaymentGateway implements PaymentGatewayRefundable {

	/**
	 * Debug flag.
	 *
	 * @var bool
	 */
	private $debug;

	/**
	 * Whether the gateway script has been loaded.
	 *
	 * @var bool
	 */
	private static bool $script_loaded = false;

	/**
	 * Gateway ID.
	 *
	 * @return string
	 */
	public static function id(): string {
		return 'chip_block';
	}

	/**
	 * Gateway ID (instance method).
	 *
	 * @return string
	 */
	public function getId(): string {
		return self::id();
	}

	/**
	 * Gateway name.
	 *
	 * @return string
	 */
	public function getName(): string {
		return __( 'CHIP', 'chip-for-givewp' );
	}

	/**
	 * Payment method label.
	 *
	 * @return string
	 */
	public function getPaymentMethodLabel(): string {
		return __( 'CHIP', 'chip-for-givewp' );
	}

	/**
	 * Display gateway fields for v2 donation forms.
	 *
	 * @param int   $formId Form ID.
	 * @param array $args   Additional args.
	 * @return string
	 */
	public function getLegacyFormFieldMarkup( int $formId, array $args ): string {
		$customization = give_get_meta( $formId, '_give_customize_chip_donations', true );

		$prefix = '';
		if ( give_is_setting_enabled( $customization ) ) {
			$prefix = '_give_';
		}

		$content = Chip_Givewp_Helper::get_fields( $formId, 'chip-content', $prefix );

		if ( empty( $content ) ) {
			$content = __( 'Complete your donation securely. You will be redirected to CHIP\'s payment page.', 'chip-for-givewp' );
		}

		return '<div class="give-chip-gateway-fields">' . wp_kses_post( $content ) . '</div>';
	}

	/**
	 * Registers a JS file to display gateway fields for v3 donation forms.
	 *
	 * @param int $formId Form ID.
	 */
	public function enqueueScript( int $formId ) {

		// Ensure loaded once.
		if ( self::$script_loaded ) {
			return;
		}

		// Get handle.
		$handle = $this::id();

		// Set script_loaded to TRUE.
		self::$script_loaded = true;

		wp_enqueue_script(
			$handle,
			plugin_dir_url( __FILE__ ) . 'js/chip-gateway.js',
			array( 'react', 'wp-element' ),
			GWP_CHIP_MODULE_VERSION,
			true
		);

		$customization = give_get_meta( $formId, '_give_customize_chip_donations', true );
		$prefix        = give_is_setting_enabled( $customization ) ? '_give_' : '';
		$content       = Chip_Givewp_Helper::get_fields( $formId, 'chip-content', $prefix );

		if ( empty( $content ) ) {
			$content = __( 'Complete your donation securely. You will be redirected to CHIP\'s payment page to finalize your transaction.', 'chip-for-givewp' );
		}

		wp_localize_script(
			$handle,
			'gwp_chip_block',
			array(
				'content' => wp_kses_post( $content ),
			)
		);
	}

	/**
	 * Creates a CHIP payment and redirects offsite.
	 *
	 * @param Donation $donation    Donation model.
	 * @param mixed    $gatewayData Gateway data.
	 * @return RedirectOffsite
	 * @throws PaymentGatewayException On payment creation failure.
	 * @throws \Exception              On API response error (re-thrown as PaymentGatewayException).
	 */
	public function createPayment( Donation $donation, $gatewayData ): RedirectOffsite {

		$form_id = $donation->formId;

		$customization = give_get_meta( $form_id, '_give_customize_chip_donations', true );

		$prefix = '';
		if ( give_is_setting_enabled( $customization ) ) {
			$prefix = '_give_';
		}

		// Assign data.
		$secret_key        = give_is_test_mode() ? Chip_Givewp_Helper::get_fields( $form_id, 'chip-test-secret-key', $prefix ) : Chip_Givewp_Helper::get_fields( $form_id, 'chip-secret-key', $prefix );
		$due_strict        = Chip_Givewp_Helper::get_fields( $form_id, 'chip-due-strict', $prefix );
		$due_strict_timing = Chip_Givewp_Helper::get_fields( $form_id, 'chip-due-strict-timing', $prefix );
		$brand_id          = Chip_Givewp_Helper::get_fields( $form_id, 'chip-brand-id', $prefix );
		$billing_fields    = Chip_Givewp_Helper::get_fields( $form_id, 'chip-enable-billing-fields', $prefix );
		$currency          = give_get_currency( $form_id );

		// Instantiate Chip_Givewp_API.
		$chip = Chip_Givewp_API::get_instance( $secret_key, $brand_id );

		// Pre-fetch and cache public key for webhook verification.
		$public_key = $chip->get_public_key();
		if ( is_string( $public_key ) && '' !== $public_key ) {
			$company_uid = $chip->get_company_uid();
			if ( is_string( $company_uid ) && '' !== $company_uid ) {
				update_option( 'gwp_chip_public_key_' . $company_uid, str_replace( '\n', "\n", $public_key ), false );
			}
		}

		// Instantiate listener.
		$listener = Chip_Givewp_Listener::get_instance();

		// Assign parameter.
		$params = array(
			'success_callback' => $listener->get_callback_url(
				array(
					'donation_id' => $donation->id,
					'status'      => 'paid',
				)
			),
			'success_redirect' => $listener->get_redirect_url( array( 'donation_id' => $donation->id ) ),
			'failure_redirect' => $listener->get_redirect_url(
				array(
					'donation_id' => $donation->id,
					'status'      => 'error',
				)
			),
			'creator_agent'    => 'GiveWP: ' . GWP_CHIP_MODULE_VERSION,
			'reference'        => substr( $donation->id, 0, 128 ),
			'platform'         => 'givewp',
			'due'              => time() + ( absint( $due_strict_timing ) * 60 ),
			'brand_id'         => $brand_id,
			'client'           => array(
				'email'     => $donation->email,
				'full_name' => substr( $donation->firstName . ' ' . $donation->lastName, 0, 30 ),
			),
			'purchase'         => array(
				'timezone'   => apply_filters( 'gwp_chip_purchase_timezone', $this->get_timezone() ),
				'currency'   => $currency,
				'due_strict' => give_is_setting_enabled( $due_strict ),
				'products'   => array(
					array(
						'name'     => substr( $donation->formTitle, 0, 256 ),
						'price'    => round( $donation->amount->getAmount() ),
						'quantity' => '1',
					),
				),
			),
		);

		// Try and catch response from CHIP.
		try {
			$payment = $chip->create_payment( $params );

			if ( ! array_key_exists( 'id', $payment ) ) {
				/* translators: Response from CHIP */
				throw new Exception( sprintf( __( 'CHIP: Something went wrong, please contact the merchant %s', 'chip-for-givewp' ), wp_json_encode( $payment ) ) );
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
		} catch ( \Exception $e ) {
			// When debug mode, display details.
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				throw new PaymentGatewayException( esc_html( $e->getMessage() ) );
			} else {
				throw new PaymentGatewayException( esc_html__( 'CHIP: Something went wrong, please contact the merchant', 'chip-for-givewp' ) );
			}
		}
	}

	/**
	 * Refunds a donation via CHIP.
	 *
	 * @param Donation $donation Donation model.
	 * @return PaymentRefunded
	 * @throws \Exception On refund failure.
	 */
	public function refundDonation( Donation $donation ): PaymentRefunded {

		// Set donation_id and payment_id.
		$donation_id = $donation->id;
		$payment_id  = $donation->gatewayTransactionId;

		// Refund initiated note.
		/* translators: 1: CHIP Transaction ID */
		give_insert_payment_note( $donation_id, sprintf( __( 'Refund initiated for CHIP transaction ID: %1$s', 'chip-for-givewp' ), $payment_id ) );

		try {
			// Get meta key.
			$chip_is_refunded = give_get_payment_meta( $donation_id, 'chip_is_refunded', true );

			$form_id       = give_get_payment_form_id( $donation_id );
			$customization = give_get_meta( $form_id, '_give_customize_chip_donations', true );

			$prefix = '';
			if ( give_is_setting_enabled( $customization ) ) {
				$prefix = '_give_';
			}

			$secret_key = give_is_test_mode() ? Chip_Givewp_Helper::get_fields( $form_id, 'chip-test-secret-key', $prefix ) : Chip_Givewp_Helper::get_fields( $form_id, 'chip-secret-key', $prefix );
			$brand_id   = Chip_Givewp_Helper::get_fields( $form_id, 'chip-brand-id', $prefix );

			// If already refunded.
			if ( 1 === (int) $chip_is_refunded ) {
				throw new Exception( __( 'Donation already refunded in CHIP.', 'chip-for-givewp' ) );
			}

			// Instantiate Chip_Givewp_API.
			$chip = Chip_Givewp_API::get_instance( $secret_key, $brand_id );

			// Refund in CHIP.
			$payment = $chip->refund_payment( $payment_id );

			// CHIP refund unsucessful.
			if ( ! is_array( $payment ) || ! array_key_exists( 'id', $payment ) ) {
				/* translators: CHIP refund_payment API response */
				$msg = sprintf( __( 'There was an error while refunding the payment. Details: %s', 'chip-for-givewp' ), wp_json_encode( $payment ) );
				Chip_Givewp_Helper::log( $donation_id, LogType::ERROR, $msg );
				wp_die( esc_html( $msg ), esc_html__( 'Error', 'chip-for-givewp' ), array( 'response' => 403 ) );
			}

			Chip_Givewp_Helper::log( $donation_id, LogType::HTTP, __( 'Payment refunded.', 'chip-for-givewp' ), $payment );

			give_update_payment_status( $donation_id, 'refunded' );

			$note_id = Give()->comment->db->add(
				array(
					'comment_parent'  => $donation_id,
					'user_id'         => get_current_user_id(),
					/* translators: CHIP Refund Transaction ID */
					'comment_content' => sprintf( __( 'Donation has been refunded with ID: %s', 'chip-for-givewp' ), $payment['id'] ),
					'comment_type'    => 'donation',
				)
			);

			// phpcs:ignore WordPress.NamingConventions.ValidHookName,WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- GiveWP core action for donor note emails.
			do_action( 'give_donor-note_email_notification', $note_id, $donation_id );

		} catch ( \Exception $e ) {
			$message = $e->getMessage();
			throw new Exception( esc_html( $message ) );
		}

		give_get_payment_note_html( $note_id );

		// Return PaymentRefunded with new CHIP refund transaction ID.
		return new PaymentRefunded( $payment['id'] );
	}

	/**
	 * Gets the site timezone string.
	 *
	 * @return string
	 */
	private function get_timezone() {
		if ( preg_match( '/^[A-Za-z]+\/[A-Za-z\_\/\-]+$/', wp_timezone_string() ) ) {
			return wp_timezone_string();
		}

		return 'UTC';
	}
}
