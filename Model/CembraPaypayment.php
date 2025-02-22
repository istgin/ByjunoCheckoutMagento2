<?php
/**
 * Created by CembraPay.
 */

namespace Byjuno\ByjunoCore\Model;

use Byjuno\ByjunoCore\Helper\Api\CembraPayCheckoutAuthorizationResponse;
use Byjuno\ByjunoCore\Helper\Api\CembraPayCheckoutCancelResponse;
use Byjuno\ByjunoCore\Helper\Api\CembraPayCheckoutCreditResponse;
use Byjuno\ByjunoCore\Helper\Api\CembraPayCheckoutScreeningResponse;
use Byjuno\ByjunoCore\Helper\Api\CembraPayCheckoutSettleResponse;
use Byjuno\ByjunoCore\Helper\Api\CembraPayCommunicator;
use Byjuno\ByjunoCore\Helper\Api\CembraPayCheckoutAutRequest;
use Byjuno\ByjunoCore\Observer\InvoiceObserver;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\DataObject;
use Magento\Payment\Model\InfoInterface;
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
use Magento\Quote\Model\Quote\Payment;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Payment\Transaction;
use Magento\SalesSequence\Model\Manager;
use Magento\Store\Model\ScopeInterface;
use Magento\Ui\Config\Data;
use Symfony\Component\Config\Definition\Exception\Exception;
use Byjuno\ByjunoCore\Helper\DataHelper;


/**
 * Pay In Store payment method model
 */
class CembraPaypayment extends \Magento\Payment\Model\Method\Adapter
{
    /* @var $_scopeConfig \Magento\Framework\App\Config\ScopeConfigInterface */
    protected $_scopeConfig;
    protected $eventManager;
    protected $_eavConfig;
    /* @var $_dataHelper DataHelper */
    protected $_dataHelper;
    protected $_state;

    /* @var $_scopeConfig \Magento\Checkout\Model\Session */
    protected $_checkoutSession;

    /**
     * @var Manager
     */
    protected $_sequenceManager;

    public function void(InfoInterface $payment)
    {
        $this->cancel($payment);
        return $this;
    }

    public function canEdit()
    {
        return true;
    }

    public function canCapture()
    {
        return true;
    }

    public function canCapturePartial()
    {
        return true;
    }

    public function isInitializeNeeded()
    {
        $objectManager = ObjectManager::getInstance();
        $state = $objectManager->get('Magento\Framework\App\State');
        
        if ($this->_scopeConfig->getValue("cembrapaycheckoutsettings/cembrapaycheckout_setup/payment_mode", ScopeInterface::SCOPE_STORE) == '0' || $state->getAreaCode() == "adminhtml") {
            return false;
        } else {
            return true;
        }
    }

    /* @var $quote \Magento\Quote\Model\Quote */
    public function isAvailable(CartInterface $quote = null)
    {
        if ($quote != null) {
            $total = $quote->getGrandTotal();
            $active = true;
            if ($total < $this->_scopeConfig->getValue('cembrapaycheckoutsettings/cembrapaycheckout_setup/minamount', ScopeInterface::SCOPE_STORE) ||
                $total > $this->_scopeConfig->getValue('cembrapaycheckoutsettings/cembrapaycheckout_setup/maxamount', ScopeInterface::SCOPE_STORE)) {
                $active = false;
            }
            return parent::isAvailable($quote) && $active;
        }
        return parent::isAvailable($quote);
    }

