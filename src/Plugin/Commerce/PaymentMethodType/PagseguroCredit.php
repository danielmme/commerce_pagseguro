<?php

namespace Drupal\commerce_pagseguro_v2\Plugin\Commerce\PaymentMethodType;

// Unused statments.
// Drupal\commerce_payment\Plugin\Commerce\PaymentMethodType\PaymentMethodTypeBase.
// Drupal\commerce_payment\Entity\PaymentMethodInterface.
use Drupal\entity\BundleFieldDefinition;
use Drupal\commerce_payment\Entity\PaymentMethodInterface;
use Drupal\commerce_payment\Plugin\Commerce\PaymentMethodType\CreditCard;
use Drupal\commerce_payment\Plugin\Commerce\PaymentMethodType\PaymentMethodTypeBase;

/**
 * Provides the Authorize.net eCheck payment method type.
 *
 * @CommercePaymentMethodType(
 *   id = "pag_credit_card",
 *   label = @Translation("Cartão de crédito"),
 *   create_label = @Translation("Cartão de crédito"),
 * )
 */
class PagseguroCredit extends CreditCard {

  /**
   * {@inheritdoc}
   */
  public function buildFieldDefinitions() {
    $fields = parent::buildFieldDefinitions();

    $fields['cpf'] = BundleFieldDefinition::create('string')
      ->setLabel(t('CPF'))
      ->setDescription(t('The CPF number of card holder'))
      ->setRequired(TRUE);

    $fields['birth_date'] = BundleFieldDefinition::create('string')
      ->setLabel(t('Birth Date'))
      ->setDescription(t('The Birth Date of card holder'))
      ->setRequired(TRUE);

    $fields['card_holder_name'] = BundleFieldDefinition::create('string')
      ->setLabel(t('Holder name'))
      ->setDescription(t('The name of card holder'))
      ->setRequired(TRUE);

    $fields['sender_hash'] = BundleFieldDefinition::create('string')
      ->setLabel(t('Sender hash'))
      ->setDescription(t('The sender hash code returned by Pagseguro'))
      ->setRequired(FALSE);
    
    $fields['card_hash'] = BundleFieldDefinition::create('string')
      ->setLabel(t('Card hash'))
      ->setDescription(t('The card hash returned by Pagseguro'))
      ->setRequired(FALSE);
      
    $fields['card_brand'] = BundleFieldDefinition::create('string')
      ->setLabel(t('Card brand'))
      ->setDescription(t('The card hash returned by Pagseguro'))
      ->setRequired(FALSE);

    $fields['installments'] = BundleFieldDefinition::create('string')
      ->setLabel(t('Installments'))
      ->setDescription(t('The installments by Pagseguro'))
      ->setRequired(FALSE);

    return $fields;
  }

}
