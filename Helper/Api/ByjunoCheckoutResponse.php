<?php
/**
 * Created by Byjuno.
 * User: i.sutugins
 * Date: 14.4.9
 * Time: 16:57
 */
namespace ByjunoCheckout\ByjunoCheckoutCore\Helper\Api;

class Authorization {
    public $authorizationValidTill; //Date
    public $authorizedRemainingAmount; //int
    public $authorizationCurrency; //String

}
class ByjunoCheckoutResponse {
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
