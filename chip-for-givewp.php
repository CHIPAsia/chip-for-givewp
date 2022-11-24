<?php

/**
 * Plugin Name: CHIP for GiveWP
 * Plugin URI: https://wordpress.org/plugins/chip-for-givewp/
 * Description: Cash, Card and Coin Handling Integrated Platform
 * Version: 1.0.0
 * Author: Chip In Sdn Bhd
 * Author URI: https://www.chip-in.asia
 *
 * Copyright: © 2022 CHIP
 * License: GNU General Public License v3.0
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 */

define( 'GWP_CHIP_MODULE_VERSION', 'v1.0.0');

require dirname(__FILE__) . '/includes/api.php';

class ChipGiveWP {

  const QUERY_VAR = 'chip-for-give-query-var';
  const LISTENER_PASSPHRASE = 'chip-for-givewp-webhook';

  public function __construct() {
    add_filter( 'give_payment_gateways', array('ChipGiveWP', 'register_payment_method') );
    add_filter( 'give_get_sections_gateways', array('ChipGiveWP', 'register_payment_gateway_sections') );
    add_filter( 'give_get_settings_gateways', array('ChipGiveWP', 'register_payment_gateway_setting_fields') );
    
    add_filter( 'give_forms_chip_donations_metabox_fields', array('ChipGiveWP', 'add_settings'));
    add_filter( 'give_metabox_form_data_settings', array('ChipGiveWP', 'add_tab'));
    add_filter( 'give_enabled_payment_gateways', array('ChipGiveWP', 'filter_gateway'), 10, 2 );

    if (is_admin()){
      add_action('admin_enqueue_scripts', array('ChipGiveWP', 'enqueue_js'));
    }

    add_action( 'give_gateway_chip', array('ChipGiveWP', 'create_purchase') );
    add_action( 'give_chip_cc_form', array('ChipGiveWP', 'cc_form') );
    add_action( 'give_before_chip_info_fields', array('ChipGiveWP', 'billing_fields') );
    
    add_action( 'init', array('ChipGiveWP', 'listener') );
  }

  private static $_instance;

  public static function get_instance() {
    if ( self::$_instance == null ) {
      self::$_instance = new ChipGiveWP();
    }

    return self::$_instance;
  }

  public static function register_payment_method( $gateways ) {
    
    $gateways['chip'] = array(
      'admin_label'    => __( 'CHIP', 'chip-for-givewp' ),
      'checkout_label' => __( 'Online Banking/Credit Card', 'chip-for-givewp' ),
    );
    
    return apply_filters( 'chip_register_payment_method' , $gateways);
  }

  public static function register_payment_gateway_sections( $sections ) {

    $sections['chip-settings'] = __( 'CHIP', 'chip-for-givewp' );

    return $sections;
  }

