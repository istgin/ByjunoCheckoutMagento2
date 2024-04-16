<?php
/**
 * Copyright © 2016 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Byjuno\ByjunoCore\Model\Ui;

use Byjuno\ByjunoCore\Helper\DataHelper;
use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Framework\Locale\Bundle\DataBundle;
use Magento\Framework\Locale\ResolverInterface;
use Magento\Framework\Json\EncoderInterface;
use Magento\Framework\App\ObjectManager;
use Magento\Payment\Helper\Data as PaymentHelper;
use Magento\Store\Model\ScopeInterface;

/**
 * Class ConfigProvider
 */
class ConfigProvider implements ConfigProviderInterface
{
    protected $_resolver;
    const CODE_INVOICE = 'byjuno_invoice';
    const CODE_INSTALLMENT = 'byjuno_installment';
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
     * JSON Encoder
     *
     * @var EncoderInterface
     */
    private $encoder;

    /**
     * @var ResolverInterface
     */
    private $localeResolver;

    /**
     * @var DataHelper
     */
    protected $dataHelper;

    public function __construct(
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        PaymentHelper $paymentHelper,
        \Magento\Framework\Locale\Resolver $resolver,
        \Magento\Checkout\Model\Session $checkoutSession,
        DataHelper $dataHelper,
        ?ResolverInterface $localeResolver = null,
        ?EncoderInterface $encoder = null
    )
    {
        $this->_checkoutSession = $checkoutSession;
        $this->methodInstanceInvoice = $paymentHelper->getMethodInstance(self::CODE_INVOICE);
        $this->methodInstanceInstallment = $paymentHelper->getMethodInstance(self::CODE_INSTALLMENT);
        $this->_scopeConfig = $scopeConfig;
        $this->_resolver = $resolver;
        $this->dataHelper = $dataHelper;
        $this->encoder = $encoder ?? ObjectManager::getInstance()->get(EncoderInterface::class);
        $this->localeResolver = $localeResolver ?? ObjectManager::getInstance()->get(ResolverInterface::class);
    }

    private function getCembraPayLogoInstallment()
    {
        return "https://cembrapay.ch/logo/gif/66x39/CembraPay_Checkout_RGB_66x39.gif";
    }

    private function getCembraPayLogoInvoice()
    {
        return "https://cembrapay.ch/logo/gif/66x39/CembraPay_Checkout_RGB_66x39.gif";
    }

