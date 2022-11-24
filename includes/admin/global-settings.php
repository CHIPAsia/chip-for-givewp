<?php

class ChipGiveWPAdminGlobalSettings extends ChipGiveWPAdminSettings {

  private static $_instance;

  public static function get_instance() {
    if ( self::$_instance == null ) {
      self::$_instance = new self();
    }

    return self::$_instance;
  }

  public function __construct()
  {
    add_filter( 'give_get_settings_gateways', array( $this, 'register_setting_fields' ) );
  }
  
  public function register_setting_fields( $settings ) {

    switch ( give_get_current_setting_section() ) {
  
      case 'chip-settings':
        $settings = array(
          array(
            'id'   => 'give_title_chip',
            'type' => 'title',
          ),
        );

        $settings = array_merge( $settings, $this->setting_fields() );
  
        $settings[] = array(
          'id'   => 'give_title_chip',
          'type' => 'sectionend',
        );
  
        break;
    }
    
    return $settings;
  }
}

ChipGiveWPAdminGlobalSettings::get_instance();