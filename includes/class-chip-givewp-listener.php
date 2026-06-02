<?php
/**
 * CHIP callback and redirect listener.
 *
 * @package GiveWPCHIP
 */

defined( 'ABSPATH' ) || exit;

use Give\Log\ValueObjects\LogType;

/**
 * Handles CHIP webhook callbacks and customer redirects.
 */
class Chip_Givewp_Listener {

	/**
	 * Single instance of the class.
	 *
	 * @var Chip_Givewp_Listener|null
	 */
	private static $instance;

	const CALLBACK_KEY        = 'chip-for-givewp-callback';
	const CALLBACK_PASSPHRASE = 'chip-for-givewp-webhook';

	const REDIRECT_KEY        = 'chip-for-givewp-redirect';
	const REDIRECT_PASSPHRASE = 'chip-for-givewp-redirect';

	/**
	 * Gets the single instance of the class.
	 *
	 * @return Chip_Givewp_Listener
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'handle_callback' ) );
		add_action( 'init', array( $this, 'handle_redirect' ) );
	}

	/**
	 * Builds the callback URL for a donation.
	 *
	 * @param array $params Query parameters.
	 * @return string
	 */
	public function get_callback_url( array $params ) {

		$passphrase = get_option( self::CALLBACK_PASSPHRASE, false );
		if ( ! $passphrase ) {
			$passphrase = md5( site_url() . time() );
			update_option( self::CALLBACK_PASSPHRASE, $passphrase );
		}

		$params[ self::CALLBACK_KEY ] = $passphrase;

		return add_query_arg( $params, site_url( '/' ) );
	}

	/**
	 * Builds the redirect URL for a donation.
	 *
	 * @param array $params Query parameters.
	 * @return string
	 */
	public function get_redirect_url( $params ) {
		$params[ self::REDIRECT_KEY ] = self::REDIRECT_PASSPHRASE;
		return add_query_arg( $params, site_url( '/' ) );
	}