    /* @var $payment \Magento\Sales\Model\Order\Payment */
    public function cancel(InfoInterface $payment)
    { /* @var $order \Magento\Sales\Model\Order */
        $order = $payment->getOrder();
        $webshopProfileId = $payment->getAdditionalInformation("webshop_profile_id");
        if ($this->_scopeConfig->getValue('cembrapaycheckoutsettings/cembrapaycheckout_setup/cembrapays5transacton', \Magento\Store\Model\ScopeInterface::SCOPE_STORE, $webshopProfileId) == '0') {
            return $this;
        }
        $txType = "CHK";
        if ($payment->getAdditionalInformation("auth_executed_ok") == 'true') {
            $txType = "AUT";
        }
        $oldOrder = true;
        if ($payment->getAdditionalInformation("auth_executed_ok") != null || $payment->getAdditionalInformation("chk_executed_ok") != null) {
            $oldOrder = false;
        }
        $tx = $this->_dataHelper->getTransactionForOrder($order->getRealOrderId(), $txType);
        if (!$oldOrder && ($tx == null || !$tx || empty($tx["transaction_id"]))) {
            throw new LocalizedException (
                __($this->_scopeConfig->getValue('cembrapaycheckoutsettings/localization/cembrapaycheckout_settle_fail', ScopeInterface::SCOPE_STORE, $webshopProfileId). " (error code: ".$txType." NOT FOUND)")
            );
        }
        $txId = null;
        if (!empty($tx["transaction_id"])) {
            $txId = $tx["transaction_id"];
        }
        $request = $this->_dataHelper->CreateMagentoShopRequestCancel($order, $order->getTotalDue(), $webshopProfileId, $txId);
        $CembraPayRequestName = $request->requestMsgType;
        $json = $request->createRequest();
        $cembrapayCommunicator = new CembraPayCommunicator($this->_dataHelper->cembraPayAzure);
        $mode = $this->_scopeConfig->getValue('cembrapaycheckoutsettings/cembrapaycheckout_setup/currentmode', ScopeInterface::SCOPE_STORE);
        if ($mode == 'live') {
            $cembrapayCommunicator->setServer('live');

        } else {
            $cembrapayCommunicator->setServer('test');
        }
        $status = "";
        $response = $cembrapayCommunicator->sendCancelRequest($json,
            $this->_dataHelper->getAccessDataWebshop($webshopProfileId, $mode),
            function ($object, $token, $accessData) {
                $object->saveToken($token, $accessData);
            });
        if ($response) { /* @var $responseRes CembraPayCheckoutCancelResponse */
            $responseRes = $this->_dataHelper->cancelResponse($response);
            $status = $responseRes->processingStatus;
            $this->_dataHelper->saveLog($json, $response, $responseRes->processingStatus, $CembraPayRequestName,
                "-","-", $request->requestMsgId,
                "-", "-", "-","-", $responseRes->transactionId, $order->getRealOrderId());
        } else {
            $this->_dataHelper->saveLog($json, $response, "Query error", $CembraPayRequestName,
                "-","-", $request->requestMsgId,
                "-", "-", "-","-", "-", "-");
        }
        if ($status != DataHelper::$CANCEL_OK) {
            throw new LocalizedException(
                __($this->_scopeConfig->getValue('cembrapaycheckoutsettings/localization/cembrapaycheckout_s5_fail', ScopeInterface::SCOPE_STORE, $webshopProfileId). " (error code: CDP_FAIL)")
            );
        }

        $authTransaction = $payment->getAuthorizationTransaction();
        if ($authTransaction && !$authTransaction->getIsClosed()) {
            $authTransaction->setIsClosed(true);
            $authTransaction->save();
        }
        $payment->setTransactionId($payment->getParentTransactionId().'-void');
        $transaction = $payment->addTransaction(Transaction::TYPE_VOID, null, true);
        $transaction->setIsClosed(true);
        $payment->save();
        $transaction->save();
        return $this;
    }

