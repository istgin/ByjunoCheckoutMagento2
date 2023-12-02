<?php
/**
 * Created by CembraPay.
 * User: i.sutugins
 * Date: 14.4.9
 * Time: 16:42
 */
namespace CembraPayCheckout\CembraPayCheckoutCore\Helper\Api;

class CembraPayCommunicator
{

    /**
     * @var \CembraPayCheckout\CembraPayCheckoutCore\Helper\Api\CembraPayAzure
     */
    public $cembraPayAzure;

    public function __construct(
        \CembraPayCheckout\CembraPayCheckoutCore\Helper\Api\CembraPayAzure $cembraPayAzure
    )
    {
        $this->cembraPayAzure = $cembraPayAzure;
    }
    private $server;

    /**
     * @param mixed $server
     */
    public function setServer($server)
    {
        $this->server = $server;
    }

    /**
     * @return mixed
     */
    public function getServer()
    {
        return $this->server;
    }

    public function sendScreeningRequest($xmlRequest, $timeout, $username, $password, $audience) {
        return $this->sendRequest($xmlRequest, 'api/v1.0/Screening', $timeout, $username, $password, $audience);
    }

    public function sendAuthRequest($xmlRequest, $timeout, $username, $password, $audience) {
        return $this->sendRequest($xmlRequest, 'api/v1.0/Transactions/authorize', $timeout, $username, $password, $audience);
    }

    public function sendCheckoutRequest($xmlRequest, $timeout, $username, $password, $audience) {
        return $this->sendRequest($xmlRequest, 'api/v1.0/Checkout', $timeout, $username, $password, $audience);
    }

    public function sendSettleRequest($xmlRequest, $timeout, $username, $password, $audience) {
        return $this->sendRequest($xmlRequest, 'api/v1.0/Transactions/settle', $timeout, $username, $password, $audience);
    }

    public function sendCreditRequest($xmlRequest, $timeout, $username, $password, $audience) {
        return $this->sendRequest($xmlRequest, 'api/v1.0/Transactions/credit', $timeout, $username, $password, $audience);
    }

    public function sendCancelRequest($xmlRequest, $timeout, $username, $password, $audience) {
        return $this->sendRequest($xmlRequest, 'api/v1.0/Transactions/cancel', $timeout, $username, $password, $audience);
    }

    public function sendGetTransactionRequest($xmlRequest, $timeout, $username, $password, $audience) {
        return $this->sendRequest($xmlRequest, 'api/v1.0/Transactions/status', $timeout, $username, $password, $audience);
    }

    private function sendRequest($xmlRequest, $endpoint, $timeout, $username, $password, $audience) {
        $token = $this->cembraPayAzure->getToken($timeout, $username, $password, $audience);
        if (empty($token["access_token"])) {
            return "";
        }
        $response = "";
        if (intval($timeout) < 0) {
            $timeout = 30;
        }
        if ($this->server == 'test') {
            $url = 'https://transactions-gateway.sit.byjunoag.ch/'.$endpoint;
        } else {
            //TODO: live server
            $url = 'https://transactions-gateway.sit.byjunoag.ch/'.$endpoint;
        }
        $request_data = $xmlRequest;

        $headers = [
            "Content-type: application/json",
            "accept: text/plain",
            "Authorization: Bearer ".$token["access_token"]
        ];
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_0);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, $timeout);
        curl_setopt($curl, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($curl, CURLOPT_POST, 1);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $request_data);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        $response = @curl_exec($curl);
        @curl_close($curl);

        $response = trim($response);
        return $response;
    }

    public function sendS4Request($xmlRequest, $timeout = 30) {
        $response = "";
        if (intval($timeout) < 0) {
            $timeout = 30;
        }
        if ($this->server == 'test') {
            $sslsock = fsockopen("ssl://secure.intrum.ch", 443, $errno, $errstr, $timeout);
        } else {
            $sslsock = fsockopen("ssl://secure.intrum.ch", 443, $errno, $errstr, $timeout);
        }
        if(is_resource($sslsock)) {

            $request_data	= urlencode("REQUEST")."=".urlencode($xmlRequest);
            $request_length	= strlen($request_data);

            if ($this->server == 'test') {
                fputs($sslsock, "POST /services/creditCheckDACH_01_41_TEST/sendTransaction.cfm HTTP/1.0\r\n");
            } else {
                fputs($sslsock, "POST /services/creditCheckDACH_01_41/sendTransaction.cfm HTTP/1.0\r\n");
            }

            fputs($sslsock, "Host: byjuno.com\r\n");
            fputs($sslsock, "Content-type: application/x-www-form-urlencoded\r\n");
            fputs($sslsock, "Content-Length: ".$request_length."\r\n");
            fputs($sslsock, "Connection: close\r\n\r\n");
            fputs($sslsock, $request_data);

            while(!feof($sslsock)) {
                $response .= @fgets($sslsock, 128);
            }

            fclose($sslsock);
            $response = substr($response, strpos($response,'<?xml')-1);
            $response = substr($response, 1,strpos($response,'Response>')+8);
        }
        return $response;
    }

};