  public static function get_gateway_settings_fields( $prefix = '' ) {
    $array =  array(
      array(
        'name'    => __( 'Collect Billing Details', 'chip-for-givewp' ),
        'desc'    => __( 'If enabled, required billing address fields are added to Donation forms. These fields are not required to process the transaction, but you may have a need to collect the data. Billing address details are added to both the donation and donor record in GiveWP. ', 'chip-for-givewp' ),
        'id'      => $prefix . 'chip-enable-billing-fields',
        'type'    => 'radio_inline',
        'default' => 'disabled',
        'options' => [
          'enabled'  => __( 'Enabled', 'chip-for-givewp' ),
          'disabled' => __( 'Disabled', 'chip-for-givewp' ),
        ],
      ),
      array(
        'name'    => __( 'Donation Instructions', 'chip-for-givewp' ),
        'desc'    => __( 'The Donation Instructions are a chance for you to educate the donor on how to best submit donations. These instructions appear directly on the form, and after submission of the form. Note: You may also customize the instructions on individual forms as needed.', 'chip-for-givewp' ),
        'id'      => $prefix . 'chip-donation-content',
        'default' => 'Pay with Online Banking/Credit Cards/Debit Cards',
        'type'    => 'wysiwyg',
        'options' => [
          'textarea_rows' => 6,
        ],
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
        'name' => __( 'Send Receipt', 'chip-for-givewp' ),
        'desc' => __( 'Whether to send receipt email when it\'s paid.', 'chip-for-givewp' ),
        'id'   => $prefix . 'chip-send-receipt',
        'type' => 'radio_inline',
        'default' => 'enabled',
        'options' => [
          'enabled'  => __( 'Enabled', 'chip-for-givewp' ),
          'disabled' => __( 'Disabled', 'chip-for-givewp' ),
        ],
      ),
      array(
        'name' => __( 'Due Strict', 'chip-for-givewp' ),
        'desc' => __( 'Whether to permit payments when Purchase\'s due has passed.', 'chip-for-givewp' ),
        'id'   => $prefix . 'chip-due-strict',
        'type' => 'radio_inline',
        'default' => 'disabled',
        'options' => [
          'enabled'  => __( 'Enabled', 'chip-for-givewp' ),
          'disabled' => __( 'Disabled', 'chip-for-givewp' ),
        ],
      ),
      array(
        'name' => __( 'Due Strict Timing (minutes)', 'chip-for-givewp' ),
        'desc' => __( 'Set timeframe allowed for a payment to be made.', 'chip-for-givewp' ),
        'id'   => $prefix . 'chip-due-strict-timing',
        'default' => '60',
        'type' => 'number',
      )
    );

    if ( !empty($prefix) ) {
      for ( $i = 0; $i < sizeof($array); $i++ ) {
        $array[$i]['row_classes'] = 'give-subfield give-hidden';
      }
    }

    return $array;
  }

  public static function register_payment_gateway_setting_fields( $settings ) {

    switch ( give_get_current_setting_section() ) {
  
      case 'chip-settings':
        $settings = array(
          array(
            'id'   => 'give_title_chip',
            'type' => 'title',
          ),
        );

        $settings = array_merge($settings, self::get_gateway_settings_fields());
  
        $settings[] = array(
          'id'   => 'give_title_chip',
          'type' => 'sectionend',
        );
  
        break;
    }
    
    return $settings;
  }

  public static function add_tab( $settings ) {
    if ( give_is_gateway_active( 'chip' ) ) {
      $settings['chip_metabox_options'] = apply_filters(
        'chip_metabox_options',
        [
          'id'        => 'chip_metabox_options',
          'title'     => __( 'CHIP', 'chip-for-givewp' ),
          'icon-html' => '<i class="fas fa-credit-card"></i>',
          'fields'    => apply_filters( 'give_forms_chip_donations_metabox_fields', [] ),
        ]
      );
    }

    return $settings;
  }

  public static function add_settings( $settings ) {
    if ( in_array( 'chip', (array) give_get_option( 'gateways' ) ) ) {
      return $settings;
    }

    $is_gateway_active = give_is_gateway_active( 'chip' );

    if ( ! $is_gateway_active ) {
      return $settings;
    }

    $check_settings = array([
      'name'    => __( 'CHIP', 'chip-for-givewp' ),
      'desc'    => __( 'Do you want to customize the CHIP configuration for this form?', 'chip-for-givewp' ),
      'id'      => '_give_customize_chip_donations',
      'type'    => 'radio_inline',
      'default' => 'global',
      'options' => apply_filters(
        'give_forms_content_options_select',
        [
          'global'   => __( 'Global Option', 'chip-for-givewp' ),
          'enabled'  => __( 'Customize', 'chip-for-givewp' ),
          'disabled' => __( 'Disable', 'chip-for-givewp' ),
        ]
      ),
    ]);

    $check_settings = array_merge($check_settings, self::get_gateway_settings_fields('_give_'));
  
    return array_merge( $settings, $check_settings );
  }

  public static function enqueue_js( $hook ) {
    if ('post.php' === $hook || $hook === 'post-new.php') {
      wp_enqueue_script('give_chip_each_form', plugins_url( 'js/metabox.js', __FILE__ ) );
    }
  }

