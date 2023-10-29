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
        $cembrapayCommunicator = new CembraPayCommunicator();
        $mode = $this->_dataHelper->_scopeConfig->getValue('cembrapaycheckoutsettings/cembrapaycheckout_setup/currentmode', ScopeInterface::SCOPE_STORE);
        if ($mode == 'live') {
            $cembrapayCommunicator->setServer('live');
        } else {
            $cembrapayCommunicator->setServer('test');
        }
        $response = $cembrapayCommunicator->sendGetTransactionRequest($json, (int)$this->_dataHelper->_scopeConfig->getValue('cembrapaycheckoutsettings/cembrapaycheckout_setup/timeout',
            ScopeInterface::SCOPE_STORE),
            $this->_dataHelper->_scopeConfig->getValue('cembrapaycheckoutsettings/cembrapaycheckout_setup/cembrapaylogin', ScopeInterface::SCOPE_STORE),
            $this->_dataHelper->_scopeConfig->getValue('cembrapaycheckoutsettings/cembrapaycheckout_setup/cembrapaypassword', ScopeInterface::SCOPE_STORE));

        $status = "";
        $transactionStatus = "";
        $responseRes = null;
        if ($response) {
            $responseRes = $this->_dataHelper->getTransactionResponse($response);
            $status = $responseRes->processingStatus;
            $transactionStatus = $responseRes->transactionStatus->transactionStatus;
            $this->_dataHelper->saveLog($json, $response, $responseRes->processingStatus, $CembraPayRequestName,
                "-","-","-","-","-","-","-",$transactionId, $order->getRealOrderId());
        } else {
            $this->_dataHelper->saveLog($json, $response, "Query error", $CembraPayRequestName,
                "-","-","-","-","-","-","-",$transactionId,"-");
        }
        if ($status == DataHelper::$GET_OK && $transactionStatus == DataHelper::$GET_OK_TRANSACTION) {
            $payment->setAdditionalInformation("chk_processed_ok", 'true');
            $payment->save();

            if ($this->_dataHelper->_scopeConfig->getValue('cembrapaycheckoutsettings/cembrapaycheckout_setup/success_state', ScopeInterface::SCOPE_STORE) == 'completed') {
                $order->setState(Order::STATE_COMPLETE);
                $order->setStatus("complete");
            } else if ($this->_dataHelper->_scopeConfig->getValue('cembrapaycheckoutsettings/cembrapaycheckout_setup/success_state', ScopeInterface::SCOPE_STORE) == 'processing') {
                $order->setState(Order::STATE_PROCESSING);
                $order->setStatus("processing");
            } else {
                $order->setStatus("pending");
            }

            $this->_dataHelper->saveStatusToOrder($order);
            try {
                $mode = $this->_dataHelper->_scopeConfig->getValue('cembrapaycheckoutsettings/cembrapaycheckout_setup/currentmode', ScopeInterface::SCOPE_STORE);
                if ($mode == 'live') {
                    $email = $this->_dataHelper->_scopeConfig->getValue('cembrapaycheckoutsettings/cembrapaycheckout_setup/cembrapaycheckout_prod_email', ScopeInterface::SCOPE_STORE);
                } else {
                    $email = $this->_dataHelper->_scopeConfig->getValue('cembrapaycheckoutsettings/cembrapaycheckout_setup/cembrapaycheckout_test_email', ScopeInterface::SCOPE_STORE);
                }
                $this->_dataHelper->_cembrapayOrderSender->sendOrder($order, $email);
            } catch (\Exception $e) {
                $this->_dataHelper->_loggerPsr->critical($e);
            }
            $resultRedirect = $this->resultRedirectFactory->create();
            $resultRedirect->setPath('checkout/success');
            return $resultRedirect;
        } else {
            $resultRedirect = $this->resultRedirectFactory->create();
            $resultRedirect->setPath('cembrapaycheckoutcore/checkout/cancel');
            return $resultRedirect;
        }


    }
}
