<?php

namespace ByjunoCheckout\ByjunoCheckoutCore\Helper\Api;

/*
 * {
"merchantId": "1234567890",
"requestMsgType": "CNT",
"requestMsgId": "48aa4108-4b5a-42e1-b515-279770fb92b7",
"requestMsgDateTime": "2022-06-06T06:14:05Z",
"idempotencyKey": "98ef3f11-cfe3-41fd-82a1-6f40173d0asdf",
"transactionId": "210728105911218888",
"merchantOrderRef": "Order2001123",
"amount": 15800,
"currency": "CHF",
"settlementDetails": {
"merchantInvoiceRef": "aaaaa1"
}
}
 */

class ByjunoCheckoutCreditRequest extends ByjunoCheckoutRequest
{
    public $merchantId; //String
    public $requestMsgType; //String
    public $requestMsgId; //String
    public $requestMsgDateTime; //Date
    public $merchantOrderRef; //String
    public $amount; //int
    public $currency; //String
    public $settlementDetails; //seliveryDetails
    public $deliveryDetails; //DeliveryDetails
    public $transactionId;

    public function __construct()
    {
        $this->deliveryDetails = new DeliveryDetails();
        $this->settlementDetails = new SettlementDetails();
    }

    public function createRequest() {
        return json_encode($this);
    }
}
