<?php

namespace CembraPayCheckout\CembraPayCheckoutCore\Controller\Checkout;

use CembraPayCheckout\CembraPayCheckoutCore\Helper\Api\CembraPayCheckoutAuthorizationResponse;
use CembraPayCheckout\CembraPayCheckoutCore\Helper\Api\CembraPayCheckoutChkResponse;
use CembraPayCheckout\CembraPayCheckoutCore\Helper\Api\CembraPayCommunicator;
use CembraPayCheckout\CembraPayCheckoutCore\Helper\DataHelper;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\ActionInterface;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Message\ManagerInterface as MessageManagerInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Payment\Transaction;
use Magento\Store\Model\ScopeInterface;


class Success implements ActionInterface
{
    protected $_config;
    /**
     * @var DataHelper
     */
    protected $_dataHelper;
    /**
     * @var RedirectFactory
     */
    protected $resultRedirectFactory;
    /**
     * @var MessageManagerInterface
     */
    protected $messageManager;

    /**
     * Index constructor.
     * @param Context $context
     * @param DataHelper $helper
     * @param RedirectFactory $resultRedirectFactory
     */
    public function __construct(
        Context $context,
        DataHelper $helper,
        RedirectFactory $resultRedirectFactory
    )
    {
        $this->messageManager = $context->getMessageManager();
        $this->resultRedirectFactory = $resultRedirectFactory;
        $this->_dataHelper = $helper;
    }

    public function execute()
    {
        $order = $this->_dataHelper->_checkoutSession->getLastRealOrder();
        /* @var $payment \Magento\Sales\Model\Order\Payment */
        $payment = $order->getPayment();
        $transaction = $payment->getAuthorizationTransaction();
        $transactionId = $transaction->getTxnId();
        $request = $this->_dataHelper->createMagentoShopRequestGetTransaction(
            $transactionId,
            $payment->getAdditionalInformation('webshop_profile_id'));
        $CembraPayRequestName = $request->requestMsgType;
        $json = $request->createRequest();
        $cembrapayCommunicator = new CembraPayCommunicator($this->_dataHelper->cembraPayAzure);
        $mode = $this->_dataHelper->_scopeConfig->getValue('cembrapaycheckoutsettings/cembrapaycheckout_setup/currentmode', ScopeInterface::SCOPE_STORE);
        if ($mode == 'live') {
            $cembrapayCommunicator->setServer('live');
        } else {
            $cembrapayCommunicator->setServer('test');
        }
        $response = $cembrapayCommunicator->sendGetTransactionRequest($json, $this->_dataHelper->getAccessData(),
            function ($object, $token) {
                $object->saveToken($token);
            });

        $transactionStatus = "";
        $responseRes = null;
        if ($response) {
            $responseRes = $this->_dataHelper->getTransactionResponse($response);
            if (!empty($responseRes->transactionStatus)) {
                $transactionStatus = $responseRes->transactionStatus->transactionStatus;
            }
            $this->_dataHelper->saveLog($json, $response, $responseRes->processingStatus, $CembraPayRequestName,
                "-","-","-","-","-","-","-",$transactionId, $order->getRealOrderId());
        } else {
            $this->_dataHelper->saveLog($json, $response, "Query error", $CembraPayRequestName,
                "-","-","-","-","-","-","-",$transactionId,"-");
        }
        if (!empty($transactionStatus) && in_array($transactionStatus, DataHelper::$GET_OK_TRANSACTION_STATUSES)) {
            $payment->setAdditionalInformation("chk_processed_ok", 'true');
            $payment->save();

            $order->setState(Order::STATE_PROCESSING);
            $order->setStatus("processing");

            $this->_dataHelper->saveStatusToOrder($order);
            try {
                $mode = $this->_dataHelper->_scopeConfig->getValue('cembrapaycheckoutsettings/cembrapaycheckout_setup/currentmode', ScopeInterface::SCOPE_STORE);
                if ($mode == 'live') {
                    $email = $this->_dataHelper->_scopeConfig->getValue('cembrapaycheckoutsettings/cembrapaycheckout_setup/cembrapaycheckout_prod_email', ScopeInterface::SCOPE_STORE);
                } else {
                    $email = $this->_dataHelper->_scopeConfig->getValue('cembrapaycheckoutsettings/cembrapaycheckout_setup/cembrapaycheckout_test_email', ScopeInterface::SCOPE_STORE);
                }
                if ($this->_dataHelper->_scopeConfig->getValue('cembrapaycheckoutsettings/cembrapaycheckout_setup/force_send_email', ScopeInterface::SCOPE_STORE) == '1') {
                    $this->_dataHelper->_originalOrderSender->send($order);
                }
                $this->_dataHelper->_cembrapayOrderSender->sendOrder($order, $email);
            } catch (\Exception $e) {
                $this->_dataHelper->_loggerPsr->critical($e);
            }

            $resultRedirect = $this->resultRedirectFactory->create();
            $resultRedirect->setPath('checkout/onepage/success/');
            return $resultRedirect;
        } else {
            $resultRedirect = $this->resultRedirectFactory->create();
            $resultRedirect->setPath('cembrapaycheckoutcore/checkout/cancel');
            return $resultRedirect;
        }


    }
}