	/**
	 * Handles the customer redirect after payment.
	 */
	public function handle_redirect() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- CHIP redirect has no nonce; validated via passphrase.
		if ( ! isset( $_GET[ self::REDIRECT_KEY ] ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- CHIP redirect has no nonce; validated via passphrase.
		if ( self::REDIRECT_PASSPHRASE !== $_GET[ self::REDIRECT_KEY ] ) {
			return;
		}

		Chip_Givewp_Helper::log( null, LogType::INFO, __( 'Redirect received', 'chip-for-givewp' ) );

		$this->handle_processing();
	}

	/**
	 * Handles the CHIP webhook callback.
	 */
	public function handle_callback() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- CHIP webhook has no nonce; validated via shared passphrase.
		if ( ! isset( $_GET[ self::CALLBACK_KEY ] ) ) {
			return;
		}

		$passphrase = get_option( self::CALLBACK_PASSPHRASE, false );
		if ( ! $passphrase ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- CHIP webhook has no nonce; validated via shared passphrase.
		if ( $passphrase !== $_GET[ self::CALLBACK_KEY ] ) {
			/* translators: 1: Callback failed */
			Chip_Givewp_Helper::log( null, LogType::NOTICE, __( 'Callback failed due to invalid passphrase: %1$s', 'chip-for-givewp' ) );
			return;
		}

		/* translators: Callback received */
		Chip_Givewp_Helper::log( null, LogType::INFO, __( 'Callback received', 'chip-for-givewp' ) );

		$this->handle_processing();
	}

	/**
	 * Processes the payment status update.
	 */
	private function handle_processing() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- CHIP redirect/callback has no nonce; validated via passphrase above.
		if ( ! isset( $_GET['donation_id'] ) ) {
			Chip_Givewp_Helper::log( null, LogType::ERROR, __( 'Processing halted due to empty donation id', 'chip-for-givewp' ) );
			status_header( 403 );
			exit;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- CHIP redirect/callback has no nonce; donation_id is sanitized below.
		$donation_id = absint( $_GET['donation_id'] );

		$payment_gateway = give_get_payment_gateway( $donation_id );

		$chip_block_view = false;

		if ( 'chip_block' === $payment_gateway ) {
			$chip_block_view = true;
		}

		if ( ! $chip_block_view ) {
			if ( 'chip' !== $payment_gateway ) {
				Chip_Givewp_Helper::log( $donation_id, LogType::ERROR, __( 'Processing halted as payment gateway is not chip', 'chip-for-givewp' ) );
				exit;
			}
		}

		$payment_id = give_get_meta( $donation_id, '_chip_purchase_id', true, false, 'donation' );

		$form_id       = give_get_payment_form_id( $donation_id );
		$customization = give_get_meta( $form_id, '_give_customize_chip_donations', true );

		$prefix = '';
		if ( give_is_setting_enabled( $customization ) ) {
			$prefix = '_give_';
		}

		$secret_key = give_is_test_mode() ? Chip_Givewp_Helper::get_fields( $form_id, 'chip-test-secret-key', $prefix ) : Chip_Givewp_Helper::get_fields( $form_id, 'chip-secret-key', $prefix );

		if ( isset( $_SERVER['HTTP_X_SIGNATURE'] ) ) {
			$content    = file_get_contents( 'php://input' );
			$payload    = json_decode( $content, true );
			$company_id = isset( $payload['company_id'] ) ? trim( (string) $payload['company_id'] ) : '';

			$public_key = '';
			if ( is_string( $company_id ) && '' !== $company_id ) {
				$public_key = get_option( 'gwp_chip_public_key_' . $company_id, '' );
				$public_key = is_string( $public_key ) ? str_replace( '\n', "\n", $public_key ) : '';
			}

			if ( '' === $public_key ) {
				$chip       = Chip_Givewp_API::get_instance( $secret_key, '' );
				$public_key = str_replace( '\n', "\n", $chip->get_public_key() );

				Chip_Givewp_Helper::log( $donation_id, LogType::INFO, __( 'Public key successfully fetched', 'chip-for-givewp' ) );

				$company_uid = $chip->get_company_uid();
				if ( is_string( $company_uid ) && '' !== $company_uid ) {
					update_option( 'gwp_chip_public_key_' . $company_uid, $public_key, false );
				}
			}

			if ( '' === $public_key ) {
				$message = __( 'Success callback failed to be processed due to failure in verification. No public key available.', 'chip-for-givewp' );
				Chip_Givewp_Helper::log( $donation_id, LogType::ERROR, $message );
				give_die( $message, __( 'Failed verification', 'chip-for-givewp' ), 403 );
			}

			$signature_b64 = isset( $_SERVER['HTTP_X_SIGNATURE'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_SIGNATURE'] ) ) : '';
			$signature     = $signature_b64 ? base64_decode( $signature_b64, true ) : false;

			if ( false === $signature ) {
				Chip_Givewp_Helper::log( $donation_id, LogType::ERROR, __( 'Failed to decode X-Signature.', 'chip-for-givewp' ) );
				give_die( __( 'Invalid signature format.', 'chip-for-givewp' ), 403 );
			}

			$key = openssl_pkey_get_public( $public_key );
			if ( false === $key ) {
				Chip_Givewp_Helper::log( $donation_id, LogType::ERROR, __( 'Invalid public key.', 'chip-for-givewp' ) );
				give_die( __( 'Invalid public key.', 'chip-for-givewp' ), 403 );
			}

			$verified = ( 1 === openssl_verify( $content, $signature, $key, OPENSSL_ALGO_SHA256 ) );

			if ( ! $verified ) {
				$message = __( 'Success callback failed to be processed due to failure in verification. Falling back to API lookup.', 'chip-for-givewp' );

				Chip_Givewp_Helper::log( $donation_id, LogType::NOTICE, $message );

				$brand_id = Chip_Givewp_Helper::get_fields( $form_id, 'chip-brand-id', $prefix );
				$chip     = Chip_Givewp_API::get_instance( $secret_key, $brand_id );
				$payment  = $chip->get_payment( $payment_id );

				if ( ! is_array( $payment ) || empty( $payment['id'] ) ) {
					Chip_Givewp_Helper::log( $donation_id, LogType::ERROR, __( 'API fallback failed to retrieve payment.', 'chip-for-givewp' ) );
					give_die( __( 'Failed verification and API fallback failed.', 'chip-for-givewp' ), 403 );
				}
			} else {
				$payment = $payload;
			}

			$payment_id = array_key_exists( 'id', $payment ) ? sanitize_key( $payment['id'] ) : '';

			Chip_Givewp_Helper::log( $donation_id, LogType::INFO, __( 'Callback message successfully validated', 'chip-for-givewp' ), $payment );
		} elseif ( $payment_id ) {
			$brand_id = Chip_Givewp_Helper::get_fields( $form_id, 'chip-brand-id', $prefix );

			$chip    = Chip_Givewp_API::get_instance( $secret_key, $brand_id );
			$payment = $chip->get_payment( $payment_id );

			Chip_Givewp_Helper::log( $donation_id, LogType::HTTP, __( 'Successfully get purchases', 'chip-for-givewp' ), $payment );
		} else {
			Chip_Givewp_Helper::log( $donation_id, LogType::ERROR, __( 'Unexpected response', 'chip-for-givewp' ) );
			give_die( __( 'Unexpected response', 'chip-for-givewp' ) );
		}

		if ( give_get_payment_total( $donation_id ) !== round( $payment['purchase']['total'] / 100, give_get_price_decimals( $donation_id ) ) ) {
			Chip_Givewp_Helper::log( $donation_id, LogType::ERROR, __( 'Payment total does not match!', 'chip-for-givewp' ), $payment );
			give_die( __( 'Payment total does not match!', 'chip-for-givewp' ) );
		}

		if ( 'paid' !== $payment['status'] ) {
			Chip_Givewp_Helper::log( $donation_id, LogType::INFO, __( 'Status updated to failed', 'chip-for-givewp' ), $payment );

			give_update_payment_status( $donation_id, 'failed' );

			$cancel_url = Chip_Givewp_Helper::get_fields( $form_id, 'chip-cancel-url', $prefix );
			if ( $cancel_url && filter_var( $cancel_url, FILTER_VALIDATE_URL ) ) {
				wp_safe_redirect( $cancel_url );
			} else {
				wp_safe_redirect( give_get_failed_transaction_uri( '?payment-id=' . $donation_id ) );
			}
			exit;
		}

		$lock_result = $GLOBALS['wpdb']->get_var(
			"SELECT GET_LOCK('gwp_chip_payment_$donation_id', 15);"
		);

		if ( 1 !== (int) $lock_result ) {
			Chip_Givewp_Helper::log( $donation_id, LogType::ERROR, __( 'Could not acquire payment lock, possible duplicate processing attempt.', 'chip-for-givewp' ) );
			give_die( __( 'Payment is being processed, please refresh in a moment.', 'chip-for-givewp' ), 409 );
		}

		if ( ! give_is_payment_complete( $donation_id ) ) {
			if ( 'paid' === $payment['status'] ) {

				Chip_Givewp_Helper::log( $donation_id, LogType::INFO, __( 'Status updated to publish', 'chip-for-givewp' ), $payment );

				$give_payment = new Give_Payment( $donation_id );

				if ( $give_payment && $give_payment->ID > 0 ) {

					$give_payment->status         = 'publish';
					$give_payment->transaction_id = $payment['id'];
					$give_payment->save();

				}
			}
		}

		$GLOBALS['wpdb']->get_results(
			"SELECT RELEASE_LOCK('gwp_chip_payment_$donation_id');"
		);

		$return = add_query_arg(
			array(
				'payment-confirmation' => 'chip',
				'payment-id'           => $donation_id,
			),
			give_get_success_page_uri()
		);

		Chip_Givewp_Helper::log( $donation_id, LogType::INFO, __( 'Processing completed', 'chip-for-givewp' ), $payment );

		wp_safe_redirect( $return );
		exit;
	}
}

Chip_Givewp_Listener::get_instance();
