<?php
/**
 * Created by Byjuno.
 */
namespace ByjunoCheckout\ByjunoCheckoutCore\Helper\Api;

class ByjunoCheckoutScreeningDetails {
    public $allowedByjunoPaymentMethods;  //array( String )
    public function __construct() {
        $this->allowedByjunoPaymentMethods = Array();
    }

}
class ByjunoCheckoutScreeningResponse {
    public $requestMsgId; //String
    public $requestMsgDateTime; //Date
    public $replyMsgId; //String
    public $replyMsgDateTime; //Date
    public $transactionId; //String
    public $merchantCustRef; //String
    public $processingStatus; //String
    public $screeningDetails; //ScreeningDetails

    public function __construct() {
        $this->screeningDetails = new ByjunoCheckoutScreeningDetails();
    }

}
