<?php
namespace ByjunoCheckout\ByjunoCheckoutCore\Helper;

use Magento\Framework\Mail\Template\TransportBuilder;
use Magento\Sales\Model\Order\Email\Container\IdentityInterface;
use Magento\Sales\Model\Order\Email\Container\Template;
use Magento\Sales\Model\Order\Email\SenderBuilder;

class ByjunoSenderBuilder extends SenderBuilder
{
    public static $orderId;
    public function send()
    {
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        /* @var $transportBuilder \ByjunoCheckout\ByjunoCheckoutCore\Mail\Template\ByjunoTransportBuilder */
        $transportBuilder = $objectManager->get('\ByjunoCheckout\ByjunoCheckoutCore\Mail\Template\ByjunoTransportBuilder');
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
