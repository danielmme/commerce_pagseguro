<?php

namespace Drupal\commerce_pagseguro_v2\Controller;

use Exception;
use Drupal\Core\Config\ConfigFactory;
use Drupal\Core\Config\ConfigManager;
use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class PaymentNotificationController.
 */
class PaymentNotificationController extends ControllerBase {


  /**
   * Get.
   *
   * @return string
   *   Return get string.
   */
  public function get(Request $request) {

    if ($request->getMethod() == 'POST' && $request->getHost() == 'sandbox.pagseguro.uol.com.br') {

    try {
      $pageseguro_geteway = \Drupal::config('commerce_payment.commerce_payment_gateway.pageseguro_geteway');
      $email = $pageseguro_geteway->get('configuration.email');
      $token = $pageseguro_geteway->get('configuration.token');
      $endpoint = $pageseguro_geteway->get('configuration.endpoint_sandbox');
      $notificationCode = $request->request->get('notificationCode');

        $url =  $endpoint . "/transactions/notifications/" . $notificationCode . "?email=". $email . "&token=" . $token;
        $client = \Drupal::httpclient();
        $response = $client->request('GET', $url, [] );
        $return = $response->getBody()->getContents();
        $xml = simplexml_load_string(utf8_encode($return));
        $json = json_decode(json_encode($xml));

        $status = $this->mapPagseguroStatus($json->status);
        $payments = \Drupal::entityTypeManager()
          ->getStorage('commerce_payment')
          ->loadByProperties(['remote_id' => $json->code]);
        $payment = reset($payments);
        $payment->setState($status);
        $payment->save();

        return new JsonResponse('Sucesso!');

      }
      catch(Exception $e) {
        $build = [
          '#markup' => $e->getMessage(),
        ];
        return $build;
      }
    }
    else {
      return new JsonResponse('Requisição não autorizada!');
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
      case '1':
        $return = 'pending';
        break;

      case '2':
        $return = 'processing';
        break;

      case '3':
        $return = 'completed';
        break;

      case '5':
        $return = 'dispute';
        break;

      case '6':
        $return = 'refunded';
        break;

      case '7':
        $return = 'canceled';
        break;
    }
    return $return;
  }


}
