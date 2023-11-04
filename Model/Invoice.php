<?php

/**
 * Created by PhpStorm.
 * User: Igor
 * Date: 29.10.2016
 * Time: 15:44
 */

namespace CembraPayCheckout\CembraPayCheckoutCore\Model;

use CembraPayCheckout\CembraPayCheckoutCore\Block\Adminhtml\Info\CembraPayInvoice;
use CembraPayCheckout\CembraPayCheckoutCore\Controller\Checkout\Startpayment;
use CembraPayCheckout\CembraPayCheckoutCore\Helper\DataHelper;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\DataObject;
use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Payment\Gateway\Command\CommandManagerInterface;
use Magento\Payment\Gateway\Command\CommandPoolInterface;
use Magento\Payment\Gateway\Config\ValueHandlerPoolInterface;
use Magento\Payment\Gateway\Data\PaymentDataObjectFactory;
use Magento\Payment\Gateway\Validator\ValidatorPoolInterface;
use Magento\Payment\Model\InfoInterface;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Quote\Model\Quote;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Payment;
use Magento\Store\Model\ScopeInterface;


/**
 * Pay In Store payment method model
 */
class Invoice extends CembraPaypayment
{

    protected $_executed;
    protected $_dataHelper;

    public function setId($id)
    {
        //Magento bug https://github.com/magento/magento2/issues/5413
    }

    /**
     * @param ManagerInterface $eventManager
     * @param ValueHandlerPoolInterface $valueHandlerPool
     * @param PaymentDataObjectFactory $paymentDataObjectFactory
     * @param string $code
     * @param string $formBlockType
     * @param string $infoBlockType
     * @param CommandPoolInterface $commandPool
     * @param ValidatorPoolInterface $validatorPool
     * @param CommandManagerInterface $commandExecutor
     */
    public function __construct(
        ManagerInterface $eventManager,
        ValueHandlerPoolInterface $valueHandlerPool,
        PaymentDataObjectFactory $paymentDataObjectFactory,
        $code,
        $formBlockType,
        $infoBlockType,
        CommandPoolInterface $commandPool = null,
        ValidatorPoolInterface $validatorPool = null,
        CommandManagerInterface $commandExecutor = null
    )
    {

        parent::__construct(
            $eventManager,
            $valueHandlerPool,
            $paymentDataObjectFactory,
            $code,
            $formBlockType,
            $infoBlockType,
            $commandPool,
            $validatorPool,
            $commandExecutor
        );
        $this->eventManager = $eventManager;
        $objectManager = ObjectManager::getInstance();
        $this->_scopeConfig = $objectManager->get('Magento\Framework\App\Config\ScopeConfigInterface');

        $objectManager = ObjectManager::getInstance();
        $state = $objectManager->get('Magento\Framework\App\State');
        if ($state->getAreaCode() == "adminhtml") {
            $this->_checkoutSession = $objectManager->get('Magento\Backend\Model\Session\Quote');
        } else {
            $this->_checkoutSession = $objectManager->get('Magento\Checkout\Model\Session');
        }
        $this->_state = $state;
        $this->_eavConfig = $objectManager->get('\Magento\Eav\Model\Config');
        $this->_dataHelper = $objectManager->get('\CembraPayCheckout\CembraPayCheckoutCore\Helper\DataHelper');
        $this->_executed = false;
    }

    public function getInfoBlockType()
    {
        return CembraPayInvoice::class;
    }

    public function getConfigData($field, $storeId = null)
    {
        if ($field == 'order_place_redirect_url') {
            if ($this->_scopeConfig->getValue("cembrapaycheckoutsettings/cembrapaycheckout_setup/payment_mode", ScopeInterface::SCOPE_STORE) == '0') {
                // Checkout page active
                return 'cembrapaycheckoutcore/checkout/startpayment';
            } else {
                var_dump("XXX");
                exit();
                return 'cembrapaycheckoutcore/checkout/startcheckout';
            }
        }
        return parent::getConfigData($field, $storeId);
    }

    public function isAvailable(CartInterface $quote = null)
    {
        $isAvaliable = $this->_scopeConfig->getValue("cembrapaycheckoutsettings/cembrapaycheckout_setup/active", ScopeInterface::SCOPE_STORE);
        if (!$isAvaliable) {
            return false;
        }
        $cembrapaycheckout_invoice_partial_allow = $this->_scopeConfig->getValue("cembrapayinvoicesettings/cembrapaycheckout_invoice_partial/cembrapaycheckout_invoice_partial_allow", ScopeInterface::SCOPE_STORE);
        $cembrapaycheckout_single_invoice_allow = $this->_scopeConfig->getValue("cembrapayinvoicesettings/cembrapaycheckout_single_invoice/cembrapaycheckout_single_invoice_allow", ScopeInterface::SCOPE_STORE);

        $methodsAvailable =
            ($this->_scopeConfig->getValue("cembrapayinvoicesettings/cembrapaycheckout_invoice_partial/active", ScopeInterface::SCOPE_STORE)
                && ($cembrapaycheckout_invoice_partial_allow == '0' || $cembrapaycheckout_invoice_partial_allow == '1'))
            ||
            ($this->_scopeConfig->getValue("cembrapayinvoicesettings/cembrapaycheckout_single_invoice/active", ScopeInterface::SCOPE_STORE)
                && ($cembrapaycheckout_single_invoice_allow == '0' || $cembrapaycheckout_single_invoice_allow == '1'));

        if (!$isAvaliable || !$methodsAvailable) {
            return false;
        }
        $creditStatus = false;
        if ($quote != null) {
            /* @var $q Quote */
            $q = $quote;
            $creditStatus = $this->_dataHelper->GetCreditStatus($q, $this->_dataHelper->getInvoiceEnabledMethods());
        }
        return $isAvaliable && $methodsAvailable && $creditStatus && parent::isAvailable($quote);
    }