    /* @throws LocalizedException
     * @var $infoInterface InfoInterface
     * @var $isCompany bool
     */
    public function validateCustomCembraPayFields(InfoInterface $infoInterface, $isCompany)
    {
        /** @var $payment Payment */
        $payment = $infoInterface;
        if ($this->_scopeConfig->getValue("cembrapaycheckoutsettings/cembrapaycheckout_setup/gender_enable",
                ScopeInterface::SCOPE_STORE) == 1) {
            if ($payment->getAdditionalInformation('customer_gender') == null || $payment->getAdditionalInformation('customer_gender') == '') {
                throw new LocalizedException(
                    __("Gender not selected")
                );
            }
        }
        $birthday_provided = false;
        $b = $this->_checkoutSession->getQuote()->getCustomerDob();
        if (!empty($b)) {
            try {
                $dobObject = new \DateTime($b);
                if ($dobObject != null) {
                    $birthday_provided = true;
                }
            } catch (\Exception $e) {

            }
        }
        if (!$isCompany) {
            if ($this->_scopeConfig->getValue("cembrapaycheckoutsettings/cembrapaycheckout_setup/birthday_enable",
                    ScopeInterface::SCOPE_STORE) == 1 && !$birthday_provided) {
                if ($payment->getAdditionalInformation('customer_dob') == null || $payment->getAdditionalInformation('customer_dob') == '') {
                    throw new LocalizedException(
                        __("Birthday not selected")
                    );
                }

                if (!preg_match("/^\s*(3[01]|[12][0-9]|0?[1-9])\.(1[012]|0?[1-9])\.((?:19|20)\d{2})\s*$/", $payment->getAdditionalInformation('customer_dob'))) {
                    throw new LocalizedException(
                        __("Birthday is invalid")
                    );
                } else {
                    $e = explode(".", $payment->getAdditionalInformation('customer_dob'));
                    if (!isset($e[2]) || intval($e[2]) < 1800 || intval($e[2]) > date("Y")) {
                        throw new LocalizedException(
                            __("Provided date is not valid")
                        );
                    }
                }
            }
        } else {
            if ($this->_scopeConfig->getValue("cembrapaycheckoutsettings/cembrapaycheckout_setup/b2b_uid",
                    ScopeInterface::SCOPE_STORE) == 1) {
                if ($payment->getAdditionalInformation('customer_b2b_uid') == null || $payment->getAdditionalInformation('customer_b2b_uid') == '') {
                    throw new LocalizedException(
                        __("Company registration number not provided")
                    );
                }
            }
        }

    }

