<?php
/**
 * Created by PhpStorm.
 * User: Igor
 * Date: 29.10.2016
 * Time: 15:44
 */

namespace CembraPayCheckout\CembraPayCheckoutCore\Model;

use CembraPayCheckout\CembraPayCheckoutCore\Controller\Checkout\Startpayment;
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
use CembraPayCheckout\CembraPayCheckoutCore\Helper\DataHelper;


/**
 * Pay In Store payment method model
 */
class Installment extends \CembraPayCheckout\CembraPayCheckoutCore\Model\CembraPaypayment
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
        $this->_dataHelper =  $objectManager->get('\CembraPayCheckout\CembraPayCheckoutCore\Helper\DataHelper');
        $this->_executed = false;
    }

    public function getInfoBlockType()
    {
        return \CembraPayCheckout\CembraPayCheckoutCore\Block\Adminhtml\Info\CembraPayInstallment::class;
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
        $paperInvoice = false;
        if ($this->_scopeConfig->getValue("cembrapaycheckoutsettings/cembrapaycheckout_setup/cembrapaycheckout_invoice_paper",
                \Magento\Store\Model\ScopeInterface::SCOPE_STORE) == 1) {
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
            $this->_scopeConfig->getValue("cembrapaycheckoutsettings/cembrapaycheckout_setup/businesstobusiness", \Magento\Store\Model\ScopeInterface::SCOPE_STORE) == '1'
        )
        {
            $isCompany = true;
        }
        $this->validateCustomCembraPayFields($payment, $isCompany);
        if ($payment->getAdditionalInformation('payment_plan') == null ||
            ($payment->getAdditionalInformation('payment_plan') != 'installment_3installment_enable' &&
                $payment->getAdditionalInformation('payment_plan') != 'installment_10installment_enable' &&
                $payment->getAdditionalInformation('payment_plan') != 'installment_12installment_enable' &&
                $payment->getAdditionalInformation('payment_plan') != 'installment_24installment_enable' &&
                $payment->getAdditionalInformation('payment_plan') != 'installment_4x12installment_enable' &&
                $payment->getAdditionalInformation('payment_plan') != 'installment_4x10installment_enable')) {
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
            return 'cembrapaycheckoutcore/checkout/startpayment';
        }
        return parent::getConfigData($field, $storeId);
    }

    public function isAvailable(CartInterface $quote = null)
    {
        $isAvaliable =  $this->_scopeConfig->getValue("cembrapaycheckoutsettings/cembrapaycheckout_setup/active", \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
        if (!$isAvaliable) {
            return false;
        }
        $isCompany = false;
        if (!empty($this->_checkoutSession->getQuote()->getBillingAddress()->getCompany()) &&
            $this->_scopeConfig->getValue("cembrapaycheckoutsettings/cembrapaycheckout_setup/businesstobusiness", \Magento\Store\Model\ScopeInterface::SCOPE_STORE) == '1'
        )
        {
            $isCompany = true;
        }
        $cembrapaycheckout_installment_3installment_allow = $this->_scopeConfig->getValue("cembrapayinstallmentsettings/cembrapaycheckout_installment_3installment/cembrapaycheckout_installment_3installment_allow", \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
        $cembrapaycheckout_installment_10installment_allow = $this->_scopeConfig->getValue("cembrapayinstallmentsettings/cembrapaycheckout_installment_10installment/cembrapaycheckout_installment_10installment_allow", \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
        $cembrapaycheckout_installment_12installment_allow = $this->_scopeConfig->getValue("cembrapayinstallmentsettings/cembrapaycheckout_installment_12installment/cembrapaycheckout_installment_12installment_allow", \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
        $cembrapaycheckout_installment_24installment_allow = $this->_scopeConfig->getValue("cembrapayinstallmentsettings/cembrapaycheckout_installment_24installment/cembrapaycheckout_installment_24installment_allow", \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
        $cembrapaycheckout_installment_4x12installment_allow = $this->_scopeConfig->getValue("cembrapayinstallmentsettings/cembrapaycheckout_installment_4x12installment/cembrapaycheckout_installment_4x12installment_allow", \Magento\Store\Model\ScopeInterface::SCOPE_STORE);

        $methodsAvailable =
            ($this->_scopeConfig->getValue("cembrapayinstallmentsettings/cembrapaycheckout_installment_3installment/active", \Magento\Store\Model\ScopeInterface::SCOPE_STORE)
            && ($cembrapaycheckout_installment_3installment_allow == '0' || ($cembrapaycheckout_installment_3installment_allow == '1' && !$isCompany) || ($cembrapaycheckout_installment_3installment_allow == '2' && $isCompany)))
            ||
            ($this->_scopeConfig->getValue("cembrapayinstallmentsettings/cembrapaycheckout_installment_10installment/active", \Magento\Store\Model\ScopeInterface::SCOPE_STORE)
            && ($cembrapaycheckout_installment_10installment_allow == '0' || ($cembrapaycheckout_installment_10installment_allow == '1' && !$isCompany) || ($cembrapaycheckout_installment_10installment_allow == '2' && $isCompany)))
            ||
            ($this->_scopeConfig->getValue("cembrapayinstallmentsettings/cembrapaycheckout_installment_12installment/active", \Magento\Store\Model\ScopeInterface::SCOPE_STORE)
            && ($cembrapaycheckout_installment_12installment_allow == '0' || ($cembrapaycheckout_installment_12installment_allow == '1' && !$isCompany) || ($cembrapaycheckout_installment_12installment_allow == '2' && $isCompany)))
            ||
            ($this->_scopeConfig->getValue("cembrapayinstallmentsettings/cembrapaycheckout_installment_24installment/active", \Magento\Store\Model\ScopeInterface::SCOPE_STORE)
            && ($cembrapaycheckout_installment_24installment_allow == '0' || ($cembrapaycheckout_installment_24installment_allow == '1' && !$isCompany) || ($cembrapaycheckout_installment_24installment_allow == '2' && $isCompany)))
            ||
            ($this->_scopeConfig->getValue("cembrapayinstallmentsettings/cembrapaycheckout_installment_4x12installment/active", \Magento\Store\Model\ScopeInterface::SCOPE_STORE)
            && ($cembrapaycheckout_installment_4x12installment_allow == '0' || ($cembrapaycheckout_installment_4x12installment_allow == '1' && !$isCompany) || ($cembrapaycheckout_installment_4x12installment_allow == '2' && $isCompany)));

        if (!$isAvaliable || !$methodsAvailable) {
            return false;
        }
        $creditStatus = false;
        if ($quote != null) {
            $creditStatus = $this->_dataHelper->GetCreditStatus($quote, Array());
        }
        return $isAvaliable && $methodsAvailable && $creditStatus && parent::isAvailable($quote);
    }

    public function getTitle()
    {
        return $this->_scopeConfig->getValue("cembrapayinstallmentsettings/cembrapaycheckout_installment_setup/title_installment", \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
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
       // if ($this->_scopeConfig->getValue("cembrapaycheckoutsettings/cembrapaycheckout_setup/singlerequest", \Magento\Store\Model\ScopeInterface::SCOPE_STORE) == '1') {
            /* @var $order \Magento\Sales\Model\Order */
            $order = $payment->getOrder();
            $result = Startpayment::executeS3Order($order, $this->_dataHelper);
            if ($result == null) {
                return $this;
            } else {
                throw new LocalizedException(
                    __($result)
                );
            }
       // } else {
     //       return $this;
     //   }
    }
}
