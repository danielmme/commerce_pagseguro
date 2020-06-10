<?php

namespace Drupal\commerce_pagseguro_v2\Controller;

use Exception;
use Drupal\Component\Plugin\Exception\PluginException;
use Drupal\Core\Config\ConfigFactory;
use Drupal\Core\Config\ConfigManager;
use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\HttpException;

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

   if ($request->getMethod() == 'POST') {

      $pageseguro_geteway = \Drupal::config('commerce_payment.commerce_payment_gateway.pagseguro_gateway');

      if (empty($configuration = $pageseguro_geteway->get('configuration'))) {      
        throw new \UnexpectedValueException("It is not a valid config gateway");
      }
  
      try {
        $email = $configuration['email'];
        $token = $configuration['token'];
        $endpoint = $configuration['endpoint_sandbox'];
        
      }
      catch (\UnexpectedValueException $e) {
        watchdog_exception('commerce_pagseguro_v2', $e->getMessage());
      }

      if (empty($notificationCode = $request->request->get('notificationCode'))) {
        throw new Exception("Requerid notificationCode param!");
      }

      \Drupal::logger('commerce_pagseguro_v2')->notice($request->headers);
      
      try {
        $url =  $endpoint . "/transactions/notifications/" . $notificationCode . "?email=". $email . "&token=" . $token;
        $client = \Drupal::httpclient();
        $response = $client->request('GET', $url, [] );
        $return = $response->getBody()->getContents();
      }
      catch (RequestException $e) {
        watchdog_exception('commerce_pagseguro_v2', $e->getMessage());
      }  

      if (empty($return)) {
        throw new Exception('PagSeguro API out of service!');
      }
     
      $xml = simplexml_load_string(utf8_encode($return));
      if (empty($xml)) {
          throw new Exception('Invalid XML');
      }

      $json = json_decode(json_encode($xml));
      if (empty($json)) {
        throw new Exception('Invalid Json');
      }
     
      $status = $this->mapPagseguroStatus($json->status);
      try {
        $payments = \Drupal::entityTypeManager()
          ->getStorage('commerce_payment')
          ->loadByProperties(['remote_id' => $json->code]);

        $payment = reset($payments);
        $payment->setState($status);
        $payment->save();
      }
      catch(Exception $e) {
        watchdog_exception('commerce_pagseguro_v2', $e->getMessage());
      }

      return new JsonResponse($request->getHost(), 200);
    }
    else {
      return new JsonResponse($request->getHost(), 403);
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

    if (empty($status)) {
      throw new Exception('PagSeguro not return correct status!');
    }
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
