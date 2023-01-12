<?php
/**
 * Copyright © 2016 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace ByjunoCheckout\ByjunoCheckoutCore\Model\Ui;

use ByjunoCheckout\ByjunoCheckoutCore\Helper\DataHelper;
use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Payment\Helper\Data as PaymentHelper;

/**
 * Class ConfigProvider
 */
class ConfigProvider implements ConfigProviderInterface
{
    protected $_resolver;
    const CODE_INVOICE = 'byjunocheckout_invoice';
    const CODE_INSTALLMENT = 'byjunocheckout_installment';
    /* @var $_scopeConfig \Magento\Framework\App\Config\ScopeConfigInterface */
    private $_scopeConfig;

    /**
     * Retrieve assoc array of checkout configuration
     *
     * @return array
     */
    /**
     * @var \Magento\Checkout\Model\Session
     */
    protected $_checkoutSession;

    /**
     * @var \Magento\Payment\Model\MethodInterface
     */
    protected $methodInstanceInvoice;

    /**
     * @var \Magento\Payment\Model\MethodInterface
     */
    protected $methodInstanceInstallment;

    /**
     * @var DataHelper
     */
    protected $dataHelper;

    public function __construct(
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        PaymentHelper $paymentHelper,
        \Magento\Framework\Locale\Resolver $resolver,
        \Magento\Checkout\Model\Session $checkoutSession,
        DataHelper $dataHelper
    )
    {
        $this->_checkoutSession = $checkoutSession;
        $this->methodInstanceInvoice = $paymentHelper->getMethodInstance(self::CODE_INVOICE);
        $this->methodInstanceInstallment = $paymentHelper->getMethodInstance(self::CODE_INSTALLMENT);
        $this->_scopeConfig = $scopeConfig;
        $this->_resolver = $resolver;
        $this->dataHelper = $dataHelper;
    }

    private function getByjunoLogoInstallment()
    {
        $logo = 'https://byjuno.ch/Content/logo/de/6639/BJ_Ratenzahlung_BLK.gif';
        if (substr($this->_resolver->getLocale(), 0, 2) == 'en') {
            $logo = 'https://byjuno.ch/Content/logo/en/6639/BJ_Installments_BLK.gif';
        } else if (substr($this->_resolver->getLocale(), 0, 2) == 'fr') {
            $logo = 'https://byjuno.ch/Content/logo/fr/6639/BJ_Paiement_echelonne_BLK.gif';
        } else if (substr($this->_resolver->getLocale(), 0, 2) == 'it') {
            $logo = 'https://byjuno.ch/Content/logo/it/6639/BJ_Pagemento_Rateale_BLK.gif';
        } else {
            $logo = 'https://byjuno.ch/Content/logo/de/6639/BJ_Ratenzahlung_BLK.gif';
        }
        return $logo;
    }

    private function getByjunoLogoInvoice()
    {
        $logo = '';
        if (substr($this->_resolver->getLocale(), 0, 2) == 'en') {
            $logo = 'https://byjuno.ch/Content/logo/en/6639/BJ_Invoice_BLK.gif';
        } else if (substr($this->_resolver->getLocale(), 0, 2) == 'fr') {
            $logo = 'https://byjuno.ch/Content/logo/fr/6639/BJ_Facture_BLK.gif';
        } else if (substr($this->_resolver->getLocale(), 0, 2) == 'it') {
            $logo = 'https://byjuno.ch/Content/logo/it/6639/BJ_Fattura_BLK.gif';
        } else {
            $logo = 'https://byjuno.ch/Content/logo/de/6639/BJ_Rechnung_BLK.gif';
        }
        return $logo;
    }

    private function isAllowedByScreening($screeningStatus, $method)
    {
        if ($this->_scopeConfig->getValue('byjunocheckoutsettings/byjunocheckout_setup/screeningbeforeshow', \Magento\Store\Model\ScopeInterface::SCOPE_STORE) == '0') {
            return true;
        }
        if ($screeningStatus == null) {
            return true;
        }
        foreach ($screeningStatus as $st) {
            if ($st == $method) {
                return true;
            }
        }
        return false;
    }

