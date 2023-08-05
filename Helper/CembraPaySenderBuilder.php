<?php
namespace CembraPayCheckout\CembraPayCheckoutCore\Helper;

use Magento\Framework\Mail\Template\TransportBuilder;
use Magento\Sales\Model\Order\Email\Container\IdentityInterface;
use Magento\Sales\Model\Order\Email\Container\Template;
use Magento\Sales\Model\Order\Email\SenderBuilder;

class CembraPaySenderBuilder extends SenderBuilder
{
    public static $orderId;
    public function send()
    {
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        /* @var $transportBuilder \CembraPayCheckout\CembraPayCheckoutCore\Mail\Template\CembraPayTransportBuilder */
        $transportBuilder = $objectManager->get('\CembraPayCheckout\CembraPayCheckoutCore\Mail\Template\CembraPayTransportBuilder');
        $this->transportBuilder = $transportBuilder;
        $this->configureEmailTemplate();
        $this->transportBuilder->addTo(
            $this->identityContainer->getCustomerEmail(),
            $this->identityContainer->getCustomerName()
        );
        $this->transportBuilder->addAttachmentAsHtml(true, "order_confirmation_".self::$orderId.".html");
        $transport = $this->transportBuilder->getTransport();
        $transport->sendMessage();
    }
}
