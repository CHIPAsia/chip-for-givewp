<?php

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
  die;
}

delete_option( 'chip-for-givewp-webhook' );

if ( function_exists('give_get_settings') ) {
  $give_keys      = array_keys( give_get_settings() );
  $chip_give_keys = preg_grep( '/chip-secret-key.*/', $give_keys );

  foreach( $chip_give_keys as $key ) {
    give_delete_option( $key );
  }
}