  public static function cc_form( $form_id ) {
    $instructions = self::get_instructions( $form_id, true );

    ob_start();
  
    do_action( 'give_before_chip_info_fields', $form_id );
    ?>
    <fieldset class="no-fields" id="give_chip_payment_info">
      <?php echo stripslashes( $instructions ); ?>
    </fieldset>
    <?php

    do_action( 'give_after_chip_info_fields', $form_id );
  
    echo ob_get_clean();
  }

  public static function billing_fields( $form_id ) {
    $chip_customization = give_get_meta( $form_id, '_give_customize_chip_donations', true );
    $billing_fields        = give_get_meta( $form_id, '_give_chip-enable-billing-fields', true );

    $global_billing_fields = give_get_option( 'chip-enable-billing-fields' );

    if (
      ( give_is_setting_enabled( $chip_customization, 'global' ) && give_is_setting_enabled( $global_billing_fields ) )
      || ( give_is_setting_enabled( $chip_customization, 'enabled' ) && give_is_setting_enabled( $billing_fields ) )
    ) {
      give_default_cc_address_fields( $form_id );
    }
  }

  public static function create_purchase( $payment_data ) {

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

    if ( $currency != 'MYR' ) {
      give_record_gateway_error(
        __( 'Chip Error', 'chip-for-givewp' ),
        sprintf(
        /* translators: %s Exception error message. */
          __( 'Unsupported currencies. Only MYR is supported. The current currency is %s.', 'chip-for-give' ),
          $currency
        )
      );

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
      give_record_gateway_error(
        __( 'Chip Error', 'chip-for-givewp' ),
        sprintf(
        /* translators: %s Exception error message. */
          __( 'Unable to create a pending donation with Give.', 'chip-for-give' )
        )
      );

      give_send_back_to_checkout( '?payment-mode=chip' );
    }

    $customization = give_get_meta( $form_id, '_give_customize_chip_donations', true );

    $prefix = '';
    if ( give_is_setting_enabled( $customization ) ) {
      $prefix = '_give_';
    }

    $secret_key        = give_is_test_mode() ? self::get_option_or_meta($form_id, 'chip-test-secret-key', $prefix) : self::get_option_or_meta($form_id, 'chip-secret-key', $prefix);
    $due_strict        = self::get_option_or_meta($form_id, 'chip-due-strict', $prefix);
    $due_strict_timing = self::get_option_or_meta($form_id, 'chip-due-strict-timing', $prefix);
    $send_receipt      = self::get_option_or_meta($form_id, 'chip-send-receipt', $prefix);
    $brand_id          = self::get_option_or_meta($form_id, 'chip-brand-id', $prefix);
    $billing_fields    = self::get_option_or_meta($form_id, 'chip-enable-billing-fields', $prefix );

    // give_get_success_page_uri()
    $params = array(
      'success_callback' => self::get_listener_url( $donation_id ),
      'success_redirect' => self::get_listener_url( array('donation_id' => $donation_id, 'nonce' => $payment_data['gateway_nonce']) ),
      // 'failure_redirect' => give_get_failed_transaction_uri( '?payment-id=' . $donation_id ),
      'failure_redirect' => self::get_listener_url( array('donation_id' => $donation_id, 'failure' => 'true') ),
      'creator_agent'    => 'GiveWP: ' . GWP_CHIP_MODULE_VERSION,
      'reference'        => $payment_data['purchase_key'],
      'platform'         => 'givewp',
      'send_receipt'     => give_is_setting_enabled( $send_receipt ),
      'due'              => time() + (absint( $due_strict_timing ) * 60),
      'brand_id'         => $brand_id,
      'client'           => [
        'email'          => $payment_data['user_email'],
        'full_name'      => substr($payment_data['user_info']['first_name'] . $payment_data['user_info']['last_name'], 0, 30),
      ],
      'purchase'         => array(
        'timezone'   => apply_filters( 'gwp_chip_purchase_timezone', self::get_timezone() ),
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
    
    $chip = GiveChipAPI::get_instance($secret_key, $brand_id);
    $payment = $chip->create_payment($params);

    if (!array_key_exists('id', $payment)) {
      // $log = LogFactory::makeFromArray( array('type' => LogType::ERROR, 'message' => '', 'category' => LogCategory::PAYMENT, 'source' => 'CHIP for GiveWP', 'context' => array(), 'id' => $donation_id ) );
      // $log->save();

      // return $log->getId();
      give_send_back_to_checkout( '?payment-mode=chip' );
    }

    Give()->session->set('payment_id', $payment['id']);
    Give()->session->set('donation_id', $donation_id);

    wp_redirect( esc_url_raw( apply_filters( 'gwp_chip_checkout_url', $payment['checkout_url'], $payment, $payment_data ) ) );
    give_die();
  }

  public static function get_option_or_meta($form_id, $column, $prefix = '') {
    if ( empty($prefix) ) {
      return give_get_option( $column );
    }
    return give_get_meta( $form_id, $prefix . $column, true );
  }

  public static function get_listener_url( $params ) {
    if (!is_array($params)) {
      $params = array( 'donation_id' => $params );
    }

    $passphrase = get_option(self::LISTENER_PASSPHRASE, false);
    if (!$passphrase) {
        $passphrase = md5(site_url() . time());
        update_option(self::LISTENER_PASSPHRASE, $passphrase);
    }

    $params[self::QUERY_VAR] = $passphrase;

    return add_query_arg($params, site_url('/'));
  }

  public static function get_timezone() {
    if (preg_match('/^[A-z]+\/[A-z\_\/\-]+$/', wp_timezone_string())) {
      return wp_timezone_string();
    }

    return 'UTC';
  }

  public static function get_instructions( $form_id, $wpautop = false ) {
    if ( ! $form_id ) {
      return '';
    }

    $donate_customization = give_get_meta( $form_id, '_give_customize_chip_donations', true );
    $donate_customization_enabled = give_is_setting_enabled( $donate_customization );
    
    if ( $donate_customization === 'disabled' ) {
      return '';
    }
    
    $instructions = give_get_meta( $form_id, '_give_chip-donation-content', true );
    $global_instructions = give_get_option( 'chip-donation-content' );
    $instructions_content = $donate_customization_enabled ? $instructions : $global_instructions;
    
    $formatted_instructions = self::get_formatted_instructions(
      $instructions_content,
      $form_id,
      $wpautop
    );

    return apply_filters(
      'chip_instructions_content',
      $formatted_instructions,
      $instructions_content,
      $form_id,
      $wpautop
    );
  }

  public static function get_formatted_instructions( $instructions, $form_id, $wpautop = false ) {

    $p_instructions = give_do_email_tags($instructions, ['form_id' => $form_id]);
  
    return $wpautop ? wpautop( do_shortcode( $p_instructions ) ) : $p_instructions;
  }

  public static function filter_gateway( $gateway_list, $form_id ) {
    if (
      ( false === strpos( $_SERVER['REQUEST_URI'], '/wp-admin/post-new.php?post_type=give_forms' ) )
      && $form_id
      && ! give_is_setting_enabled( give_get_meta( $form_id, '_give_customize_chip_donations', true, 'global' ), [ 'enabled', 'global' ] )
    ) {
      unset( $gateway_list['chip'] );
    }
  
    // Output.
    return $gateway_list;
  }

  public static function listener() {
    if (!isset($_GET[self::QUERY_VAR])) {
      return;
    }

    $passphrase = get_option(self::LISTENER_PASSPHRASE, false);
    if (!$passphrase) {
      return;
    }
  
    if ($_GET[self::QUERY_VAR] != $passphrase) {
      return;
    }
  
    if (!isset($_GET['donation_id'])) {
      status_header(403);
      exit;
    }

    $donation_id = absint( $_GET['donation_id'] );

    $payment_gateway = give_get_payment_gateway( $donation_id );

    if ( $payment_gateway != 'chip' ) {
      exit;
    }

    $payment_id = Give()->session->get( 'payment_id' );
    $session_donation_id = Give()->session->get( 'donation_id' );

    Give()->session->set( 'payment_id', false );
    Give()->session->set( 'donation_id', false );

    if ( !empty($session_donation_id) && $donation_id != $session_donation_id) {
      give_die('Session donation not match with donation id!');
    }

    if ( !empty($payment_id) && isset($_SERVER['HTTP_X_SIGNATURE']) ) {
      $form_id = give_get_payment_form_id( $donation_id );
      $customization = give_get_meta( $form_id, '_give_customize_chip_donations', true );
      
      $prefix = '';
      if ( give_is_setting_enabled( $customization ) ) {
        $prefix = '_give_';
      }

      $secret_key = give_is_test_mode() ? self::get_option_or_meta($form_id, 'chip-test-secret-key', $prefix) : self::get_option_or_meta($form_id, 'chip-secret-key', $prefix);
      $ten_secret_key = substr($secret_key, 0, 10);
      
      if ( empty($public_key = self::get_option_or_meta( $form_id, 'chip-public-key' . $ten_secret_key, $prefix )) ) {
        $chip = GiveChipAPI::get_instance($secret_key, '');
        $public_key = str_replace('\n',"\n", $chip->get_public_key());
        
        self::update_option_or_meta( $form_id, 'chip-secret-key' . $ten_secret_key, $public_key, $prefix );
      }

      $content = file_get_contents('php://input');

      if (openssl_verify( $content,  base64_decode($_SERVER['HTTP_X_SIGNATURE']), $public_key, 'sha256WithRSAEncryption' ) != 1) {
        $message = __('Success callback failed to be processed due to failure in verification.', 'chip-for-woocommerce');
        give_die($message, 'Failed verification', 403);
      }

      $payment    = json_decode($content, true);
      $payment_id = array_key_exists('id', $payment) ? sanitize_key($payment['id']) : '';
    } else if ( $payment_id ) {
      
      $form_id = give_get_payment_form_id( $donation_id );
      $customization = give_get_meta( $form_id, '_give_customize_chip_donations', true );

      $prefix = '';
      if ( give_is_setting_enabled( $customization ) ) {
        $prefix = '_give_';
      }

      $secret_key = give_is_test_mode() ? self::get_option_or_meta($form_id, 'chip-test-secret-key', $prefix) : self::get_option_or_meta($form_id, 'chip-secret-key', $prefix);

      $chip = GiveChipAPI::get_instance($secret_key, '');
      $payment = $chip->get_payment($payment_id);
    } else {
      give_die( __('Unexpected response', 'chip-for-givewp') );
    }

    if ( give_get_payment_key( $donation_id ) != $payment['reference'] ) {
      give_die( __('Purchase key does not match!', 'chip-for-givewp'));
    }

    if ( give_get_payment_total( $donation_id ) != round($payment['purchase']['total'] / 100, give_get_price_decimals( $donation_id )) ) {
      give_die( __('Payment total does not match!', 'chip-for-givewp'));
    }

    if ( isset($_GET['failure']) ) {
      // add update donation to failed state
      give_update_payment_status( $donation_id, 'failed' );
      wp_safe_redirect( give_get_failed_transaction_uri('?payment-id=' . $donation_id) );
      exit;
    }
    
    $GLOBALS['wpdb']->get_results(
      "SELECT GET_LOCK('chip_payment_$donation_id', 15);"
    );

    if ( !give_is_payment_complete( $donation_id ) ) {
      if ($payment['status'] == 'paid') {
        give_update_payment_status( $donation_id );
      }
    }

    $GLOBALS['wpdb']->get_results(
      "SELECT RELEASE_LOCK('chip_payment_$donation_id');"
    );

    $return = add_query_arg(
      array(
        'payment-confirmation' => 'chip',
        'payment-id' => $donation_id,
      ), give_get_success_page_uri()
    );

    wp_safe_redirect( $return );
    exit;
  }

  public static function update_option_or_meta($form_id, $column, $value, $prefix = '') {
    if ( empty($prefix) ) {
      return give_update_option( $column, $value );
    }
    
    return give_update_meta( $form_id, $prefix . $column, $value );
  }

}

ChipGiveWP::get_instance();
