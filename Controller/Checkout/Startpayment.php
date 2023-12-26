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

    public function execute()
    {
        $status = self::$_dataHelper->_checkoutSession->getCembraPayCheckoutStatus();
        self::$_dataHelper->_checkoutSession->setScreeningStatus("");
        $resultRedirect = $this->resultRedirectFactory->create();
        if ($status == DataHelper::$AUTH_OK) {
            self::$_dataHelper->_checkoutSession->setCembraPayCheckoutStatus('');
            $resultRedirect->setPath('checkout/onepage/success');
        } else {
            self::$_dataHelper->_checkoutSession->setCembraPayCheckoutStatus('');
            try {
                $order = self::$_dataHelper->_checkoutSession->getLastRealOrder();
                if ($order != null) {
                    $order->registerCancellation("Payment canceled")->save();
                    $this->restoreQuote();
                    $this->messageManager->addExceptionMessage(new \Exception("Payment canceled"), "Payment canceled");
                }
            } catch (\Exception $e)
            {
            }
            $resultRedirect->setPath('checkout/cart');
        }
        return $resultRedirect;
    }

    public static function executeAuthorizeRequestOrder(Order $order, DataHelper $_internalDataHelper)
    {
        /* @var $payment \Magento\Sales\Model\Order\Payment */
        $payment = $order->getPayment();
        try {
            $request = $_internalDataHelper->createMagentoShopRequestAuthorization(
                $order,
                $payment,
                $payment->getAdditionalInformation('customer_gender'),
                $payment->getAdditionalInformation('customer_dob'),
                $payment->getAdditionalInformation('pref_lang'),
                $payment->getAdditionalInformation('customer_b2b_uid'),
                $payment->getAdditionalInformation('agree_tc'),
                $payment->getAdditionalInformation('webshop_profile_id')
            );
            $CembraPayRequestName = $request->requestMsgType;
            $json = $request->createRequest();
            $cembrapayCommunicator = new CembraPayCommunicator($_internalDataHelper->cembraPayAzure);
            $mode = $_internalDataHelper->_scopeConfig->getValue('cembrapaycheckoutsettings/cembrapaycheckout_setup/currentmode', ScopeInterface::SCOPE_STORE);
            if ($mode == 'live') {
                $cembrapayCommunicator->setServer('live');
            } else {
                $cembrapayCommunicator->setServer('test');
            }
            $response = $cembrapayCommunicator->sendAuthRequest($json,
                $_internalDataHelper->getAccessData(),
                function ($object, $token) {
                    $object->saveToken($token);
                });

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
                $transaction = $payment->addTransaction(\Magento\Sales\Model\Order\Payment\Transaction::TYPE_AUTH, null, true);
                $transaction->setIsClosed(false);
                $order->setState(Order::STATE_PROCESSING);
                $order->setStatus("processing");
                $payment->setAdditionalInformation("auth_executed_ok", 'true');

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
                $error = $_internalDataHelper->getCembraPayErrorMessage();
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
