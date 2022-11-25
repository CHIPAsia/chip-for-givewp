<?php
use Give\Log\LogFactory as Log;
use Give\Log\ValueObjects\LogCategory;
class Chip_Givewp_Helper {
  
  public static function get_fields( $form_id, $column, $prefix = '' ) {
    if ( empty($prefix) ) {
      return give_get_option( $column );
    }
    return give_get_meta( $form_id, $prefix . $column, true );
  }

  public static function update_fields($form_id, $column, $value, $prefix = '') {
    if ( empty($prefix) ) {
      return give_update_option( $column, $value );
    }
    
    return give_update_meta( $form_id, $prefix . $column, $value );
  }

  public static function log( $form_id, $type, $message, $context = array() ) {
    $log = Log::makeFromArray([
      'type' => $type,
      'message' => $message,
      'category' => LogCategory::PAYMENT,
      'source' => 'CHIP for GiveWP version ' . GWP_CHIP_MODULE_VERSION,
      'context' => $context,
      'id' => $form_id
    ]);

    $log->save();

    return $log->getId();
  }
}