    /* @var $payment \Magento\Sales\Model\Order\Payment */
    public function refund(InfoInterface $payment, $amount)
    {
        /* @var $order \Magento\Sales\Model\Order */
        $order = $payment->getOrder();
        /* @var $memo \Magento\Sales\Model\Order\Creditmemo */
        $memo = $payment->getCreditmemo();
        $incoiceId = $memo->getInvoice()->getIncrementId();
        $invoiceSettlementId = $memo->getInvoice()->getTransactionId();
        $webshopProfileId = $payment->getAdditionalInformation("webshop_profile_id");
        if ($this->_scopeConfig->getValue('cembrapaycheckoutsettings/cembrapaycheckout_setup/cembrapays5transacton', ScopeInterface::SCOPE_STORE, $webshopProfileId) == '0') {
            return $this;
        }

        $txType = "CHK";
        if ($payment->getAdditionalInformation("auth_executed_ok") == 'true') {
            $txType = "AUT";
        }
        $oldOrder = true;
        if ($payment->getAdditionalInformation("auth_executed_ok") != null || $payment->getAdditionalInformation("chk_executed_ok") != null) {
            $oldOrder = false;
        }
        $tx = $this->_dataHelper->getTransactionForOrder($order->getRealOrderId(), $txType);
        if (!$oldOrder && ($tx == null || !$tx || empty($tx["transaction_id"]))) {
            throw new LocalizedException (
                __($this->_scopeConfig->getValue('cembrapaycheckoutsettings/localization/cembrapaycheckout_settle_fail', ScopeInterface::SCOPE_STORE, $webshopProfileId). " (error code: ".$txType." NOT FOUND)")
            );
        }
        $txId = null;
        if (!empty($tx["transaction_id"])) {
            $txId = $tx["transaction_id"];
        }
        if ($oldOrder) {
            $invoiceSettlementId = "";
        }
        $request = $this->_dataHelper->CreateMagentoShopRequestCredit($order, $amount, $incoiceId, $txId, $invoiceSettlementId);
        $CembraPayRequestName = $request->requestMsgType;
        $json = $request->createRequest();
        $cembrapayCommunicator = new CembraPayCommunicator($this->_dataHelper->cembraPayAzure);
        $mode = $this->_scopeConfig->getValue('cembrapaycheckoutsettings/cembrapaycheckout_setup/currentmode', ScopeInterface::SCOPE_STORE);
        if ($mode == 'live') {
            $cembrapayCommunicator->setServer('live');
            $email = $this->_dataHelper->_scopeConfig->getValue('cembrapaycheckoutsettings/cembrapaycheckout_setup/cembrapaycheckout_prod_email', ScopeInterface::SCOPE_STORE, $webshopProfileId);

        } else {
            $cembrapayCommunicator->setServer('test');
            $email = $this->_dataHelper->_scopeConfig->getValue('cembrapaycheckoutsettings/cembrapaycheckout_setup/cembrapaycheckout_test_email', ScopeInterface::SCOPE_STORE, $webshopProfileId);

        }
        $status = "";
        $response = $cembrapayCommunicator->sendCreditRequest($json,
            $this->_dataHelper->getAccessDataWebshop($webshopProfileId, $mode),
            function ($object, $token, $accessData) {
                $object->saveToken($token, $accessData);
            });
        if ($response) { /* @var $responseRes CembraPayCheckoutCreditResponse */
            $responseRes = $this->_dataHelper->creditResponse($response);
            $status = $responseRes->processingStatus;
            $this->_dataHelper->saveLog($json, $response, $responseRes->processingStatus, $CembraPayRequestName,
                "-","-", $request->requestMsgId,
                "-", "-", "-","-", $responseRes->transactionId, $order->getRealOrderId());
        } else {
            $this->_dataHelper->saveLog($json, $response, "Query error", $CembraPayRequestName,
                "-","-", $request->requestMsgId,
                "-", "-", "-","-", "-", "-");
        }
        if ($status != DataHelper::$CREDIT_OK) {
            throw new LocalizedException(
                __($this->_scopeConfig->getValue('cembrapaycheckoutsettings/localization/cembrapaycheckout_s5_fail', ScopeInterface::SCOPE_STORE, $webshopProfileId). " (error code: CDP_FAIL)")
            );
        } else {
            $this->_dataHelper->_cembrapayCreditmemoSender->sendCreditMemo($memo, $email);
        }

        $payment->setTransactionId($payment->getParentTransactionId().'-'.microtime(true).'-r');
        $transaction = $payment->addTransaction(Transaction::TYPE_REFUND, null, true);
        $transaction->setIsClosed(true);
        $payment->save();
        $transaction->save();
        return $this;
    }

