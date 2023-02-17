<?php

class Give_Recurring_Chip extends Give_Recurring_Gateway {

  public $chip_purchase;

  public function init() {

    $this->id = 'chip';
    $this->offsite = true;

    add_action( "give_recurring_cancel_{$this->id}_subscription", array( $this, 'cancel' ), 10, 2 );
  }

  public function cancel( $subscription, $valid ) {

    // Bailout, if no access cancel subscription.
    if ( empty( $valid ) ) {
      return false;
    }

    // Call CHIP cancel recurring with:
    // $subscription->profile_id;
    // return true if success and false if otherwise
    return true;
  }

  public function can_cancel( $ret, $subscription ) {

    if (
      $subscription->gateway === $this->id &&
      ! empty( $subscription->profile_id ) &&
      'active' === $subscription->status
    ) {
      $ret = true;
    }

    return $ret;
  }

  public function create_payment_profiles() {
    $form_id = $this->subscriptions['form_id'];

    $customization = give_get_meta( $form_id, '_give_customize_chip_donations', true );

    $prefix = '';
    if ( give_is_setting_enabled( $customization ) ) {
      $prefix = '_give_';
    }

    $secret_key = give_is_test_mode() ? Chip_Givewp_Helper::get_fields($form_id, 'chip-test-secret-key', $prefix) : Chip_Givewp_Helper::get_fields($form_id, 'chip-secret-key', $prefix);
    $brand_id  = Chip_Givewp_Helper::get_fields($form_id, 'chip-brand-id', $prefix);
    $chip = Chip_Givewp_API::get_instance('','');
    $chip->set_keys($secret_key, $brand_id);

    $get_client_by_email = $chip->get_client_by_email($this->purchase_data['user_email']);
    
    if (array_key_exists('__all__', $get_client_by_email)) {
      give_set_error( '1', __('Invalid Secret Key', 'chip-for-givewp') );
      return;
    }

    if (is_array($get_client_by_email['results']) AND !empty($get_client_by_email['results'])) {
      $client = $get_client_by_email['results'][0];
    } else {
      $client = $chip->create_client(array(
        'full_name' => substr($this->purchase_data['post_data']['give_first'] . ' ' . $this->purchase_data['post_data']['give_last'], 0, 30),
        'email' => $this->purchase_data['post_data']['give_email'],
      ));
    }

    if (array_key_exists('__all__', $client)) {
      give_set_error( '1', __('Failed to retrieve client', 'chip-for-givewp') );
      return;
    }

    $billing_template_params = array(
      'success_redirect' => 'https://google.com',
      'failure_redirect' => 'https://google.com',
      'purchase' => array(
        'currency' => 'MYR',
        'products' => array(
          array(
            'name' => give_payment_gateway_item_title($this->purchase_data, 256),
            'price' => round($this->purchase_data['price'] * 100),
          )
          ),
        'notes' => 'recurring test',
        'timezone' => 'Asia/Kuala_Lumpur',
        'due_strict' => true
      ),
      'brand_id' => $brand_id,
      'title' => substr(give_payment_gateway_item_title($this->purchase_data, 128) . ' ' . $this->purchase_data['post_data']['give_first'] . ' ' . $this->purchase_data['post_data']['give_last'], 0, 256),
      'is_subscription' => true,
      'subscription_period' => $this->get_subscription_period($this->subscriptions['period'], $this->subscriptions['frequency']),
      'subscription_period_units' => $this->get_subscription_period_units($this->subscriptions['period']),
      "subscription_due_period" => 1,
      "subscription_due_period_units" => "days",
      'subscription_charge_period_end' => false,
      'subscription_trial_periods' => 0,
      'subscription_active' => true,
      'force_recurring' => true
    );

    $billing_templates = $chip->create_billing_templates($billing_template_params);

    $purchase = $chip->add_subscriber($billing_templates['id'], array(
      'client_id' => $client['id'],
      'send_invoice_on_charge_failure' => true,
      'send_invoice_on_add_subscriber' => false,
      'send_receipt' => true,
    ));

    
		// This is a temporary ID used to look it up later during webhook events
		$this->subscriptions['profile_id'] = $billing_templates['id'];
    $this->subscriptions['transaction_id'] = $purchase['purchase']['id'];

    $this->chip_purchase = $purchase;
	}

  private function get_subscription_period($subscription, $frequency) {
    if (in_array($subscription, array('day', 'week', 'month'))){
      return $frequency;
    } elseif ($subscription == 'quarter') {
      return $frequency * 3;
    }
    
    return $frequency * 12;
  }

  private function get_subscription_period_units($subscription) {
    if ($subscription == 'day') {
      return 'days';
    } elseif ($subscription == 'week'){
      return 'weeks';
    }
      
    return 'months';
  }

  public function link_profile_id( $profile_id, $subscription ) {

    if ( ! empty( $profile_id ) ) {
      $html       = '<a href="%s" target="_blank">' . $profile_id . '</a>';
      $base_url   = 'https://gate.chip-in.asia/t/';
      $link       = esc_url( $base_url . '56d72c15-c42b-4214-877d-38b811f20392/billing/' . $profile_id . '/' );
      $profile_id = sprintf( $html, $link );
    }

    return $profile_id;

  }

  public function complete_signup() {
    wp_redirect( $this->chip_purchase['purchase']['checkout_url'] );
    exit;
  }
}

new Give_Recurring_Chip();