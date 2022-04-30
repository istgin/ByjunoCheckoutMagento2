<?php
namespace ByjunoCheckout\ByjunoCheckoutCore\Block\Adminhtml\Info;

use ByjunoCheckout\ByjunoCheckoutCore\Helper\DataHelper;

class ByjunoInvoice extends \Magento\Payment\Block\Info
{
    /**
     * @var string
     */
    public function  toHtml() {
        $paymentMehtodName = $this->getMethod()->getTitle();
        $info = $this->getInfo()->getAdditionalInformation("is_b2b");
        $plId = $this->getInfo()->getAdditionalInformation("payment_plan");
        $repayment = "";
        $webshopProfileId = $this->getInfo()->getAdditionalInformation("webshop_profile_id");
        if ($plId == DataHelper::$SINGLEINVOICE) {
            $repayment = $this->_scopeConfig->getValue("byjunoinvoicesettings/byjunocheckout_single_invoice/name", \Magento\Store\Model\ScopeInterface::SCOPE_STORE, $webshopProfileId);
        } else if ($plId == DataHelper::$BYJUNOINVOICE) {
            $repayment = $this->_scopeConfig->getValue("byjunoinvoicesettings/byjunocheckout_invoice_partial/name", \Magento\Store\Model\ScopeInterface::SCOPE_STORE, $webshopProfileId);
        }
        $paymentSend = $this->getInfo()->getAdditionalInformation("payment_send");
        $htmlAdd = '';
        if ($paymentSend == 'email')
        {
            $htmlAdd = __("Delivery method by E-Mail");
        }
        else if ($paymentSend == 'postal')
        {
            $htmlAdd = __("Delivery method by Post");
        }
        $out = '(B2C)';
        if ($info == true) {
            $out = '(B2B)';
        }
        return $paymentMehtodName."<br />".$repayment.' '.$out.'<br />'.$htmlAdd;
    }

    /**
     * @return string
     */
    public function toPdf()
    {
        $paymentMehtodName = $this->getMethod()->getTitle();
        $info = $this->getInfo()->getAdditionalInformation("is_b2b");
        $plId = $this->getInfo()->getAdditionalInformation("payment_plan");
        $repayment = "";
        $webshopProfileId = $this->getInfo()->getAdditionalInformation("webshop_profile_id");
        if ($plId == DataHelper::$SINGLEINVOICE) {
            $repayment = $this->_scopeConfig->getValue("byjunoinvoicesettings/byjunocheckout_single_invoice/name", \Magento\Store\Model\ScopeInterface::SCOPE_STORE, $webshopProfileId);
        } else if ($plId == DataHelper::$BYJUNOINVOICE) {
            $repayment = $this->_scopeConfig->getValue("byjunoinvoicesettings/byjunocheckout_invoice_partial/name", \Magento\Store\Model\ScopeInterface::SCOPE_STORE, $webshopProfileId);
        }
        $paymentSend = $this->getInfo()->getAdditionalInformation("payment_send");
        $htmlAdd = '';
        if ($paymentSend == 'email')
        {
            $htmlAdd = __("Delivery method by E-Mail");
        }
        else if ($paymentSend == 'postal')
        {
            $htmlAdd = __("Delivery method by Post");
        }
        $out = '(B2C)';
        if ($info == true) {
            $out = '(B2B)';
        }
        return $paymentMehtodName."{{pdf_row_separator}}".$repayment.' '.$out.'{{pdf_row_separator}}'.$htmlAdd;
    }
}
