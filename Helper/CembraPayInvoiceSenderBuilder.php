<?php
namespace CembraPayCheckout\CembraPayCheckoutCore\Helper;

use Magento\Framework\Mail\Template\TransportBuilder;
use Magento\Sales\Model\Order\Email\SenderBuilder;

class CembraPayInvoiceSenderBuilder extends SenderBuilder
{
    public static $pdf = "";
    public static $pdf_id = "";

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
        $this->transportBuilder->addAttachment(self::$pdf, "invoice_".self::$pdf_id.".pdf", "application/pdf");
        $transport = $this->transportBuilder->getTransport();
        $transport->sendMessage();
    }
}
