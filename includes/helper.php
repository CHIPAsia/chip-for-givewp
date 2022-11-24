<?php

class ChipGiveWPHelper {
  
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
}