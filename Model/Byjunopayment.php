<?php
/**
 * Created by PhpStorm.
 * User: Igor
 * Date: 08.12.2016
 * Time: 19:31
 */

namespace ByjunoCheckout\ByjunoCheckoutCore\Model;

use ByjunoCheckout\ByjunoCheckoutCore\Helper\Api\ByjunoCheckoutAuthorizationResponse;
use ByjunoCheckout\ByjunoCheckoutCore\Helper\Api\ByjunoCheckoutScreeningResponse;
use ByjunoCheckout\ByjunoCheckoutCore\Helper\Api\ByjunoCommunicator;
use ByjunoCheckout\ByjunoCheckoutCore\Helper\Api\ByjunoCheckoutRequest;
use ByjunoCheckout\ByjunoCheckoutCore\Observer\InvoiceObserver;
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
use Magento\Store\Model\ScopeInterface;
use Symfony\Component\Config\Definition\Exception\Exception;
use ByjunoCheckout\ByjunoCheckoutCore\Helper\DataHelper;


/**
 * Pay In Store payment method model
 */
class Byjunopayment extends \Magento\Payment\Model\Method\Adapter
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
       if ($this->_scopeConfig->getValue("byjunocheckoutsettings/byjunocheckout_setup/payment_mode", \Magento\Store\Model\ScopeInterface::SCOPE_STORE) == '0') {
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
            if ($total < $this->_scopeConfig->getValue('byjunocheckoutsettings/byjunocheckout_setup/minamount', \Magento\Store\Model\ScopeInterface::SCOPE_STORE) ||
                $total > $this->_scopeConfig->getValue('byjunocheckoutsettings/byjunocheckout_setup/maxamount', \Magento\Store\Model\ScopeInterface::SCOPE_STORE)) {
                $active = false;
            }
            return parent::isAvailable($quote) && $active;
        }
        return parent::isAvailable($quote);
    }

    /* @var $payment \Magento\Sales\Model\Order\Payment */
    public function cancel(InfoInterface $payment)
    {
        /* @var $order \Magento\Sales\Model\Order */
        $order = $payment->getOrder();
        $webshopProfileId = $payment->getAdditionalInformation("webshop_profile_id");
        if ($this->_scopeConfig->getValue('byjunocheckoutsettings/byjunocheckout_setup/byjunos5transacton', \Magento\Store\Model\ScopeInterface::SCOPE_STORE, $webshopProfileId) == '0') {
            return $this;
        }

        $request = $this->_dataHelper->CreateMagentoShopRequestS5Paid($order, $order->getTotalDue(), "EXPIRED", '', $webshopProfileId);
        $ByjunoRequestName = 'Byjuno Checkout S5 Cancel';
        $xml = $request->createRequest();
        $byjunoCommunicator = new \ByjunoCheckout\ByjunoCheckoutCore\Helper\Api\ByjunoCommunicator();
        $mode = $this->_dataHelper->_scopeConfig->getValue('byjunocheckoutsettings/byjunocheckout_setup/currentmode', \Magento\Store\Model\ScopeInterface::SCOPE_STORE, $webshopProfileId);
        if ($mode == 'live') {
            $byjunoCommunicator->setServer('live');
            $email = $this->_dataHelper->_scopeConfig->getValue('byjunocheckoutsettings/byjunocheckout_setup/byjunocheckout_prod_email', \Magento\Store\Model\ScopeInterface::SCOPE_STORE, $webshopProfileId);
        } else {
            $byjunoCommunicator->setServer('test');
            $email = $this->_dataHelper->_scopeConfig->getValue('byjunocheckoutsettings/byjunocheckout_setup/byjunocheckout_test_email', \Magento\Store\Model\ScopeInterface::SCOPE_STORE, $webshopProfileId);
        }
        $response = $byjunoCommunicator->sendS4Request($xml, (int)$this->_dataHelper->_scopeConfig->getValue('byjunocheckoutsettings/byjunocheckout_setup/timeout', \Magento\Store\Model\ScopeInterface::SCOPE_STORE, $webshopProfileId));
        if ($response) {
            $this->_dataHelper->_responseS4->setRawResponse($response);
            $this->_dataHelper->_responseS4->processResponse();
            $status = $this->_dataHelper->_responseS4->getProcessingInfoClassification();
            $this->_dataHelper->saveS5Log($order, $request, $xml, $response, $status, $ByjunoRequestName);
        } else {
            $status = "ERR";
            $this->_dataHelper->saveS5Log($order, $request, $xml, "empty response", $status, $ByjunoRequestName);
        }
        if ($status == 'ERR') {
            throw new LocalizedException(
                __($this->_scopeConfig->getValue('byjunocheckoutsettings/localization/byjunocheckout_s5_fail', \Magento\Store\Model\ScopeInterface::SCOPE_STORE, $webshopProfileId). " (error code: CDP_FAIL)")
            );
        }

        $authTransaction = $payment->getAuthorizationTransaction();
        if ($authTransaction && !$authTransaction->getIsClosed()) {
            $authTransaction->setIsClosed(true);
            $authTransaction->save();
        }
        $payment->setTransactionId($payment->getParentTransactionId().'-void');
        $transaction = $payment->addTransaction(\Magento\Sales\Model\Order\Payment\Transaction::TYPE_VOID, null, true);
        $transaction->setIsClosed(true);
        $payment->save();
        $transaction->save();
        return $this;
    }

    protected $_savedUser = Array(
        "FirstName" => "",
        "LastName" => "",
        "FirstLine" => "",
        "CountryCode" => "",
        "PostCode" => "",
        "Town" => "",
        "CompanyName1",
        "DateOfBirth",
        "Email",
        "TelephonePrivate",
        "Gender",
        "DELIVERY_FIRSTNAME",
        "DELIVERY_LASTNAME",
        "DELIVERY_FIRSTLINE",
        "DELIVERY_COUNTRYCODE",
        "DELIVERY_POSTCODE",
        "DELIVERY_TOWN",
        "DELIVERY_COMPANYNAME"
    );


    public function isTheSame(ByjunoCheckoutRequest $request) {

        if ($request->custDetails->firstName != $this->_savedUser["FirstName"]
            || $request->custDetails->lastName != $this->_savedUser["LastName"]
            || $request->billingAddr->addrFirstLine != $this->_savedUser["FirstLine"]
            || $request->billingAddr->country != $this->_savedUser["CountryCode"]
            || $request->billingAddr->postalCode != $this->_savedUser["PostCode"]
            || $request->billingAddr->town != $this->_savedUser["Town"]
            || $request->custDetails->companyName != $this->_savedUser["CompanyName1"]
            || $request->custDetails->dateOfBirth != $this->_savedUser["DateOfBirth"]
            || $request->custContacts->email != $this->_savedUser["Email"]
            || $request->custContacts->phoneMobile != $this->_savedUser["TelephonePrivate"]
            || $request->custDetails->salutation != $this->_savedUser["Gender"]
            || $request->amount != $this->_savedUser["Amount"]
            || $request->deliveryDetails->deliveryFirstName != $this->_savedUser["DELIVERY_FIRSTNAME"]
            || $request->deliveryDetails->deliverySecondName != $this->_savedUser["DELIVERY_LASTNAME"]
            || $request->deliveryDetails->deliveryAddrFirstLine != $this->_savedUser["DELIVERY_FIRSTLINE"]
            || $request->deliveryDetails->deliveryAddrCountry != $this->_savedUser["DELIVERY_COUNTRYCODE"]
            || $request->deliveryDetails->deliveryAddrPostalCode != $this->_savedUser["DELIVERY_POSTCODE"]
            || $request->deliveryDetails->deliveryAddrTown != $this->_savedUser["DELIVERY_TOWN"]
            || $request->deliveryDetails->deliveryCompanyName != $this->_savedUser["DELIVERY_COMPANYNAME"]
        ) {
            return false;
        }
        return true;
    }

    /* @var $quote \Magento\Quote\Model\Quote */
    public function GetCreditStatus($quote, $methods) {
        if ($quote == null) {
            return true;
        }
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $state =  $objectManager->get('Magento\Framework\App\State');
        if ($state->getAreaCode() == "adminhtml") {
            //skip credit check for backend
            return true;
        }
        if ($this->_scopeConfig->getValue('byjunocheckoutsettings/byjunocheckout_setup/screeningbeforeshow', \Magento\Store\Model\ScopeInterface::SCOPE_STORE) == '1'
            && $quote != null
            && $quote->getBillingAddress() != null) {
            $theSame = $this->_dataHelper->_checkoutSession->getIsTheSame();
            if (!empty($theSame) && is_array($theSame)) {
                $this->_savedUser = $theSame;
            }
            $status = $this->_dataHelper->_checkoutSession->getScreeningStatus();
            try {
                $request = $this->_dataHelper->CreateMagentoShopRequestScreening($quote);
                if ($request->amount == 0) {
                    return false;
                }
                $arrCheck = Array(
                    "FirstName" => $request->custDetails->firstName,
                    "LastName" => $request->custDetails->lastName,
                    "CountryCode" => $request->billingAddr->country,
                    "Town" => $request->billingAddr->town
                );
                foreach($arrCheck as $arrK => $arrV) {
                   if (empty($arrV)) {
                       return false;
                   }
                }
                if (!$this->isTheSame($request) || empty($status)) {
                    $ByjunoRequestName = $request->requestMsgType;
                  //  $json = "{}";
                 //   if ($request->custDetails->custType == 'C' && $this->_dataHelper->_scopeConfig->getValue('byjunocheckoutsettings/byjunocheckout_setup/businesstobusiness',
                 //           \Magento\Store\Model\ScopeInterface::SCOPE_STORE) == '1') {
                 //       $ByjunoRequestName = "Screening request for company";
                 //       $json = $request->createRequest();
                 //   } else {
                 //       $json = $request->createRequest();
                 //   }
                    $json = $request->createRequest();
                    $byjunoCommunicator = new ByjunoCommunicator();
                    $mode = $this->_dataHelper->_scopeConfig->getValue('byjunocheckoutsettings/byjunocheckout_setup/currentmode', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
                    if ($mode == 'live') {
                        $byjunoCommunicator->setServer('live');
                    } else {
                        $byjunoCommunicator->setServer('test');
                    }
                    $response = $byjunoCommunicator->sendScreeningRequest($json, (int)$this->_dataHelper->_scopeConfig->getValue('byjunocheckoutsettings/byjunocheckout_setup/timeout',
                        \Magento\Store\Model\ScopeInterface::SCOPE_STORE));

                    if ($response) {
                        /* @var $responseRes ByjunoCheckoutScreeningResponse */
                        $responseRes = $this->_dataHelper->screeningResponse($response);
                        $status = $responseRes->screeningDetails->allowedByjunoPaymentMethods;
                        $this->_dataHelper->saveLog($json, $response, $responseRes->processingStatus, $ByjunoRequestName,
                            $request->custDetails->firstName, $request->custDetails->lastName, $request->requestMsgId,
                            $request->billingAddr->postalCode, $request->billingAddr->town, $request->billingAddr->country, $request->billingAddr->addrFirstLine, $responseRes->transactionId, "-");
                    } else {
                        $this->_dataHelper->saveLog($json, $response, "Query error", $ByjunoRequestName,
                            $request->custDetails->firstName, $request->custDetails->lastName, $request->requestMsgId,
                            $request->billingAddr->postalCode, $request->billingAddr->town, $request->billingAddr->country, $request->billingAddr->addrFirstLine, "-", "-");
                    }

                    $this->_savedUser = Array(
                        "FirstName" => $request->custDetails->firstName,
                        "LastName" => $request->custDetails->lastName,
                        "FirstLine" => $request->billingAddr->addrFirstLine ,
                        "CountryCode" => $request->billingAddr->country,
                        "PostCode" => $request->billingAddr->postalCode,
                        "Town" => $request->billingAddr->town,
                        "CompanyName1" => $request->custDetails->companyName,
                        "DateOfBirth" => $request->custDetails->dateOfBirth,
                        "Email" => $request->custContacts->email,
                        "TelephonePrivate" => $request->custContacts->phoneMobile,
                        "Gender" => $request->custDetails->salutation,
                        "Amount" => $request->amount,
                        "DELIVERY_FIRSTNAME" => $request->deliveryDetails->deliveryFirstName,
                        "DELIVERY_LASTNAME" => $request->deliveryDetails->deliverySecondName,
                        "DELIVERY_FIRSTLINE" => $request->deliveryDetails->deliveryAddrFirstLine,
                        "DELIVERY_COUNTRYCODE" => $request->deliveryDetails->deliveryAddrCountry,
                        "DELIVERY_POSTCODE" => $request->deliveryDetails->deliveryAddrPostalCode,
                        "DELIVERY_TOWN" => $request->deliveryDetails->deliveryAddrTown,
                        "DELIVERY_COMPANYNAME" => $request->deliveryDetails->deliveryCompanyName
                    );
                    $this->_dataHelper->_checkoutSession->setIsTheSame($this->_savedUser);
                    $this->_dataHelper->_checkoutSession->setScreeningStatus($status);
                }
                foreach ($methods as $method) {
                    foreach ($status as $st) {
                        if ($st == $method) {
                            return true;
                        }
                    }
                }
            } catch (\Exception $e) {
                var_dump($e);
                exit('error');
            }
        }
        return false;
    }

    /* @throws LocalizedException
     * @var $infoInterface InfoInterface
     * @var $isCompany bool
     */
    public function validateCustomByjunoFields(InfoInterface $infoInterface, $isCompany)
    {
        /** @var $payment Payment */
        $payment = $infoInterface;
        if ($this->_scopeConfig->getValue("byjunocheckoutsettings/byjunocheckout_setup/gender_enable",
                \Magento\Store\Model\ScopeInterface::SCOPE_STORE) == 1) {
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
            if ($this->_scopeConfig->getValue("byjunocheckoutsettings/byjunocheckout_setup/birthday_enable",
                    \Magento\Store\Model\ScopeInterface::SCOPE_STORE) == 1 && !$birthday_provided) {
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
            if ($this->_scopeConfig->getValue("byjunocheckoutsettings/byjunocheckout_setup/b2b_uid",
                    \Magento\Store\Model\ScopeInterface::SCOPE_STORE) == 1) {
                if ($payment->getAdditionalInformation('customer_b2b_uid') == null || $payment->getAdditionalInformation('customer_b2b_uid') == '') {
                    throw new LocalizedException(
                        __("Company registration number not provided")
                    );
                }
            }
        }

        if ($this->_scopeConfig->getValue("byjunocheckoutsettings/byjunocheckout_setup/country_phone_validation",
                \Magento\Store\Model\ScopeInterface::SCOPE_STORE) == 1 && $payment->getQuote() != null) {

            $pattern = "/^[0-9]{4}$/";
            if (strtolower($payment->getQuote()->getBillingAddress()->getCountryId()) == 'ch' && !preg_match($pattern, $payment->getQuote()->getBillingAddress()->getPostcode())) {
                throw new LocalizedException(
                    __($this->_scopeConfig->getValue('byjunocheckoutsettings/localization/postal_code_wrong', \Magento\Store\Model\ScopeInterface::SCOPE_STORE).
                        ": " . $payment->getQuote()->getBillingAddress()->getPostcode())
                );
            }
            if (!preg_match("/^[0-9\+\(\)\s]+$/", $payment->getQuote()->getBillingAddress()->getTelephone())) {
                throw new LocalizedException(
                    __($this->_scopeConfig->getValue('byjunocheckoutsettings/localization/telephone_code_wrong', \Magento\Store\Model\ScopeInterface::SCOPE_STORE).
                        ": " . $payment->getQuote()->getBillingAddress()->getTelephone())
                );
            }
        }
    }

    /* @var $payment \Magento\Sales\Model\Order\Payment */
    public function refund(InfoInterface $payment, $amount)
    {
		$this->_dataHelper->_objectManager->configure($this->_dataHelper->_configLoader->load('adminhtml'));
        /* @var $order \Magento\Sales\Model\Order */
        $order = $payment->getOrder();
        /* @var $memo \Magento\Sales\Model\Order\Creditmemo */
        $memo = $payment->getCreditmemo();
        $incoiceId = $memo->getInvoice()->getIncrementId();
        $webshopProfileId = $payment->getAdditionalInformation("webshop_profile_id");
        if ($this->_scopeConfig->getValue('byjunocheckoutsettings/byjunocheckout_setup/byjunos5transacton', \Magento\Store\Model\ScopeInterface::SCOPE_STORE, $webshopProfileId) == '0') {
            return $this;
        }
        $request = $this->_dataHelper->CreateMagentoShopRequestS5Paid($order, $amount, "REFUND", $incoiceId, $webshopProfileId);
        $ByjunoRequestName = 'Byjuno Checkout S5 Refund';
        $xml = $request->createRequest();
        $byjunoCommunicator = new \ByjunoCheckout\ByjunoCheckoutCore\Helper\Api\ByjunoCommunicator();
        $mode = $this->_dataHelper->_scopeConfig->getValue('byjunocheckoutsettings/byjunocheckout_setup/currentmode', \Magento\Store\Model\ScopeInterface::SCOPE_STORE, $webshopProfileId);
        if ($mode == 'live') {
            $byjunoCommunicator->setServer('live');
            $email = $this->_dataHelper->_scopeConfig->getValue('byjunocheckoutsettings/byjunocheckout_setup/byjunocheckout_prod_email', \Magento\Store\Model\ScopeInterface::SCOPE_STORE, $webshopProfileId);
        } else {
            $byjunoCommunicator->setServer('test');
            $email = $this->_dataHelper->_scopeConfig->getValue('byjunocheckoutsettings/byjunocheckout_setup/byjunocheckout_test_email', \Magento\Store\Model\ScopeInterface::SCOPE_STORE, $webshopProfileId);
        }
        $response = $byjunoCommunicator->sendS4Request($xml, (int)$this->_dataHelper->_scopeConfig->getValue('byjunocheckoutsettings/byjunocheckout_setup/timeout', \Magento\Store\Model\ScopeInterface::SCOPE_STORE, $webshopProfileId));
        if ($response) {
            $this->_dataHelper->_responseS4->setRawResponse($response);
            $this->_dataHelper->_responseS4->processResponse();
            $status = $this->_dataHelper->_responseS4->getProcessingInfoClassification();
            $this->_dataHelper->saveS5Log($order, $request, $xml, $response, $status, $ByjunoRequestName);
        } else {
            $status = "ERR";
            $this->_dataHelper->saveS5Log($order, $request, $xml, "empty response", $status, $ByjunoRequestName);
        }
        if ($status == 'ERR') {
            throw new LocalizedException(
                __($this->_scopeConfig->getValue('byjunocheckoutsettings/localization/byjunocheckout_s5_fail', \Magento\Store\Model\ScopeInterface::SCOPE_STORE, $webshopProfileId). " (error code: CDP_FAIL)")
            );
        } else {
            $this->_dataHelper->_byjunoCreditmemoSender->sendCreditMemo($memo, $email);
        }

        $payment->setTransactionId($payment->getParentTransactionId().'-refund');
        $transaction = $payment->addTransaction(\Magento\Sales\Model\Order\Payment\Transaction::TYPE_REFUND, null, true);
        $transaction->setIsClosed(true);
        $payment->save();
        $transaction->save();
        return $this;
    }

    /* @var $payment \Magento\Sales\Model\Order\Payment */
    public function capture(InfoInterface $payment, $amount)
    {
		$this->_dataHelper->_objectManager->configure($this->_dataHelper->_configLoader->load('adminhtml'));
        /* @var $invoice \Magento\Sales\Model\Order\Invoice */
        $order = $payment->getOrder();
        $webshopProfileId = $payment->getAdditionalInformation("webshop_profile_id");
        $invoice = InvoiceObserver::$Invoice;
        if ($invoice == null) {
            throw new LocalizedException(
                __("Internal invoice (InvoiceObserver) error")
            );
        }
        if ($this->_scopeConfig->getValue("byjunocheckoutsettings/byjunocheckout_setup/byjunosettletransacton", \Magento\Store\Model\ScopeInterface::SCOPE_STORE, $webshopProfileId) == '0') {
            return $this;
        }
        if ($payment->getAdditionalInformation("auth_executed_ok") == null || $payment->getAdditionalInformation("auth_executed_ok") == 'false') {
            throw new LocalizedException (
                __($this->_scopeConfig->getValue('byjunocheckoutsettings/localization/byjunocheckout_settle_fail', \Magento\Store\Model\ScopeInterface::SCOPE_STORE, $webshopProfileId). " (error code: AUT NOT FOUND)")
            );
        }
        $incrementValue =  $this->_eavConfig->getEntityType($invoice->getEntityType())->fetchNewIncrementId($invoice->getStoreId());
        if ($invoice->getIncrementId() == null) {
            $invoice->setIncrementId($incrementValue);
        }
        $tx = $this->_dataHelper->getTransactionForOrder($order->getRealOrderId());
        if ($tx == null || !$tx || empty($tx["transaction_id"])) {
            throw new LocalizedException (
                __($this->_scopeConfig->getValue('byjunocheckoutsettings/localization/byjunocheckout_settle_fail', \Magento\Store\Model\ScopeInterface::SCOPE_STORE, $webshopProfileId). " (error code: AUT NOT FOUND)")
            );
        }
        $request = $this->_dataHelper->CreateMagentoShopRequestSettlePaid($order, $invoice, $payment, $webshopProfileId, $tx["transaction_id"]);

        $ByjunoRequestName = $request->requestMsgType;
        $json = $request->createRequest();
        $byjunoCommunicator = new ByjunoCommunicator();
        $mode = $this->_scopeConfig->getValue('byjunocheckoutsettings/byjunocheckout_setup/currentmode', ScopeInterface::SCOPE_STORE);
        if ($mode == 'live') {
            $byjunoCommunicator->setServer('live');
            $email = $this->_dataHelper->_scopeConfig->getValue('byjunocheckoutsettings/byjunocheckout_setup/byjunocheckout_prod_email', \Magento\Store\Model\ScopeInterface::SCOPE_STORE, $webshopProfileId);

        } else {
            $byjunoCommunicator->setServer('test');
            $email = $this->_dataHelper->_scopeConfig->getValue('byjunocheckoutsettings/byjunocheckout_setup/byjunocheckout_test_email', \Magento\Store\Model\ScopeInterface::SCOPE_STORE, $webshopProfileId);

        }
        $response = $byjunoCommunicator->sendSettleRequest($json, (int)$this->_scopeConfig->getValue('byjunocheckoutsettings/byjunocheckout_setup/timeout',
            ScopeInterface::SCOPE_STORE));

        $status = "";
        $responseRes = null;
        if ($response) {
            /* @var $responseRes ByjunoCheckoutAuthorizationResponse */
            $responseRes = $this->_dataHelper->settleResponse($response);
            $status = $responseRes->processingStatus;
            $this->_dataHelper->saveLog($json, $response, $responseRes->processingStatus, $ByjunoRequestName,
               "-","-", $request->requestMsgId,
                "-", "-", "-","-", $responseRes->transactionId, $order->getRealOrderId());
        } else {
            $this->_dataHelper->saveLog($json, $response, "Query error", $ByjunoRequestName,
                "-","-", $request->requestMsgId,
                "-", "-", "-","-", "-", "-");
        }
        if ($status == "SUCCESS") {
            $this->_dataHelper->_byjunoInvoiceSender->sendInvoice($invoice, $email, $this->_dataHelper);
            $authTransaction = $payment->getAuthorizationTransaction();
            if ($authTransaction && !$authTransaction->getIsClosed()) {
                $authTransaction->setIsClosed($payment->isCaptureFinal($amount));
                $authTransaction->save();
            }

            $payment->setTransactionId($responseRes->transactionId);
            $transaction = $payment->addTransaction(\Magento\Sales\Model\Order\Payment\Transaction::TYPE_CAPTURE, null, true);
            $transaction->setIsClosed(true);
            $payment->save();

            $transaction->save();
            return $this;

        } else {
            throw new LocalizedException(
                __($this->_scopeConfig->getValue('byjunocheckoutsettings/localization/byjunocheckout_settle_fail', \Magento\Store\Model\ScopeInterface::SCOPE_STORE, $webshopProfileId))
            );
        }

/*
        $ByjunoRequestName = 'Byjuno Checkout S4';
        $xml = $request->createRequest();
        $byjunoCommunicator = new \ByjunoCheckout\ByjunoCheckoutCore\Helper\Api\ByjunoCommunicator();
        $mode = $this->_dataHelper->_scopeConfig->getValue('byjunocheckoutsettings/byjunocheckout_setup/currentmode', \Magento\Store\Model\ScopeInterface::SCOPE_STORE, $webshopProfileId);
        if ($mode == 'live') {
            $byjunoCommunicator->setServer('live');
            $email = $this->_dataHelper->_scopeConfig->getValue('byjunocheckoutsettings/byjunocheckout_setup/byjunocheckout_prod_email', \Magento\Store\Model\ScopeInterface::SCOPE_STORE, $webshopProfileId);
        } else {
            $byjunoCommunicator->setServer('test');
            $email = $this->_dataHelper->_scopeConfig->getValue('byjunocheckoutsettings/byjunocheckout_setup/byjunocheckout_test_email', \Magento\Store\Model\ScopeInterface::SCOPE_STORE, $webshopProfileId);
        }
        $response = $byjunoCommunicator->sendS4Request($xml, (int)$this->_dataHelper->_scopeConfig->getValue('byjunocheckoutsettings/byjunocheckout_setup/timeout', \Magento\Store\Model\ScopeInterface::SCOPE_STORE, $webshopProfileId));
        if ($response) {
            $this->_dataHelper->_responseS4->setRawResponse($response);
            $this->_dataHelper->_responseS4->processResponse();
            $status = $this->_dataHelper->_responseS4->getProcessingInfoClassification();
            $this->_dataHelper->saveS4Log($order, $request, $xml, $response, $status, $ByjunoRequestName);
        } else {
            $status = "ERR";
            $this->_dataHelper->saveS4Log($order, $request, $xml, "empty response", $status, $ByjunoRequestName);
        }
        if ($status == 'ERR') {
            throw new LocalizedException(
                __($this->_scopeConfig->getValue('byjunocheckoutsettings/localization/byjunocheckout_settle_fail', \Magento\Store\Model\ScopeInterface::SCOPE_STORE, $webshopProfileId). " (error code: CDP_FAIL)")
            );
        } else {
            $this->_dataHelper->_byjunoInvoiceSender->sendInvoice($invoice, $email, $this->_dataHelper);
        }

        $authTransaction = $payment->getAuthorizationTransaction();
        if ($authTransaction && !$authTransaction->getIsClosed()) {
            $authTransaction->setIsClosed($payment->isCaptureFinal($amount));
            $authTransaction->save();
        }

        $payment->setTransactionId($incrementValue.'-invoice');
        $transaction = $payment->addTransaction(\Magento\Sales\Model\Order\Payment\Transaction::TYPE_CAPTURE, null, true);
        $transaction->setIsClosed(true);
        $payment->save();

        $transaction->save();
        return $this;
*/
    }


}
