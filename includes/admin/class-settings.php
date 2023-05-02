<?php

abstract class Chip_Givewp_Admin_Settings {
  public function setting_fields( $prefix = '' ) {
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
        'id'      => $prefix . 'chip-content',
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
        'name'    => __( 'Send Receipt', 'chip-for-givewp' ),
        'desc'    => __( 'Whether to send receipt email when it\'s paid.', 'chip-for-givewp' ),
        'id'      => $prefix . 'chip-send-receipt',
        'type'    => 'radio_inline',
        'default' => 'enabled',
        'options' => [
          'enabled'  => __( 'Enabled', 'chip-for-givewp' ),
          'disabled' => __( 'Disabled', 'chip-for-givewp' ),
        ],
      ),
      array(
        'name'    => __( 'Due Strict', 'chip-for-givewp' ),
        'desc'    => __( 'Whether to permit payments when Purchase\'s due has passed.', 'chip-for-givewp' ),
        'id'      => $prefix . 'chip-due-strict',
        'type'    => 'radio_inline',
        'default' => 'disabled',
        'options' => [
          'enabled'  => __( 'Enabled', 'chip-for-givewp' ),
          'disabled' => __( 'Disabled', 'chip-for-givewp' ),
        ],
      ),
      array(
        'name'    => __( 'Due Strict Timing (minutes)', 'chip-for-givewp' ),
        'desc'    => __( 'Set timeframe allowed for a payment to be made.', 'chip-for-givewp' ),
        'id'      => $prefix . 'chip-due-strict-timing',
        'default' => '60',
        'type'    => 'number',
      )
    );

    if (class_exists('Give_Recurring') && class_exists('Give_Recurring_Gateway')) {
      $listener     = Chip_Givewp_Recurring_Listener::get_instance();
      $callback_url = $listener->get_callback_url( array() );

      $array[] = array(
        'name'    => __( 'Webhook Public Key', 'chip-for-givewp' ),
        'desc'    => sprintf( __( 'This is are mandatory option to allow recurring donation. Event must be subscribed to <code>Purchase paid</code>, <code>Subscription payment failed</code>, <code>Client cancelled billing subscription</code>. Callback URL: <code>%1$s</code>', 'chip-for-givewp' ), $callback_url ),
        'id'      => $prefix . 'chip-webhook-public-key',
        'type'    => 'textarea'
      );
    }

    if ( !empty($prefix) ) {
      for ( $i = 0; $i < sizeof($array); $i++ ) {
        $array[$i]['row_classes'] = 'give-subfield give-hidden';
      }
    }

    return apply_filters( 'gwp_chip_setting_fields', $array );
  }
}