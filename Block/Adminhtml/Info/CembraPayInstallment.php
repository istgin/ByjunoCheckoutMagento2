<?php
namespace Byjuno\ByjunoCore\Block\Adminhtml\Info;

class CembraPayInstallment extends \Magento\Payment\Block\Info
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
        if ($plId == 'installment_3installment_enable') {
            $repayment = $this->_scopeConfig->getValue("cembrapayinstallmentsettings/cembrapaycheckout_installment_3installment/name", \Magento\Store\Model\ScopeInterface::SCOPE_STORE, $webshopProfileId);
        } else if ($plId == 'installment_4installment_enable') {
            $repayment = $this->_scopeConfig->getValue("cembrapayinstallmentsettings/cembrapaycheckout_installment_4installment/name", \Magento\Store\Model\ScopeInterface::SCOPE_STORE, $webshopProfileId);
        } else if ($plId == 'installment_6installment_enable') {
            $repayment = $this->_scopeConfig->getValue("cembrapayinstallmentsettings/cembrapaycheckout_installment_6installment/name", \Magento\Store\Model\ScopeInterface::SCOPE_STORE, $webshopProfileId);
        } else if ($plId == 'installment_12installment_enable') {
            $repayment = $this->_scopeConfig->getValue("cembrapayinstallmentsettings/cembrapaycheckout_installment_12installment/name", \Magento\Store\Model\ScopeInterface::SCOPE_STORE, $webshopProfileId);
        } else if ($plId == 'installment_24installment_enable') {
            $repayment = $this->_scopeConfig->getValue("cembrapayinstallmentsettings/cembrapaycheckout_installment_24installment/name", \Magento\Store\Model\ScopeInterface::SCOPE_STORE, $webshopProfileId);
        } else if ($plId == 'installment_36installment_enable') {
            $repayment = $this->_scopeConfig->getValue("cembrapayinstallmentsettings/cembrapaycheckout_installment_36installment/name", \Magento\Store\Model\ScopeInterface::SCOPE_STORE, $webshopProfileId);
        } else if ($plId == 'installment_48installment_enable') {
            $repayment = $this->_scopeConfig->getValue("cembrapayinstallmentsettings/cembrapaycheckout_installment_48installment/name", \Magento\Store\Model\ScopeInterface::SCOPE_STORE, $webshopProfileId);
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
        if ($plId == 'installment_3installment_enable') {
            $repayment = $this->_scopeConfig->getValue("cembrapayinstallmentsettings/cembrapaycheckout_installment_3installment/name", \Magento\Store\Model\ScopeInterface::SCOPE_STORE, $webshopProfileId);
        } else if ($plId == 'installment_4installment_enable') {
            $repayment = $this->_scopeConfig->getValue("cembrapayinstallmentsettings/cembrapaycheckout_installment_4installment/name", \Magento\Store\Model\ScopeInterface::SCOPE_STORE, $webshopProfileId);
        } else if ($plId == 'installment_6installment_enable') {
            $repayment = $this->_scopeConfig->getValue("cembrapayinstallmentsettings/cembrapaycheckout_installment_6installment/name", \Magento\Store\Model\ScopeInterface::SCOPE_STORE, $webshopProfileId);
        } else if ($plId == 'installment_12installment_enable') {
            $repayment = $this->_scopeConfig->getValue("cembrapayinstallmentsettings/cembrapaycheckout_installment_12installment/name", \Magento\Store\Model\ScopeInterface::SCOPE_STORE, $webshopProfileId);
        } else if ($plId == 'installment_24installment_enable') {
            $repayment = $this->_scopeConfig->getValue("cembrapayinstallmentsettings/cembrapaycheckout_installment_24installment/name", \Magento\Store\Model\ScopeInterface::SCOPE_STORE, $webshopProfileId);
        } else if ($plId == 'installment_36installment_enable') {
            $repayment = $this->_scopeConfig->getValue("cembrapayinstallmentsettings/cembrapaycheckout_installment_36installment/name", \Magento\Store\Model\ScopeInterface::SCOPE_STORE, $webshopProfileId);
        } else if ($plId == 'installment_48installment_enable') {
            $repayment = $this->_scopeConfig->getValue("cembrapayinstallmentsettings/cembrapaycheckout_installment_48installment/name", \Magento\Store\Model\ScopeInterface::SCOPE_STORE, $webshopProfileId);
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
