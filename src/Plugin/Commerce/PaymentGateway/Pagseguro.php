<?php

namespace Drupal\commerce_pagseguro_v2\Plugin\Commerce\PaymentGateway;

use Exception;
use Drupal\commerce_price\Price;
use Drupal\commerce_payment\CreditCard;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Component\Datetime\TimeInterface;
use Symfony\Component\HttpFoundation\Request;
use Drupal\commerce_payment\PaymentTypeManager;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Drupal\commerce_payment\Entity\PaymentInterface;
use Drupal\commerce_payment\PaymentMethodTypeManager;
use Drupal\commerce_payment\Entity\PaymentMethodInterface;
use Drupal\commerce_payment\Exception\HardDeclineException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Drupal\commerce_pagseguro_v2\Plugin\Commerce\PaymentGateway\PaymentRequest;
use Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\OnsitePaymentGatewayBase;

/**
 * Provides the QuickPay onsite checkout payment gateway
 * 
 * @CommercePaymentGateway(
 *  id = "quickpay_pagseguro_checkout",
 *  label = @Translation("QuickPay Pagseguro"),
 *  display_label = @Translation("QuickPay Pagseguro"),
 *  forms = {
 *     "add-payment-method" = "Drupal\commerce_pagseguro_v2\PluginForm\Onsite\PaymentMethodAddForm",
 *   },
 *  js_library = "commerce_pagseguro_v2/commerce_pagseguro",
 *  payment_method_types = {"pag_credit_card"},
 *  credit_card_types = {
 *    "amex", "dinersclub", "discover", "jcb", "maestro", "mastercard", "visa",
 *  },
 *  requires_billing_information = FALSE,
 * )
 */
class Pagseguro extends OnsitePaymentGatewayBase {


  protected $token;

  protected $token_sandbox;

  protected $email;

  /**
   * {@inheritdoc}
   */
  public function getToken() {
    return $this->token;
  }

  /**
   * {@inheritdoc}
   */
  public function getSandboxToken() {
    return $this->token_sandbox;
  }

  /**
   * {@inheritdoc}
   */
  public function getEmail() {
    return $this->email;
  }