    public function getConfig()
    {
        $isAvaliable =  $this->_scopeConfig->getValue("byjunocheckoutsettings/byjunocheckout_setup/active", \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
        if (!$isAvaliable) {
            return [];
        }
        $isCompany = false;
        if (!empty($this->_checkoutSession->getQuote()->getBillingAddress()->getCompany()) &&
            $this->_scopeConfig->getValue("byjunocheckoutsettings/byjunocheckout_setup/businesstobusiness", \Magento\Store\Model\ScopeInterface::SCOPE_STORE) == '1'
        )
        {
            $isCompany = true;
        }
        $quote = $this->_checkoutSession->getQuote();
        if ($quote == null) {
            return [];
        }
        $this->dataHelper->GetCreditStatus($quote, $this->dataHelper->getInvoiceEnabledMethods());
        $status = DataHelper::$screeningStatus;
        if (empty($status)) {
            $status = null;
        }
        $methodsAvailableInvoice = Array();
        $availableMethods = $this->dataHelper->getMethodsMapping();
        $byjunocheckout_single_invoice_allow = $this->_scopeConfig->getValue("byjunoinvoicesettings/byjunocheckout_single_invoice/byjunocheckout_single_invoice_allow", \Magento\Store\Model\ScopeInterface::SCOPE_STORE);

        $byjunocheckout_invoice_partial_allow = $this->_scopeConfig->getValue("byjunoinvoicesettings/byjunocheckout_invoice_partial/byjunocheckout_invoice_partial_allow", \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
        if ($this->_scopeConfig->getValue("byjunoinvoicesettings/byjunocheckout_invoice_partial/active", \Magento\Store\Model\ScopeInterface::SCOPE_STORE)
            && (($byjunocheckout_invoice_partial_allow == '0' && $this->isAllowedByScreening($status, DataHelper::$BYJUNOINVOICE)) || $byjunocheckout_invoice_partial_allow == '1')) {
            $methodsAvailableInvoice[] = Array(
                "value" => $availableMethods[DataHelper::$BYJUNOINVOICE]["value"],
                "name" => $availableMethods[DataHelper::$BYJUNOINVOICE]["name"],
                "link" => $availableMethods[DataHelper::$BYJUNOINVOICE]["link"]
            );
        }

        if ($this->_scopeConfig->getValue("byjunoinvoicesettings/byjunocheckout_single_invoice/active", \Magento\Store\Model\ScopeInterface::SCOPE_STORE)
            && (($byjunocheckout_single_invoice_allow == '0' && $this->isAllowedByScreening($status, DataHelper::$SINGLEINVOICE)) || $byjunocheckout_single_invoice_allow == '1')) {
            $methodsAvailableInvoice[] = Array(
                "value" => $availableMethods[DataHelper::$SINGLEINVOICE]["value"],
                "name" => $availableMethods[DataHelper::$SINGLEINVOICE]["name"],
                "link" => $availableMethods[DataHelper::$SINGLEINVOICE]["link"]
            );
        }

        $defaultInvoicePlan = DataHelper::$BYJUNOINVOICE;
        if (count($methodsAvailableInvoice) > 0) {
            $defaultInvoicePlan = $methodsAvailableInvoice[0]["value"];
        }

        $methodsAvailableInstallment = Array();
/*
        $byjunocheckout_installment_3installment_allow = $this->_scopeConfig->getValue("byjunoinstallmentsettings/byjunocheckout_installment_3installment/byjunocheckout_installment_3installment_allow", \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
        if ($this->_scopeConfig->getValue("byjunoinstallmentsettings/byjunocheckout_installment_3installment/active", \Magento\Store\Model\ScopeInterface::SCOPE_STORE)
            && ($byjunocheckout_installment_3installment_allow == '0' || ($byjunocheckout_installment_3installment_allow == '1' && !$isCompany) || ($byjunocheckout_installment_3installment_allow == '2' && $isCompany))) {
            $methodsAvailableInstallment[] = Array(
                "value" => 'installment_3installment_enable',
                "name" => $this->_scopeConfig->getValue("byjunoinstallmentsettings/byjunocheckout_installment_3installment/name", \Magento\Store\Model\ScopeInterface::SCOPE_STORE),
                "link" => $this->_scopeConfig->getValue("byjunoinstallmentsettings/byjunocheckout_installment_3installment/link", \Magento\Store\Model\ScopeInterface::SCOPE_STORE)
            );
        }

        $byjunocheckout_installment_10installment_allow = $this->_scopeConfig->getValue("byjunoinstallmentsettings/byjunocheckout_installment_10installment/byjunocheckout_installment_10installment_allow", \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
        if ($this->_scopeConfig->getValue("byjunoinstallmentsettings/byjunocheckout_installment_10installment/active", \Magento\Store\Model\ScopeInterface::SCOPE_STORE)
            && ($byjunocheckout_installment_10installment_allow == '0' || ($byjunocheckout_installment_10installment_allow == '1' && !$isCompany) || ($byjunocheckout_installment_10installment_allow == '2' && $isCompany))) {
            $methodsAvailableInstallment[] = Array(
                "value" => 'installment_10installment_enable',
                "name" => $this->_scopeConfig->getValue("byjunoinstallmentsettings/byjunocheckout_installment_10installment/name", \Magento\Store\Model\ScopeInterface::SCOPE_STORE),
                "link" => $this->_scopeConfig->getValue("byjunoinstallmentsettings/byjunocheckout_installment_10installment/link", \Magento\Store\Model\ScopeInterface::SCOPE_STORE)
            );
        }

        $byjunocheckout_installment_12installment_allow = $this->_scopeConfig->getValue("byjunoinstallmentsettings/byjunocheckout_installment_12installment/byjunocheckout_installment_12installment_allow", \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
        if ($this->_scopeConfig->getValue("byjunoinstallmentsettings/byjunocheckout_installment_12installment/active", \Magento\Store\Model\ScopeInterface::SCOPE_STORE)
            && ($byjunocheckout_installment_12installment_allow == '0' || ($byjunocheckout_installment_12installment_allow == '1' && !$isCompany) || ($byjunocheckout_installment_12installment_allow == '2' && $isCompany))) {
            $methodsAvailableInstallment[] = Array(
                "value" => 'installment_12installment_enable',
                "name" => $this->_scopeConfig->getValue("byjunoinstallmentsettings/byjunocheckout_installment_12installment/name", \Magento\Store\Model\ScopeInterface::SCOPE_STORE),
                "link" => $this->_scopeConfig->getValue("byjunoinstallmentsettings/byjunocheckout_installment_12installment/link", \Magento\Store\Model\ScopeInterface::SCOPE_STORE)
            );
        }

        $byjunocheckout_installment_24installment_allow = $this->_scopeConfig->getValue("byjunoinstallmentsettings/byjunocheckout_installment_24installment/byjunocheckout_installment_24installment_allow", \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
        if ($this->_scopeConfig->getValue("byjunoinstallmentsettings/byjunocheckout_installment_24installment/active", \Magento\Store\Model\ScopeInterface::SCOPE_STORE)
            && ($byjunocheckout_installment_24installment_allow == '0' || ($byjunocheckout_installment_24installment_allow == '1' && !$isCompany) || ($byjunocheckout_installment_24installment_allow == '2' && $isCompany))) {
            $methodsAvailableInstallment[] = Array(
                "value" => 'installment_24installment_enable',
                "name" => $this->_scopeConfig->getValue("byjunoinstallmentsettings/byjunocheckout_installment_24installment/name", \Magento\Store\Model\ScopeInterface::SCOPE_STORE),
                "link" => $this->_scopeConfig->getValue("byjunoinstallmentsettings/byjunocheckout_installment_24installment/link", \Magento\Store\Model\ScopeInterface::SCOPE_STORE)
            );
        }

        $byjunocheckout_installment_4x12installment_allow = $this->_scopeConfig->getValue("byjunoinstallmentsettings/byjunocheckout_installment_4x12installment/byjunocheckout_installment_4x12installment_allow", \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
        if ($this->_scopeConfig->getValue("byjunoinstallmentsettings/byjunocheckout_installment_4x12installment/active", \Magento\Store\Model\ScopeInterface::SCOPE_STORE)
            && ($byjunocheckout_installment_4x12installment_allow == '0' || ($byjunocheckout_installment_4x12installment_allow == '1' && !$isCompany) || ($byjunocheckout_installment_4x12installment_allow == '2' && $isCompany))) {
            $methodsAvailableInstallment[] = Array(
                "value" => 'installment_4x12installment_enable',
                "name" => $this->_scopeConfig->getValue("byjunoinstallmentsettings/byjunocheckout_installment_4x12installment/name", \Magento\Store\Model\ScopeInterface::SCOPE_STORE),
                "link" => $this->_scopeConfig->getValue("byjunoinstallmentsettings/byjunocheckout_installment_4x12installment/link", \Magento\Store\Model\ScopeInterface::SCOPE_STORE)
            );
        }
*/
        $defaultInstallmentPlan = 'installment_3installment_enable';
/*        if (count($methodsAvailableInstallment) > 0) {
            $defaultInstallmentPlan = $methodsAvailableInstallment[0]["value"];
        }
*/
        $invoiceDelivery[] = Array(
            "value" => "email",
            "text" => __($this->_scopeConfig->getValue("byjunoinvoicesettings/byjunocheckout_invoice_localization/byjunocheckout_invoice_email_text",
                    \Magento\Store\Model\ScopeInterface::SCOPE_STORE)) . ": "
        );

        $invoiceDelivery[] = Array(
            "value" => "postal",
            "text" => __($this->_scopeConfig->getValue("byjunoinvoicesettings/byjunocheckout_invoice_localization/byjunocheckout_invoice_postal_text",
                    \Magento\Store\Model\ScopeInterface::SCOPE_STORE)) . ": "
        );
/*
        $installmentDelivery[] = Array(
            "value" => "email",
            "text" => __($this->_scopeConfig->getValue("byjunoinstallmentsettings/byjunocheckout_installment_localization/byjunocheckout_installment_email_text",
                \Magento\Store\Model\ScopeInterface::SCOPE_STORE)) . ": "
        );

        $installmentDelivery[] = Array(
            "value" => "postal",
            "text" => __($this->_scopeConfig->getValue("byjunoinstallmentsettings/byjunocheckout_installment_localization/byjunocheckout_installment_postal_text",
                    \Magento\Store\Model\ScopeInterface::SCOPE_STORE)) . ": "
        );
*/
        $gender_enable = false;
        if ($this->_scopeConfig->getValue("byjunocheckoutsettings/byjunocheckout_setup/gender_enable",
                \Magento\Store\Model\ScopeInterface::SCOPE_STORE) == 1) {
            $gender_enable = true;
        }
        $birthday_enable = false;
        if (!$isCompany && $this->_scopeConfig->getValue("byjunocheckoutsettings/byjunocheckout_setup/birthday_enable",
                \Magento\Store\Model\ScopeInterface::SCOPE_STORE) == 1) {
            $birthday_enable = true;
            $b = $this->_checkoutSession->getQuote()->getCustomerDob();
            if (!empty($b)) {
                try {
                    $dobObject = new \DateTime($b);
                    if ($dobObject != null) {
                        $birthday_enable = false;
                    }
                } catch (\Exception $e) {

                }
            }
        }

        $b2b_uid = false;
        if ($isCompany && $this->_scopeConfig->getValue("byjunocheckoutsettings/byjunocheckout_setup/b2b_uid",
                \Magento\Store\Model\ScopeInterface::SCOPE_STORE) == 1) {
            $b2b_uid = true;
        }
        $gender_prefix = trim($this->_scopeConfig->getValue("byjunocheckoutsettings/byjunocheckout_setup/gender_prefix", \Magento\Store\Model\ScopeInterface::SCOPE_STORE));
        $gendersArray = explode(";", $gender_prefix);
        foreach($gendersArray as $g) {
            if ($g != '') {
                $genders[] = Array(
                    "value" => trim($g),
                    "text" => trim($g)
                );
            }
        }
        $dafualtGender = '';
        if (!empty($genders[0]["value"])) {
            $dafualtGender = $genders[0]["value"];
        }

        $paperInvoice = false;
        if ($this->_scopeConfig->getValue("byjunocheckoutsettings/byjunocheckout_setup/byjunocheckout_invoice_paper",
                \Magento\Store\Model\ScopeInterface::SCOPE_STORE) == 1) {
            $paperInvoice = true;
        }
        return [
            'payment' => [
                self::CODE_INVOICE => [
                    'redirectUrl' => $this->methodInstanceInvoice->getConfigData('order_place_redirect_url'),
                    'methods' => $methodsAvailableInvoice,
                    'delivery' => $invoiceDelivery,
                    'default_payment' => $defaultInvoicePlan,
                    'default_delivery' => 'email',
                    'paper_invoice' => $paperInvoice,
                    'logo' => $this->getByjunoLogoInvoice(),
                    'default_customgender' => $dafualtGender,
                    'custom_genders' => $genders,
                    'gender_enable' => $gender_enable,
                    'birthday_enable' => $birthday_enable,
                    'b2b_uid' => $b2b_uid
                ],
                self::CODE_INSTALLMENT => [
                    'redirectUrl' => $this->methodInstanceInvoice->getConfigData('order_place_redirect_url'),
                    'methods' => $methodsAvailableInstallment,
                    'delivery' => $invoiceDelivery,
                    'default_payment' => $defaultInstallmentPlan,
                    'default_delivery' => 'email',
                    'paper_invoice' => $paperInvoice,
                    'logo' => $this->getByjunoLogoInstallment(),
                    'default_customgender' => $dafualtGender,
                    'custom_genders' => $genders,
                    'gender_enable' => $gender_enable,
                    'birthday_enable' => $birthday_enable,
                    'b2b_uid' => $b2b_uid
                ]
            ]
        ];
    }
}
