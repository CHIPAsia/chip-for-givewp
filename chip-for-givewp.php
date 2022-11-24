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

class ChipGiveWP {

  private static $_instance;

  public static function get_instance() {
    if ( self::$_instance == null ) {
      self::$_instance = new self();
    }

    return self::$_instance;
  }

  public function __construct() {
    $this->define();
    $this->includes();
    $this->add_filters();
    $this->add_actions();
  }

  public function define() {
    define( 'GWP_CHIP_FILE', __FILE__ );
  }

  public function includes() {
    $includes_dir = dirname( __FILE__ ) . '/includes';
    include $includes_dir . '/api.php';
    include $includes_dir . '/helper.php';

    if ( is_admin() ){
      include $includes_dir . '/admin/settings.php';
      include $includes_dir . '/admin/global-settings.php';
      include $includes_dir . '/admin/metabox-settings.php';
    }

    include $includes_dir . '/listener.php';
    include $includes_dir . '/purchase.php';
  }

  public function add_filters() {
    add_filter( 'give_payment_gateways', array( $this, 'register_payment_method' ) );
    add_filter( 'give_get_sections_gateways', array( $this, 'register_payment_gateway_sections' ) );
    add_filter( 'give_enabled_payment_gateways', array( $this, 'filter_gateway' ), 10, 2 );
  }

  public function add_actions() {
    add_action( 'give_before_chip_info_fields', array( $this, 'billing_fields' ) );
  }

  public function register_payment_method( $gateways ) {
    
    $gateways['chip'] = array(
      'admin_label'    => __( 'CHIP', 'chip-for-givewp' ),
      'checkout_label' => __( 'Online Banking/Credit Card', 'chip-for-givewp' ),
    );
    
    return apply_filters( 'gwp_chip_register_payment_method' , $gateways);
  }

  public function register_payment_gateway_sections( $sections ) {

    $sections['chip-settings'] = __( 'CHIP', 'chip-for-givewp' );

    return $sections;
  }

  public function filter_gateway( $gateway_list, $form_id ) {
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

  public function billing_fields( $form_id ) {
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
}

ChipGiveWP::get_instance();
