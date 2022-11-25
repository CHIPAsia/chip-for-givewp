<?php
use Give\Log\ValueObjects\LogType;

class ChipGiveWPListener {

  private static $_instance;

  const CALLBACK_KEY = 'chip-for-givewp-callback';
  const CALLBACK_PASSPHRASE = 'chip-for-givewp-webhook';

  const REDIRECT_KEY = 'chip-for-givewp-redirect';
  const REDIRECT_PASSPHRASE = 'chip-for-givewp-redirect';

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

    ChipGiveWPHelper::log( null, LogType::INFO, __( 'Redirect received', 'chip-for-givewp' ) );

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
      ChipGiveWPHelper::log( null, LogType::NOTICE, __( 'Callback failed due to invalid passphrase: %1$s', 'chip-for-givewp' ) );
      return;
    }

    ChipGiveWPHelper::log( null, LogType::INFO, __( 'Callback received', 'chip-for-givewp' ) );

    $this->handle_processing();
  }

  private function handle_processing() {
    if ( !isset( $_GET['donation_id'] ) ) {
      ChipGiveWPHelper::log( null, LogType::ERROR, __( 'Processing halted due to empty donation id', 'chip-for-givewp' ) );
      status_header(403);
      exit;
    }

    $donation_id = absint( $_GET['donation_id'] );

    $payment_gateway = give_get_payment_gateway( $donation_id );

    if ( $payment_gateway != 'chip' ) {
      ChipGiveWPHelper::log( $donation_id, LogType::ERROR, __( 'Processing halted as payment gateway is not chip', 'chip-for-givewp' ) );
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
      ChipGiveWPHelper::log( $donation_id, LogType::ERROR, __( 'Session donation not match with donation id!', 'chip-for-givewp' ) );
      give_die( __('Session donation not match with donation id!', 'chip-for-givewp') );
    }

    if ( empty($payment_id) && isset($_SERVER['HTTP_X_SIGNATURE']) ) {
      $form_id = give_get_payment_form_id( $donation_id );
      $customization = give_get_meta( $form_id, '_give_customize_chip_donations', true );
      
      $prefix = '';
      if ( give_is_setting_enabled( $customization ) ) {
        $prefix = '_give_';
      }

      $secret_key = give_is_test_mode() ? ChipGiveWPHelper::get_fields($form_id, 'chip-test-secret-key', $prefix) : ChipGiveWPHelper::get_fields($form_id, 'chip-secret-key', $prefix);
      $ten_secret_key = substr($secret_key, 0, 10);
      
      if ( empty($public_key = ChipGiveWPHelper::get_fields( $form_id, 'chip-public-key' . $ten_secret_key, $prefix )) ) {
        $chip = ChipGiveWPAPI::get_instance($secret_key, '');
        $public_key = str_replace('\n',"\n", $chip->get_public_key());
        
        ChipGiveWPHelper::log( $donation_id, LogType::INFO, __( 'Public key successfully fetched', 'chip-for-givewp' ) );

        ChipGiveWPHelper::update_fields( $form_id, 'chip-secret-key' . $ten_secret_key, $public_key, $prefix );
      }

      $content = file_get_contents('php://input');

      if (openssl_verify( $content,  base64_decode($_SERVER['HTTP_X_SIGNATURE']), $public_key, 'sha256WithRSAEncryption' ) != 1) {
        $message = __('Success callback failed to be processed due to failure in verification.', 'chip-for-woocommerce');

        ChipGiveWPHelper::log( $donation_id, LogType::ERROR, $message );

        give_die($message, __('Failed verification', 'chip-for-givewp'), 403);
      }

      ChipGiveWPHelper::log( $donation_id, LogType::INFO, __('callback message successfully validated', 'chip-for-givewp') );

      $payment    = json_decode($content, true);
      $payment_id = array_key_exists('id', $payment) ? sanitize_key($payment['id']) : '';
    } else if ( $payment_id ) {
      
      $form_id = give_get_payment_form_id( $donation_id );
      $customization = give_get_meta( $form_id, '_give_customize_chip_donations', true );

      $prefix = '';
      if ( give_is_setting_enabled( $customization ) ) {
        $prefix = '_give_';
      }

      $secret_key = give_is_test_mode() ? ChipGiveWPHelper::get_fields($form_id, 'chip-test-secret-key', $prefix) : ChipGiveWPHelper::get_fields($form_id, 'chip-secret-key', $prefix);

      $chip = ChipGiveWPAPI::get_instance($secret_key, '');
      $payment = $chip->get_payment($payment_id);
    } else {
      ChipGiveWPHelper::log( $donation_id, LogType::ERROR, __('Unexpected response', 'chip-for-givewp') );
      give_die( __('Unexpected response', 'chip-for-givewp') );
    }

    if ( give_get_payment_key( $donation_id ) != $payment['reference'] ) {
      ChipGiveWPHelper::log( $donation_id, LogType::ERROR, __('Purchase key does not match!', 'chip-for-givewp') );
      give_die( __('Purchase key does not match!', 'chip-for-givewp'));
    }

    if ( give_get_payment_total( $donation_id ) != round($payment['purchase']['total'] / 100, give_get_price_decimals( $donation_id )) ) {
      ChipGiveWPHelper::log( $donation_id, LogType::ERROR, __('Payment total does not match!', 'chip-for-givewp') );
      give_die( __('Payment total does not match!', 'chip-for-givewp'));
    }

    if ( isset($_GET['status']) AND $_GET['status'] == 'error' ) {
      ChipGiveWPHelper::log( $donation_id, LogType::INFO, __('Status updated to failed', 'chip-for-givewp') );

      give_update_payment_status( $donation_id, 'failed' );
      wp_safe_redirect( give_get_failed_transaction_uri('?payment-id=' . $donation_id) );
      exit;
    }

    $GLOBALS['wpdb']->get_results(
      "SELECT GET_LOCK('gwp_chip_payment_$donation_id', 15);"
    );

    if ( !give_is_payment_complete( $donation_id ) ) {
      if ($payment['status'] == 'paid') {
        ChipGiveWPHelper::log( $donation_id, LogType::INFO, __('Status updated to publish', 'chip-for-givewp') );
        give_update_payment_status( $donation_id );
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

    ChipGiveWPHelper::log( $donation_id, LogType::INFO, __('Processing completed', 'chip-for-givewp') );

    wp_safe_redirect( $return );
    exit;
  }
}

ChipGiveWPListener::get_instance();