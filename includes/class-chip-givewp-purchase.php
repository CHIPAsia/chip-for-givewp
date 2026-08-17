<?php
/**
 * Legacy form purchase handler.
 *
 * @package GiveWPCHIP
 */

defined( 'ABSPATH' ) || exit;

use Give\Log\ValueObjects\LogType;

/**
 * Handles legacy form donation creation and redirect to CHIP checkout.
 */
class Chip_Givewp_Purchase {

	/**
	 * Single instance of the class.
	 *
	 * @var Chip_Givewp_Purchase|null
	 */
	private static $instance;

	/**
	 * Gets the single instance of the class.
	 *
	 * @return Chip_Givewp_Purchase
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
		add_action( 'give_chip_cc_form', array( $this, 'cc_form' ) );
		add_action( 'give_gateway_chip', array( $this, 'create' ) );
	}

	/**
	 * Renders the CHIP payment info fields.
	 *
	 * @param int $form_id Form ID.
	 */
	public function cc_form( $form_id ) {
		$instructions = $this->get_instructions( $form_id, true );

		ob_start();

		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- GiveWP core hook for gateway field rendering.
		do_action( 'give_before_chip_info_fields', $form_id );
		?>
		<fieldset class="no-fields" id="give_chip_payment_info">
			<?php echo wp_kses_post( $instructions ); ?>
		</fieldset>
		<?php

		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- GiveWP core hook for gateway field rendering.
		do_action( 'give_after_chip_info_fields', $form_id );

		echo wp_kses_post( ob_get_clean() );
	}

	/**
	 * Gets the donation instructions for a form.
	 *
	 * @param int  $form_id  Form ID.
	 * @param bool $wpautop  Whether to apply wpautop.
	 * @return string
	 */
	private function get_instructions( $form_id, $wpautop = false ) {
		if ( ! $form_id ) {
			return '';
		}

		$customization = give_get_meta( $form_id, '_give_customize_chip_donations', true );

		if ( 'disabled' === $customization ) {
			return '';
		}

		$prefix = '';
		if ( give_is_setting_enabled( $customization ) ) {
			$prefix = '_give_';
		}

		$content = Chip_Givewp_Helper::get_fields( $form_id, 'chip-content', $prefix );

		$formatted_content = $this->get_formatted_content(
			$content,
			$form_id,
			$wpautop
		);

		return apply_filters(
			'gwp_chip_content',
			$formatted_content,
			$content,
			$form_id,
			$wpautop
		);
	}

	/**
	 * Formats the instruction content.
	 *
	 * @param string $content  Raw content.
	 * @param int    $form_id  Form ID.
	 * @param bool   $wpautop  Whether to apply wpautop.
	 * @return string
	 */
	private function get_formatted_content( $content, $form_id, $wpautop = false ) {

		$p_content = give_do_email_tags( $content, array( 'form_id' => $form_id ) );

		return $wpautop ? wpautop( do_shortcode( $p_content ) ) : $p_content;
	}

