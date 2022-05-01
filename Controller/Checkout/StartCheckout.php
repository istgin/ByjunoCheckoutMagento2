<?php

namespace ByjunoCheckout\ByjunoCheckoutCore\Controller\Checkout;

use ByjunoCheckout\ByjunoCheckoutCore\Helper\DataHelper;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\ActionInterface;
use Magento\Framework\Controller\Result\RedirectFactory;


class StartCheckout implements ActionInterface
{
    protected $_config;
    /**
     * @var DataHelper
     */
    protected static $_dataHelper;
    /**
     * @var RedirectFactory
     */
    protected $resultRedirectFactory;

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
        $this->resultRedirectFactory = $resultRedirectFactory;
        self::$_dataHelper = $helper;
    }

    public function execute()
    {
        $order = self::$_dataHelper->_checkoutSession->getLastRealOrder();
        if ($order != null) {
            exit($order->getIncrementId());
            $resultRedirect = $this->resultRedirectFactory->create();
            $resultRedirect->setUrl('https://www.google.com?aaa=' . $order->getIncrementId());
            return $resultRedirect;
        }
    }
}
