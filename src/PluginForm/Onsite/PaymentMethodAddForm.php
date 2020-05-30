<?php

namespace Drupal\commerce_pagseguro_v2\PluginForm\Onsite;

use Exception;
use Drupal\commerce_order\Entity\Order;
use Drupal\commerce_payment\CreditCard;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Drupal\commerce_payment\Exception\DeclineException;
use Drupal\commerce_payment\PluginForm\PaymentMethodFormBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\commerce_payment\Exception\PaymentGatewayException;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Ajax\OpenModalDialogCommand;

class PaymentMethodAddForm extends PaymentMethodFormBase {

  /**
   * {@inheritdoc}
   */
  public function getErrorElement(array $form, FormStateInterface $form_state) {
    return $form['payment_details'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);
    /** @var \Drupal\commerce_payment\Entity\PaymentMethodInterface $payment_method */
    $payment_method = $this->entity;

    $form['payment_details'] = [
      '#parents' => array_merge($form['#parents'], ['payment_details']),
      '#type' => 'container',
      '#payment_method_type' => $payment_method->bundle(),
    ];
    if ($payment_method->bundle() == 'pag_credit_card') {
      $form['payment_details'] = $this->buildCreditCardForm($form['payment_details'], $form_state);
    }
    elseif ($payment_method->bundle() == 'paypal') {
      $form['payment_details'] = $this->buildPayPalForm($form['payment_details'], $form_state);
    }
    // Move the billing information below the payment details.
    if (isset($form['billing_information'])) {
      $form['billing_information']['#weight'] = 10;
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::validateConfigurationForm($form, $form_state);
    /** @var \Drupal\commerce_payment\Entity\PaymentMethodInterface $payment_method */
    $payment_method = $this->entity;

    if ($payment_method->bundle() == 'pag_credit_card') {
      $this->validateCreditCardForm($form['payment_details'], $form_state);
    }
    elseif ($payment_method->bundle() == 'paypal') {
      $this->validatePayPalForm($form['payment_details'], $form_state);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::submitConfigurationForm($form, $form_state);
    /** @var \Drupal\commerce_payment\Entity\PaymentMethodInterface $payment_method */
    $payment_method = $this->entity;

    if ($payment_method->bundle() == 'pag_credit_card') {
      $this->submitCreditCardForm($form['payment_details'], $form_state);
    }
    elseif ($payment_method->bundle() == 'paypal') {
      $this->submitPayPalForm($form['payment_details'], $form_state);
    }

    $values = $form_state->getValue($form['#parents']);
    /** @var \Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\SupportsStoredPaymentMethodsInterface $payment_gateway_plugin */
    $payment_gateway_plugin = $this->plugin;
    // The payment method form is customer facing. For security reasons
    // the returned errors need to be more generic.
    try {
      $payment_gateway_plugin->createPaymentMethod($payment_method, $values['payment_details']);
    }
    catch (DeclineException $e) {
      $this->logger->warning($e->getMessage());
      throw new DeclineException(t('We encountered an error processing your payment method. Please verify your details and try again.'));
    }
    catch (PaymentGatewayException $e) {
      $this->logger->error($e->getMessage());
      throw new PaymentGatewayException(t('We encountered an unexpected error processing your payment method. Please try again later.'));
    }
  }

  /**
   * Builds the credit card form.
   *
   * @param array $element
   *   The target element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the complete form.
   *
   * @return array
   *   The built credit card form.
   */
  protected function buildCreditCardForm(array $element, FormStateInterface $form_state) {

    $plugin = $this->plugin;
    $config = $plugin->getConfig();

    $amount = 0;
    // Loading order for loading total price and customer.
    $param = \Drupal::routeMatch()->getParameter('commerce_order');
    if (isset($param)) {
      if(method_exists($param,'id')) {
        $order_id = $param->id();
        $order = Order::load($order_id);
        $getOrder = $order->getTotalPrice()->getNumber();
        $amount = number_format($getOrder, 2,'.','');
      }
    }
  
    // Build a month select list that shows months with a leading zero.
    $months = [];
    for ($i = 1; $i < 13; $i++) {
      $month = str_pad($i, 2, '0', STR_PAD_LEFT);
      $months[$month] = $month;
    }
    // Build a year select list that uses a 4 digit key with a 2 digit value.
    $current_year = date('Y');
    $years = [];
    for ($i = 0; $i < 14; $i++) {
      $years[$current_year + $i] = $current_year + $i;
    }

    $parcelas = [
      -1 => 'Escolha a quantidade de parcelas...',
    ];

    $element['#attributes']['class'][] = 'credit-card-form';
    // Placeholder for the detected card type. Set by validateCreditCardForm().
    $element['type'] = [
      '#type' => 'hidden',
      '#value' => '',
    ];
    $element['number'] = [
      '#type' => 'textfield',
      '#title' => t('Card number'),
      '#attributes' => [
        'autocomplete' => 'off', 
        'id' => 'card-number'
      ],
      '#required' => TRUE,
      '#maxlength' => 19,
      '#size' => 20,
      '#suffix' => '<div id="show-card-brand"></div>',
    ];

    $element['expiration'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['credit-card-form__expiration'],
      ],
    ];

    $element['expiration']['month'] = [
      '#type' => 'select',
      '#title' => t('Month'),
      '#options' => $months,
      '#default_value' => date('m'),
      '#required' => TRUE,
      '#attributes' => ['id' => 'expiration-month'],
    ];

    $element['expiration']['divider'] = [
      '#type' => 'item',
      '#title' => '',
      '#markup' => '<span class="credit-card-form__divider">/</span>',
    ];

    $element['expiration']['year'] = [
      '#type' => 'select',
      '#title' => t('Year'),
      '#options' => $years,
      '#default_value' => $current_year,
      '#required' => TRUE,
      '#prefix' => '<span>&nbsp;&nbsp;',
      '$suffix' => '</span>',
      '#attributes' => ['id' => 'expiration-year'],
    ];

    $element['expiration']['security_code'] = [
      '#type' => 'textfield',
      '#title' => t('CVV'),
      '#attributes' => [
        'autocomplete' => 'off',
        'id' => 'security-code',
      ],
      '#required' => TRUE,
      '#maxlength' => 10,
      '#size' => 10,
    ];

    $element['card_holder_name'] = [
      '#type' => 'textfield',
      '#title' => t('Nome Impresso no cartão'),
      '#attributes' => [
        'autocomplete' => 'off',
        'id' => 'holder-name',
      ],
      '#required' => TRUE,
      '#maxlength' => 60,
      '#size' => 60,
    ];

    $element['information'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['credit-card-form__information'],
      ],
    ];

    $element['information']['cpf'] = [
      '#type' => 'textfield',
      '#title' => t('CPF do titular'),
      '#attributes' => [
        'autocomplete' => 'off',
        'id' => 'cpf-card',
      ],
      '#required' => TRUE,
      '#maxlength' => 20,
      '#size' => 20,
      '#prefix' => '<span>&nbsp;&nbsp;',
      '$suffix' => '</span>',
    ];

    $element['information']['birth_date'] = [
      '#type' => 'date',
      '#title' => t('Data de aniversário'),
      '#attributes' => [
        'autocomplete' => 'off',
        'id' => 'birth-card',
      ],
      '#date_date_format' => 'd/m/Y',
      '#required' => TRUE,
    ];

    $element['installments'] = [
      '#type' => 'select',
      '#title' => t('Deseja parcelar?'),
      '#options' => $parcelas,
      '#default_value' => $parcelas,
      '#required' => TRUE,
      '#attributes' => ['id' => 'installments'],
      '#validated' => TRUE,
    ];

    $element['sender_hash'] = [
      '#type' => 'hidden',
      '#default_value' => '',
      '#attributes' => ['id' => 'sender-hash'],
    ];

    $element['card_hash'] = [
      '#type' => 'hidden',
      '#default_value' => '',
      '#attributes' => ['id' => 'card-hash'],
    ];

    $element['card_brand'] = [
      '#type' => 'hidden',
      '#default_value' => '',
      '#attributes' => ['id' => 'card-brand'],
    ];

    $element['#attached']['library'][] = 'commerce_pagseguro_v2/pagseguro_sandbox';

    $session = $this->getSession($config);
    // Passing the params session to the .js.
    $element['#attached']['drupalSettings']['commercePagseguroV2']['commercePagseguro']['session'] = $session;
    $element['#attached']['drupalSettings']['commercePagseguroV2']['commercePagseguro']['amount'] = $amount;

    return $element;
  }

