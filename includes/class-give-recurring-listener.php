<?php
use Give\Log\ValueObjects\LogType;

class Chip_Givewp_Recurring_Listener {

  private static $_instance;

  const CALLBACK_KEY = 'chip-for-givewp-recurring-callback';
  const CALLBACK_PASSPHRASE = 'chip-for-givewp-recurring-webhook';

  const REDIRECT_KEY = 'chip-for-givewp-recurring-redirect';
  const REDIRECT_PASSPHRASE = 'chip-for-givewp-recurring-redirect';

  public static function get_instance() {
    if ( self::$_instance == null ) {
      self::$_instance = new self();
    }

    return self::$_instance;
  }

  public function __construct()
  {
    add_action( 'init', array( $this, 'handle_callback' ) );
    add_action( 'init', array( $this, 'handle_redirect' ) );
  }

  public function get_callback_url( array $params ) {

    $passphrase = get_option(self::CALLBACK_PASSPHRASE, false);
    if (!$passphrase) {
        $passphrase = md5(site_url() . time());
        update_option(self::CALLBACK_PASSPHRASE, $passphrase);
    }

    $params[self::CALLBACK_KEY] = $passphrase;

    return add_query_arg($params, site_url('/'));
  }

  public function get_redirect_url( $params ) {
    $params[self::REDIRECT_KEY] = self::REDIRECT_PASSPHRASE;
    return add_query_arg($params, site_url('/') );
  }

  public function handle_redirect() {
    if (!isset($_GET[self::REDIRECT_KEY])) {
      return;
    }

    if ($_GET[self::REDIRECT_KEY] != self::REDIRECT_PASSPHRASE) {
      return;
    }

    Chip_Givewp_Helper::log( null, LogType::INFO, __( 'Recurring Redirect received', 'chip-for-givewp' ) );

    $this->handle_processing();
  }

  public function handle_callback() {
    if (!isset($_GET[self::CALLBACK_KEY])) {
      return;
    }

    $passphrase = get_option(self::CALLBACK_PASSPHRASE, false);
    if (!$passphrase) {
      return;
    }

    if ($_GET[self::CALLBACK_KEY] != $passphrase) {
      Chip_Givewp_Helper::log( null, LogType::NOTICE, __( 'Recurring callback failed due to invalid passphrase: %1$s', 'chip-for-givewp' ) );
      return;
    }

    Chip_Givewp_Helper::log( null, LogType::INFO, __( 'Recurring callback received', 'chip-for-givewp' ) );

    $this->handle_processing();
  }

  private function handle_processing() {
    if ( !isset( $_GET['donation_id'] ) ) {
      Chip_Givewp_Helper::log( null, LogType::ERROR, __( 'Processing halted due to empty donation id', 'chip-for-givewp' ) );
      status_header(403);
      exit;
    }

    $donation_id = absint( $_GET['donation_id'] );

    $payment_gateway = give_get_payment_gateway( $donation_id );

    if ( $payment_gateway != 'chip' ) {
      Chip_Givewp_Helper::log( $donation_id, LogType::ERROR, __( 'Processing halted as payment gateway is not chip', 'chip-for-givewp' ) );
      exit;
    }

    $payment_id = Give()->session->get( 'chip_id' );
    $session_donation_id = Give()->session->get( 'donation_id' );

    if ( $payment_id ) {
      Give()->session->set( 'chip_id', false );
    }

    if ( $session_donation_id ) {
      Give()->session->set( 'donation_id', false );
    }

    if ( !empty($session_donation_id) && $donation_id != $session_donation_id) {
      Chip_Givewp_Helper::log( $donation_id, LogType::ERROR, __( 'Session donation not match with donation id!', 'chip-for-givewp' ) );
      give_die( __('Session donation not match with donation id!', 'chip-for-givewp') );
    }

    $form_id = give_get_payment_form_id( $donation_id );
    $customization = give_get_meta( $form_id, '_give_customize_chip_donations', true );

    $prefix = '';
    if ( give_is_setting_enabled( $customization ) ) {
      $prefix = '_give_';
    }

    $secret_key = give_is_test_mode() ? Chip_Givewp_Helper::get_fields($form_id, 'chip-test-secret-key', $prefix) : Chip_Givewp_Helper::get_fields($form_id, 'chip-secret-key', $prefix);

    $chip = Chip_Givewp_API::get_instance($secret_key, '');

    $payment_id = sanitize_key( $_GET['purchase_id'] );

    $payment = $chip->get_payment( $payment_id );

    if ( give_get_payment_total( $donation_id ) != round($payment['purchase']['total'] / 100, give_get_price_decimals( $donation_id )) ) {
      Chip_Givewp_Helper::log( $donation_id, LogType::ERROR, __('Payment total does not match!', 'chip-for-givewp'), $payment );
      give_die( __('Payment total does not match!', 'chip-for-givewp'));
    }

    if ( $payment['status'] != 'paid' ) {
      Chip_Givewp_Helper::log( $donation_id, LogType::INFO, __('Status updated to failed', 'chip-for-givewp'), $payment );

      give_update_payment_status( $donation_id, 'failed' );
      wp_safe_redirect( give_get_failed_transaction_uri('?payment-id=' . $donation_id) );
      exit;
    }

    $GLOBALS['wpdb']->get_results(
      "SELECT GET_LOCK('gwp_chip_payment_$donation_id', 15);"
    );

    if ( !give_is_payment_complete( $donation_id ) ) {
      if ($payment['status'] == 'paid') {

        Chip_Givewp_Helper::log( $donation_id, LogType::INFO, __('Status updated to publish', 'chip-for-givewp'), $payment );

        $give_payment = new Give_Payment( $donation_id );

        if ( $give_payment && $give_payment->ID > 0 ) {

          $give_payment->status         = 'publish';
          $give_payment->transaction_id = $payment['id'];
          $give_payment->save();

        }

        $subscription = new Give_Subscription( $payment['billing_template_id'], true );

        $args = array(
          'amount'         => $payment['payment']['amount'],
          'transaction_id' => $payment['id'],
          'post_date'      => date_i18n( 'Y-m-d H:i:s', $payment['payment']['paid_on'] ),
        );

        $subscription->add_payment( $args );
        $subscription->renew();
      }
    }

    $GLOBALS['wpdb']->get_results(
      "SELECT RELEASE_LOCK('gwp_chip_payment_$donation_id');"
    );

    $return = add_query_arg(
      array(
        'payment-confirmation' => 'chip',
        'payment-id' => $donation_id,
      ), give_get_success_page_uri()
    );

    Chip_Givewp_Helper::log( $donation_id, LogType::INFO, __('Processing completed', 'chip-for-givewp'), $payment );

    wp_safe_redirect( $return );
    exit;
  }
}

Chip_Givewp_Recurring_Listener::get_instance();