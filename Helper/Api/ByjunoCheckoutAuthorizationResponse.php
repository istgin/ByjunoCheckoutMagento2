<?php
/**
 * Created by Byjuno.
 */
namespace ByjunoCheckout\ByjunoCheckoutCore\Helper\Api;

class Authorization {
    public $authorizationValidTill; //Date
    public $authorizedRemainingAmount; //int
    public $authorizationCurrency; //String

}
class ByjunoCheckoutAuthorizationResponse {
    public $requestMsgType; //String
    public $requestMsgId; //String
    public $requestMsgDateTime; //Date
    public $idempotencyKey; //String
    public $replyMsgId; //String
    public $replyMsgDateTime; //Date
    public $transactionId; //String
    public $merchantCustRef; //String
    public $token; //String
    public $merchantOrderRef; //String
    public $processingStatus; //String
    public $authorization; //Authorization
}