	/**
	 * Creates a CHIP payment and redirects the donor to checkout.
	 *
	 * @param array $payment_data Payment data from GiveWP.
	 */
	public function create( $payment_data ) {

		if ( 'chip' !== $payment_data['post_data']['give-gateway'] ) {
			return;
		}

		give_clear_errors();

		if ( give_get_errors() ) {
			give_send_back_to_checkout( '?payment-mode=chip' );
		}

		$form_id         = intval( $payment_data['post_data']['give-form-id'] );
		$price_id        = ! empty( $payment_data['post_data']['give-price-id'] ) ? $payment_data['post_data']['give-price-id'] : 0;
		$donation_amount = ! empty( $payment_data['price'] ) ? $payment_data['price'] : 0;
		$currency        = give_get_currency( $form_id, $payment_data );

		if ( $donation_amount < 1 ) {

			/* translators: Donation Amount */
			Chip_Givewp_Helper::log( $form_id, LogType::ERROR, sprintf( __( 'Amount to be paid is less than 1. The amount to be paid is %s.', 'chip-for-givewp' ), $donation_amount ), $payment_data );

			give_send_back_to_checkout( '?payment-mode=chip' );
		}

		if ( 'MYR' !== $currency ) {

			/* translators: Currency */
			Chip_Givewp_Helper::log( $form_id, LogType::ERROR, sprintf( __( 'Unsupported currencies. Only MYR is supported. The current currency is %s.', 'chip-for-givewp' ), $currency ), $payment_data );

			give_send_back_to_checkout( '?payment-mode=chip' );
		}

		$donation_data = array(
			'price'           => $donation_amount,
			'give_form_title' => $payment_data['post_data']['give-form-title'],
			'give_form_id'    => $form_id,
			'give_price_id'   => $price_id,
			'date'            => $payment_data['date'],
			'user_email'      => $payment_data['user_email'],
			'purchase_key'    => $payment_data['purchase_key'],
			'currency'        => $currency,
			'user_info'       => $payment_data['user_info'],
			'status'          => 'pending',
			'gateway'         => 'chip',
		);

		$donation_id = give_insert_payment( $donation_data );

		if ( ! $donation_id ) {

			Chip_Givewp_Helper::log( $form_id, LogType::ERROR, __( 'Unable to create a pending donation with Give', 'chip-for-givewp' ), $donation_data );

			give_send_back_to_checkout( '?payment-mode=chip' );
		}

		$customization = give_get_meta( $form_id, '_give_customize_chip_donations', true );

		$prefix = '';
		if ( give_is_setting_enabled( $customization ) ) {
			$prefix = '_give_';
		}

		$secret_key               = give_is_test_mode() ? Chip_Givewp_Helper::get_fields( $form_id, 'chip-test-secret-key', $prefix ) : Chip_Givewp_Helper::get_fields( $form_id, 'chip-secret-key', $prefix );
		$due_strict               = Chip_Givewp_Helper::get_fields( $form_id, 'chip-due-strict', $prefix );
		$due_strict_timing        = Chip_Givewp_Helper::get_fields( $form_id, 'chip-due-strict-timing', $prefix );
		$brand_id                 = Chip_Givewp_Helper::get_fields( $form_id, 'chip-brand-id', $prefix );
		$billing_fields           = Chip_Givewp_Helper::get_fields( $form_id, 'chip-enable-billing-fields', $prefix );
		$payment_method_whitelist = Chip_Givewp_Helper::get_fields( $form_id, 'chip-payment-method-whitelist', $prefix );

		// Pre-fetch and cache public key for webhook verification.
		$chip       = Chip_Givewp_API::get_instance( $secret_key, '' );
		$public_key = $chip->get_public_key();
		if ( is_string( $public_key ) && '' !== $public_key ) {
			$company_uid = $chip->get_company_uid();
			if ( is_string( $company_uid ) && '' !== $company_uid ) {
				update_option( 'gwp_chip_public_key_' . $company_uid, str_replace( '\n', "\n", $public_key ), false );
			}
		}

		$listener = Chip_Givewp_Listener::get_instance();

		$params = array(
			'success_callback' => $listener->get_callback_url(
				array(
					'donation_id' => $donation_id,
					'status'      => 'paid',
				)
			),
			'success_redirect' => $listener->get_redirect_url(
				array(
					'donation_id' => $donation_id,
					'nonce'       => $payment_data['gateway_nonce'],
				)
			),
			'failure_redirect' => $listener->get_redirect_url(
				array(
					'donation_id' => $donation_id,
					'status'      => 'error',
				)
			),
			'cancel_redirect'  => $listener->get_redirect_url(
				array(
					'donation_id' => $donation_id,
					'status'      => 'cancel',
				)
			),
			'creator_agent'    => 'GiveWP: ' . GWP_CHIP_MODULE_VERSION,
			'reference'        => substr( $donation_id, 0, 128 ),
			'platform'         => 'givewp',
			'due'              => time() + ( absint( $due_strict_timing ) * 60 ),
			'brand_id'         => $brand_id,
			'client'           => array(
				'email'     => $payment_data['user_email'],
				'full_name' => trim( substr( $payment_data['user_info']['first_name'] . ' ' . $payment_data['user_info']['last_name'], 0, 30 ) ),
			),
			'purchase'         => array(
				'timezone'   => apply_filters( 'gwp_chip_purchase_timezone', $this->get_timezone() ),
				'currency'   => $currency,
				'due_strict' => give_is_setting_enabled( $due_strict ),
				'products'   => array(
					array(
						'name'     => substr( give_payment_gateway_item_title( $payment_data ), 0, 256 ),
						'price'    => round( $payment_data['price'] * 100 ),
						'quantity' => '1',
					),
				),
			),
		);

		if ( give_is_setting_enabled( $billing_fields ) ) {
			$params['client']['street_address'] = trim( substr( ( $payment_data['post_data']['card_address'] ?? '' ) . ' ' . ( $payment_data['post_data']['card_address_2'] ?? '' ), 0, 128 ) );
			$params['client']['country']        = trim( $payment_data['post_data']['billing_country'] ?? '' );
			$params['client']['city']           = trim( $payment_data['post_data']['card_city'] ?? '' );
			$params['client']['zip_code']       = trim( $payment_data['post_data']['card_zip'] ?? '' );
			$params['client']['state']          = trim( substr( $payment_data['post_data']['card_state'], 0, 2 ) ?? '' );
		}

		if ( is_array( $payment_method_whitelist ) && ! empty( $payment_method_whitelist ) ) {
			$whitelist = array();
			foreach ( $payment_method_whitelist as $method ) {
				if ( 'cards' === $method ) {
					$whitelist = array_merge( $whitelist, array( 'visa', 'mastercard', 'maestro' ) );
				} else {
					$whitelist[] = $method;
				}
			}
			$params['payment_method_whitelist'] = array_values( array_unique( $whitelist ) );
			$params['payment_method_whitelist'] = Chip_Givewp_Helper::resolve_duitnow_methods(
				$params['payment_method_whitelist'],
				$currency,
				(int) round( $donation_amount * 100 ),
				$secret_key,
				$brand_id,
				$form_id
			);
		}

		foreach ( $params['client'] as $key => $value ) {
			if ( empty( $value ) ) {
				unset( $params['client'][ $key ] );
			}
		}

		$params = apply_filters( 'gwp_chip_purchase_params', $params, $payment_data, $this );

		$chip    = Chip_Givewp_API::get_instance( $secret_key, $brand_id );
		$payment = $chip->create_payment( $params );

		if ( ! array_key_exists( 'id', $payment ) ) {
			/* translators: CHIP create_payment response */
			Chip_Givewp_Helper::log( $form_id, LogType::ERROR, sprintf( __( 'Unable to create purchases: %s', 'chip-for-givewp' ), wp_json_encode( $payment ) ) );

			give_insert_payment_note( $donation_id, __( 'Failed to create purchase.', 'chip-for-givewp' ) );
			give_send_back_to_checkout( '?payment-mode=chip' );
		}

		/* translators: Donation ID */
		Chip_Givewp_Helper::log( $form_id, LogType::HTTP, sprintf( __( 'Create purchases success for donation id %1$s', 'chip-for-givewp' ), $donation_id ), $payment );

		give_update_meta( $donation_id, '_chip_purchase_id', $payment['id'], '', 'donation' );

		if ( give_is_test_mode() ) {
			give_insert_payment_note( $donation_id, __( 'This is test environment where payment status is simulated.', 'chip-for-givewp' ) );
		}
		/* translators: 1: CHIP Checkout URL */
		give_insert_payment_note( $donation_id, sprintf( __( 'URL: %1$s', 'chip-for-givewp' ), $payment['checkout_url'] ) );

		// phpcs:ignore WordPress.Security.SafeRedirect -- Checkout URL comes from trusted CHIP API via HTTPS.
		wp_redirect( esc_url_raw( apply_filters( 'gwp_chip_checkout_url', $payment['checkout_url'], $payment, $payment_data ) ) );
		give_die();
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

Chip_Givewp_Purchase::get_instance();
