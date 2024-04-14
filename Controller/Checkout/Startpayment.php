<?php

namespace Byjuno\ByjunoCore\Controller\Checkout;

use Byjuno\ByjunoCore\Helper\Api\CembraPayCheckoutAuthorizationResponse;
use Byjuno\ByjunoCore\Helper\Api\CembraPayCommunicator;
use Byjuno\ByjunoCore\Helper\DataHelper;
use Magento\Framework\App\Action\Action;
use Magento\Sales\Model\Order;
use Magento\Setup\Exception;
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
        $resultRedirect = $this->resultRedirectFactory->create();
        $status = self::$_dataHelper->_checkoutSession->getCembraPayCheckoutStatus();
        self::$_dataHelper->_checkoutSession->setScreeningStatus("");
        if ($status == DataHelper::$AUTH_OK) {
            try {
                if (self::$_dataHelper->_scopeConfig->getValue("cembrapaycheckoutsettings/cembrapaycheckout_setup/auto_invoice", ScopeInterface::SCOPE_STORE) == '1') {
                    $order = self::$_dataHelper->_checkoutSession->getLastRealOrder();
                    self::$_dataHelper->generateInvoice($order);
                }
                self::$_dataHelper->_checkoutSession->setCembraPayCheckoutStatus('');
                $resultRedirect->setPath('checkout/onepage/success');
                return $resultRedirect;
            } catch (\Exception $e) {
            }
        }
        $resultRedirect->setPath('cembrapaycheckoutcore/checkout/cancel');
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
                $_internalDataHelper->getAccessDataWebshop($payment->getAdditionalInformation('webshop_profile_id'), $mode),
                function ($object, $token, $accessData) {
                    $object->saveToken($token, $accessData);
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

                $mode = $_internalDataHelper->_scopeConfig->getValue('cembrapaycheckoutsettings/cembrapaycheckout_setup/currentmode', ScopeInterface::SCOPE_STORE);
                if ($mode == 'live') {
                    $email = $_internalDataHelper->_scopeConfig->getValue('cembrapaycheckoutsettings/cembrapaycheckout_setup/cembrapaycheckout_prod_email', ScopeInterface::SCOPE_STORE);
                } else {
                    $email = $_internalDataHelper->_scopeConfig->getValue('cembrapaycheckoutsettings/cembrapaycheckout_setup/cembrapaycheckout_test_email', ScopeInterface::SCOPE_STORE);
                }
                try {
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
}