    private function isAllowedByScreening($screeningStatus, $method)
    {
        if ($this->_scopeConfig->getValue('cembrapaycheckoutsettings/cembrapaycheckout_setup/screeningbeforeshow', ScopeInterface::SCOPE_STORE) == '0') {
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
        $isAvaliable =  $this->_scopeConfig->getValue("cembrapaycheckoutsettings/cembrapaycheckout_setup/active", ScopeInterface::SCOPE_STORE);
        if (!$isAvaliable) {
            return [];
        }


        $localeData = (new DataBundle())->get($this->localeResolver->getLocale());
        $monthsData = $localeData['calendar']['gregorian']['monthNames'];
        $daysData = $localeData['calendar']['gregorian']['dayNames'];

        $calendarConfig = [
            'closeText' => __('Done'),
            'prevText' => __('Prev'),
            'nextText' => __('Next'),
            'currentText' => __('Today'),
            'monthNames' => array_values(iterator_to_array($monthsData['format']['wide'])),
            'monthNamesShort' => array_values(iterator_to_array($monthsData['format']['abbreviated'])),
            'dayNames' => array_values(iterator_to_array($daysData['format']['wide'])),
            'dayNamesShort' => array_values(iterator_to_array($daysData['format']['abbreviated'])),
            'dayNamesMin' => array_values(iterator_to_array($daysData['format']['short'])),
        ];

        $quote = $this->_checkoutSession->getQuote();
        if ($quote == null) {
            return [];
        }
        $allowedCembraPayPaymentMethods = DataHelper::$allowedCembraPayPaymentMethods;
        if (empty($allowedCembraPayPaymentMethods)) {
            $allowedCembraPayPaymentMethods = null;
        }
        $methodsAvailableInvoice = Array();
        $availableMethods = $this->dataHelper->getMethodsMapping();

        $cembrapaycheckout_single_invoice_allow = $this->_scopeConfig->getValue("cembrapayinvoicesettings/cembrapaycheckout_single_invoice/cembrapaycheckout_single_invoice_allow", ScopeInterface::SCOPE_STORE);
        if ($this->_scopeConfig->getValue("cembrapayinvoicesettings/cembrapaycheckout_invoice_partial/active", ScopeInterface::SCOPE_STORE)) {
            $methodsAvailableInvoice[] = Array(
                "value" => $availableMethods[DataHelper::$SINGLEINVOICE]["value"],
                "name" => $availableMethods[DataHelper::$SINGLEINVOICE]["name"],
                "checked" => false,
                "allow" => $cembrapaycheckout_single_invoice_allow
            );
        }

        $cembrapaycheckout_invoice_partial_allow = $this->_scopeConfig->getValue("cembrapayinvoicesettings/cembrapaycheckout_invoice_partial/cembrapaycheckout_invoice_partial_allow", ScopeInterface::SCOPE_STORE);
        if ($this->_scopeConfig->getValue("cembrapayinvoicesettings/cembrapaycheckout_single_invoice/active", ScopeInterface::SCOPE_STORE)) {
            $methodsAvailableInvoice[] = Array(
                "value" => $availableMethods[DataHelper::$CEMBRAPAYINVOICE]["value"],
                "name" => $availableMethods[DataHelper::$CEMBRAPAYINVOICE]["name"],
                "checked" => false,
                "allow" => $cembrapaycheckout_invoice_partial_allow
            );
        }
        $defaultInvoicePlan = DataHelper::$CEMBRAPAYINVOICE;

        if (count($methodsAvailableInvoice) > 0) {
            $defaultInvoicePlan = $methodsAvailableInvoice[0]["value"];
        }

        $methodsAvailableInstallment = Array();

        $byjuno_installment_3installment_allow = $this->_scopeConfig->getValue("cembrapayinstallmentsettings/cembrapaycheckout_installment_3installment/cembrapaycheckout_installment_3installment_allow", ScopeInterface::SCOPE_STORE);
        if ($this->_scopeConfig->getValue("cembrapayinstallmentsettings/cembrapaycheckout_installment_3installment/active", ScopeInterface::SCOPE_STORE)) {
            $methodsAvailableInstallment[] = Array(
                "value" => $availableMethods[DataHelper::$INSTALLMENT_3]["value"],
                "name" => $availableMethods[DataHelper::$INSTALLMENT_3]["name"],
                "checked" => false,
                "allow" => $byjuno_installment_3installment_allow
            );
        }

        $byjuno_installment_4installment_allow = $this->_scopeConfig->getValue("cembrapayinstallmentsettings/cembrapaycheckout_installment_4installment/cembrapaycheckout_installment_4installment_allow", ScopeInterface::SCOPE_STORE);
        if ($this->_scopeConfig->getValue("cembrapayinstallmentsettings/cembrapaycheckout_installment_4installment/active", ScopeInterface::SCOPE_STORE)) {
            $methodsAvailableInstallment[] = Array(
                "value" => $availableMethods[DataHelper::$INSTALLMENT_4]["value"],
                "name" => $availableMethods[DataHelper::$INSTALLMENT_4]["name"],
                "checked" => false,
                "allow" => $byjuno_installment_4installment_allow
            );
        }

        $byjuno_installment_6installment_allow = $this->_scopeConfig->getValue("cembrapayinstallmentsettings/cembrapaycheckout_installment_6installment/cembrapaycheckout_installment_6installment_allow", ScopeInterface::SCOPE_STORE);
        if ($this->_scopeConfig->getValue("cembrapayinstallmentsettings/cembrapaycheckout_installment_6installment/active", ScopeInterface::SCOPE_STORE)) {
            $methodsAvailableInstallment[] = Array(
                "value" => $availableMethods[DataHelper::$INSTALLMENT_6]["value"],
                "name" => $availableMethods[DataHelper::$INSTALLMENT_6]["name"],
                "checked" => false,
                "allow" => $byjuno_installment_6installment_allow
            );
        }

        $byjuno_installment_12installment_allow = $this->_scopeConfig->getValue("cembrapayinstallmentsettings/cembrapaycheckout_installment_12installment/cembrapaycheckout_installment_12installment_allow", ScopeInterface::SCOPE_STORE);
        if ($this->_scopeConfig->getValue("cembrapayinstallmentsettings/cembrapaycheckout_installment_12installment/active", ScopeInterface::SCOPE_STORE)) {
            $methodsAvailableInstallment[] = Array(
                "value" => $availableMethods[DataHelper::$INSTALLMENT_12]["value"],
                "name" => $availableMethods[DataHelper::$INSTALLMENT_12]["name"],
                "checked" => false,
                "allow" => $byjuno_installment_12installment_allow
            );
        }

        $byjuno_installment_24installment_allow = $this->_scopeConfig->getValue("cembrapayinstallmentsettings/cembrapaycheckout_installment_24installment/cembrapaycheckout_installment_24installment_allow", ScopeInterface::SCOPE_STORE);
        if ($this->_scopeConfig->getValue("cembrapayinstallmentsettings/cembrapaycheckout_installment_24installment/active", ScopeInterface::SCOPE_STORE)) {
            $methodsAvailableInstallment[] = Array(
                "value" => $availableMethods[DataHelper::$INSTALLMENT_24]["value"],
                "name" => $availableMethods[DataHelper::$INSTALLMENT_24]["name"],
                "checked" => false,
                "allow" => $byjuno_installment_24installment_allow
            );
        }

        $byjuno_installment_36installment_allow = $this->_scopeConfig->getValue("cembrapayinstallmentsettings/cembrapaycheckout_installment_36installment/cembrapaycheckout_installment_36installment_allow", ScopeInterface::SCOPE_STORE);
        if ($this->_scopeConfig->getValue("cembrapayinstallmentsettings/cembrapaycheckout_installment_36installment/active", ScopeInterface::SCOPE_STORE)) {
            $methodsAvailableInstallment[] = Array(
                "value" => $availableMethods[DataHelper::$INSTALLMENT_36]["value"],
                "name" => $availableMethods[DataHelper::$INSTALLMENT_36]["name"],
                "checked" => false,
                "allow" => $byjuno_installment_36installment_allow
            );
        }

        $byjuno_installment_48installment_allow = $this->_scopeConfig->getValue("cembrapayinstallmentsettings/cembrapaycheckout_installment_48installment/cembrapaycheckout_installment_48installment_allow", ScopeInterface::SCOPE_STORE);
        if ($this->_scopeConfig->getValue("cembrapayinstallmentsettings/cembrapaycheckout_installment_48installment/active", ScopeInterface::SCOPE_STORE)) {
            $methodsAvailableInstallment[] = Array(
                "value" => $availableMethods[DataHelper::$INSTALLMENT_48]["value"],
                "name" => $availableMethods[DataHelper::$INSTALLMENT_48]["name"],
                "checked" => false,
                "allow" => $byjuno_installment_48installment_allow
            );
        }

        $defaultInstallmentPlan = DataHelper::$INSTALLMENT_3;
        if (count($methodsAvailableInstallment) > 0) {
            $defaultInstallmentPlan = $methodsAvailableInstallment[0]["value"];
        }

        $invoiceDelivery[] = Array(
            "value" => "email",
            "text" => __($this->_scopeConfig->getValue("cembrapayinvoicesettings/cembrapaycheckout_invoice_localization/cembrapaycheckout_invoice_email_text",
                    ScopeInterface::SCOPE_STORE)) . ": "
        );

        $invoiceDelivery[] = Array(
            "value" => "postal",
            "text" => __($this->_scopeConfig->getValue("cembrapayinvoicesettings/cembrapaycheckout_invoice_localization/cembrapaycheckout_invoice_postal_text",
                    ScopeInterface::SCOPE_STORE)) . ": "
        );

        $installmentDelivery[] = Array(
            "value" => "email",
            "text" => __($this->_scopeConfig->getValue("cembrapayinstallmentsettings/cembrapaycheckout_installment_localization/cembrapaycheckout_installment_email_text",
                ScopeInterface::SCOPE_STORE)) . ": "
        );

        $installmentDelivery[] = Array(
            "value" => "postal",
            "text" => __($this->_scopeConfig->getValue("cembrapayinstallmentsettings/cembrapaycheckout_installment_localization/cembrapaycheckout_installment_postal_text",
                    ScopeInterface::SCOPE_STORE)) . ": "
        );

        $gender_enable = false;
        if ($this->_scopeConfig->getValue("cembrapaycheckoutsettings/cembrapaycheckout_setup/gender_enable",
                ScopeInterface::SCOPE_STORE) == 1) {
            $gender_enable = true;
        }
        $birthday_enable = false;
        if ($this->_scopeConfig->getValue("cembrapaycheckoutsettings/cembrapaycheckout_setup/birthday_enable",
                ScopeInterface::SCOPE_STORE) == 1) {
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
        if ($this->_scopeConfig->getValue("cembrapaycheckoutsettings/cembrapaycheckout_setup/b2b_uid",
                ScopeInterface::SCOPE_STORE) == 1) {
            $b2b_uid = true;
        }
        $gender_prefix = trim($this->_scopeConfig->getValue("cembrapaycheckoutsettings/cembrapaycheckout_setup/gender_prefix", ScopeInterface::SCOPE_STORE));
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
        if ($this->_scopeConfig->getValue("cembrapaycheckoutsettings/cembrapaycheckout_setup/cembrapaycheckout_invoice_paper",
                ScopeInterface::SCOPE_STORE) == 1) {
            $paperInvoice = true;
        }
        $paymentMode = "checkout";
        if ($this->_scopeConfig->getValue("cembrapaycheckoutsettings/cembrapaycheckout_setup/payment_mode", ScopeInterface::SCOPE_STORE) == '0') {
            $paymentMode = "authorization";
        }
        return [
            'payment' => [
                self::CODE_INVOICE => [
                    'redirectUrl' => $this->methodInstanceInvoice->getConfigData('order_place_redirect_url'),
                    'methods' => $methodsAvailableInvoice,
                    'delivery' => $invoiceDelivery,
                    'default_delivery' => 'email',
                    'default_agreetc' => false,
                    'paper_invoice' => $paperInvoice,
                    'logo' => $this->getCembraPayLogoInvoice(),
                    'default_customgender' => $dafualtGender,
                    'custom_genders' => $genders,
                    'gender_enable' => $gender_enable,
                    'birthday_enable' => $birthday_enable,
                    'b2b_uid' => $b2b_uid,
                    'calendar_config' => $calendarConfig,
                    'payment_mode' => $paymentMode,
                    'tc_link' => $this->_scopeConfig->getValue("cembrapayinvoicesettings/cembrapaycheckout_invoice_setup/tc_invoice", ScopeInterface::SCOPE_STORE)
                ],
                self::CODE_INSTALLMENT => [
                    'redirectUrl' => $this->methodInstanceInvoice->getConfigData('order_place_redirect_url'),
                    'methods' => $methodsAvailableInstallment,
                    'delivery' => $invoiceDelivery,
                    'default_delivery' => 'email',
                    'default_agreetc' => false,
                    'paper_invoice' => $paperInvoice,
                    'logo' => $this->getCembraPayLogoInstallment(),
                    'default_customgender' => $dafualtGender,
                    'custom_genders' => $genders,
                    'gender_enable' => $gender_enable,
                    'birthday_enable' => $birthday_enable,
                    'b2b_uid' => $b2b_uid,
                    'calendar_config' => $calendarConfig,
                    'payment_mode' => $paymentMode,
                    'tc_link' => $this->_scopeConfig->getValue("cembrapayinstallmentsettings/cembrapaycheckout_installment_setup/tc_installment", ScopeInterface::SCOPE_STORE)
                ]
            ]
        ];
    }
}
