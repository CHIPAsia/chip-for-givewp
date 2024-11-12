<?php

use Give\Framework\PaymentGateways\PaymentGateway;
use Give\Donations\Models\Donation;
use Give\Framework\PaymentGateways\Commands\GatewayCommand;
class ChipGateway extends PaymentGateway {
  public static function id(): string {
    return 'chip_block';
  }

  public function getId(): string {
    return self::id();
  }

  public function getName(): string {
    return __('CHIP', 'give');
  }

  public function getPaymentMethodLabel(): string {
    return __('CHIP', 'give');
  }

  public function createPayment(Donation $donation, $gatewayData): GatewayCommand {
  }

  public function refundDonation(Donation $donation) {
  }

  public function formSettings(int $formId): array {
    exit('fff');
    return [
      'formId' => $formId,
    ];
  }

  public function supportsFormVersions(): array {
    return [3];
  }

}