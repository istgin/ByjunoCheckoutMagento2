<?php

namespace ByjunoCheckout\ByjunoCheckoutCore\Controller\Checkout;

use ByjunoCheckout\ByjunoCheckoutCore\Helper\Api\ByjunoCheckoutAuthorizationResponse;
use ByjunoCheckout\ByjunoCheckoutCore\Helper\Api\ByjunoCommunicator;
use ByjunoCheckout\ByjunoCheckoutCore\Helper\DataHelper;
use Magento\Framework\App\Action\Action;
use Magento\Sales\Model\Order;
use Magento\Store\Model\ScopeInterface;

class Startpayment extends Action
{
    protected $_config;
    /**
     * @var DataHelper
     */
    protected static $_dataHelper;

    /**
     * Index constructor.
     * @param \Magento\Framework\App\Action\Context $context
     * @param Session $checkoutSession
     * @param \Psr\Log\LoggerInterface $logger
     */
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        DataHelper $helper
    )
    {
        self::$_dataHelper = $helper;
        parent::__construct($context);
    }

    public static function executeS3($order, \Magento\Sales\Model\Order\Payment $payment, $transaction, $accept, $savePrefix, DataHelper $dataHelper)
    {
        $request = $dataHelper->CreateMagentoShopRequestPaid($order,
            $payment,
            $payment->getAdditionalInformation('customer_gender'),
            $payment->getAdditionalInformation('customer_dob'),
            $transaction,
            $accept,
            $payment->getAdditionalInformation('pref_lang'),
            $payment->getAdditionalInformation('customer_b2b_uid'),
            $payment->getAdditionalInformation('webshop_profile_id'));
        $ByjunoRequestName = "Order paid" . $savePrefix;
        $requestType = 'b2c';
        if ($request->getCompanyName1() != '' && $dataHelper->_scopeConfig->getValue('byjunocheckoutsettings/byjunocheckout_setup/businesstobusiness', ScopeInterface::SCOPE_STORE) == '1') {
            $ByjunoRequestName = "Order paid for Company" . $savePrefix;
            $requestType = 'b2b';
            $xml = $request->createRequestCompany();
            $payment->setAdditionalInformation("is_b2b", true);
        } else {
            $xml = $request->createRequest();
            $payment->setAdditionalInformation("is_b2b", false);
        }
        $mode = $dataHelper->_scopeConfig->getValue('byjunocheckoutsettings/byjunocheckout_setup/currentmode', ScopeInterface::SCOPE_STORE);
        if ($mode == 'live') {
            $dataHelper->_communicator->setServer('live');
        } else {
            $dataHelper->_communicator->setServer('test');
        }
        $response = $dataHelper->_communicator->sendScreeningRequest($xml, (int)$dataHelper->_scopeConfig->getValue('byjunocheckoutsettings/byjunocheckout_setup/timeout', ScopeInterface::SCOPE_STORE));
        $status = 0;
        if ($response) {
            $dataHelper->_response->setRawResponse($response);
            $dataHelper->_response->processResponse();
            $status = (int)$dataHelper->_response->getCustomerRequestStatus();
            if (intval($status) > 15) {
                $status = 0;
            }
            $dataHelper->saveLog($request, $xml, $response, $status, $ByjunoRequestName);
        } else {
            $dataHelper->saveLog($request, $xml, "empty response", "0", $ByjunoRequestName);
        }
        return array($status, $requestType);
    }

    /* public static function executeS2Quote(\Magento\Quote\Model\Quote $quote, \Magento\Quote\Model\Quote\Payment $payment, DataHelper $_internalDataHelper, $savePrefix = "")
     {
         $request = $_internalDataHelper->CreateMagentoShopRequestOrderQuote($quote,
             $payment,
             $payment->getAdditionalInformation('customer_gender'),
             $payment->getAdditionalInformation('customer_dob'),
             $payment->getAdditionalInformation('pref_lang'),
             $payment->getAdditionalInformation('customer_b2b_uid'), $payment->getAdditionalInformation('webshop_profile_id'));

         $ByjunoRequestName = "Order request" . $savePrefix;
         $requestType = 'b2c';
         if ($request->getCompanyName1() != '' && $_internalDataHelper->_scopeConfig->getValue('byjunocheckoutsettings/byjunocheckout_setup/businesstobusiness', \Magento\Store\Model\ScopeInterface::SCOPE_STORE) == '1') {
             $ByjunoRequestName = "Order request for Company" . $savePrefix;
             $requestType = 'b2b';
             $xml = $request->createRequestCompany();
             $payment->setAdditionalInformation("is_b2b", true);
         } else {
             $xml = $request->createRequest();
             $payment->setAdditionalInformation("is_b2b", false);
         }
         $mode = $_internalDataHelper->_scopeConfig->getValue('byjunocheckoutsettings/byjunocheckout_setup/currentmode', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
         if ($mode == 'live') {
             $_internalDataHelper->_communicator->setServer('live');
         } else {
             $_internalDataHelper->_communicator->setServer('test');
         }

         $response = $_internalDataHelper->_communicator->sendRequest($xml, (int)$_internalDataHelper->_scopeConfig->getValue('byjunocheckoutsettings/byjunocheckout_setup/timeout', \Magento\Store\Model\ScopeInterface::SCOPE_STORE));
         $status = 0;
         if ($response) {
             $_internalDataHelper->_response->setRawResponse($response);
             $_internalDataHelper->_response->processResponse();
             $status = (int)$_internalDataHelper->_response->getCustomerRequestStatus();
             if ($_internalDataHelper->_checkoutSession != null) {
                 $_internalDataHelper->_checkoutSession->setByjunoTransaction($_internalDataHelper->_response->getTransactionNumber());
                 $_internalDataHelper->_checkoutSession->setS2Response($response);
             }
             $_internalDataHelper->saveLog($request, $xml, $response, $status, $ByjunoRequestName);
             if (intval($status) > 15) {
                 $status = 0;
             }
         } else {
             $_internalDataHelper->saveLog($request, $xml, "empty response", "0", $ByjunoRequestName);
             if ($_internalDataHelper->_checkoutSession != null) {
                 $_internalDataHelper->_checkoutSession->setS2Response("");
             }
         }
         if ($_internalDataHelper->_checkoutSession != null) {
             $_internalDataHelper->_checkoutSession->setIntrumStatus($status);
             $_internalDataHelper->_checkoutSession->setIntrumRequestType($requestType);
         }
         return array($status, $requestType, $_internalDataHelper->_response);

     }
 */
    public static function executeBackendOrder(DataHelper $helper, Order $order)
    {
        self::$_dataHelper = $helper;
        /* @var $payment \Magento\Sales\Model\Order\Payment */
        $payment = $order->getPayment();
        try {
            $statusS2 = self::$_dataHelper->_checkoutSession->getIntrumStatus();
            $typeS2 = self::$_dataHelper->_checkoutSession->getIntrumRequestType();
            $responseS2String = self::$_dataHelper->_checkoutSession->getS2Response();
            if ($responseS2String == "") {
                throw new \Exception("Empty response set");
            }
            self::$_dataHelper->_response->setRawResponse($responseS2String);
            self::$_dataHelper->_response->processResponse();
            $responseS2 = clone self::$_dataHelper->_response;

            if ($payment->getAdditionalInformation('accept') != "") {
                $byjunoTrx = self::$_dataHelper->_checkoutSession->getByjunoTransaction();
                list($statusS3, $requestTypeS3) = self::executeS3($order, $payment, $responseS2->getTransactionNumber(), $payment->getAdditionalInformation('accept'), " (Backend)", self::$_dataHelper);
                if (self::$_dataHelper->byjunoIsStatusOk($statusS3, "byjunocheckoutsettings/byjunocheckout_setup/accepted_s3")) {
                    if ($byjunoTrx == "") {
                        $byjunoTrx = "byjunotx-" . uniqid();
                    }

                    $payment->setTransactionId($byjunoTrx);
                    $payment->setParentTransactionId($payment->getTransactionId());
                    $transaction = $payment->addTransaction(\Magento\Sales\Model\Order\Payment\Transaction::TYPE_AUTH, null, true);
                    $transaction->setIsClosed(false);
                    $transaction->save();

                    $payment->setAdditionalInformation("auth_executed_ok", 'true');
                    $payment->save();

                    if (self::$_dataHelper->_scopeConfig->getValue('byjunocheckoutsettings/byjunocheckout_setup/success_state', ScopeInterface::SCOPE_STORE) == 'completed') {
                        $order->setState(Order::STATE_COMPLETE);
                        $order->setStatus("complete");
                    } else if (self::$_dataHelper->_scopeConfig->getValue('byjunocheckoutsettings/byjunocheckout_setup/success_state', ScopeInterface::SCOPE_STORE) == 'processing') {
                        $order->setState(Order::STATE_PROCESSING);
                        $order->setStatus("processing");
                    } else {
                        $order->setStatus("pending");
                    }

                    self::$_dataHelper->saveStatusToOrder($order, $responseS2);
                    try {
                        $mode = self::$_dataHelper->_scopeConfig->getValue('byjunocheckoutsettings/byjunocheckout_setup/currentmode', ScopeInterface::SCOPE_STORE);
                        if ($mode == 'live') {
                            $email = self::$_dataHelper->_scopeConfig->getValue('byjunocheckoutsettings/byjunocheckout_setup/byjunocheckout_prod_email', ScopeInterface::SCOPE_STORE);
                        } else {
                            $email = self::$_dataHelper->_scopeConfig->getValue('byjunocheckoutsettings/byjunocheckout_setup/byjunocheckout_test_email', ScopeInterface::SCOPE_STORE);
                        }
                        self::$_dataHelper->_byjunoOrderSender->sendOrder($order, $email);
                    } catch (\Exception $e) {
                        self::$_dataHelper->_loggerPsr->critical($e);
                    }
                    // ALL OK
                } else {
                    $error = self::$_dataHelper->getByjunoErrorMessage($statusS3, $requestTypeS3) . "(S3)";
                    $order->registerCancellation($error)->save();
                    throw new \Exception($error);
                }
            } else {
                $error = self::$_dataHelper->getByjunoErrorMessage(
                    $statusS2,
                    $typeS2
                );
                $order->registerCancellation($error)->save();
                throw new \Exception($error);
            }

        } catch (\Exception $e) {
            $error = __($e->getMessage());
            throw new \Exception($e->getMessage());
        }
    }

    public function execute()
    {
        $status = self::$_dataHelper->_checkoutSession->getByjunoCheckoutStatus();
        self::$_dataHelper->_checkoutSession->setScreeningStatus("");
        $resultRedirect = $this->resultRedirectFactory->create();
        if ($status == "SUCCESS") {
            self::$_dataHelper->_checkoutSession->setByjunoCheckoutStatus('');
            $resultRedirect->setPath('checkout/onepage/success');
        } else {
            $order = self::$_dataHelper->_checkoutSession->getLastRealOrder();
            $order->registerCancellation("Payment canceled")->save();
            $this->restoreQuote();
            $this->messageManager->addExceptionMessage(new \Exception("Payment canceled"), "Payment canceled");
            $resultRedirect->setPath('checkout/cart');
        }
        return $resultRedirect;
    }

    public static function executeAuthorizeRequestOrder(Order $order, DataHelper $_internalDataHelper)
    {
        /* @var $payment \Magento\Sales\Model\Order\Payment */
        $payment = $order->getPayment();

        try {
            //      $byjunoTrx = $_internalDataHelper->_checkoutSession->getByjunoTransaction();
            //      list($statusS3, $requestTypeS3) = self::executeS3($order, $payment, $byjunoTrx, $payment->getAdditionalInformation('accept'), "", $_internalDataHelper);
            $request = $_internalDataHelper->createMagentoShopRequestAuthorization(
                $order,
                $payment,
                $payment->getAdditionalInformation('customer_gender'),
                $payment->getAdditionalInformation('customer_dob'),
                $payment->getAdditionalInformation('pref_lang'),
                $payment->getAdditionalInformation('customer_b2b_uid'),
                $payment->getAdditionalInformation('webshop_profile_id')
            );
            $ByjunoRequestName = "Authorization request";
            $json = "{}";
            if ($request->custDetails->custType == 'C' && $_internalDataHelper->_scopeConfig->getValue('byjunocheckoutsettings/byjunocheckout_setup/businesstobusiness',
                    ScopeInterface::SCOPE_STORE) == '1') {
                $ByjunoRequestName = "Authorization request for company";
            }
            $json = $request->createRequest();
            $byjunoCommunicator = new ByjunoCommunicator();
            $mode = $_internalDataHelper->_scopeConfig->getValue('byjunocheckoutsettings/byjunocheckout_setup/currentmode', ScopeInterface::SCOPE_STORE);
            if ($mode == 'live') {
                $byjunoCommunicator->setServer('live');
            } else {
                $byjunoCommunicator->setServer('test');
            }
            $response = $byjunoCommunicator->sendAuthRequest($json, (int)$_internalDataHelper->_scopeConfig->getValue('byjunocheckoutsettings/byjunocheckout_setup/timeout',
                ScopeInterface::SCOPE_STORE));

            $status = "";
            $responseRes = null;
            if ($response) {
                /* @var $responseRes ByjunoCheckoutAuthorizationResponse */
                $responseRes = $_internalDataHelper->authorizationResponse($response);
                $status = $responseRes->processingStatus;
                $_internalDataHelper->saveLog($json, $response, $responseRes->processingStatus, $ByjunoRequestName,
                    $request->custDetails->firstName, $request->custDetails->lastName, $request->requestMsgId,
                    $request->billingAddr->postalCode, $request->billingAddr->town, $request->billingAddr->country, $request->billingAddr->addrFirstLine, $responseRes->transactionId, $order->getRealOrderId());
            } else {
                $_internalDataHelper->saveLog($json, $response, "Query error", $ByjunoRequestName,
                    $request->custDetails->firstName, $request->custDetails->lastName, $request->requestMsgId,
                    $request->billingAddr->postalCode, $request->billingAddr->town, $request->billingAddr->country, $request->billingAddr->addrFirstLine, "-", "-");
            }
            if ($status == "SUCCESS") {
                $byjunoTrx = $responseRes->transactionId;
                $payment->setTransactionId($byjunoTrx);
                $payment->setParentTransactionId($payment->getTransactionId());
                // $payment->setIsTransactionPending(true);
                $transaction = $payment->addTransaction(\Magento\Sales\Model\Order\Payment\Transaction::TYPE_AUTH, null, true);
                $transaction->setIsClosed(false);
                $order->setState(Order::STATE_PROCESSING);
                $order->setStatus("byjunocheckout_processing");
                $payment->setAdditionalInformation("auth_executed_ok", 'true');
                /*
                if ($_internalDataHelper->_scopeConfig->getValue('byjunocheckoutsettings/byjunocheckout_setup/success_state', ScopeInterface::SCOPE_STORE) == 'completed') {
                    $order->setState(Order::STATE_COMPLETE);
                    $order->setStatus("complete");
                } else if ($_internalDataHelper->_scopeConfig->getValue('byjunocheckoutsettings/byjunocheckout_setup/success_state', ScopeInterface::SCOPE_STORE) == 'processing') {
                    $order->setState(Order::STATE_PROCESSING);
                    $order->setStatus("processing");
                } else {
                    $order->setStatus("pending");
                }*/

                $_internalDataHelper->saveStatusToOrder($order);

                try {
                    $mode = $_internalDataHelper->_scopeConfig->getValue('byjunocheckoutsettings/byjunocheckout_setup/currentmode', ScopeInterface::SCOPE_STORE);
                    if ($mode == 'live') {
                        $email = $_internalDataHelper->_scopeConfig->getValue('byjunocheckoutsettings/byjunocheckout_setup/byjunocheckout_prod_email', ScopeInterface::SCOPE_STORE);
                    } else {
                        $email = $_internalDataHelper->_scopeConfig->getValue('byjunocheckoutsettings/byjunocheckout_setup/byjunocheckout_test_email', ScopeInterface::SCOPE_STORE);
                    }
                    if ($_internalDataHelper->_scopeConfig->getValue('byjunocheckoutsettings/byjunocheckout_setup/force_send_email', ScopeInterface::SCOPE_STORE) == '1') {
                        $_internalDataHelper->_originalOrderSender->send($order);
                    }
                    $_internalDataHelper->_byjunoOrderSender->sendOrder($order, $email);
                } catch (\Exception $e) {
                    $_internalDataHelper->_loggerPsr->critical($e);
                }
                $_internalDataHelper->_checkoutSession->setByjunoCheckoutStatus($status);

            } else {
                $error = "Payment rejected";//$_internalDataHelper->getByjunoErrorMessage($statusS3, $requestTypeS3) . "(S3)";
                $order->registerCancellation($error)->save();
                return $error;
            }

        } catch (\Exception $e) {
            $error = __($e->getMessage());
            try {
                $order->registerCancellation($error)->save();
            } catch (\Exception $e) {
                $error = "Error cancel order";
                return $error;
            }
            return $error;
        }
        return null;
    }

    private static function restoreQuote()
    {
        /** @var Order $order */
        $order = self::$_dataHelper->_checkoutSession->getLastRealOrder();
        if ($order->getId()) {
            try {
                $quote = self::$_dataHelper->quoteRepository->get($order->getQuoteId());
                $quote->setIsActive(1)->setReservedOrderId(null);
                self::$_dataHelper->quoteRepository->save($quote);
                self::$_dataHelper->_checkoutSession->replaceQuote($quote)->unsLastRealOrderId();
                return true;
            } catch (\Magento\Framework\Exception\NoSuchEntityException $e) {
            }
        }
        return false;
    }
}
