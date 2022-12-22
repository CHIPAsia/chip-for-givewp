<?php

class Chip_Givewp_Admin_Metabox_Settings extends Chip_Givewp_Admin_Settings {

  private static $_instance;

  public static function get_instance() {

    if ( self::$_instance == null ) {
      self::$_instance = new self();
    }

    return self::$_instance;
  }

  public function __construct() {

    add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_js' ) );

    add_filter( 'give_metabox_form_data_settings', array( $this, 'add_tab') );
    add_filter( 'gwp_chip_metabox_fields', array( $this, 'metabox_fields') );
  }

  public function enqueue_js( $hook ) {

    if ('post.php' === $hook || $hook === 'post-new.php') {
      wp_enqueue_script( 'gwp_chip_metabox', plugins_url( 'includes/js/metabox.js', GWP_CHIP_FILE ) );
    }
  }

  public function add_tab( $settings ) {
    if ( give_is_gateway_active( 'chip' ) ) {
      $settings['chip_metabox_options'] = apply_filters(
        'gwp_chip_metabox_options',
        [
          'id'        => 'chip_metabox_options',
          'title'     => __( 'CHIP', 'chip-for-givewp' ),
          'icon-html' => '<object data=" ' . esc_url( plugins_url( 'assets/logo.svg', GWP_CHIP_FILE ) ) . '" width="13" height="13.18"></object>',
          'fields'    => apply_filters( 'gwp_chip_metabox_fields', [] ),
        ]
      );
    }

    return $settings;
  }

  public function metabox_fields( $settings ) {
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

    $check_settings = array_merge($check_settings, $this->setting_fields('_give_') );
  
    return array_merge( $settings, $check_settings );
  }
}

Chip_Givewp_Admin_Metabox_Settings::get_instance();