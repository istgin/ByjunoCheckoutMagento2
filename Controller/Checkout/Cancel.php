<?php

namespace Byjuno\ByjunoCore\Controller\Checkout;

use Byjuno\ByjunoCore\Helper\Api\CembraPayCheckoutAuthorizationResponse;
use Byjuno\ByjunoCore\Helper\Api\CembraPayCheckoutChkResponse;
use Byjuno\ByjunoCore\Helper\Api\CembraPayCommunicator;
use Byjuno\ByjunoCore\Helper\DataHelper;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\ActionInterface;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Message\ManagerInterface as MessageManagerInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Payment\Transaction;
use Magento\Store\Model\ScopeInterface;


class Cancel implements ActionInterface
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
        $error = $this->_dataHelper->getCembraPayErrorMessage();
        try {
            if ($order != null && $order->getId() != null) {
                $order->registerCancellation($error)->save();
                $this->restoreQuote();
            }
        } catch (\Exception $e) {

        }
        $this->messageManager->addExceptionMessage(new \Exception("ex"), $error);
        $resultRedirect = $this->resultRedirectFactory->create();
        $resultRedirect->setPath('checkout/cart');
        return $resultRedirect;
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
