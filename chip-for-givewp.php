<?php

/**
 * Plugin Name: CHIP for GiveWP
 * Plugin URI: https://wordpress.org/plugins/chip-for-givewp/
 * Description: CHIP - Better Payment & Business Solutions
 * Version: 1.0.3
 * Author: Chip In Sdn Bhd
 * Author URI: https://www.chip-in.asia
 *
 * Copyright: © 2023 CHIP
 * License: GNU General Public License v3.0
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 */

define( 'GWP_CHIP_MODULE_VERSION', 'v1.0.3');

class Chip_Givewp {

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
    define( 'GWP_CHIP_BASENAME', plugin_basename(GWP_CHIP_FILE));
  }

  public function includes() {
    $includes_dir = plugin_dir_path( GWP_CHIP_FILE ) . 'includes/';
    include $includes_dir . 'class-api.php';
    include $includes_dir . 'class-helper.php';

    if ( is_admin() ){
      include $includes_dir . 'admin/class-settings.php';
      include $includes_dir . 'admin/class-global-settings.php';
      include $includes_dir . 'admin/class-metabox-settings.php';
      include $includes_dir . 'admin/class-refund-button.php';
    }

    include $includes_dir . 'class-listener.php';
    include $includes_dir . 'class-purchase.php';
  }

  public function add_filters() {
    add_filter( 'plugin_action_links_' . GWP_CHIP_BASENAME, array( $this, 'setting_link' ) );
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

    return $gateway_list;
  }

  public function billing_fields( $form_id ) {
    $chip_customization = give_get_meta( $form_id, '_give_customize_chip_donations', true, 'global' );
    $billing_fields        = give_get_meta( $form_id, '_give_chip-enable-billing-fields', true );

    $global_billing_fields = give_get_option( 'chip-enable-billing-fields' );

    if (
      ( give_is_setting_enabled( $chip_customization, 'global' ) && give_is_setting_enabled( $global_billing_fields ) )
      || ( give_is_setting_enabled( $chip_customization, 'enabled' ) && give_is_setting_enabled( $billing_fields ) )
    ) {
      give_default_cc_address_fields( $form_id );
    }
  }

  public function setting_link($links) {
    $new_links = array(
      'settings' => sprintf(
        '<a href="%1$s">%2$s</a>', admin_url('edit.php?post_type=give_forms&page=give-settings&tab=gateways&section=chip-settings'), esc_html__('Settings', 'chip-for-givewp')
      )
    );

    return array_merge($new_links, $links);
  }
}

Chip_Givewp::get_instance();
