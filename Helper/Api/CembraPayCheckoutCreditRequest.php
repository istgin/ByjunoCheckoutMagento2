<?php

namespace CembraPayCheckout\CembraPayCheckoutCore\Helper\Api;

use CembraPayCheckout\CembraPayCheckoutCore\Helper\Api\CembraPayCheckoutAutRequest;
use CembraPayCheckout\CembraPayCheckoutCore\Helper\Api\DeliveryDetails;
use CembraPayCheckout\CembraPayCheckoutCore\Helper\Api\SettlementDetails;

class CembraPayCheckoutCreditRequest extends CembraPayCheckoutAutRequest
{
    public $requestMsgType; //String
    public $requestMsgId; //String
    public $requestMsgDateTime; //Date
    public $merchantOrderRef; //String
    public $amount; //int
    public $currency; //String
    public $settlementDetails; //seliveryDetails
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
