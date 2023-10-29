<?php

namespace CembraPayCheckout\CembraPayCheckoutCore\Helper\Api;

/*
"merchantId": "1234567890",
"requestMsgType": "TST",
"requestMsgId": "ed58eb92-8424-487e-bb7c-fcb43066dcac",
"requestMsgDateTime": "2023-10-27T14:21:51Z",
"transactionId": "210728105911212199"
 */
class CembraPayGetStatusRequest
{
    public $merchantId; //String
    public $requestMsgType; //boolean
    public $requestMsgId; //String
    public $requestMsgDateTime; //String
    public $transactionId; //String
}
