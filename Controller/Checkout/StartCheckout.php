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


class StartCheckout implements ActionInterface
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
        $resultRedirect = $this->resultRedirectFactory->create();
        if ($order != null) {
            $payment = $order->getPayment();
            $payment->setAdditionalInformation("webshop_profile_id", 1);
            $payment->setAdditionalInformation("chk_executed_ok", 'false');
            $payment->save();
            $request = $this->_dataHelper->createMagentoShopRequestCheckout(
                $order,
                $payment,
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
            $response = $cembrapayCommunicator->sendCheckoutRequest($json, (int)$this->_dataHelper->_scopeConfig->getValue('cembrapaycheckoutsettings/cembrapaycheckout_setup/timeout',
                ScopeInterface::SCOPE_STORE),
                $this->_dataHelper->_scopeConfig->getValue('cembrapaycheckoutsettings/cembrapaycheckout_setup/cembrapaylogin', ScopeInterface::SCOPE_STORE),
                $this->_dataHelper->_scopeConfig->getValue('cembrapaycheckoutsettings/cembrapaycheckout_setup/cembrapaypassword', ScopeInterface::SCOPE_STORE));

            $status = "";
            $responseRes = null;
            if ($response) {
                $responseRes = $this->_dataHelper->checkoutResponse($response);
                $status = $responseRes->processingStatus;
                $this->_dataHelper->saveLog($json, $response, $responseRes->processingStatus, $CembraPayRequestName,
                    $request->custDetails->firstName, $request->custDetails->lastName, $request->requestMsgId,
                    $request->billingAddr->postalCode, $request->billingAddr->town, $request->billingAddr->country, $request->billingAddr->addrFirstLine, $responseRes->transactionId, $order->getRealOrderId());
            } else {
                $this->_dataHelper->saveLog($json, $response, "Query error", $CembraPayRequestName,
                    $request->custDetails->firstName, $request->custDetails->lastName, $request->requestMsgId,
                    $request->billingAddr->postalCode, $request->billingAddr->town, $request->billingAddr->country, $request->billingAddr->addrFirstLine, "-", "-");
            }
            if ($status == DataHelper::$CHK_OK) {
                $cembrapayTrx = $responseRes->transactionId;
                $redirectUrl = $responseRes->redirectUrlCheckout;
                $payment->setTransactionId($cembrapayTrx);
                $payment->setParentTransactionId($payment->getTransactionId());
                // $payment->setIsTransactionPending(true);
                $transaction = $payment->addTransaction(Transaction::TYPE_AUTH, null, true);
                $transaction->setIsClosed(false);
                $payment->setAdditionalInformation("chk_executed_ok", 'true');
                $this->_dataHelper->saveStatusToOrder($order);
                $resultRedirect->setUrl($redirectUrl);
            } else {
                $error = $this->_dataHelper->getCembraPayErrorMessage();
                $order->registerCancellation($error)->save();
                $this->restoreQuote();
                $this->messageManager->addExceptionMessage(new \Exception("ex"), $error);
                $resultRedirect = $this->resultRedirectFactory->create();
                $resultRedirect->setPath('checkout/cart');
            }
            return $resultRedirect;
        }
    }
    private function restoreQuote()
    {
        /** @var \Magento\Sales\Model\Order $order */
        $order = $this->_dataHelper->_checkoutSession->getLastRealOrder();
        if ($order->getId()) {
            try {
                $quote = $this->_dataHelper->quoteRepository->get($order->getQuoteId());
                $quote->setIsActive(1)->setReservedOrderId(null);
                $this->_dataHelper->quoteRepository->save($quote);
                $this->_dataHelper->_checkoutSession->replaceQuote($quote)->unsLastRealOrderId();
                return true;
            } catch (\Magento\Framework\Exception\NoSuchEntityException $e) {
            }
        }
        return false;
    }
}
