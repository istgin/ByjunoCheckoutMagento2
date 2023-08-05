<?php

namespace ByjunoCheckout\ByjunoCheckoutCore\Helper\Api;

use ByjunoCheckout\ByjunoCheckoutCore\Helper\Api\ByjunoCheckoutAutRequest;
use ByjunoCheckout\ByjunoCheckoutCore\Helper\Api\DeliveryDetails;
use ByjunoCheckout\ByjunoCheckoutCore\Helper\Api\SettlementDetails;

class ByjunoCheckoutCreditRequest extends ByjunoCheckoutAutRequest
{
    public $merchantId; //String
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
