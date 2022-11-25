<?php

class ChipGiveWPRefundButton {

  private static $_instance;

  public static function get_instance() {
    if ( self::$_instance == null ) {
      self::$_instance = new self();
    }

    return self::$_instance;
  }

  public function __construct()
  {
    if ( !defined( 'GWP_CHIP_DISABLE_REFUND_PAYMENT' ) ) {
      $this->add_actions();
    }
  }

  public function add_actions() {
    add_action( 'give_view_donation_details_payment_meta_after', array( $this, 'refund_button') );
    add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_js' ) );
    add_action( 'wp_ajax_gwp_chip_refund', array( $this, 'refund' ), 10, 0 );
  }

  public function refund_button( $donation_id ) {
    if ( give_get_payment_gateway( $donation_id ) != 'chip' ) {
      return;
    }

    if ( !give_is_payment_complete( $donation_id ) ) {
      return;
    }

    if ( !give_get_meta( $donation_id, '_give_payment_transaction_id', true ) ) {
      return;
    }

    ?>
    <div class="give-order-tx-id give-admin-box-inside">
      <p>
        <button id="chip-refund-button" class="button button-primary" data-donation-id="<?php echo absint( $donation_id ); ?>"><?php _e( 'Refund', 'chip-for-givewp' ); ?></button>
      </p>
    </div>
    <?php
  }

  public function enqueue_js( $hook ) {
    if ('give_forms_page_give-payment-history' === $hook) {
      wp_enqueue_script( 'gwp_chip_metabox', plugins_url( 'includes/js/refund.js', GWP_CHIP_FILE ) );
    }
  }

  public function refund() {
    $donation_id = absint( $_POST['donation_id'] );

    if ( ! current_user_can( 'edit_give_payments', $donation_id ) ) {
      wp_die( __( 'You do not have permission to refund payments.', 'chip-for-givewp' ), __( 'Error', 'chip-for-givewp' ), array( 'response' => 403 ) );
    }

    if ( empty( $donation_id ) ) {
      die( '-1' );
    }

    if ( !give_is_payment_complete( $donation_id ) ) {
      wp_die( __( 'Donation is not in completed state.', 'chip-for-givewp' ), __( 'Error', 'chip-for-givewp' ), array( 'response' => 403 ) );
    }

    $form_id = give_get_payment_form_id( $donation_id );
    $customization = give_get_meta( $form_id, '_give_customize_chip_donations', true );

    $prefix = '';
    if ( give_is_setting_enabled( $customization ) ) {
      $prefix = '_give_';
    }

    $secret_key = give_is_test_mode() ? ChipGiveWPHelper::get_fields($form_id, 'chip-test-secret-key', $prefix) : ChipGiveWPHelper::get_fields($form_id, 'chip-secret-key', $prefix);
    $payment_id = give_get_meta( $donation_id, '_give_payment_transaction_id', true );

    $chip = ChipGiveWPAPI::get_instance($secret_key, '');
    $payment = $chip->refund_payment( $payment_id );

    if ( !is_array($payment) || !array_key_exists('id', $payment) ) {
      wp_die( __( 'There was an error while refunding the payment.', 'chip-for-givewp' ), __( 'Error', 'chip-for-givewp' ), array( 'response' => 403 ) );
    }

    give_update_payment_status( $donation_id, 'refunded' );

    $note_id = Give()->comment->db->add(
      array(
        'comment_parent'  => $donation_id,
        'user_id'         => get_current_user_id(),
        'comment_content' => sprintf( __('Donation has been refunded with ID: %s', 'chip-for-givewp' ), $payment['id'] ),
        'comment_type'    => 'donation',
      )
    );

    do_action( 'give_donor-note_email_notification', $note_id, $donation_id );
    
    die( give_get_payment_note_html( $note_id ) );
  }
}

ChipGiveWPRefundButton::get_instance();