  /**
   * {@inheritdoc}
   */
  public function getConfig() {
    return $this->config;
  }


  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager, PaymentTypeManager $payment_type_manager, PaymentMethodTypeManager $payment_method_type_manager, TimeInterface $time) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $entity_type_manager, $payment_type_manager, $payment_method_type_manager, $time);

    $this->email = $this->configuration['email'];
    $this->token = $this->configuration['token'];
    $this->token_sandbox = $this->configuration['token_sandbox'];
    $this->endpoint = $this->configuration['endpoint'];
    $this->endpoint_sandbox = $this->configuration['endpoint_sandbox'];
    $this->config = $this->configuration;

  }

    public function defaultConfiguration() {
        return [
            'token' => '',
            'token_sandbox' => '',
            'email' => '',
            'endpoint' => '',
            'endpoint_sandbox' => '',
          ] + parent::defaultConfiguration();
      }


    public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
        $form = parent::buildConfigurationForm($form, $form_state);
    
        $form['token'] = [
          '#type' => 'textfield',
          '#title' => $this->t('Token'),
          '#description' => $this->t('This is the private key from the Quickpay manager.'),
          '#default_value' => $this->configuration['token'],
          '#required' => TRUE,
        ];

        $form['token_sandbox'] = [
          '#type' => 'textfield',
          '#title' => $this->t('Sandbox token'),
          '#description' => $this->t('The sandbox token of Pagseguro'),
          '#default_value' => $this->configuration['token_sandbox'],
          '#required' => FALSE,
        ];

        $form['endpoint'] = [
          '#type' => 'textfield',
          '#title' => $this->t('PagSeguro API endpoint'),
          '#description' => $this->t('This is the private key from the Quickpay manager.'),
          '#default_value' => $this->configuration['endpoint'],
          '#required' => TRUE,
        ];

        $form['endpoint_sandbox'] = [
          '#type' => 'textfield',
          '#title' => $this->t('PagSeguro API endpoint'),
          '#description' => $this->t('This is the private key from the Quickpay manager.'),
          '#default_value' => $this->configuration['endpoint_sandbox'],
          '#required' => TRUE,
        ];
    
        $form['email'] = [
          '#type' => 'textfield',
          '#title' => $this->t('Email'),
          '#description' => $this->t('The API key for the same user as used in Agreement ID.'),
          '#default_value' => $this->configuration['email'],
          '#required' => TRUE,
        ];
    
        return $form;
      }


      public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
        parent::submitConfigurationForm($form, $form_state);

        $values = $form_state->getValue($form['#parents']);
        $this->configuration['token'] = $values['token'];
        $this->configuration['token_sandbox'] = $values['token_sandbox'];
        $this->configuration['email'] = $values['email'];
        $this->configuration['endpoint'] = $values['endpoint'];
        $this->configuration['endpoint_sandbox'] = $values['endpoint_sandbox'];
      }


        /**
   * {@inheritdoc}
   */
  public function createPayment(PaymentInterface $payment, $capture = TRUE) {

    $order = $payment->getOrder();
    $payment_method = $payment->getPaymentMethod();

    $response = $this->fecharPedido($order, $payment);

    $this->assertPaymentState($payment, ['new']);
    $this->assertPaymentMethod($payment_method);

    $status = $this->mapPagseguroStatus($response['status']);
    $payment->setState($status);
    $payment->setRemoteId($response['code']);
    $payment->save();
  }

   /**
   * {@inheritdoc}
   */
  public function createPaymentMethod(PaymentMethodInterface $payment_method, array $payment_details) {
   
  }

  /**
   * {@inheritdoc}
   */
  public function deletePaymentMethod(PaymentMethodInterface $payment_method) {
    // Delete the remote record here, throw an exception if it fails.
    // See \Drupal\commerce_payment\Exception for the available exceptions.
    // Delete the local entity.
    $payment_method->delete();
  }

  /**
   * {@inheritdoc}
   */

  public function fecharPedido(OrderInterface $order, $payment) {

    $payment_method = $payment->getPaymentMethod();
    $items = $order->getItems();
    $shipments = $order->shipments->entity;
    $billing = $order->getBillingProfile();

    $credetial = [
      'email' => $this->email,
      'token' => $this->token,
      'receiverEmail' => $this->email,
    ];
    
    $payment_request  = new PaymentRequest;
    $dadosFecharPedidos = $payment_request->build($order, $payment, $payment_method, $items, $shipments, $billing, $credetial);
   
    $query = http_build_query($dadosFecharPedidos);
    try {
      $url = $this->endpoint . '/transactions';
      $client = \Drupal::httpClient();
      $response = $client->request('POST', $url, [
          'headers' => [
              'Content-Type' => 'application/x-www-form-urlencoded;charset=UTF-8',
          ],
          'body' => $query,
      ]);
      $xml = simplexml_load_string($response->getBody()->getContents());
      $json = json_encode($xml);
      return json_decode($json, true);
    }
    catch(Exception $e) {
      echo $e->getMessage();
    }

  }

  /**
   * Convert pagseguro's status codes to translatable string status description.
   *
   * @todo Change return to a translatable string?
   *
   * @param int $status
   *   Status code returned by pagseguro.
   *
   * @return string
   *   String status description.
   */
  private function mapPagseguroStatus($status) {
    $return = '';
    switch ($status) {
      // t('Awaiting payment')
      case '1':
        $return = 'completed';
        break;

      // t('Under analysis')
      case '2':
        $return = 'completed';
        break;

      // t('Paid')
      case '3':
        $return = 'completed';
        break;

      // t('In dispute')
      case '5':
        $return = 'authorization';
        break;

      // t('Refunded')
      case '6':
        $return = 'authorization';
        break;

      // t('Canceled')
      case '7':
        $return = 'authorization';
        break;
    }
    return $return;
  }



   
  
}
