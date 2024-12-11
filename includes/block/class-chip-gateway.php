<?php
use Give\Framework\PaymentGateways\Commands\PaymentComplete;
use Give\Framework\PaymentGateways\PaymentGateway;
use Give\Donations\Models\Donation;
use Give\Framework\PaymentGateways\Commands\GatewayCommand;
use Give\Donations\Models\DonationNote;
use Give\Donations\ValueObjects\DonationStatus;
use Give\Framework\Exceptions\Primitives\Exception;
use Give\Framework\Http\Response\Types\RedirectResponse;
use Give\Framework\PaymentGateways\Commands\PaymentRefunded;
use Give\Framework\PaymentGateways\Commands\RedirectOffsite;
use Give\Framework\PaymentGateways\Exceptions\PaymentGatewayException;
use Give\Log\ValueObjects\LogType;

class ChipGateway extends PaymentGateway 
{
  private $debug;

  private static bool $scriptLoaded = false;

	public static function id(): string {
		return 'chip_block';
	}

	public function getId(): string {
		return self::id();
	}

	public function getName(): string {
		return __( 'CHIP', 'chip-for-givewp' );
	}

	public function getPaymentMethodLabel(): string {
		return __( 'CHIP', 'chip-for-givewp' );
	}

	/**
	 * Display gateway fields for v2 donation forms
	 */
	public function getLegacyFormFieldMarkup( $formId, $args ) {
    return "<div class=''>
            <p>You will be redirected to CHIP Payment Gateway</p>
        </div>";
	}

  /**
   * Register a js file to display gateway fields for v3 donation forms
   */
  public function enqueueScript(int $formId) {

    // Ensure loaded once
    if (self::$scriptLoaded) {
      return;
    }

    // Get handle
    $handle = $this::id();

    // Set scriptLoaded to TRUE
    self::$scriptLoaded = true;

    wp_enqueue_script(
      $handle,
      plugin_dir_url( __FILE__ ) .'js/chip-gateway.js', 
      ['react', 'wp-element'], 
      '1.0.0', 
      true);
  }

  public function createPayment( Donation $donation, $gatewayData ): RedirectOffsite {

  try {
    $give_settings = give_get_settings();

    $form_id = $donation->formId;
    
    $customization = give_get_meta( $form_id, '_give_customize_chip_donations', true );

    $prefix = '';
    if ( give_is_setting_enabled( $customization ) ) {
      $prefix = '_give_';
    }

    // Assign data
    $secret_key        = give_is_test_mode() ? Chip_Givewp_Helper::get_fields($form_id, 'chip-test-secret-key', $prefix) : Chip_Givewp_Helper::get_fields($form_id, 'chip-secret-key', $prefix);
    $due_strict        = Chip_Givewp_Helper::get_fields($form_id, 'chip-due-strict', $prefix);
    $due_strict_timing = Chip_Givewp_Helper::get_fields($form_id, 'chip-due-strict-timing', $prefix);
    $send_receipt      = Chip_Givewp_Helper::get_fields($form_id, 'chip-send-receipt', $prefix);
    $brand_id          = Chip_Givewp_Helper::get_fields($form_id, 'chip-brand-id', $prefix);
    $billing_fields    = Chip_Givewp_Helper::get_fields($form_id, 'chip-enable-billing-fields', $prefix );
    $currency        = give_get_currency( $form_id );

    // Instantiate Chip_Givewp_API
    $chip = Chip_Givewp_API::get_instance( $secret_key, $brand_id );

    // Instantiate listener 
    $listener = Chip_Givewp_Listener::get_instance();

    // Assign parameter
    $params = array(
      'success_callback' => $listener->get_callback_url( array('donation_id' => $donation->id, 'status' => 'paid') ),
      'success_redirect' => $listener->get_redirect_url( array('donation_id' => $donation->id )),
      'failure_redirect' => $listener->get_redirect_url( array('donation_id' => $donation->id, 'status' => 'error') ),
      'creator_agent'    => 'GiveWP: ' . GWP_CHIP_MODULE_VERSION,
      'reference'        => substr($donation->id,0,128),
      'platform'         => 'givewp',
      'send_receipt'     => give_is_setting_enabled( $send_receipt ),
      'due'              => time() + (absint( $due_strict_timing ) * 60),
      'brand_id'         => $brand_id,
      'client'           => [
        'email'          => $donation->email,
        'full_name'      => substr($donation->firstName . ' ' . $donation->lastName, 0, 30),
      ],
      'purchase'         => array(
        'timezone'   => apply_filters( 'gwp_chip_purchase_timezone', $this->get_timezone() ),
        'currency'   => $currency,
        'due_strict' => give_is_setting_enabled( $due_strict ),
        'products'   => array([
          'name'     => substr( $donation->formTitle , 0, 256), //substr(give_payment_gateway_item_title($payment_data), 0, 256),
          'price'    => round($donation->amount->getAmount()),
          'quantity' => '1',
        ]),
      ),
    );

    $payment = $chip->create_payment($params);

    if (!array_key_exists('id', $payment)) {
      
      Chip_Givewp_Helper::log( $form_id, LogType::ERROR, sprintf( __( 'Unable to create purchases: %s', 'chip-for-givewp' ), print_r($payment, true)) );

      give_insert_payment_note( $donation->id, __('Failed to create purchase.', 'chip-for-givewp') );
      give_send_back_to_checkout( '?payment-mode=chip' );
    }

    Chip_Givewp_Helper::log( $form_id, LogType::HTTP, sprintf( __( 'Create purchases success for donation id %1$s', 'chip-for-givewp' ), $donation->id), $payment );

    give_update_meta( $donation->id, '_chip_purchase_id', $payment['id'], '', 'donation' );

    if ( give_is_test_mode() ) {
      give_insert_payment_note( $donation->id, __('This is test environment where payment status is simulated.', 'chip-for-givewp') );
    }
    give_insert_payment_note( $donation->id, sprintf( __('URL: %1$s', 'chip-for-givewp'), $payment['checkout_url']) );

    return new RedirectOffsite($payment['checkout_url']);
  }
    catch (\Exception $e) {
			$log_message = $e->getMessage();
      error_log(print_r($log_message, true));

			$status_message = __('CHIP: Something went wrong, please contact the merchant', 'chip-for-givewp');
			throw new PaymentGatewayException($status_message);
		}
	}

	public function refundDonation( Donation $donation ) {
    // 
	}

  /**
   * Get Timezone
   * @return string
   */
  private function get_timezone() {
    if (preg_match('/^[A-z]+\/[A-z\_\/\-]+$/', wp_timezone_string())) {
      return wp_timezone_string();
    }

    return 'UTC';
  }
}