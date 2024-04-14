<?php

namespace Byjuno\ByjunoCore\Controller\Checkout;

use Byjuno\ByjunoCore\Helper\Api\CembraPayCommunicator;
use Byjuno\ByjunoCore\Helper\DataHelper;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\ActionInterface;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Message\ManagerInterface as MessageManagerInterface;
use Magento\Sales\Model\Order;
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
        $resultRedirect = $this->resultRedirectFactory->create();
        $order = $this->_dataHelper->_checkoutSession->getLastRealOrder();
        if ($order == null) {
            return $this->redirectCancel($resultRedirect);
        }
        /* @var $payment \Magento\Sales\Model\Order\Payment */
        $payment = $order->getPayment();
        if ($payment == null) {
            return $this->redirectCancel($resultRedirect);
        }
        $transaction = $payment->getAuthorizationTransaction();
        $transactionId = $transaction->getTxnId();
        $request = $this->_dataHelper->createMagentoShopRequestConfirmTransaction(
            $transactionId,
            $payment->getAdditionalInformation('webshop_profile_id'));
        $CembraPayRequestName = "CNF";
        $json = $request->createRequest();
        $cembrapayCommunicator = new CembraPayCommunicator($this->_dataHelper->cembraPayAzure);
        $mode = $this->_dataHelper->_scopeConfig->getValue('cembrapaycheckoutsettings/cembrapaycheckout_setup/currentmode', ScopeInterface::SCOPE_STORE);
        if ($mode == 'live') {
            $cembrapayCommunicator->setServer('live');
        } else {
            $cembrapayCommunicator->setServer('test');
        }
        $response = $cembrapayCommunicator->sendConfirmTransactionRequest($json, $this->_dataHelper->getAccessDataWebshop($payment->getAdditionalInformation('webshop_profile_id'), $mode),
            function ($object, $token, $accessData) {
                $object->saveToken($token, $accessData);
            });
        $transactionStatus = "";
        $responseRes = null;
        if ($response) {
            $responseRes = $this->_dataHelper->confirmTransactionResponse($response);
            if (!empty($responseRes->transactionStatus)) {
                $transactionStatus = $responseRes->transactionStatus->transactionStatus;
            }
            $this->_dataHelper->saveLog($json, $response, $transactionStatus, $CembraPayRequestName,
                "-", "-", $request->requestMsgId, "-", "-", "-", "-", $transactionId, $order->getRealOrderId());
        } else {
            $this->_dataHelper->saveLog($json, $response, "Query error", $CembraPayRequestName,
                "-", "-", $request->requestMsgId, "-", "-", "-", "-", $transactionId, "-");
        }
        if (!empty($transactionStatus) && in_array($transactionStatus, DataHelper::$CNF_OK_TRANSACTION_STATUSES)) {
            $payment->setAdditionalInformation("chk_processed_ok", 'true');
            $payment->save();

            $order->setState(Order::STATE_PROCESSING);
            $order->setStatus("processing");

            $this->_dataHelper->saveStatusToOrder($order);
            $mode = $this->_dataHelper->_scopeConfig->getValue('cembrapaycheckoutsettings/cembrapaycheckout_setup/currentmode', ScopeInterface::SCOPE_STORE);
            if ($mode == 'live') {
                $email = $this->_dataHelper->_scopeConfig->getValue('cembrapaycheckoutsettings/cembrapaycheckout_setup/cembrapaycheckout_prod_email', ScopeInterface::SCOPE_STORE);
            } else {
                $email = $this->_dataHelper->_scopeConfig->getValue('cembrapaycheckoutsettings/cembrapaycheckout_setup/cembrapaycheckout_test_email', ScopeInterface::SCOPE_STORE);
            }
            try {
                if ($this->_dataHelper->_scopeConfig->getValue('cembrapaycheckoutsettings/cembrapaycheckout_setup/force_send_email', ScopeInterface::SCOPE_STORE) == '1') {
                    $this->_dataHelper->_originalOrderSender->send($order);
                }
                $this->_dataHelper->_cembrapayOrderSender->sendOrder($order, $email);
            } catch (\Exception $e) {
                $this->_dataHelper->_loggerPsr->critical($e);
            }

            try {
                if ($this->_dataHelper->_scopeConfig->getValue("cembrapaycheckoutsettings/cembrapaycheckout_setup/auto_invoice", ScopeInterface::SCOPE_STORE) == '1') {
                    $this->_dataHelper->generateInvoice($order);
                }
                $resultRedirect->setPath('checkout/onepage/success/');
                return $resultRedirect;
            } catch (\Exception $e) {
                return $this->redirectCancel($resultRedirect);
            }
        } else {
            return $this->redirectCancel($resultRedirect);
        }
    }

    private function redirectCancel($resultRedirect)
    {
        $resultRedirect->setPath('cembrapaycheckoutcore/checkout/cancel');
        return $resultRedirect;
    }

}