    public function getTitle()
    {
        return $this->_scopeConfig->getValue("cembrapayinvoicesettings/cembrapaycheckout_invoice_setup/title_invoice", ScopeInterface::SCOPE_STORE);
    }

    public function assignData(DataObject $data)
    {
        $dataKey = $data->getDataByKey('additional_data');
        $payment = $this->getInfoInstance();
        $payment->setAdditionalInformation('payment_plan', null);
        $payment->setAdditionalInformation('payment_send', null);
        $payment->setAdditionalInformation('payment_send_to', null);
        $payment->setAdditionalInformation('auth_executed_ok', null);
        $payment->setAdditionalInformation('webshop_profile_id', null);
        if (isset($dataKey['invoice_payment_plan'])) {
            $payment->setAdditionalInformation('payment_plan', $dataKey['invoice_payment_plan']);
        }
        $paperInvoice = false;
        if ($this->_scopeConfig->getValue("cembrapaycheckoutsettings/cembrapaycheckout_setup/cembrapaycheckout_invoice_paper",
                ScopeInterface::SCOPE_STORE) == 1) {
            $paperInvoice = true;
        }
        if (isset($dataKey['invoice_send']) && $paperInvoice) {
            $sentTo = '';
            if ($dataKey['invoice_send'] == 'postal') {
                $sentTo = (String)$this->_checkoutSession->getQuote()->getBillingAddress()->getStreetFull() . ', ' .
                    (String)$this->_checkoutSession->getQuote()->getBillingAddress()->getCity() . ', ' .
                    (String)$this->_checkoutSession->getQuote()->getBillingAddress()->getPostcode();
            } else if ($dataKey['invoice_send'] == 'email') {
                $sentTo = (String)$this->_checkoutSession->getQuote()->getBillingAddress()->getEmail();
            }
            $payment->setAdditionalInformation('payment_send', $dataKey['invoice_send']);
            $payment->setAdditionalInformation('payment_send_to', $sentTo);
        } else {
            $payment->setAdditionalInformation('payment_send', 'email');
            $payment->setAdditionalInformation('payment_send_to', (String)$this->_checkoutSession->getQuote()->getBillingAddress()->getEmail());
        }
        if (isset($dataKey['invoice_customer_gender'])) {
            $payment->setAdditionalInformation('customer_gender', $dataKey['invoice_customer_gender']);
        } else {
            $payment->setAdditionalInformation('customer_gender', '');
        }
        if (isset($dataKey['pref_lang'])) {
            $payment->setAdditionalInformation('pref_lang', $dataKey['pref_lang']);
        } else {
            $payment->setAdditionalInformation('pref_lang', '');
        }
        if (isset($dataKey['invoice_customer_dob'])) {
            $payment->setAdditionalInformation('customer_dob', $dataKey['invoice_customer_dob']);
        } else {
            $payment->setAdditionalInformation('customer_dob', '');
        }
        if (isset($dataKey['invoice_customer_b2b_uid'])) {
            $payment->setAdditionalInformation('customer_b2b_uid', $dataKey['invoice_customer_b2b_uid']);
        } else {
            $payment->setAdditionalInformation('customer_b2b_uid', '');
        }
        $payment->setAdditionalInformation('auth_executed_ok', 'false');
        $payment->setAdditionalInformation("webshop_profile_id", $this->getStore());
        return $this;
    }

    public function validate()
    {
        $payment = $this->getInfoInstance();
        $isCompany = false;
        if (!empty($this->_checkoutSession->getQuote()->getBillingAddress()->getCompany()) &&
            $this->_scopeConfig->getValue("cembrapaycheckoutsettings/cembrapaycheckout_setup/businesstobusiness", ScopeInterface::SCOPE_STORE) == '1'
        ) {
            $isCompany = true;
        }
        $this->validateCustomCembraPayFields($payment, $isCompany);
        if ($payment->getAdditionalInformation('payment_plan') == null ||
            ($payment->getAdditionalInformation('payment_plan') != DataHelper::$SINGLEINVOICE &&
                $payment->getAdditionalInformation('payment_plan') != DataHelper::$CEMBRAPAYINVOICE)) {
            throw new LocalizedException(
                __("Invalid payment plan")
            );
        }

        if ($payment->getAdditionalInformation('payment_send') == null ||
            ($payment->getAdditionalInformation('payment_send') != 'email' &&
                $payment->getAdditionalInformation('payment_send') != 'postal')) {
            throw new LocalizedException(
                __("Please select invoice send way")
            );
        }

        if ($payment->getAdditionalInformation('payment_send_to') == null) {
            throw new LocalizedException(
                __("Invalid invoice send way")
            );
        }
        return $this;
    }

    public function order(InfoInterface $payment, $amount)
    {
        return $this;
    }

    /* @return Invoice
     * @throws LocalizedException
     * @var $payment \Magento\Payment\Model\
     * @var $amount float
     */
    public function authorize(InfoInterface $payment, $amount)
    {
        /* @var $order Order */
        /* @var $p Payment*/
        if ($this->_scopeConfig->getValue("cembrapaycheckoutsettings/cembrapaycheckout_setup/payment_mode", ScopeInterface::SCOPE_STORE) == '0') {

            $p = $payment;
            $order = $p->getOrder();
            $result = Startpayment::executeAuthorizeRequestOrder($order, $this->_dataHelper);
            if ($result == null) {
                return $this;
            } else {
                throw new LocalizedException(
                    __($result)
                );
            }
        } else {
            return $this;
        }
    }

}
