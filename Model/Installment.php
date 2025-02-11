<?php
/**
 * Created by PhpStorm.
 * User: Igor
 * Date: 29.10.2016
 * Time: 15:44
 */

namespace Byjuno\ByjunoCore\Model;

use Byjuno\ByjunoCore\Controller\Checkout\Startpayment;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\DataObject;
use Magento\Payment\Observer\AbstractDataAssignObserver;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Payment\Model\MethodInterface;
use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Payment\Gateway\Command\CommandManagerInterface;
use Magento\Payment\Gateway\Command\CommandPoolInterface;
use Magento\Payment\Gateway\Data\PaymentDataObjectFactory;
use Magento\Payment\Gateway\Config\ValueHandlerPoolInterface;
use Magento\Payment\Gateway\Validator\ValidatorPoolInterface;
use Byjuno\ByjunoCore\Helper\DataHelper;
use Magento\Quote\Model\Quote;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Payment;
use Magento\Store\Model\ScopeInterface;


/**
 * Pay In Store payment method model
 */
class Installment extends \Byjuno\ByjunoCore\Model\CembraPaypayment
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
    ) {

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
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $this->_scopeConfig = $objectManager->get('Magento\Framework\App\Config\ScopeConfigInterface');

        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $state =  $objectManager->get('Magento\Framework\App\State');
        if ($state->getAreaCode() == "adminhtml") {
            $this->_checkoutSession = $objectManager->get('Magento\Backend\Model\Session\Quote');
        } else {
            $this->_checkoutSession = $objectManager->get('Magento\Checkout\Model\Session');
        }
        $this->_state = $state;
        $this->_eavConfig = $objectManager->get('\Magento\Eav\Model\Config');
        $this->_dataHelper =  $objectManager->get('\Byjuno\ByjunoCore\Helper\DataHelper');
        $this->_sequenceManager =  $objectManager->get('\Magento\SalesSequence\Model\Manager');
        $this->_executed = false;
    }

    public function getInfoBlockType()
    {
        return \Byjuno\ByjunoCore\Block\Adminhtml\Info\CembraPayInstallment::class;
    }

    public function assignData(\Magento\Framework\DataObject $data)
    {
        $dataKey = $data->getDataByKey('additional_data');
        $payment = $this->getInfoInstance();
        $payment->setAdditionalInformation('payment_plan', null);
        $payment->setAdditionalInformation('payment_send', null);
        $payment->setAdditionalInformation('payment_send_to', null);
        $payment->setAdditionalInformation('auth_executed_ok', null);
        $payment->setAdditionalInformation('webshop_profile_id', null);
        if (isset($dataKey['installment_payment_plan'])) {
            $payment->setAdditionalInformation('payment_plan', $dataKey['installment_payment_plan']);
        }
        if (isset($dataKey['agree_tc'])) {
            $payment->setAdditionalInformation('agree_tc', $dataKey['agree_tc']);
        }
        $paperInvoice = false;
        if ($this->_scopeConfig->getValue("cembrapaycheckoutsettings/cembrapaycheckout_setup/cembrapaycheckout_invoice_paper",
                ScopeInterface::SCOPE_STORE) == 1) {
            $paperInvoice = true;
        }
        if (isset($dataKey['installment_send']) && $paperInvoice) {
            $sentTo = '';
            if ($dataKey['installment_send'] == 'postal') {
                $sentTo = (String)$this->_checkoutSession->getQuote()->getBillingAddress()->getStreetFull().', '.
                    (String)$this->_checkoutSession->getQuote()->getBillingAddress()->getCity().', '.
                    (String)$this->_checkoutSession->getQuote()->getBillingAddress()->getPostcode();
            } else if ($dataKey['installment_send'] == 'email') {
                $sentTo = (String)$this->_checkoutSession->getQuote()->getBillingAddress()->getEmail();
            }
            $payment->setAdditionalInformation('payment_send', $dataKey['installment_send']);
            $payment->setAdditionalInformation('payment_send_to', $sentTo);
        } else {
            $payment->setAdditionalInformation('payment_send', 'email');
            $payment->setAdditionalInformation('payment_send_to', (String)$this->_checkoutSession->getQuote()->getBillingAddress()->getEmail());
        }
        if (isset($dataKey['installment_customer_gender'])) {
            $payment->setAdditionalInformation('customer_gender', $dataKey['installment_customer_gender']);
        } else {
            $payment->setAdditionalInformation('customer_gender', '');
        }
        if (isset($dataKey['pref_lang'])) {
            $payment->setAdditionalInformation('pref_lang', $dataKey['pref_lang']);
        } else {
            $payment->setAdditionalInformation('pref_lang', '');
        }
        if (isset($dataKey['installment_customer_dob'])) {
            $payment->setAdditionalInformation('customer_dob', $dataKey['installment_customer_dob']);
        } else {
            $payment->setAdditionalInformation('customer_dob', '');
        }
        if (isset($dataKey['installment_customer_b2b_uid'])) {
            $payment->setAdditionalInformation('customer_b2b_uid', $dataKey['installment_customer_b2b_uid']);
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
        )
        {
            $isCompany = true;
        }
        $this->validateCustomCembraPayFields($payment, $isCompany);
        if ($payment->getAdditionalInformation('payment_plan') == null ||
            ($payment->getAdditionalInformation('payment_plan') != DataHelper::$INSTALLMENT_3 &&
                $payment->getAdditionalInformation('payment_plan') != DataHelper::$INSTALLMENT_4 &&
                $payment->getAdditionalInformation('payment_plan') != DataHelper::$INSTALLMENT_6 &&
                $payment->getAdditionalInformation('payment_plan') != DataHelper::$INSTALLMENT_12 &&
                $payment->getAdditionalInformation('payment_plan') != DataHelper::$INSTALLMENT_24 &&
                $payment->getAdditionalInformation('payment_plan') != DataHelper::$INSTALLMENT_36 &&
                $payment->getAdditionalInformation('payment_plan') != DataHelper::$INSTALLMENT_48)) {
            throw new LocalizedException(
                __("Invalid payment plan")
            );
        }

        if ($payment->getAdditionalInformation('payment_send') == null ||
            ($payment->getAdditionalInformation('payment_send') != 'email' &&
                $payment->getAdditionalInformation('payment_send') != 'postal')) {
            throw new LocalizedException(
                __("Please select installment send way")
            );
        }

        if ($payment->getAdditionalInformation('payment_send_to') == null) {
            throw new LocalizedException(
                __("Invalid installment send way")
            );
        }
        return $this;
    }

    public function getConfigData($field, $storeId = null)
    {
        if ($field == 'order_place_redirect_url') {
            if ($this->_scopeConfig->getValue("cembrapaycheckoutsettings/cembrapaycheckout_setup/payment_mode", ScopeInterface::SCOPE_STORE) == '0') {
                // Checkout page active
                return 'cembrapaycheckoutcore/checkout/startpayment';
            } else {
                return 'cembrapaycheckoutcore/checkout/startcheckout';
            }
        }
        return parent::getConfigData($field, $storeId);
    }

    public function isAvailable(CartInterface $quote = null)
    {
        $isAvaliable =  $this->_scopeConfig->getValue("cembrapaycheckoutsettings/cembrapaycheckout_setup/active", ScopeInterface::SCOPE_STORE);
        if (!$isAvaliable) {
            return false;
        }

        $cembrapaycheckout_installment_3installment_allow = $this->_scopeConfig->getValue("cembrapayinstallmentsettings/cembrapaycheckout_installment_3installment/cembrapaycheckout_installment_3installment_allow", ScopeInterface::SCOPE_STORE);
        $cembrapaycheckout_installment_4installment_allow = $this->_scopeConfig->getValue("cembrapayinstallmentsettings/cembrapaycheckout_installment_3installment/cembrapaycheckout_installment_4installment_allow", ScopeInterface::SCOPE_STORE);
        $cembrapaycheckout_installment_6installment_allow = $this->_scopeConfig->getValue("cembrapayinstallmentsettings/cembrapaycheckout_installment_3installment/cembrapaycheckout_installment_6installment_allow", ScopeInterface::SCOPE_STORE);
        $cembrapaycheckout_installment_12installment_allow = $this->_scopeConfig->getValue("cembrapayinstallmentsettings/cembrapaycheckout_installment_12installment/cembrapaycheckout_installment_12installment_allow", ScopeInterface::SCOPE_STORE);
        $cembrapaycheckout_installment_24installment_allow = $this->_scopeConfig->getValue("cembrapayinstallmentsettings/cembrapaycheckout_installment_24installment/cembrapaycheckout_installment_24installment_allow", ScopeInterface::SCOPE_STORE);
        $cembrapaycheckout_installment_36installment_allow = $this->_scopeConfig->getValue("cembrapayinstallmentsettings/cembrapaycheckout_installment_24installment/cembrapaycheckout_installment_36installment_allow", ScopeInterface::SCOPE_STORE);
        $cembrapaycheckout_installment_48installment_allow = $this->_scopeConfig->getValue("cembrapayinstallmentsettings/cembrapaycheckout_installment_24installment/cembrapaycheckout_installment_48installment_allow", ScopeInterface::SCOPE_STORE);

        $isCompany = false;
        if (!empty($this->_checkoutSession->getQuote()->getBillingAddress()->getCompany()) &&
            $this->_scopeConfig->getValue("cembrapaycheckoutsettings/cembrapaycheckout_setup/businesstobusiness", ScopeInterface::SCOPE_STORE) == '1'
        )
        {
            $isCompany = true;
        }

        $methodsAvailable =
            ($this->_scopeConfig->getValue("cembrapayinstallmentsettings/cembrapaycheckout_installment_3installment/active", ScopeInterface::SCOPE_STORE)
            && ($cembrapaycheckout_installment_3installment_allow == '0' || ($cembrapaycheckout_installment_3installment_allow == '1' && !$isCompany) || ($cembrapaycheckout_installment_3installment_allow == '2' && $isCompany)))
            ||
            ($this->_scopeConfig->getValue("cembrapayinstallmentsettings/cembrapaycheckout_installment_4installment/active", ScopeInterface::SCOPE_STORE)
            && ($cembrapaycheckout_installment_4installment_allow == '0' || ($cembrapaycheckout_installment_4installment_allow == '1' && !$isCompany) || ($cembrapaycheckout_installment_4installment_allow == '2' && $isCompany)))
            ||
            ($this->_scopeConfig->getValue("cembrapayinstallmentsettings/cembrapaycheckout_installment_6installment/active", ScopeInterface::SCOPE_STORE)
            && ($cembrapaycheckout_installment_6installment_allow == '0' || ($cembrapaycheckout_installment_6installment_allow == '1' && !$isCompany) || ($cembrapaycheckout_installment_6installment_allow == '2' && $isCompany)))
            ||
            ($this->_scopeConfig->getValue("cembrapayinstallmentsettings/cembrapaycheckout_installment_12installment/active", ScopeInterface::SCOPE_STORE)
            && ($cembrapaycheckout_installment_12installment_allow == '0' || ($cembrapaycheckout_installment_12installment_allow == '1' && !$isCompany) || ($cembrapaycheckout_installment_12installment_allow == '2' && $isCompany)))
            ||
            ($this->_scopeConfig->getValue("cembrapayinstallmentsettings/cembrapaycheckout_installment_24installment/active", ScopeInterface::SCOPE_STORE)
            && ($cembrapaycheckout_installment_24installment_allow == '0' || ($cembrapaycheckout_installment_24installment_allow == '1' && !$isCompany) || ($cembrapaycheckout_installment_24installment_allow == '2' && $isCompany)))
            ||
            ($this->_scopeConfig->getValue("cembrapayinstallmentsettings/cembrapaycheckout_installment_36installment/active", ScopeInterface::SCOPE_STORE)
            && ($cembrapaycheckout_installment_36installment_allow == '0' || ($cembrapaycheckout_installment_36installment_allow == '1' && !$isCompany) || ($cembrapaycheckout_installment_36installment_allow == '2' && $isCompany)))
            ||
            ($this->_scopeConfig->getValue("cembrapayinstallmentsettings/cembrapaycheckout_installment_48installment/active", ScopeInterface::SCOPE_STORE)
                && ($cembrapaycheckout_installment_48installment_allow == '0' || ($cembrapaycheckout_installment_48installment_allow == '1' && !$isCompany) || ($cembrapaycheckout_installment_48installment_allow == '2' && $isCompany)));

        if (!$isAvaliable || !$methodsAvailable) {
            return false;
        }
        $creditStatus = false;
        if ($quote != null) {
            /* @var $q Quote */
            $q = $quote;
            $creditStatus = $this->_dataHelper->GetCreditStatus($q, $this->_dataHelper->getEnabledMethods());
        }
        return $isAvaliable && $methodsAvailable && $creditStatus && parent::isAvailable($quote);
    }

    public function getTitle()
    {
        return $this->_scopeConfig->getValue("cembrapayinstallmentsettings/cembrapaycheckout_installment_setup/title_installment", ScopeInterface::SCOPE_STORE);
    }

    public function order(\Magento\Payment\Model\InfoInterface $payment, $amount)
    {
        return $this;
    }

    /* @return Installment
     * @throws LocalizedException
     * @var $payment \Magento\Payment\Model\InfoInterface
     */
    public function authorize(\Magento\Payment\Model\InfoInterface $payment, $amount)
    {
        /* @var $order Order */
        /* @var $p Payment*/

        $objectManager = ObjectManager::getInstance();
        $state = $objectManager->get('Magento\Framework\App\State');

        if ($this->_scopeConfig->getValue("cembrapaycheckoutsettings/cembrapaycheckout_setup/payment_mode", ScopeInterface::SCOPE_STORE) == '0' || $state->getAreaCode() == "adminhtml") {

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