  /**
   * Validates the credit card form.
   *
   * @param array $element
   *   The credit card form element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the complete form.
   */
  protected function validateCreditCardForm(array &$element, FormStateInterface $form_state) {
    $values = $form_state->getValue($element['#parents']);
 
    $card_type = CreditCard::detectType($values['number']);
    if (!$card_type) {
      $form_state->setError($element['number'], t('You have entered a credit card number of an unsupported card type.'));
      return;
    }
    if (!CreditCard::validateNumber($values['number'], $card_type)) {
      $form_state->setError($element['number'], t('You have entered an invalid credit card number.'));
    }
    if (!CreditCard::validateExpirationDate($values['expiration']['month'], $values['expiration']['year'])) {
      $form_state->setError($element['expiration'], t('You have entered an expired credit card.'));
    }
    if (!CreditCard::validateSecurityCode($values['expiration']['security_code'], $card_type)) {
      $form_state->setError($element['expiration']['security_code'], t('You have entered an invalid CVV.'));
    }

    // Persist the detected card type.
    $form_state->setValueForElement($element['type'], $card_type->getId());
  }

  /**
   * Handles the submission of the credit card form.
   *
   * @param array $element
   *   The credit card form element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the complete form.
   */
  protected function submitCreditCardForm(array $element, FormStateInterface $form_state) {

    $values = $form_state->getValue($element['#parents']);
 
    $this->entity->card_type = $values['type'];
    $this->entity->card_number = substr($values['number'], -4);
    $this->entity->card_exp_month = $values['expiration']['month'];
    $this->entity->card_exp_year = $values['expiration']['year'];
    $this->entity->cpf = $values['information']['cpf'];
    $this->entity->birth_date = $values['information']['birth_date'];
    $this->entity->card_holder_name = $values['card_holder_name'];
    $this->entity->sender_hash = $values['sender_hash'];
    $this->entity->card_hash = $values['card_hash'];
    $this->entity->card_brand = $values['card_brand'];
    $this->entity->installments = $values['installments'];
  }

  protected function getSession($config) {
 
    try {
      $url = $config['endpoint'] . '/sessions';
      $client = \Drupal::httpclient();
      $response = $client->request('POST', $url, [
          'query' => [
              'token' => $config['token'],
              'email' => $config['email'],
          ],
      ]);
      $xml = simplexml_load_string($response->getBody()->getContents());
      $json = json_encode($xml->id);
      return json_decode($json, true)[0]; 
    }
    catch(Exception $e) {
      return [];
    }
  }

}
