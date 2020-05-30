<?php

namespace Drupal\commerce_pagseguro_v2\Plugin\Commerce\PaymentType;

use Drupal\commerce_payment\Plugin\Commerce\PaymentType\PaymentTypeBase;

/**
 * Provides the default payment type.
 *
 * @CommercePaymentType(
 *   id = "credit_card_v2",
 *   label = @Translation("Pagseguro Credit Cardsssssssss"),
 *   workflow = "pagseguro_credit",
 * )
 */
class PagseguroCredit extends PaymentTypeBase {

  /**
   * {@inheritdoc}
   */
  public function buildFieldDefinitions() {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getLabel() {
    return $this->pluginDefinition['label'];
  }

}
