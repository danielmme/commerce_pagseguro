<?php

namespace Drupal\commerce_pagseguro_v2\Plugin\Commerce\PaymentGateway;

use Exception;

class PaymentRequest {

    // Itens to send to pagseguro 
    public $email;
    public $token;
    public $paymentMode;
    public $paymentMethod;
    public $receiverEmail;
    public $currency;
    public $extraAmount;
    public $notificationURL;
    public $reference;
    public $senderName;
    public $senderCPF;
    public $senderAreaCode;
    public $senderPhone;
    public $senderEmail;
    public $senderHash;
    public $shippingAddressStreet;
    public $shippingAddressNumber;
    public $shippingAddressComplement;
    public $shippingAddressDistrict;
    public $shippingAddressPostalCode;
    public $shippingAddressCity;
    public $shippingAddressState;
    public $shippingAddressCountry;
    public $shippingType;
    public $shippingCost;
    public $creditCardToken;
    public $installmentQuantity;
    public $installmentValue;
    public $noIntersetInstallmentQuantity;
    public $creditCardHolderName;
    public $creditCardHolderCPF;
    public $creditCardHolderBirthDate;
    public $creditCardHolderAreaCode;
    public $creditCardHolderPhone;
    public $billingAddressStreet;
    public $billingAddressNumber;
    public $billingAddressComplemnt;
    public $billingAddressDistrict;
    public $billingAddressPostalCode;
    public $billingAddressCity;
    public $billingAddressState;
    public $billingAddressCountry;
    // end itens

    public function build($order, $payment, $payment_method, $items, $shipments, $billing, $credetial) {

        try { 
            $amount = $payment->getAmount();
            $sender_cpf = $payment_method->get('cpf')->first()->getValue()['value'];

            //  costumer phone information
            $phone = $billing->get('field_telefone')->first()->getValue()['value'];
            $sender_areacode = substr($phone, 0,2); 
            $sender_phone = substr($phone, 2, strlen($phone));

            $this->email = $credetial['email'];
            $this->token = $credetial['token'];
            $this->paymentMode = 'default';
            $this->paymentMethod = 'creditCard';
            $this->receiverEmail = $credetial['email'];
            $this->notificationURL = 'http://danielmm.com.br/payment/notify';
            $this->extraAmount = '0.00';
            $this->reference = '123456';

            $this->currency = $amount->getCurrencyCode();
            $this->senderCPF = $sender_cpf;
            $this->senderAreaCode = $sender_areacode;
            $this->senderPhone = $sender_phone;
            $this->senderEmail = $order->getEmail();
            $this->senderHash = $payment_method->get('sender_hash')->first()->getValue()['value'];

        // $this->installments = $this->payment_method->get('installments')->first()->getValue()['value'];
            // itens from checkout card
            foreach ($items as $key => $order_item) {
                $key++;
                $itemId = "itemId{$key}";
                $itemDescription = "itemDescription{$key}";
                $itemAmount = "itemAmount{$key}";
                $itemQuantity = "itemQuantity{$key}";
                $this->$itemId = $order_item->id();
                $this->$itemDescription = $order_item->getTitle();
                $this->$itemAmount = number_format($order_item->getUnitPrice()->getNumber(), 2, '.', '');
                $this->$itemQuantity = (integer) $order_item->getQuantity();
            }

            // Shipping
            $this->shippingCost = number_format($shipments->getAmount()->getNumber(),2,'.','');
            $this->shippingType = intval($shipments->getShippingMethodId());
                // customer shipment informations
            $address = $shipments->getShippingProfile()->get('address')->first();
            
            $this->shippingAddressState = $address->getAdministrativeArea(); // EX SP
            $this->shippingAddressCity = $address->getLocality();  // Cidade
            $this->shippingAddressCountry = 'BRA';
            $this->shippingAddressDistrict = $address->getDependentLocality(); // Bairro
            $this->shippingAddressPostalCode = $address->getPostalCode(); // CEP
            $this->shippingAddressStreet = $address->getAddressLine1(); // Endereço
            $this->shippingAddressNumber = 64;
            $this->shippingAddressComplement = $address->getAddressLine2(); // Complemento
            
            // Billing
            $address = $billing->get('address')->first();

            $this->senderName = $address->getGivenName() . ' ' . $address->getAdditionalName() . ' ' . $address->getFamilyName(); // Nome completo
            // customer billing informations
            $this->billingAddressState = $address->getAdministrativeArea(); // EX SP
            $this->billingAddressCity = $address->getLocality();  // Cidade
            $this->billingAddressDistrict = $address->getDependentLocality(); // Bairro
            $this->billingAddressPostalCode = $address->getPostalCode(); // CEP
            $this->billingAddressStreet= $address->getAddressLine1(); // Endereço
            $this->billingAddressComplemnt = $address->getAddressLine2(); // Complemento
            $this->billingAddressNumber = '543';
            $this->billingAddressCountry = 'BRA';

            $this->creditCardToken = $payment_method->get('card_hash')->first()->getValue()['value'];;
            $this->installmentQuantity = 1;
            $this->installmentValue = number_format($amount->getNumber(),2,'.','');
            $this->noIntersetInstallmentQuantity = 4;
            $this->creditCardHolderName = $payment_method->get('card_holder_name')->first()->getValue()['value'];
            $this->creditCardHolderCPF = $sender_cpf;
            $this->creditCardHolderBirthDate = date("d/m/Y", strtotime($payment_method->get('birth_date')->first()->getValue()['value'])); //Ex. 1995-04-14
            $this->creditCardHolderAreaCode = $sender_areacode;
            $this->creditCardHolderPhone = $sender_phone;

            return (array) $this;
        }
        catch(Exception $e) {
            echo $e->getMessage();
        }
    }
}


    // Add a built in test for testing decline exceptions.
    // Note: Since requires_billing_information is FALSE, the payment method
    // is not guaranteed to have a billing profile. Confirm tha
    // $payment_method->getBillingProfile() is not NULL before trying to use it.
    // if ($billing_profile = $payment_method->getBillingProfile()) {
    //     /** @var \Drupal\address\Plugin\Field\FieldType\AddressItem $billing_address */
    //     $billing_address = $billing_profile->get('address')->first();
  
  
    //     if ($billing_address->getPostalCode() == '53140') {
    //       throw new HardDeclineException('The payment was declined');
    //     }
    //   }
  
      // Perform the create payment request here, throw an exception if it fails.
      // See \Drupal\commerce_payment\Exception for the available exceptions.
      // Remember to take into account $capture when performing the request.