    /* @var $payment \Magento\Sales\Model\Order\Payment */
    public function capture(InfoInterface $payment, $amount)
    {
        /* @var $invoice \Magento\Sales\Model\Order\Invoice */
        $order = $payment->getOrder();
        $webshopProfileId = $payment->getAdditionalInformation("webshop_profile_id");
        $invoice = InvoiceObserver::$Invoice;
        if ($invoice == null) {
            throw new LocalizedException(
                __("Internal invoice (InvoiceObserver) error")
            );
        }
        if ($this->_scopeConfig->getValue("cembrapaycheckoutsettings/cembrapaycheckout_setup/cembrapaysettletransacton", ScopeInterface::SCOPE_STORE, $webshopProfileId) == '0') {
            return $this;
        }
        $txType = "CHK";
        if ($payment->getAdditionalInformation("auth_executed_ok") == 'true') {
            $txType = "AUT";
        }
        $oldOrder = true;
        if ($payment->getAdditionalInformation("auth_executed_ok") != null || $payment->getAdditionalInformation("chk_executed_ok") != null) {
            $oldOrder = false;
        }
        $tx = $this->_dataHelper->getTransactionForOrder($order->getRealOrderId(), $txType);
        if (!$oldOrder && ($tx == null || !$tx || empty($tx["transaction_id"]))) {
            throw new LocalizedException (
                __($this->_scopeConfig->getValue('cembrapaycheckoutsettings/localization/cembrapaycheckout_settle_fail', ScopeInterface::SCOPE_STORE, $webshopProfileId). " (error code: ".$txType." NOT FOUND)")
            );
        }
        $txId = null;
        if (!empty($tx["transaction_id"])) {
            $txId = $tx["transaction_id"];
        }
        $incrementValue = $invoice->getIncrementId();
        if ($incrementValue == null) {
            $incrementValue = $this->_sequenceManager->getSequence(
                $invoice->getEntityType(),
                $invoice->getStoreId()
            )->getNextValue();
            $invoice->setIncrementId($incrementValue);
        }
        $request = $this->_dataHelper->CreateMagentoShopRequestSettlePaid($order, $amount, $invoice, $payment, $webshopProfileId, $txId);

        $CembraPayRequestName = $request->requestMsgType;
        $json = $request->createRequest();
        $cembrapayCommunicator = new CembraPayCommunicator($this->_dataHelper->cembraPayAzure);
        $mode = $this->_scopeConfig->getValue('cembrapaycheckoutsettings/cembrapaycheckout_setup/currentmode', ScopeInterface::SCOPE_STORE);
        if ($mode == 'live') {
            $cembrapayCommunicator->setServer('live');
            $email = $this->_dataHelper->_scopeConfig->getValue('cembrapaycheckoutsettings/cembrapaycheckout_setup/cembrapaycheckout_prod_email', ScopeInterface::SCOPE_STORE, $webshopProfileId);

        } else {
            $cembrapayCommunicator->setServer('test');
            $email = $this->_dataHelper->_scopeConfig->getValue('cembrapaycheckoutsettings/cembrapaycheckout_setup/cembrapaycheckout_test_email', ScopeInterface::SCOPE_STORE, $webshopProfileId);

        }
        $response = $cembrapayCommunicator->sendSettleRequest($json,
            $this->_dataHelper->getAccessDataWebshop($webshopProfileId, $mode),
            function ($object, $token, $accessData) {
                $object->saveToken($token, $accessData);
            });
        $status = "";
        $responseRes = null;
        if ($response) {
            /* @var $responseRes CembraPayCheckoutSettleResponse */
            $responseRes = $this->_dataHelper->settleResponse($response);
            $status = $responseRes->processingStatus;
            $this->_dataHelper->saveLog($json, $response, $responseRes->processingStatus, $CembraPayRequestName,
               "-","-", $request->requestMsgId,
                "-", "-", "-","-", $responseRes->transactionId, $order->getRealOrderId());
        } else {
            $this->_dataHelper->saveLog($json, $response, "Query error", $CembraPayRequestName,
                "-","-", $request->requestMsgId,
                "-", "-", "-","-", "-", "-");
        }
        if (!empty($status) && in_array($status, DataHelper::$SETTLE_STATUSES)) {
            $this->_dataHelper->_cembrapayInvoiceSender->sendInvoice($invoice, $email, $this->_dataHelper);
            $authTransaction = $payment->getAuthorizationTransaction();
            if ($authTransaction && !$authTransaction->getIsClosed()) {
                $authTransaction->setIsClosed($payment->isCaptureFinal($amount));
                $authTransaction->save();
            }

            $payment->setTransactionId($responseRes->settlementId);
            $transaction = $payment->addTransaction(Transaction::TYPE_CAPTURE, null, true);
            $transaction->setIsClosed(true);
            $payment->save();
            $transaction->save();

            return $this;

        } else {
            throw new LocalizedException(
                __($this->_scopeConfig->getValue('cembrapaycheckoutsettings/localization/cembrapaycheckout_settle_fail', ScopeInterface::SCOPE_STORE, $webshopProfileId))
            );
        }
    }
}
