<?php
use Give\Framework\PaymentGateways\PaymentGatewayRegister;
class Chip_Givewp_Block {
  private static $_instance;
  public static function get_instance() {
    if ( static::$_instance == null ) {
      static::$_instance = new static();
    }
    return static::$_instance;
  }
  public function __construct(){
    $this->add_actions();
    $this->add_filters();
  }
  
  public function add_actions() {
    add_action('givewp_register_payment_gateway', static function (PaymentGatewayRegister $registrar) {
      
      include plugin_dir_path( GWP_CHIP_FILE ) . 'includes/block/class-chip-gateway.php';
      $registrar->registerGateway(ChipGateway::class);
    });
  }

  public function add_filters() {
    // add_filter("")
  }
}
Chip_Givewp_Block::get_instance();