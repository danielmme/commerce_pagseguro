(function (Drupal) {
  Drupal.behaviors.commercePagseguroV2 = {
    attach: function (context, settings) {
  
      var card = document.querySelector('#card-number');
      card.addEventListener("change", function(event){
     
        var session = drupalSettings.commercePagseguroV2.commercePagseguro.session;
        PagSeguroDirectPayment.setSessionId(session);

        PagSeguroDirectPayment.getBrand({
          cardBin: document.querySelector('#card-number').value,
          success: function (response) {
            document.querySelector('#show-card-brand').textContent = 'Cartão da bandeira : ' + response['brand']['name'];
            document.querySelector('#card-brand').textContent = response['brand']['name'];
            document.querySelector('#card-brand').value = response['brand']['name'];

            var amount = drupalSettings.commercePagseguroV2.commercePagseguro.amount;
            var cartao = response['brand']['name'];
      
            PagSeguroDirectPayment.getInstallments({
              amount: amount,
              brand: response['brand']['name'],
              maxInstallmentNoInterest: 4,
              success: function (response) {
                setInstallmentInfo(response.installments[cartao]);
              },
              error: function (response) {
              }
            });

          },
          error: function (response) {
          }
        });
        
      });

        var form = document.querySelector(".commerce-checkout-flow");
        form.addEventListener("submit", function(event){
          event.preventDefault();
          PagSeguroDirectPayment.createCardToken({
            cardNumber: document.querySelector('#card-number').value, // Número do cartão de crédito
            brand: document.querySelector('#card-brand').textContent, // Bandeira do cartão
            cvv: document.querySelector('#security-code').value, // CVV do cartão
            expirationMonth: document.querySelector('#expiration-month').value, // Mês da expiração do cartão
            expirationYear: document.querySelector('#expiration-year').value, // Ano da expiração do cartão, é necessário os 4 dígitos.
            success: function(response) {
              console.log(response);
              document.querySelector('#card-hash').textContent = response.card.token;
              document.querySelector('#card-hash').value = response.card.token;
              PagSeguroDirectPayment.onSenderHashReady(function(response){
                if(response.status == 'error') {
                    return false;
                }
                document.querySelector('#sender-hash').textContent = response.senderHash;
                document.querySelector('#sender-hash').value = response.senderHash;
                console.log(form);
                form.submit();
              });
            },
          });
        });
       
      function setInstallmentInfo(response) {

        var array = response;
        var select = document.querySelector('#installments');
        // create new option element
        if (select.length <= 1) {
          for (var i = 0; i <  4; i++) {
            var option = document.createElement("option");
            if (i == 0 && array[i].interestFree == true) {
              option.text = 'Á vista no valor de R$ '+array[i].installmentAmount;
              option.value = array[i].installmentAmount;
            }
            else if (i > 0 && array[i].interestFree == true) {
              option.text = array[i].quantity+' vezes de R$ '+array[i].installmentAmount+' - valor total: R$  '+array[i].totalAmount;
              option.value = array[i].installmentAmount;
            }
            select.add(option);
          }
        }
      }


    }
  };
})(Drupal);
