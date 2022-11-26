<?php
use Give\Log\ValueObjects\LogType;

class Chip_Givewp_Refund_Button {

  private static $_instance;

  public static function get_instance() {

    if ( self::$_instance == null ) {
      self::$_instance = new self();
    }

    return self::$_instance;
  }

  public function __construct() {

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
        <button id="chip-refund-button" class="button button-primary" data-nonce="<?php echo wp_create_nonce( 'gwp_chip_refund_payment' ); ?>" data-donation-id="<?php echo esc_attr( $donation_id ); ?>"><?php _e( 'Refund', 'chip-for-givewp' ); ?></button>
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

    check_admin_referer( 'gwp_chip_refund_payment', 'gwp_chip_refund_payment' );

    $donation_id = absint( $_POST['donation_id'] );

    if ( empty( $donation_id ) ) {
      Chip_Givewp_Helper::log( null, LogType::ERROR, __( 'Donation ID was empty', 'chip-for-givewp' ) );
      die( '-1' );
    }

    if ( ! current_user_can( 'edit_give_payments', $donation_id ) ) {
      Chip_Givewp_Helper::log( $donation_id, LogType::ERROR, __( 'User didn\'t have permission to refund payment', 'chip-for-givewp' ) );
      wp_die( __( 'You do not have permission to refund payments.', 'chip-for-givewp' ), __( 'Error', 'chip-for-givewp' ), array( 'response' => 403 ) );
    }

    if ( !give_is_payment_complete( $donation_id ) ) {
      Chip_Givewp_Helper::log( $donation_id, LogType::ERROR, __( 'Donation is not in completed state.', 'chip-for-givewp' ) );
      wp_die( __( 'Donation is not in completed state.', 'chip-for-givewp' ), __( 'Error', 'chip-for-givewp' ), array( 'response' => 403 ) );
    }

    $form_id       = give_get_payment_form_id( $donation_id );
    $customization = give_get_meta( $form_id, '_give_customize_chip_donations', true );

    $prefix = '';
    if ( give_is_setting_enabled( $customization ) ) {
      $prefix = '_give_';
    }

    $secret_key = give_is_test_mode() ? Chip_Givewp_Helper::get_fields($form_id, 'chip-test-secret-key', $prefix) : Chip_Givewp_Helper::get_fields($form_id, 'chip-secret-key', $prefix);
    $payment_id = give_get_meta( $donation_id, '_give_payment_transaction_id', true );

    $chip    = Chip_Givewp_API::get_instance($secret_key, '');
    $payment = $chip->refund_payment( $payment_id );

    if ( !is_array($payment) || !array_key_exists('id', $payment) ) {
      $msg = sprintf( __('There was an error while refunding the payment. Details: %s', 'chip-for-givewp' ), print_r($payment, true));
      Chip_Givewp_Helper::log( $donation_id, LogType::ERROR, $msg );
      wp_die( $msg, __( 'Error', 'chip-for-givewp' ), array( 'response' => 403 ) );
    }

    Chip_Givewp_Helper::log( $donation_id, LogType::HTTP, __('Payment refunded.', 'chip-for-givewp'), $payment );

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

Chip_Givewp_Refund_Button::get_instance();