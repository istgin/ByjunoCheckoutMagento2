<?php

namespace CembraPayCheckout\CembraPayCheckoutCore\Controller\Checkout;

use CembraPayCheckout\CembraPayCheckoutCore\Helper\Api\CembraPayCheckoutAuthorizationResponse;
use CembraPayCheckout\CembraPayCheckoutCore\Helper\Api\CembraPayCommunicator;
use CembraPayCheckout\CembraPayCheckoutCore\Helper\DataHelper;
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
        $CembraPayRequestName = "Order paid" . $savePrefix;
        $requestType = 'b2c';
        if ($request->getCompanyName1() != '' && $dataHelper->_scopeConfig->getValue('cembrapaycheckoutsettings/cembrapaycheckout_setup/businesstobusiness', ScopeInterface::SCOPE_STORE) == '1') {
            $CembraPayRequestName = "Order paid for Company" . $savePrefix;
            $requestType = 'b2b';
            $xml = $request->createRequestCompany();
            $payment->setAdditionalInformation("is_b2b", true);
        } else {
            $xml = $request->createRequest();
            $payment->setAdditionalInformation("is_b2b", false);
        }
        $mode = $dataHelper->_scopeConfig->getValue('cembrapaycheckoutsettings/cembrapaycheckout_setup/currentmode', ScopeInterface::SCOPE_STORE);
        if ($mode == 'live') {
            $dataHelper->_communicator->setServer('live');
        } else {
            $dataHelper->_communicator->setServer('test');
        }
        $response = $dataHelper->_communicator->sendScreeningRequest($xml, (int)$dataHelper->_scopeConfig->getValue('cembrapaycheckoutsettings/cembrapaycheckout_setup/timeout', ScopeInterface::SCOPE_STORE));
        $status = 0;
        if ($response) {
            $dataHelper->_response->setRawResponse($response);
            $dataHelper->_response->processResponse();
            $status = (int)$dataHelper->_response->getCustomerRequestStatus();
            if (intval($status) > 15) {
                $status = 0;
            }
            $dataHelper->saveLog($request, $xml, $response, $status, $CembraPayRequestName);
        } else {
            $dataHelper->saveLog($request, $xml, "empty response", "0", $CembraPayRequestName);
        }
        return array($status, $requestType);
    }

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
                $cembrapayTrx = self::$_dataHelper->_checkoutSession->getCembraPayTransaction();
                list($statusS3, $requestTypeS3) = self::executeS3($order, $payment, $responseS2->getTransactionNumber(), $payment->getAdditionalInformation('accept'), " (Backend)", self::$_dataHelper);
                if (self::$_dataHelper->cembrapayIsStatusOk($statusS3, "cembrapaycheckoutsettings/cembrapaycheckout_setup/accepted_s3")) {
                    if ($cembrapayTrx == "") {
                        $cembrapayTrx = "cembrapaytx-" . uniqid();
                    }

                    $payment->setTransactionId($cembrapayTrx);
                    $payment->setParentTransactionId($payment->getTransactionId());
                    $transaction = $payment->addTransaction(\Magento\Sales\Model\Order\Payment\Transaction::TYPE_AUTH, null, true);
                    $transaction->setIsClosed(false);
                    $transaction->save();

                    $payment->setAdditionalInformation("auth_executed_ok", 'true');
                    $payment->save();

                    if (self::$_dataHelper->_scopeConfig->getValue('cembrapaycheckoutsettings/cembrapaycheckout_setup/success_state', ScopeInterface::SCOPE_STORE) == 'completed') {
                        $order->setState(Order::STATE_COMPLETE);
                        $order->setStatus("complete");
                    } else if (self::$_dataHelper->_scopeConfig->getValue('cembrapaycheckoutsettings/cembrapaycheckout_setup/success_state', ScopeInterface::SCOPE_STORE) == 'processing') {
                        $order->setState(Order::STATE_PROCESSING);
                        $order->setStatus("processing");
                    } else {
                        $order->setStatus("pending");
                    }

                    self::$_dataHelper->saveStatusToOrder($order);
                    try {
                        $mode = self::$_dataHelper->_scopeConfig->getValue('cembrapaycheckoutsettings/cembrapaycheckout_setup/currentmode', ScopeInterface::SCOPE_STORE);
                        if ($mode == 'live') {
                            $email = self::$_dataHelper->_scopeConfig->getValue('cembrapaycheckoutsettings/cembrapaycheckout_setup/cembrapaycheckout_prod_email', ScopeInterface::SCOPE_STORE);
                        } else {
                            $email = self::$_dataHelper->_scopeConfig->getValue('cembrapaycheckoutsettings/cembrapaycheckout_setup/cembrapaycheckout_test_email', ScopeInterface::SCOPE_STORE);
                        }
                        self::$_dataHelper->_cembrapayOrderSender->sendOrder($order, $email);
                    } catch (\Exception $e) {
                        self::$_dataHelper->_loggerPsr->critical($e);
                    }
                    // ALL OK
                } else {
                    $error = self::$_dataHelper->getCembraPayErrorMessage($statusS3, $requestTypeS3) . "(S3)";
                    $order->registerCancellation($error)->save();
                    throw new \Exception($error);
                }
            } else {
                $error = self::$_dataHelper->getCembraPayErrorMessage(
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
        $status = self::$_dataHelper->_checkoutSession->getCembraPayCheckoutStatus();
        self::$_dataHelper->_checkoutSession->setScreeningStatus("");
        $resultRedirect = $this->resultRedirectFactory->create();
        if ($status == DataHelper::$AUTH_OK) {
            self::$_dataHelper->_checkoutSession->setCembraPayCheckoutStatus('');
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
            //      $cembrapayTrx = $_internalDataHelper->_checkoutSession->getCembraPayTransaction();
            //      list($statusS3, $requestTypeS3) = self::executeS3($order, $payment, $cembrapayTrx, $payment->getAdditionalInformation('accept'), "", $_internalDataHelper);
            $request = $_internalDataHelper->createMagentoShopRequestAuthorization(
                $order,
                $payment,
                $payment->getAdditionalInformation('customer_gender'),
                $payment->getAdditionalInformation('customer_dob'),
                $payment->getAdditionalInformation('pref_lang'),
                $payment->getAdditionalInformation('customer_b2b_uid'),
                $payment->getAdditionalInformation('webshop_profile_id')
            );
            $CembraPayRequestName = $request->requestMsgType;
           // $json = "{}";
           // if ($request->custDetails->custType == 'C' && $_internalDataHelper->_scopeConfig->getValue('cembrapaycheckoutsettings/cembrapaycheckout_setup/businesstobusiness',
           //         ScopeInterface::SCOPE_STORE) == '1') {
           //     $CembraPayRequestName = "Authorization request for company";
           // }
            $json = $request->createRequest();
            $cembrapayCommunicator = new CembraPayCommunicator();
            $mode = $_internalDataHelper->_scopeConfig->getValue('cembrapaycheckoutsettings/cembrapaycheckout_setup/currentmode', ScopeInterface::SCOPE_STORE);
            if ($mode == 'live') {
                $cembrapayCommunicator->setServer('live');
            } else {
                $cembrapayCommunicator->setServer('test');
            }
            $response = $cembrapayCommunicator->sendAuthRequest($json, (int)$_internalDataHelper->_scopeConfig->getValue('cembrapaycheckoutsettings/cembrapaycheckout_setup/timeout',
                ScopeInterface::SCOPE_STORE),
                $_internalDataHelper->_scopeConfig->getValue('cembrapaycheckoutsettings/cembrapaycheckout_setup/cembrapaylogin', ScopeInterface::SCOPE_STORE),
                $_internalDataHelper->_scopeConfig->getValue('cembrapaycheckoutsettings/cembrapaycheckout_setup/cembrapaypassword', ScopeInterface::SCOPE_STORE));

            $status = "";
            $responseRes = null;
            if ($response) {
                /* @var $responseRes CembraPayCheckoutAuthorizationResponse */
                $responseRes = $_internalDataHelper->authorizationResponse($response);
                $status = $responseRes->processingStatus;
                $_internalDataHelper->saveLog($json, $response, $responseRes->processingStatus, $CembraPayRequestName,
                    $request->custDetails->firstName, $request->custDetails->lastName, $request->requestMsgId,
                    $request->billingAddr->postalCode, $request->billingAddr->town, $request->billingAddr->country, $request->billingAddr->addrFirstLine, $responseRes->transactionId, $order->getRealOrderId());
            } else {
                $_internalDataHelper->saveLog($json, $response, "Query error", $CembraPayRequestName,
                    $request->custDetails->firstName, $request->custDetails->lastName, $request->requestMsgId,
                    $request->billingAddr->postalCode, $request->billingAddr->town, $request->billingAddr->country, $request->billingAddr->addrFirstLine, "-", "-");
            }
            if ($status == DataHelper::$AUTH_OK) {
                $cembrapayTrx = $responseRes->transactionId;
                $payment->setTransactionId($cembrapayTrx);
                $payment->setParentTransactionId($payment->getTransactionId());
                // $payment->setIsTransactionPending(true);
                $transaction = $payment->addTransaction(\Magento\Sales\Model\Order\Payment\Transaction::TYPE_AUTH, null, true);
                $transaction->setIsClosed(false);
                $order->setState(Order::STATE_PROCESSING);
                $order->setStatus("cembrapaycheckout_processing");
                $payment->setAdditionalInformation("auth_executed_ok", 'true');
                /*
                if ($_internalDataHelper->_scopeConfig->getValue('cembrapaycheckoutsettings/cembrapaycheckout_setup/success_state', ScopeInterface::SCOPE_STORE) == 'completed') {
                    $order->setState(Order::STATE_COMPLETE);
                    $order->setStatus("complete");
                } else if ($_internalDataHelper->_scopeConfig->getValue('cembrapaycheckoutsettings/cembrapaycheckout_setup/success_state', ScopeInterface::SCOPE_STORE) == 'processing') {
                    $order->setState(Order::STATE_PROCESSING);
                    $order->setStatus("processing");
                } else {
                    $order->setStatus("pending");
                }*/

                $_internalDataHelper->saveStatusToOrder($order);

                try {
                    $mode = $_internalDataHelper->_scopeConfig->getValue('cembrapaycheckoutsettings/cembrapaycheckout_setup/currentmode', ScopeInterface::SCOPE_STORE);
                    if ($mode == 'live') {
                        $email = $_internalDataHelper->_scopeConfig->getValue('cembrapaycheckoutsettings/cembrapaycheckout_setup/cembrapaycheckout_prod_email', ScopeInterface::SCOPE_STORE);
                    } else {
                        $email = $_internalDataHelper->_scopeConfig->getValue('cembrapaycheckoutsettings/cembrapaycheckout_setup/cembrapaycheckout_test_email', ScopeInterface::SCOPE_STORE);
                    }
                    if ($_internalDataHelper->_scopeConfig->getValue('cembrapaycheckoutsettings/cembrapaycheckout_setup/force_send_email', ScopeInterface::SCOPE_STORE) == '1') {
                        $_internalDataHelper->_originalOrderSender->send($order);
                    }
                    $_internalDataHelper->_cembrapayOrderSender->sendOrder($order, $email);
                } catch (\Exception $e) {
                    $_internalDataHelper->_loggerPsr->critical($e);
                }
                $_internalDataHelper->_checkoutSession->setCembraPayCheckoutStatus($status);

            } else {
                $error = "Payment rejected";//$_internalDataHelper->getCembraPayErrorMessage($statusS3, $requestTypeS3) . "(S3)";
                return $error;
            }

        } catch (\Exception $e) {
            $error = __($e->getMessage());
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
