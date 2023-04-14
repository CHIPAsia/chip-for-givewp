<?php

class Chip_Givewp_Recurring {
  private static $_instance;

  public static function get_instance() {
    if ( self::$_instance == null ) {
      self::$_instance = new self();
    }

    return self::$_instance;
  }

  public function __construct(){
    $this->init();
  }

  public function init() {
    add_filter( 'give_recurring_available_gateways', array( $this, 'register_payment_gateway' ) );
  }

  public function register_payment_gateway( $gateways ) {
    $gateways['chip'] = 'Give_Recurring_Chip';
    return $gateways;
  }
}

Chip_Givewp_Recurring::get_instance();