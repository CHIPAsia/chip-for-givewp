<?php

use Give\Log\ValueObjects\LogType;

class Chip_Givewp_Purchase {

  private static $_instance;

  public static function get_instance() {
    if ( self::$_instance == null ) {
      self::$_instance = new self();
    }

    return self::$_instance;
  }

  public function __construct()
  {
    add_action( 'give_chip_cc_form', array( $this, 'cc_form' ) );
    add_action( 'give_gateway_chip', array( $this, 'create') );
  }

  public function cc_form( $form_id ) {
    $instructions = $this->get_instructions( $form_id, true );

    ob_start();
  
    do_action( 'give_before_chip_info_fields', $form_id );
    ?>
    <fieldset class="no-fields" id="give_chip_payment_info">
      <?php echo stripslashes( wp_kses_post( $instructions ) ); ?>
    </fieldset>
    <?php

    do_action( 'give_after_chip_info_fields', $form_id );
  
    echo ob_get_clean();
  }

  private function get_instructions( $form_id, $wpautop = false ) {
    if ( ! $form_id ) {
      return '';
    }

    $customization = give_get_meta( $form_id, '_give_customize_chip_donations', true );
    
    if ( $customization === 'disabled' ) {
      return '';
    }

    $prefix = '';
    if ( give_is_setting_enabled( $customization ) ) {
      $prefix = '_give_';
    }

    $content = Chip_Givewp_Helper::get_fields( $form_id, 'chip-content', $prefix);

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

  private function get_formatted_content( $content, $form_id, $wpautop = false ) {

    $p_content = give_do_email_tags($content, ['form_id' => $form_id]);

    return $wpautop ? wpautop( do_shortcode( $p_content ) ) : $p_content;
  }

  public function create( $payment_data ) {

    if ( 'chip' != $payment_data['post_data']['give-gateway'] ) {
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

      Chip_Givewp_Helper::log( $form_id, LogType::ERROR, sprintf( __( 'Amount to be paid is less than 1. The amount to be paid is %s.', 'chip-for-givewp' ), $donation_amount ), $payment_data );

      give_send_back_to_checkout( '?payment-mode=chip' );
    }

    if ( $currency != 'MYR' ) {

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

    $secret_key        = give_is_test_mode() ? Chip_Givewp_Helper::get_fields($form_id, 'chip-test-secret-key', $prefix) : Chip_Givewp_Helper::get_fields($form_id, 'chip-secret-key', $prefix);
    $due_strict        = Chip_Givewp_Helper::get_fields($form_id, 'chip-due-strict', $prefix);
    $due_strict_timing = Chip_Givewp_Helper::get_fields($form_id, 'chip-due-strict-timing', $prefix);
    $send_receipt      = Chip_Givewp_Helper::get_fields($form_id, 'chip-send-receipt', $prefix);
    $brand_id          = Chip_Givewp_Helper::get_fields($form_id, 'chip-brand-id', $prefix);
    $billing_fields    = Chip_Givewp_Helper::get_fields($form_id, 'chip-enable-billing-fields', $prefix );

    $listener = Chip_Givewp_Listener::get_instance();

    $params = array(
      'success_callback' => $listener->get_callback_url( array('donation_id' => $donation_id, 'status' => 'paid') ),
      'success_redirect' => $listener->get_redirect_url( array('donation_id' => $donation_id, 'nonce' => $payment_data['gateway_nonce']) ),
      'failure_redirect' => $listener->get_redirect_url( array('donation_id' => $donation_id, 'status' => 'error') ),
      'creator_agent'    => 'GiveWP: ' . GWP_CHIP_MODULE_VERSION,
      'reference'        => $payment_data['purchase_key'],
      'platform'         => 'givewp',
      'send_receipt'     => give_is_setting_enabled( $send_receipt ),
      'due'              => time() + (absint( $due_strict_timing ) * 60),
      'brand_id'         => $brand_id,
      'client'           => [
        'email'          => $payment_data['user_email'],
        'full_name'      => substr($payment_data['user_info']['first_name'] . ' ' . $payment_data['user_info']['last_name'], 0, 30),
      ],
      'purchase'         => array(
        'timezone'   => apply_filters( 'gwp_chip_purchase_timezone', $this->get_timezone() ),
        'currency'   => $currency,
        'due_strict' => give_is_setting_enabled( $due_strict ),
        'products'   => array([
          'name'     => substr(give_payment_gateway_item_title($payment_data), 0, 256),
          'price'    => round($payment_data['price'] * 100),
          'quantity' => '1',
        ]),
      ),
    );

    if ( give_is_setting_enabled( $billing_fields ) ) {
      $params['client']['street_address'] = substr($payment_data['post_data']['card_address'] ?? 'Address' . ' ' . ($payment_data['post_data']['card_address_2'] ?? ''), 0, 128);
      $params['client']['country']        = $payment_data['post_data']['billing_country'] ?? 'MY';
      $params['client']['city']           = $payment_data['post_data']['card_city'] ?? 'Kuala Lumpur';
      $params['client']['zip_code']       = $payment_data['post_data']['card_zip'] ?? '10000';
      $params['client']['state']          = substr($payment_data['post_data']['card_state'], 0, 2) ?? 'KL';
    }
    
    $chip = Chip_Givewp_API::get_instance($secret_key, $brand_id);
    $payment = $chip->create_payment($params);

    if (!array_key_exists('id', $payment)) {
      
      Chip_Givewp_Helper::log( $form_id, LogType::ERROR, sprintf( __( 'Unable to create purchases: %s', 'chip-for-givewp' ), print_r($payment, true)) );

      give_insert_payment_note( $donation_id, __('Failed to create purchase.', 'chip-for-givewp') );
      give_send_back_to_checkout( '?payment-mode=chip' );
    }

    Chip_Givewp_Helper::log( $form_id, LogType::HTTP, sprintf( __( 'Create purchases success for donation id %1$s', 'chip-for-givewp' ), $donation_id), $payment );

    Give()->session->set('chip_id', $payment['id']);
    Give()->session->set('donation_id', $donation_id);

    if ( give_is_test_mode() ) {
      give_insert_payment_note( $donation_id, __('This is test environment where payment status is simulated.', 'chip-for-givewp') );
    }
    give_insert_payment_note( $donation_id, sprintf( __('URL: %1$s', 'chip-for-givewp'), $payment['checkout_url']) );
    
    wp_redirect( esc_url_raw( apply_filters( 'gwp_chip_checkout_url', $payment['checkout_url'], $payment, $payment_data ) ) );
    give_die();
  }

  private function get_timezone() {
    if (preg_match('/^[A-z]+\/[A-z\_\/\-]+$/', wp_timezone_string())) {
      return wp_timezone_string();
    }

    return 'UTC';
  }
}

Chip_Givewp_Purchase::get_instance();
