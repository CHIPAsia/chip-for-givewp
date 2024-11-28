<?php
use Give\Framework\PaymentGateways\Commands\PaymentComplete;
use Give\Framework\PaymentGateways\PaymentGateway;
use Give\Donations\Models\Donation;
use Give\Framework\PaymentGateways\Commands\GatewayCommand;

class ChipGateway extends PaymentGateway {

  private static bool $scriptLoaded = false;

	public static function id(): string {
		return 'chip_block';
	}

	public function getId(): string {
		return self::id();
	}

	public function getName(): string {
    // error_log('In getName()');
		return __( 'CHIP', 'chip-give' );
	}

	public function getPaymentMethodLabel(): string {
		return __( 'CHIP', 'chip-give' );
	}

	/**
	 * Display gateway fields for v2 donation forms
	 */
	public function getLegacyFormFieldMarkup( $formId, $args ) {
    // Step 1: add any gateway fields to the form using html.  In order to retrieve this data later the name of the input must be inside the key gatewayData (name='gatewayData[input_name]').
    // Step 2: you can alternatively send this data to the $gatewayData param using the filter `givewp_create_payment_gateway_data_{gatewayId}`.
    // return "<div><input type='text' name='gatewayData[chip-gateway-id]' placeholder='CHIP gateway field' /></div>";
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

    error_log(plugin_dir_url( __FILE__ ) .'js/chip-gateway.js');


    // error_log('formId: ' . $formId);
    // wp_enqueue_script('chip-gateway', plugin_dir_url( __FILE__ ) .'js/chip-gateway.js', ['react', 'wp-element'], '1.0.0', true);
    wp_enqueue_script(
      $handle,
      plugin_dir_url( __FILE__ ) .'js/chip-gateway.js', 
      ['react', 'wp-element'], 
      '1.0.0', 
      true);

    // Check if script enqueued
    // if( true === wp_script_is( 'chip-gateway', 'enqueued' ) ){
    //   error_log('Script successfully enqueued');
    // } else {
    //   error_log('Script fail enqueued');
    // }
  }

  /**
   * Send form settings to the js gateway counterpart
   */
	public function formSettings( int $formId ): array {
		return [ 
      'clientKey' => '1234567890'
			// 'formId' => $formId,
		];
	}

  public function createPayment( Donation $donation, $gatewayData ): GatewayCommand {
    // die();
    // error_log('Create payment from GiveWP V3');
    // exit;

    $intent =  $gatewayDaya['chipGatewayIntent'] ?? 'chip-gateway-intent';

    return new PaymentComplete( "chip-gateway-transaction-id-{$intent}-$donation->id");


    // try {
    //     // Step 1: Validate any data passed from the gateway fields in $gatewayData.  Throw the PaymentGatewayException if the data is invalid.
    //     if (empty($gatewayData['chip-gateway-id'])) {
    //         throw new PaymentGatewayException(__('CHIP payment ID is required.', 'chip-give' ) );
    //     }

    //     // Step 2: Create a payment with your gateway.
    //     $response = $this->chipRequest(['transaction_id' => $gatewayData['chip-gateway-id']]);

    //     // Step 3: Return a command to complete the donation. You can alternatively return PaymentProcessing for gateways that require a webhook or similar to confirm that the payment is complete. PaymentProcessing will trigger a Payment Processing email notification, configurable in the settings.
    //     return new PaymentComplete($response['transaction_id']);
    // } catch (Exception $e) {
    //     // Step 4: If an error occurs, you can update the donation status to something appropriate like failed, and finally throw the PaymentGatewayException for the framework to catch the message.
    //     $errorMessage = $e->getMessage();

    //     $donation->status = DonationStatus::FAILED();
    //     $donation->save();

    //     DonationNote::create([
    //         'donationId' => $donation->id,
    //         'content' => sprintf(esc_html__('Donation failed. Reason: %s', 'chip-give'), $errorMessage)
    //     ]);

    //     throw new PaymentGatewayException($errorMessage);
    // }
	}

	public function refundDonation( Donation $donation ) {
	}

	// public function supportsFormVersions(): array {
	// 	return [ 3 ];
	// }

  /**
   * CHIP request to gateway
   */
  private function chipRequest(array $data): array
    {
        return array_merge([
            'success' => true,
            'transaction_id' => '1234567890',
            'subscription_id' => '0987654321',
        ], $data);
    }
}