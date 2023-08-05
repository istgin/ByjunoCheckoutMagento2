<?php

namespace CembraPayCheckout\CembraPayCheckoutCore\Helper;

use CembraPayCheckout\CembraPayCheckoutCore\Helper\Api\CembraPayCheckoutAuthorizationResponse;
use CembraPayCheckout\CembraPayCheckoutCore\Helper\Api\CembraPayCheckoutChkRequest;
use CembraPayCheckout\CembraPayCheckoutCore\Helper\Api\CembraPayCheckoutChkResponse;
use CembraPayCheckout\CembraPayCheckoutCore\Helper\Api\CembraPayCheckoutCreditRequest;
use CembraPayCheckout\CembraPayCheckoutCore\Helper\Api\CembraPayCheckoutCreditResponse;
use CembraPayCheckout\CembraPayCheckoutCore\Helper\Api\CembraPayCheckoutAutRequest;
use CembraPayCheckout\CembraPayCheckoutCore\Helper\Api\CembraPayCheckoutScreeningResponse;
use CembraPayCheckout\CembraPayCheckoutCore\Helper\Api\CembraPayCheckoutSettleRequest;
use CembraPayCheckout\CembraPayCheckoutCore\Helper\Api\CembraPayCheckoutSettleResponse;
use CembraPayCheckout\CembraPayCheckoutCore\Helper\Api\CembraPayCommunicator;
use CembraPayCheckout\CembraPayCheckoutCore\Helper\Api\CustomerConsents;
use Magento\Framework\App\ObjectManager;
use Magento\Quote\Model\Quote\Payment;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Invoice;
use Magento\Store\Model\ScopeInterface;

class DataHelper extends \Magento\Framework\App\Helper\AbstractHelper
{

    public static $SINGLEINVOICE = 'SINGLE-INVOICE';
    public static $CEMBRAPAYINVOICE = 'BYJUNO-INVOICE';

    public static $MESSAGE_SCREENING = 'SCR';
    public static $MESSAGE_AUTH = 'AUT';
    public static $MESSAGE_SET = 'SET';
    public static $MESSAGE_CNL = 'CNT';
    public static $MESSAGE_CHK = 'CHK';

    public static $CUSTOMER_PRIVATE = 'P';
    public static $CUSTOMER_BUSINESS = 'C';


    public static $GENTER_UNKNOWN = 'N';
    public static $GENTER_MALE = 'M';
    public static $GENTER_FEMALE = 'F';


    public static $DELIVERY_POST = 'POST';
    public static $DELIVERY_VIRTUAL = 'DIGITAL';

    public static $SCREENING_OK = 'SCREENING-APPROVED';
    public static $SETTLE_OK = 'SETTLED';
    public static $AUTH_OK = 'AUTHORIZED';
    public static $CREDIT_OK = 'SUCCESS';
    public static $CHK_OK = 'SUCCESS';


    public static $REQUEST_ERROR = 'REQUEST_ERROR';

    public static $screeningStatus;

    /**
     * @var \Magento\Quote\Api\CartRepositoryInterface
     */
    public $quoteRepository;

    protected $_storeManager;
    protected $_iteratorFactory;
    protected $_blockMenu;
    protected $_url;
    /* @var $_scopeConfig \Magento\Framework\App\Config\ScopeConfigInterface */
    public $_scopeConfig;
    public $_checkoutSession;
    protected $_countryHelper;
    protected $_resolver;
    public $_originalOrderSender;
    public $_cembrapayOrderSender;
    public $_cembrapayCreditmemoSender;
    public $_cembrapayInvoiceSender;
    public $_cembrapayLogger;
    public $_objectManager;
    public $_configLoader;
    public $_customerMetadata;

    /**
     * @var \Psr\Log\LoggerInterface
     */
    public $_loggerPsr;

    /**
     * @var \CembraPayCheckout\CembraPayCheckoutCore\Helper\Api\CembraPayCommunicator
     */
    public $_communicator;

    public function getMethodsMapping()
    {
        $methods = Array(
            self::$CEMBRAPAYINVOICE => Array(
                "value" => self::$CEMBRAPAYINVOICE,
                "name" => $this->_scopeConfig->getValue("cembrapayinvoicesettings/cembrapaycheckout_invoice_partial/name", ScopeInterface::SCOPE_STORE),
                "link" => $this->_scopeConfig->getValue("cembrapayinvoicesettings/cembrapaycheckout_invoice_partial/link", ScopeInterface::SCOPE_STORE)
            ),
            self::$SINGLEINVOICE => Array(
                "value" => self::$SINGLEINVOICE,
                "name" => $this->_scopeConfig->getValue("cembrapayinvoicesettings/cembrapaycheckout_single_invoice/name", ScopeInterface::SCOPE_STORE),
                "link" => $this->_scopeConfig->getValue("cembrapayinvoicesettings/cembrapaycheckout_single_invoice/link", ScopeInterface::SCOPE_STORE)
            ),
        );
        return $methods;
    }

    function saveLog($request, $response, $status, $type,
                     $firstName, $lastName, $requestId,
                     $postcode, $town, $country, $street1, $transactionId, $orderId)
    {
        $json_string1 = json_decode($request);
        if ($json_string1 == null) {
            $json_string11 = $request;
        } else {
            $json_string11 = json_encode($json_string1, JSON_PRETTY_PRINT);
        }
        $json_string2 = json_decode($response);
        if ($json_string2 == null) {
            $json_string22 = $response;
        } else {
            $json_string22 = json_encode($json_string2, JSON_PRETTY_PRINT);
        }
        $data = array('firstname' => (string)$firstName,
            'lastname' => (string)$lastName,
            'postcode' => (string)$postcode,
            'town' => (string)$town,
            'country' => (string)$country,
            'street1' => (string)$street1,
            'status' => (string)$status,
            'request_id' => (string)$requestId,
            'error' => '',
            'request' => (string)$json_string11,
            'response' => (string)$json_string22,
            'type' => (string)$type,
            'order_id' => (string)$orderId,
            'transaction_id' => (string)$transactionId,
            'ip' => $this->getClientIp());

        $this->_cembrapayLogger->log($data);
    }

    function getTransactionForOrder($orderId)
    {
        return $this->_cembrapayLogger->getAuthTransaction($orderId);
    }

    function saveS4Log(Order $order, CembraPayS4Request $request, $xml_request, $xml_response, $status, $type)
    {

        $data = array('firstname' => $order->getBillingAddress()->getFirstname(),
            'lastname' => $order->getBillingAddress()->getLastname(),
            'postcode' => '-',
            'town' => '-',
            'country' => '-',
            'street1' => '-',
            'request_id' => $request->getRequestId(),
            'status' => $status,
            'error' => '',
            'request' => $xml_request,
            'response' => $xml_response,
            'type' => $type,
            'ip' => $this->getClientIp());

        $this->_cembrapayLogger->log($data);
    }

    function saveS5Log(Order $order, CembraPayS5Request $request, $xml_request, $xml_response, $status, $type)
    {

        $data = array('firstname' => $order->getBillingAddress()->getFirstname(),
            'lastname' => $order->getBillingAddress()->getLastname(),
            'postcode' => '-',
            'town' => '-',
            'country' => '-',
            'street1' => '-',
            'request_id' => $request->getRequestId(),
            'status' => $status,
            'error' => '',
            'request' => $xml_request,
            'response' => $xml_response,
            'type' => $type,
            'ip' => $this->getClientIp());

        $this->_cembrapayLogger->log($data);
    }

    public function valueToStatus($val)
    {
        $status[0] = 'Fail to connect (status Error)';
        $status[1] = 'There are serious negative indicators (status 1)';
        $status[2] = 'All payment methods allowed (status 2)';
        $status[3] = 'Manual post-processing (currently not yet in use) (status 3)';
        $status[4] = 'Postal address is incorrect (status 4)';
        $status[5] = 'Enquiry exceeds the credit limit (the credit limit is specified in the cooperation agreement) (status 5)';
        $status[6] = 'Customer specifications not met (optional) (status 6)';
        $status[7] = 'Enquiry exceeds the net credit limit (enquiry amount plus open items exceeds credit limit) (status 7)';
        $status[8] = 'Person queried is not of creditworthy age (status 8)';
        $status[9] = 'Delivery address does not match invoice address (for payment guarantee only) (status 9)';
        $status[10] = 'Household cannot be identified at this address (status 10)';
        $status[11] = 'Country is not supported (status 11)';
        $status[12] = 'Party queried is not a natural person (status 12)';
        $status[13] = 'System is in maintenance mode (status 13)';
        $status[14] = 'Address with high fraud risk (status 14)';
        $status[15] = 'Allowance is too low (status 15)';
        if (isset($status[$val])) {
            return $status[$val];
        }
        return $status[0];
    }

    public function getClientIp()
    {
        $ipaddress = '';
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            $ipaddress = $_SERVER['HTTP_CLIENT_IP'];
        } else if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ipaddress = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } else if (!empty($_SERVER['HTTP_X_FORWARDED'])) {
            $ipaddress = $_SERVER['HTTP_X_FORWARDED'];
        } else if (!empty($_SERVER['HTTP_FORWARDED_FOR'])) {
            $ipaddress = $_SERVER['HTTP_FORWARDED_FOR'];
        } else if (!empty($_SERVER['HTTP_FORWARDED'])) {
            $ipaddress = $_SERVER['HTTP_FORWARDED'];
        } else if (!empty($_SERVER['REMOTE_ADDR'])) {
            $ipaddress = $_SERVER['REMOTE_ADDR'];
        } else {
            $ipaddress = 'UNKNOWN';
        }
        $addrMethod = $this->_scopeConfig->getValue('cembrapaycheckoutsettings/advanced/ip_detect_string', ScopeInterface::SCOPE_STORE);
        if (!empty($addrMethod) && !empty($_SERVER[$addrMethod])) {
            $ipaddress = $_SERVER[$addrMethod];
        }
        return $ipaddress;
    }

    function getCembraPayErrorMessage()
    {
        $message = $this->_scopeConfig->getValue('cembrapaycheckoutsettings/localization/cembrapaycheckout_fail_message', ScopeInterface::SCOPE_STORE);
        return $message;
    }

    public function saveStatusToOrder(Order $order)
    {
        $order->addStatusHistoryComment('<b>CembraPay Checkout status: OK</b>');
        $order->save();
    }

    public function __construct(
        \Magento\Framework\App\Helper\Context $context,
        \Magento\Backend\Model\Menu\Filter\IteratorFactory $iteratorFactory,
        \Magento\Backend\Block\Menu $blockMenu,
        \Magento\Backend\Model\UrlInterface $url,
        \Magento\Store\Model\StoreManager $storeManager,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Magento\Directory\Model\Config\Source\Country $countryHelper,
        \Magento\Framework\Locale\Resolver $resolver,
        \CembraPayCheckout\CembraPayCheckoutCore\Helper\Api\CembraPayCommunicator $communicator,
        \CembraPayCheckout\CembraPayCheckoutCore\Helper\CembraPayOrderSender $cembrapayOrderSender,
        \CembraPayCheckout\CembraPayCheckoutCore\Helper\CembraPayCreditmemoSender $cembrapayCreditmemoSender,
        \CembraPayCheckout\CembraPayCheckoutCore\Helper\CembraPayInvoiceSender $cembrapayInvoiceSender,
        \Magento\Sales\Model\Order\Email\Sender\OrderSender $originalOrderSender,
        \CembraPayCheckout\CembraPayCheckoutCore\Helper\Api\CembraPayLogger $cembrapayLogger,
        \Magento\Framework\ObjectManagerInterface $objectManager,
        \Magento\Framework\ObjectManager\ConfigLoaderInterface $configLoader,
        \Magento\Customer\Api\CustomerMetadataInterface $customerMetadata,
        \Magento\Quote\Api\CartRepositoryInterface $quoteRepository
    )
    {

        parent::__construct($context);
        $this->_customerMetadata = $customerMetadata;
        $this->_configLoader = $configLoader;
        $this->_objectManager = $objectManager;
        $this->_cembrapayLogger = $cembrapayLogger;
        $this->_cembrapayOrderSender = $cembrapayOrderSender;
        $this->_originalOrderSender = $originalOrderSender;
        $this->_cembrapayCreditmemoSender = $cembrapayCreditmemoSender;
        $this->_cembrapayInvoiceSender = $cembrapayInvoiceSender;
        $this->_communicator = $communicator;
        $this->_resolver = $resolver;
        $this->_countryHelper = $countryHelper;
        $this->_checkoutSession = $checkoutSession;
        $this->_scopeConfig = $context->getScopeConfig();
        $this->_storeManager = $storeManager;
        $this->_iteratorFactory = $iteratorFactory;
        $this->_blockMenu = $blockMenu;
        $this->_url = $url;
        $this->quoteRepository = $quoteRepository;
    }

    function cembrapayIsStatusOk($status, $position)
    {
        try {
            $config = trim($this->_scopeConfig->getValue($position, ScopeInterface::SCOPE_STORE));
            if ($config === "") {
                return false;
            }
            $stateArray = explode(",", $this->_scopeConfig->getValue($position, ScopeInterface::SCOPE_STORE));
            if (in_array($status, $stateArray)) {
                return true;
            }
            return false;
        } catch (\Exception $e) {
            return false;
        }
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

    public function getInvoiceEnabledMethods()
    {
        $methodsAvailableInvoice = Array();
        if ($this->_scopeConfig->getValue("cembrapayinvoicesettings/cembrapaycheckout_single_invoice/active", ScopeInterface::SCOPE_STORE)) {
            $methodsAvailableInvoice[] = DataHelper::$SINGLEINVOICE;
        }

        if ($this->_scopeConfig->getValue("cembrapayinvoicesettings/cembrapaycheckout_invoice_partial/active", ScopeInterface::SCOPE_STORE)) {
            $methodsAvailableInvoice[] = DataHelper::$CEMBRAPAYINVOICE;
        }
        return $methodsAvailableInvoice;
    }

    /* @var $quote \Magento\Quote\Model\Quote */
    public function GetCreditStatus($quote, $methods) {
        if ($quote == null) {
            return true;
        }
        $objectManager = ObjectManager::getInstance();
        $state =  $objectManager->get('Magento\Framework\App\State');
        if ($state->getAreaCode() == "adminhtml") {
            //skip credit check for backend
            return true;
        }
        if ($this->_scopeConfig->getValue('cembrapaycheckoutsettings/cembrapaycheckout_setup/screeningbeforeshow', ScopeInterface::SCOPE_STORE) == '1'
            && $quote != null
            && $quote->getBillingAddress() != null) {
            $theSame = $this->_checkoutSession->getIsTheSame();
            if (!empty($theSame) && is_array($theSame)) {
                $this->_savedUser = $theSame;
            }
            $status = $this->_checkoutSession->getScreeningStatus();
            try {
                $request = $this->CreateMagentoShopRequestScreening($quote);
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
                    $CembraPayRequestName = $request->requestMsgType;
                    //  $json = "{}";
                    //   if ($request->custDetails->custType == 'C' && $this->_scopeConfig->getValue('cembrapaycheckoutsettings/cembrapaycheckout_setup/businesstobusiness',
                    //           \Magento\Store\Model\ScopeInterface::SCOPE_STORE) == '1') {
                    //       $CembraPayRequestName = "Screening request for company";
                    //       $json = $request->createRequest();
                    //   } else {
                    //       $json = $request->createRequest();
                    //   }
                    $json = $request->createRequest();
                    $cembrapayCommunicator = new CembraPayCommunicator();
                    $mode = $this->_scopeConfig->getValue('cembrapaycheckoutsettings/cembrapaycheckout_setup/currentmode', ScopeInterface::SCOPE_STORE);
                    if ($mode == 'live') {
                        $cembrapayCommunicator->setServer('live');
                    } else {
                        $cembrapayCommunicator->setServer('test');
                    }
                    $response = $cembrapayCommunicator->sendScreeningRequest($json, (int)$this->_scopeConfig->getValue('cembrapaycheckoutsettings/cembrapaycheckout_setup/timeout',
                        ScopeInterface::SCOPE_STORE),
                        $this->_scopeConfig->getValue('cembrapaycheckoutsettings/cembrapaycheckout_setup/cembrapaylogin', ScopeInterface::SCOPE_STORE),
                        $this->_scopeConfig->getValue('cembrapaycheckoutsettings/cembrapaycheckout_setup/cembrapaypassword', ScopeInterface::SCOPE_STORE));

                    if ($response) {
                        /* @var $responseRes CembraPayCheckoutScreeningResponse */
                        $responseRes = $this->screeningResponse($response);
                        $status = $responseRes->screeningDetails->allowedCembraPayPaymentMethods;
                        $this->saveLog($json, $response, $responseRes->processingStatus, $CembraPayRequestName,
                            $request->custDetails->firstName, $request->custDetails->lastName, $request->requestMsgId,
                            $request->billingAddr->postalCode, $request->billingAddr->town, $request->billingAddr->country, $request->billingAddr->addrFirstLine, $responseRes->transactionId, "-");
                    } else {
                        $this->saveLog($json, $response, "Query error", $CembraPayRequestName,
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
                    $this->_checkoutSession->setIsTheSame($this->_savedUser);
                    $this->_checkoutSession->setScreeningStatus($status);
                }
                DataHelper::$screeningStatus = $status;
                foreach ($methods as $method) {
                    foreach ($status as $st) {
                        if ($st == $method) {
                            return true;
                        }
                    }
                }
                return false;
            } catch (\Exception $e) {
                return false;
            }
        }
        return true;
    }

    public function isTheSame(CembraPayCheckoutAutRequest $request) {

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

    function CreateMagentoShopRequestOrderQuote(\Magento\Quote\Model\Quote $quote,
                                                Payment $paymentmethod,
                                                $gender_custom, $dob_custom, $pref_lang, $b2b_uid, $webshopProfile)
    {
        /*
                $request = new \CembraPayCheckout\CembraPayCheckoutCore\Helper\Api\CembraPayRequest();
                $request->setClientId($this->_scopeConfig->getValue('cembrapaycheckoutsettings/cembrapaycheckout_setup/clientid', \Magento\Store\Model\ScopeInterface::SCOPE_STORE, $webshopProfile));
                $request->setUserID($this->_scopeConfig->getValue('cembrapaycheckoutsettings/cembrapaycheckout_setup/userid', \Magento\Store\Model\ScopeInterface::SCOPE_STORE, $webshopProfile));
                $request->setPassword($this->_scopeConfig->getValue('cembrapaycheckoutsettings/cembrapaycheckout_setup/password', \Magento\Store\Model\ScopeInterface::SCOPE_STORE, $webshopProfile));
                $request->setVersion("1.00");
                try {
                    $request->setRequestEmail($this->_scopeConfig->getValue('cembrapaycheckoutsettings/cembrapaycheckout_setup/mail', \Magento\Store\Model\ScopeInterface::SCOPE_STORE, $webshopProfile));
                } catch (\Exception $e) {

                }
                $b = $quote->getCustomerDob();
                if (!empty($b)) {
                    try {
                        $dobObject = new \DateTime($b);
                        if ($dobObject != null) {
                            $request->setDateOfBirth($dobObject->format('Y-m-d'));
                        }
                    } catch (\Exception $e) {

                    }
                }

                if (!empty($dob_custom)) {
                    try {
                        $dobObject = new \DateTime($dob_custom);
                        if ($dobObject != null) {
                            $request->setDateOfBirth($dobObject->format('Y-m-d'));
                        }
                    } catch (\Exception $e) {

                    }
                }
                $gender_male_possible_prefix_array = $this->_scopeConfig->getValue('cembrapaycheckoutsettings/cembrapaycheckout_setup/gender_male_possible_prefix',
                    \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
                $gender_female_possible_prefix_array = $this->_scopeConfig->getValue('cembrapaycheckoutsettings/cembrapaycheckout_setup/gender_female_possible_prefix',
                    \Magento\Store\Model\ScopeInterface::SCOPE_STORE);

                $gender_male_possible_prefix = explode(";", strtolower($gender_male_possible_prefix_array));
                $gender_female_possible_prefix = explode(";", strtolower($gender_female_possible_prefix_array));

                $g = $quote->getCustomerGender();
                $request->setGender('0');
                if ($this->_customerMetadata->getAttributeMetadata('gender')->isVisible()) {
                    if (!empty($g)) {
                        if ($g == '1') {
                            $request->setGender('1');
                        } else if ($g == '2') {
                            $request->setGender('2');
                        }
                    }
                }
                if ($this->_customerMetadata->getAttributeMetadata('prefix')->isVisible()) {
                    if (in_array(strtolower($quote->getBillingAddress()->getPrefix()), $gender_male_possible_prefix)) {
                        $request->setGender('1');
                    } else if (in_array(strtolower($quote->getBillingAddress()->getPrefix()), $gender_female_possible_prefix)) {
                        $request->setGender('2');
                    }
                }

                if (!empty($gender_custom)) {
                    if (in_array(strtolower($gender_custom), $gender_male_possible_prefix)) {
                        $request->setGender('1');
                    } else if (in_array(strtolower($gender_custom), $gender_female_possible_prefix)) {
                        $request->setGender('2');
                    }
                }

                $billingStreet = $quote->getBillingAddress()->getStreet();
                $billingStreet = implode("", $billingStreet);
                $requestId = uniqid((String)$quote->getEntityId() . "_");
                $request->setRequestId($requestId);
                $reference = $quote->getCustomerId();
                if (empty($reference)) {
                    $request->setCustomerReference("guest_" . $quote->getId());
                } else {
                    $request->setCustomerReference($quote->getCustomerId());
                }
                $request->setFirstName((String)$quote->getBillingAddress()->getFirstname());
                $request->setLastName((String)$quote->getBillingAddress()->getLastname());
                $request->setFirstLine(trim((String)$billingStreet));
                $request->setCountryCode(strtoupper($quote->getBillingAddress()->getCountryId()));
                $request->setPostCode((String)$quote->getBillingAddress()->getPostcode());
                $request->setTown((String)$quote->getBillingAddress()->getCity());
                $request->setFax((String)trim($quote->getBillingAddress()->getFax(), '-'));
                if (!empty($pref_lang)) {
                    $request->setLanguage($pref_lang);
                } else {
                    $request->setLanguage((String)substr($this->_resolver->getLocale(), 0, 2));
                }

                if ($quote->getBillingAddress()->getCompany()) {
                    $request->setCompanyName1($quote->getBillingAddress()->getCompany());
                }

                $request->setTelephonePrivate((String)trim($quote->getBillingAddress()->getTelephone(), '-'));
                $request->setEmail((String)$quote->getBillingAddress()->getEmail());

                $extraInfo["Name"] = 'ORDERCLOSED';
                $extraInfo["Value"] = 'NO';
                $request->setExtraInfo($extraInfo);

                $extraInfo["Name"] = 'ORDERAMOUNT';
                $extraInfo["Value"] = number_format($quote->getGrandTotal(), 2, '.', '');
                $request->setExtraInfo($extraInfo);

                $extraInfo["Name"] = 'ORDERCURRENCY';
                $extraInfo["Value"] = $quote->getQuoteCurrencyCode();
                $request->setExtraInfo($extraInfo);

                $extraInfo["Name"] = 'IP';
                $extraInfo["Value"] = $this->getClientIp();
                $request->setExtraInfo($extraInfo);

                if (!empty($b2b_uid)) {
                    $extraInfo["Name"] = 'REGISTERNUMBER';
                    $extraInfo["Value"] = $b2b_uid;
                    $request->setExtraInfo($extraInfo);
                }

                $sedId = $this->_checkoutSession->getTmxSession();
                if ($this->_scopeConfig->getValue('cembrapaycheckoutsettings/cembrapaycheckout_setup/tmxenabled', \Magento\Store\Model\ScopeInterface::SCOPE_STORE) == '1' && !empty($sedId)) {
                    $extraInfo["Name"] = 'DEVICE_FINGERPRINT_ID';
                    $extraInfo["Value"] = $sedId;
                    $request->setExtraInfo($extraInfo);
                }

                if ($paymentmethod->getAdditionalInformation('payment_send') == 'postal') {
                    $extraInfo["Name"] = 'PAPER_INVOICE';
                    $extraInfo["Value"] = 'YES';
                    $request->setExtraInfo($extraInfo);
                }

                if (!$quote->isVirtual()) {
                    $shippingStreet = $quote->getShippingAddress()->getStreet();
                    $shippingStreet = implode("", $shippingStreet);

                    $extraInfo["Name"] = 'DELIVERY_FIRSTLINE';
                    $extraInfo["Value"] = trim((String)$shippingStreet);
                    $request->setExtraInfo($extraInfo);

                    $extraInfo["Name"] = 'DELIVERY_HOUSENUMBER';
                    $extraInfo["Value"] = '';
                    $request->setExtraInfo($extraInfo);

                    $extraInfo["Name"] = 'DELIVERY_COUNTRYCODE';
                    $extraInfo["Value"] = strtoupper($quote->getShippingAddress()->getCountryId());
                    $request->setExtraInfo($extraInfo);

                    $extraInfo["Name"] = 'DELIVERY_POSTCODE';
                    $extraInfo["Value"] = $quote->getShippingAddress()->getPostcode();
                    $request->setExtraInfo($extraInfo);

                    $extraInfo["Name"] = 'DELIVERY_TOWN';
                    $extraInfo["Value"] = $quote->getShippingAddress()->getCity();
                    $request->setExtraInfo($extraInfo);

                    if ($quote->getShippingAddress()->getCompany() != '' && $this->_scopeConfig->getValue('cembrapaycheckoutsettings/cembrapaycheckout_setup/businesstobusiness', \Magento\Store\Model\ScopeInterface::SCOPE_STORE) == '1') {

                        $extraInfo["Name"] = 'DELIVERY_COMPANYNAME';
                        $extraInfo["Value"] = $quote->getShippingAddress()->getCompany();
                        $request->setExtraInfo($extraInfo);

                        $extraInfo["Name"] = 'DELIVERY_FIRSTNAME';
                        $extraInfo["Value"] = '';
                        $request->setExtraInfo($extraInfo);

                        $extraInfo["Name"] = 'DELIVERY_LASTNAME';
                        $extraInfo["Value"] = $quote->getShippingAddress()->getCompany();
                        $request->setExtraInfo($extraInfo);

                    } else {

                        $extraInfo["Name"] = 'DELIVERY_FIRSTNAME';
                        $extraInfo["Value"] = $quote->getShippingAddress()->getFirstname();
                        $request->setExtraInfo($extraInfo);

                        $extraInfo["Name"] = 'DELIVERY_LASTNAME';
                        $extraInfo["Value"] = $quote->getShippingAddress()->getLastname();
                        $request->setExtraInfo($extraInfo);
                    }
                }

                $extraInfo["Name"] = 'PP_TRANSACTION_NUMBER';
                $extraInfo["Value"] = $requestId;
                $request->setExtraInfo($extraInfo);

                $extraInfo["Name"] = 'PAYMENTMETHOD';
                $extraInfo["Value"] = $this->mapMethod($paymentmethod->getAdditionalInformation('payment_plan'));
                $request->setExtraInfo($extraInfo);

                $extraInfo["Name"] = 'REPAYMENTTYPE';
                $extraInfo["Value"] = $this->mapRepayment($paymentmethod->getAdditionalInformation('payment_plan'));
                $request->setExtraInfo($extraInfo);

                $extraInfo["Name"] = 'RISKOWNER';
                $extraInfo["Value"] = 'IJ';
                $request->setExtraInfo($extraInfo);

                $extraInfo["Name"] = 'CONNECTIVTY_MODULE';
                $extraInfo["Value"] = 'CembraPay Checkout Magento 2 module 1.7.4';
                $request->setExtraInfo($extraInfo);
                return $request;
        */
        return null;
    }

    function CreateMagentoShopRequestPaid(Order $order,
                                          Order\Payment $paymentmethod,
                                          $gender_custom, $dob_custom, $transaction, $riskOwner, $pref_lang, $b2b_uid, $webshopProfile)
    {

        $request = new \CembraPayCheckout\CembraPayCheckoutCore\Helper\Api\CembraPayRequest();
        $request->setClientId($this->_scopeConfig->getValue('cembrapaycheckoutsettings/cembrapaycheckout_setup/clientid', ScopeInterface::SCOPE_STORE, $webshopProfile));
        $request->setUserID($this->_scopeConfig->getValue('cembrapaycheckoutsettings/cembrapaycheckout_setup/userid', ScopeInterface::SCOPE_STORE, $webshopProfile));
        $request->setPassword($this->_scopeConfig->getValue('cembrapaycheckoutsettings/cembrapaycheckout_setup/password', ScopeInterface::SCOPE_STORE, $webshopProfile));
        $request->setVersion("1.00");
        try {
            $request->setRequestEmail($this->_scopeConfig->getValue('cembrapaycheckoutsettings/cembrapaycheckout_setup/mail', ScopeInterface::SCOPE_STORE, $webshopProfile));
        } catch (\Exception $e) {

        }
        $b = $order->getCustomerDob();
        if (!empty($b)) {
            try {
                $dobObject = new \DateTime($b);
                if ($dobObject != null) {
                    $request->setDateOfBirth($dobObject->format('Y-m-d'));
                }
            } catch (\Exception $e) {

            }
        }

        if (!empty($dob_custom)) {
            try {
                $dobObject = new \DateTime($dob_custom);
                if ($dobObject != null) {
                    $request->setDateOfBirth($dobObject->format('Y-m-d'));
                }
            } catch (\Exception $e) {

            }
        }

        $gender_male_possible_prefix_array = $this->_scopeConfig->getValue('cembrapaycheckoutsettings/cembrapaycheckout_setup/gender_male_possible_prefix',
            ScopeInterface::SCOPE_STORE);
        $gender_female_possible_prefix_array = $this->_scopeConfig->getValue('cembrapaycheckoutsettings/cembrapaycheckout_setup/gender_female_possible_prefix',
            ScopeInterface::SCOPE_STORE);

        $gender_male_possible_prefix = explode(";", strtolower($gender_male_possible_prefix_array));
        $gender_female_possible_prefix = explode(";", strtolower($gender_female_possible_prefix_array));

        $g = $order->getCustomerGender();
        $request->setGender('0');
        if ($this->_customerMetadata->getAttributeMetadata('gender')->isVisible()) {
            if (!empty($g)) {
                if ($g == '1') {
                    $request->setGender('1');
                } else if ($g == '2') {
                    $request->setGender('2');
                }
            }
        }
        if ($this->_customerMetadata->getAttributeMetadata('prefix')->isVisible()) {
            if (in_array(strtolower($order->getBillingAddress()->getPrefix()), $gender_male_possible_prefix)) {
                $request->setGender('1');
            } else if (in_array(strtolower($order->getBillingAddress()->getPrefix()), $gender_female_possible_prefix)) {
                $request->setGender('2');
            }
        }

        if (!empty($gender_custom)) {
            if (in_array(strtolower($gender_custom), $gender_male_possible_prefix)) {
                $request->setGender('1');
            } else if (in_array(strtolower($gender_custom), $gender_female_possible_prefix)) {
                $request->setGender('2');
            }
        }
        $billingStreet = $order->getBillingAddress()->getStreet();
        $billingStreet = implode("", $billingStreet);
        $requestId = uniqid((String)$order->getBillingAddress()->getEntityId() . "_");
        $request->setRequestId($requestId);
        $reference = $order->getCustomerId();
        if (empty($reference)) {
            $request->setCustomerReference("guest_" . $order->getId());
        } else {
            $request->setCustomerReference($order->getCustomerId());
        }
        $request->setFirstName((String)$order->getBillingAddress()->getFirstname());
        $request->setLastName((String)$order->getBillingAddress()->getLastname());
        //quote.billingAddress().street[0] + ", " + quote.billingAddress().city + ", " + quote.billingAddress().postcode
        $request->setFirstLine(trim((String)$billingStreet));
        $request->setCountryCode(strtoupper($order->getBillingAddress()->getCountryId()));
        $request->setPostCode((String)$order->getBillingAddress()->getPostcode());
        $request->setTown((String)$order->getBillingAddress()->getCity());
        $request->setFax((String)trim($order->getBillingAddress()->getFax(), '-'));

        if (!empty($pref_lang)) {
            $request->setLanguage($pref_lang);
        } else {
            $request->setLanguage((String)substr($this->_resolver->getLocale(), 0, 2));
        }

        if ($order->getBillingAddress()->getCompany()) {
            $request->setCompanyName1($order->getBillingAddress()->getCompany());
        }

        $request->setTelephonePrivate((String)trim($order->getBillingAddress()->getTelephone(), '-'));
        $request->setEmail((String)$order->getBillingAddress()->getEmail());

        if (!empty($transaction)) {
            $extraInfo["Name"] = 'TRANSACTIONNUMBER';
            $extraInfo["Value"] = $transaction;
            $request->setExtraInfo($extraInfo);
        }
        $txid_extrainfo = $this->_scopeConfig->getValue('cembrapaycheckoutsettings/cembrapaycheckout_setup/txid_extrainfo',
            ScopeInterface::SCOPE_STORE);

        if (!empty($transaction) && $txid_extrainfo == 1) {
            $extraInfo["Name"] = 'ICP-FLD-CUSTOM1';
            $extraInfo["Value"] = $transaction;
            $request->setExtraInfo($extraInfo);
        }

        $extraInfo["Name"] = 'ORDERCLOSED';
        $extraInfo["Value"] = 'YES';
        $request->setExtraInfo($extraInfo);

        $extraInfo["Name"] = 'ORDERAMOUNT';
        $extraInfo["Value"] = number_format($order->getGrandTotal(), 2, '.', '');
        $request->setExtraInfo($extraInfo);

        $extraInfo["Name"] = 'ORDERCURRENCY';
        $extraInfo["Value"] = $order->getOrderCurrencyCode();
        $request->setExtraInfo($extraInfo);

        $extraInfo["Name"] = 'IP';
        $extraInfo["Value"] = $this->getClientIp();
        $request->setExtraInfo($extraInfo);

        if (!empty($b2b_uid)) {
            $extraInfo["Name"] = 'REGISTERNUMBER';
            $extraInfo["Value"] = $b2b_uid;
            $request->setExtraInfo($extraInfo);
        }

        $sedId = $this->_checkoutSession->getTmxSession();
        if ($this->_scopeConfig->getValue('cembrapaycheckoutsettings/cembrapaycheckout_setup/tmxenabled', ScopeInterface::SCOPE_STORE) == '1' && !empty($sedId)) {
            $extraInfo["Name"] = 'DEVICE_FINGERPRINT_ID';
            $extraInfo["Value"] = $sedId;
            $request->setExtraInfo($extraInfo);
        }

        if ($paymentmethod->getAdditionalInformation('payment_send') == 'postal') {
            $extraInfo["Name"] = 'PAPER_INVOICE';
            $extraInfo["Value"] = 'YES';
            $request->setExtraInfo($extraInfo);
        }

        if ($order->canShip()) {

            $shippingStreet = $order->getShippingAddress()->getStreet();
            $shippingStreet = implode("", $shippingStreet);

            $extraInfo["Name"] = 'DELIVERY_FIRSTLINE';
            $extraInfo["Value"] = trim($shippingStreet);
            $request->setExtraInfo($extraInfo);

            $extraInfo["Name"] = 'DELIVERY_HOUSENUMBER';
            $extraInfo["Value"] = '';
            $request->setExtraInfo($extraInfo);

            $extraInfo["Name"] = 'DELIVERY_COUNTRYCODE';
            $extraInfo["Value"] = strtoupper($order->getShippingAddress()->getCountryId());
            $request->setExtraInfo($extraInfo);

            $extraInfo["Name"] = 'DELIVERY_POSTCODE';
            $extraInfo["Value"] = $order->getShippingAddress()->getPostcode();
            $request->setExtraInfo($extraInfo);

            $extraInfo["Name"] = 'DELIVERY_TOWN';
            $extraInfo["Value"] = $order->getShippingAddress()->getCity();
            $request->setExtraInfo($extraInfo);

            if ($order->getShippingAddress()->getCompany() != '' && $this->_scopeConfig->getValue('cembrapaycheckoutsettings/cembrapaycheckout_setup/businesstobusiness', ScopeInterface::SCOPE_STORE) == '1') {

                $extraInfo["Name"] = 'DELIVERY_COMPANYNAME';
                $extraInfo["Value"] = $order->getShippingAddress()->getCompany();
                $request->setExtraInfo($extraInfo);

                $extraInfo["Name"] = 'DELIVERY_FIRSTNAME';
                $extraInfo["Value"] = '';
                $request->setExtraInfo($extraInfo);

                $extraInfo["Name"] = 'DELIVERY_LASTNAME';
                $extraInfo["Value"] = $order->getShippingAddress()->getCompany();
                $request->setExtraInfo($extraInfo);

            } else {

                $extraInfo["Name"] = 'DELIVERY_FIRSTNAME';
                $extraInfo["Value"] = $order->getShippingAddress()->getFirstname();
                $request->setExtraInfo($extraInfo);

                $extraInfo["Name"] = 'DELIVERY_LASTNAME';
                $extraInfo["Value"] = $order->getShippingAddress()->getLastname();
                $request->setExtraInfo($extraInfo);

            }
        }

        $extraInfo["Name"] = 'ORDERID';
        $extraInfo["Value"] = $order->getIncrementId();
        $request->setExtraInfo($extraInfo);

        $extraInfo["Name"] = 'PAYMENTMETHOD';
        $extraInfo["Value"] = $this->mapMethod($paymentmethod->getAdditionalInformation('payment_plan'));
        $request->setExtraInfo($extraInfo);

        $extraInfo["Name"] = 'REPAYMENTTYPE';
        $extraInfo["Value"] = $this->mapRepayment($paymentmethod->getAdditionalInformation('payment_plan'));
        $request->setExtraInfo($extraInfo);

        if ($riskOwner != "") {
            $extraInfo["Name"] = 'RISKOWNER';
            $extraInfo["Value"] = $riskOwner;
            $request->setExtraInfo($extraInfo);
        }
        $extraInfo["Name"] = 'CONNECTIVTY_MODULE';
        $extraInfo["Value"] = 'CembraPay Checkout Magento 2 module 1.7.4';
        $request->setExtraInfo($extraInfo);

        return $request;

    }

    function CreateMagentoShopRequestSettlePaid(Order $order, Invoice $invoice, Order\Payment $payment, $webshopProfile, $tx)
    {
        $request = new CembraPayCheckoutSettleRequest();
        $request->merchantId = $this->_scopeConfig->getValue('cembrapaycheckoutsettings/cembrapaycheckout_setup/merchantid', ScopeInterface::SCOPE_STORE, $webshopProfile);
        $request->requestMsgType = self::$MESSAGE_SET;
        $request->requestMsgId = CembraPayCheckoutAutRequest::GUID();
        $request->requestMsgDateTime = CembraPayCheckoutAutRequest::Date();
        $request->transactionId = $tx;
        $request->merchantOrderRef = $order->getRealOrderId();
        $request->amount = number_format($order->getGrandTotal(), 2, '.', '') * 100;
        $request->currency = $order->getOrderCurrencyCode();
        $request->settlementDetails->isFinal = $payment->isCaptureFinal($order->getGrandTotal());
        $request->settlementDetails->merchantInvoiceRef = $invoice->getIncrementId();
        return $request;
        /*

        $request = new CembraPayS4Request();
        $request->setClientId($this->_scopeConfig->getValue('cembrapaycheckoutsettings/cembrapaycheckout_setup/clientid', ScopeInterface::SCOPE_STORE, $webshopProfile));
        $request->setUserID($this->_scopeConfig->getValue('cembrapaycheckoutsettings/cembrapaycheckout_setup/userid', ScopeInterface::SCOPE_STORE, $webshopProfile));
        $request->setPassword($this->_scopeConfig->getValue('cembrapaycheckoutsettings/cembrapaycheckout_setup/password', ScopeInterface::SCOPE_STORE, $webshopProfile));
        $request->setVersion("1.00");
        try {
            $request->setRequestEmail($this->_scopeConfig->getValue('cembrapaycheckoutsettings/cembrapaycheckout_setup/mail', ScopeInterface::SCOPE_STORE, $webshopProfile));
        } catch (\Exception $e) {

        }

        $request->setRequestId(uniqid((String)$order->getIncrementId() . "_"));

        $request->setOrderId($order->getIncrementId());
        $reference = $order->getCustomerId();
        if (empty($reference)) {
            $request->setClientRef("guest_" . $order->getId());
        } else {
            $request->setClientRef($order->getCustomerId());
        }
        $orderDateString = \Zend_Locale_Format::getDate(
            $order->getCreatedAt(),
            array(
                'date_format' => \Magento\Framework\Stdlib\DateTime::DATE_INTERNAL_FORMAT,
            )
        );
        $request->setTransactionDate($orderDateString["year"] . "-" . $orderDateString["month"] . '-' . $orderDateString["day"]);
        $request->setTransactionAmount(number_format($invoice->getGrandTotal(), 2, '.', ''));
        $request->setTransactionCurrency($order->getOrderCurrencyCode());
        $request->setAdditional1("INVOICE");
        $request->setAdditional2($invoice->getIncrementId());
        $request->setOpenBalance(number_format($invoice->getGrandTotal(), 2, '.', ''));

        return $request;
        */
    }

    function nullToString($str)
    {
        if (!isset($str)) {
            return "";
        }
        return $str;
    }

    function CreateMagentoShopRequestScreening(\Magento\Quote\Model\Quote $quote)
    {

        $request = new CembraPayCheckoutAutRequest();
        $request->merchantId = $this->_scopeConfig->getValue('cembrapaycheckoutsettings/cembrapaycheckout_setup/merchantid', ScopeInterface::SCOPE_STORE);
        $request->requestMsgType = self::$MESSAGE_SCREENING;
        $request->requestMsgId = CembraPayCheckoutAutRequest::GUID();
        $request->requestMsgDateTime = CembraPayCheckoutAutRequest::Date();
        $request->merchantOrderRef = null;
        $request->amount = number_format($quote->getGrandTotal(), 2, '.', '') * 100;
        $request->currency = $quote->getQuoteCurrencyCode();

        $reference = $quote->getCustomerId();
        if (empty($reference)) {
            $request->custDetails->merchantCustRef = "guest_" . $quote->getId();
            $request->custDetails->loggedIn = false;
        } else {
            $request->custDetails->merchantCustRef = (String)$quote->getCustomerId();
            $request->custDetails->loggedIn = true;
        }
        if ($quote->getBillingAddress()->getCompany()
            && $this->_scopeConfig->getValue('cembrapaycheckoutsettings/cembrapaycheckout_setup/businesstobusiness', ScopeInterface::SCOPE_STORE) == '1') {
            $request->custDetails->custType = self::$CUSTOMER_BUSINESS;
        } else {
            $request->custDetails->custType = self::$CUSTOMER_PRIVATE;
        }
        $request->custDetails->firstName = (String)$quote->getBillingAddress()->getFirstname();
        $request->custDetails->lastName = (String)$quote->getBillingAddress()->getLastname();
        $request->custDetails->language = (String)substr($this->_resolver->getLocale(), 0, 2);
        $b = $quote->getCustomerDob();
        if (!empty($b)) {
            try {
                $dobObject = new \DateTime($b);
                if ($dobObject != null) {
                    $request->custDetails->dateOfBirth = $dobObject->format('Y-m-d');
                }
            } catch (\Exception $e) {

            }
        }
        $g = $quote->getCustomerGender();
        $request->custDetails->salutation = self::$GENTER_UNKNOWN;
        $genderEntity = null;
        try {
            $genderEntity = $this->_customerMetadata->getAttributeMetadata('gender');
        } catch (\Exception $e) {
        }
        if ($genderEntity != null && $genderEntity->isVisible()) {
            if (!empty($g)) {
                if ($g == '1') {
                    $request->custDetails->salutation = self::$GENTER_MALE;
                } else if ($g == '2') {
                    $request->custDetails->salutation = self::$GENTER_FEMALE;
                }
            }
        }

        $gender_male_possible_prefix_array = $this->_scopeConfig->getValue('cembrapaycheckoutsettings/cembrapaycheckout_setup/gender_male_possible_prefix',
            ScopeInterface::SCOPE_STORE);
        $gender_female_possible_prefix_array = $this->_scopeConfig->getValue('cembrapaycheckoutsettings/cembrapaycheckout_setup/gender_female_possible_prefix',
            ScopeInterface::SCOPE_STORE);
        $gender_male_possible_prefix = explode(";", strtolower($gender_male_possible_prefix_array));
        $gender_female_possible_prefix = explode(";", strtolower($gender_female_possible_prefix_array));
        if ($genderEntity != null && $genderEntity->isVisible()) {
            if (in_array(strtolower($quote->getBillingAddress()->getPrefix()), $gender_male_possible_prefix)) {
                $request->custDetails->salutation = self::$GENTER_MALE;
            } else if (in_array(strtolower($quote->getBillingAddress()->getPrefix()), $gender_female_possible_prefix)) {
                $request->custDetails->salutation = self::$GENTER_FEMALE;
            }
        }

        $billingStreet = $quote->getBillingAddress()->getStreet();
        $billingStreet = implode("", $billingStreet);

        $request->billingAddr->addrFirstLine = (String)$billingStreet;
        $request->billingAddr->postalCode = (String)$quote->getBillingAddress()->getPostcode();
        $request->billingAddr->town = (String)$quote->getBillingAddress()->getCity();
        $request->billingAddr->country = strtoupper($quote->getBillingAddress()->getCountryId());

        $request->custContacts->phoneMobile = (String)trim($quote->getBillingAddress()->getTelephone(), '-');
        $request->custContacts->phoneBusiness = (String)trim($quote->getBillingAddress()->getTelephone(), '-');
        $request->custContacts->phonePrivate = (String)trim($quote->getBillingAddress()->getTelephone(), '-');
        $request->custContacts->email = (String)$quote->getBillingAddress()->getEmail();

        if (!$quote->isVirtual()) {
            $request->deliveryDetails->deliveryDetailsDifferent = false;
            $request->deliveryDetails->deliveryMethod = self::$DELIVERY_POST;
            $request->deliveryDetails->deliveryFirstName = $this->nullToString($quote->getShippingAddress()->getFirstname());
            $request->deliveryDetails->deliverySecondName = $this->nullToString($quote->getShippingAddress()->getLastname());
            if ($quote->getShippingAddress()->getCompany() != '' && $this->_scopeConfig->getValue('cembrapaycheckoutsettings/cembrapaycheckout_setup/businesstobusiness', ScopeInterface::SCOPE_STORE) == '1') {
                $request->deliveryDetails->deliveryCompanyName = $this->nullToString($quote->getShippingAddress()->getCompany());
            }
            $request->deliveryDetails->deliverySalutation = null;

            $shippingStreet = $quote->getShippingAddress()->getStreet();
            $shippingStreet = implode("", $shippingStreet);

            $request->deliveryDetails->deliveryAddrFirstLine = trim((String)$shippingStreet);
            $request->deliveryDetails->deliveryAddrPostalCode = $this->nullToString($quote->getShippingAddress()->getPostcode());
            $request->deliveryDetails->deliveryAddrTown = $this->nullToString($quote->getShippingAddress()->getCity());
            $request->deliveryDetails->deliveryAddrCountry = strtoupper($quote->getShippingAddress()->getCountryId());

        } else {
            $request->deliveryDetails->deliveryDetailsDifferent = false;
            $request->deliveryDetails->deliveryMethod = self::$DELIVERY_VIRTUAL;
        }

        // $request->order->basketItemsGoogleTaxonomies = null;
        //$request->order->basketItemsPrices = null;

        $sedId = $this->_checkoutSession->getTmxSession();
        if ($this->_scopeConfig->getValue('cembrapaycheckoutsettings/cembrapaycheckout_setup/tmxenabled', ScopeInterface::SCOPE_STORE) == '1' && !empty($sedId)) {
            $request->sessionInfo->fingerPrint = $sedId;
        }


        //$request->byjunoDetails->byjunoProductType = "SINGLE-INVOICE";
        //$request->byjunoDetails->invoiceDeliveryType = "EMAIL";

        $request->merchantDetails->transactionChannel = "WEB";
        $request->merchantDetails->integrationModule = "CembraPay Checkout Magento 2 module 0.0.1";

        return $request;
    }

    function screeningResponse($response)
    {

        $responseObject = json_decode($response);
        $result = new CembraPayCheckoutScreeningResponse();
        if (empty($responseObject->processingStatus)) {
            $result->processingStatus = self::$REQUEST_ERROR;
        } else {
            if ($responseObject->processingStatus == self::$SCREENING_OK) {
                $result->merchantCustRef = $responseObject->merchantCustRef;
                $result->processingStatus = $responseObject->processingStatus;
                $result->replyMsgDateTime = $responseObject->replyMsgDateTime;
                $result->replyMsgId = $responseObject->replyMsgId;
                $result->requestMsgDateTime = $responseObject->requestMsgDateTime;
                $result->requestMsgId = $responseObject->requestMsgId;
                $result->transactionId = $responseObject->transactionId;
                if (!empty($responseObject->screeningDetails) && !empty(!empty($responseObject->screeningDetails->allowedCembraPayPaymentMethods))) {
                    $result->screeningDetails->allowedCembraPayPaymentMethods = $responseObject->screeningDetails->allowedCembraPayPaymentMethods;
                }
            } else {
                $result->processingStatus = $responseObject->processingStatus;
            }
        }
        return $result;
    }

    public function createMagentoShopRequestAuthorization(Order $order,
                                                          Order\Payment $paymentMethod,
                                                          $gender_custom, $dob_custom, $pref_lang, $b2b_uid, $webShopProfile)
    {

        $request = new CembraPayCheckoutAutRequest();
        $request->merchantId = $this->_scopeConfig->getValue('cembrapaycheckoutsettings/cembrapaycheckout_setup/merchantid', ScopeInterface::SCOPE_STORE, $webShopProfile);
        $request->requestMsgType = self::$MESSAGE_AUTH;
        $request->requestMsgId = CembraPayCheckoutAutRequest::GUID();
        $request->requestMsgDateTime = CembraPayCheckoutAutRequest::Date();
        $request->merchantOrderRef = $order->getRealOrderId();
        $request->amount = number_format($order->getGrandTotal(), 2, '.', '') * 100;
        $request->currency = $order->getOrderCurrencyCode();

        $reference = $order->getCustomerId();
        if (empty($reference)) {
            $request->custDetails->merchantCustRef = "guest_" . $order->getId();
            $request->custDetails->loggedIn = false;
        } else {
            $request->custDetails->merchantCustRef = (String)$order->getCustomerId();
            $request->custDetails->loggedIn = true;
        }
        if ($order->getBillingAddress()->getCompany() && $this->_scopeConfig->getValue('cembrapaycheckoutsettings/cembrapaycheckout_setup/businesstobusiness', ScopeInterface::SCOPE_STORE, $webShopProfile) == '1') {
            $request->custDetails->custType = self::$CUSTOMER_BUSINESS;
        } else {
            $request->custDetails->custType = self::$CUSTOMER_PRIVATE;
        }
        $request->custDetails->firstName = (String)$order->getBillingAddress()->getFirstname();
        $request->custDetails->lastName = (String)$order->getBillingAddress()->getLastname();
        if (!empty($pref_lang)) {
            $request->custDetails->language = (String)$pref_lang;
        } else {
            $request->custDetails->language = (String)substr($this->_resolver->getLocale(), 0, 2);
        }

        if (!empty($b2b_uid)) {
            $request->custDetails->companyRegNum = (String)$b2b_uid;
        }

        $b = $order->getCustomerDob();
        if (!empty($b)) {
            try {
                $dobObject = new \DateTime($b);
                if ($dobObject != null) {
                    $request->custDetails->dateOfBirth = $dobObject->format('Y-m-d');
                }
            } catch (\Exception $e) {

            }
        }

        if (!empty($dob_custom)) {
            try {
                $dobObject = new \DateTime($dob_custom);
                if ($dobObject != null) {
                    $request->custDetails->dateOfBirth = $dobObject->format('Y-m-d');
                }
            } catch (\Exception $e) {

            }
        }

        $g = $order->getCustomerGender();
        $request->custDetails->salutation = self::$GENTER_UNKNOWN;

        $genderEntity = null;
        try {
            $genderEntity = $this->_customerMetadata->getAttributeMetadata('gender');
        } catch (\Exception $e) {
        }

        if ($genderEntity != null && $genderEntity->isVisible()) {
            if (!empty($g)) {
                if ($g == '1') {
                    $request->custDetails->salutation = self::$GENTER_MALE;
                } else if ($g == '2') {
                    $request->custDetails->salutation = self::$GENTER_FEMALE;
                }
            }
        }

        $gender_male_possible_prefix_array = $this->_scopeConfig->getValue('cembrapaycheckoutsettings/cembrapaycheckout_setup/gender_male_possible_prefix',
            ScopeInterface::SCOPE_STORE, $webShopProfile);
        $gender_female_possible_prefix_array = $this->_scopeConfig->getValue('cembrapaycheckoutsettings/cembrapaycheckout_setup/gender_female_possible_prefix',
            ScopeInterface::SCOPE_STORE, $webShopProfile);
        $gender_male_possible_prefix = explode(";", strtolower($gender_male_possible_prefix_array));
        $gender_female_possible_prefix = explode(";", strtolower($gender_female_possible_prefix_array));
        if ($genderEntity != null && $genderEntity->isVisible()) {
            if (in_array(strtolower($order->getBillingAddress()->getPrefix()), $gender_male_possible_prefix)) {
                $request->custDetails->salutation = self::$GENTER_MALE;
            } else if (in_array(strtolower($order->getBillingAddress()->getPrefix()), $gender_female_possible_prefix)) {
                $request->custDetails->salutation = self::$GENTER_FEMALE;
            }
        }

        if (!empty($gender_custom)) {
            if (in_array(strtolower($gender_custom), $gender_male_possible_prefix)) {
                $request->custDetails->salutation = self::$GENTER_MALE;
            } else if (in_array(strtolower($gender_custom), $gender_female_possible_prefix)) {
                $request->custDetails->salutation = self::$GENTER_FEMALE;
            }
        }

        $billingStreet = $order->getBillingAddress()->getStreet();
        $billingStreet = implode("", $billingStreet);

        $request->billingAddr->addrFirstLine = (String)$billingStreet;
        $request->billingAddr->postalCode = (String)$order->getBillingAddress()->getPostcode();
        $request->billingAddr->town = (String)$order->getBillingAddress()->getCity();
        $request->billingAddr->country = strtoupper($order->getBillingAddress()->getCountryId());

        $request->custContacts->phoneMobile = (String)trim($order->getBillingAddress()->getTelephone(), '-');
        $request->custContacts->phonePrivate = (String)trim($order->getBillingAddress()->getTelephone(), '-');
        $request->custContacts->phoneBusiness = (String)trim($order->getBillingAddress()->getTelephone(), '-');
        $request->custContacts->email = (String)$order->getBillingAddress()->getEmail();

        if (!$order->getIsVirtual()) {
            $request->deliveryDetails->deliveryDetailsDifferent = false;
            $request->deliveryDetails->deliveryMethod = self::$DELIVERY_POST;
            $request->deliveryDetails->deliveryFirstName = $this->nullToString($order->getShippingAddress()->getFirstname());
            $request->deliveryDetails->deliverySecondName = $this->nullToString($order->getShippingAddress()->getLastname());
            if ($order->getShippingAddress()->getCompany() != '' && $this->_scopeConfig->getValue('cembrapaycheckoutsettings/cembrapaycheckout_setup/businesstobusiness', ScopeInterface::SCOPE_STORE, $webShopProfile) == '1') {
                $request->deliveryDetails->deliveryCompanyName = $this->nullToString($order->getShippingAddress()->getCompany());
            }
            $request->deliveryDetails->deliverySalutation = null;

            $shippingStreet = $order->getShippingAddress()->getStreet();
            $shippingStreet = implode("", $shippingStreet);

            $request->deliveryDetails->deliveryAddrFirstLine = trim((String)$shippingStreet);
            $request->deliveryDetails->deliveryAddrPostalCode = $this->nullToString($order->getShippingAddress()->getPostcode());
            $request->deliveryDetails->deliveryAddrTown = $this->nullToString($order->getShippingAddress()->getCity());
            $request->deliveryDetails->deliveryAddrCountry = strtoupper($order->getShippingAddress()->getCountryId());

        } else {
            $request->deliveryDetails->deliveryDetailsDifferent = false;
            $request->deliveryDetails->deliveryMethod = self::$DELIVERY_VIRTUAL;
        }

        $request->order->basketItemsGoogleTaxonomies = Array();
        $request->order->basketItemsPrices = Array();

        $sedId = $this->_checkoutSession->getTmxSession();
        if ($this->_scopeConfig->getValue('cembrapaycheckoutsettings/cembrapaycheckout_setup/tmxenabled', ScopeInterface::SCOPE_STORE) == '1' && !empty($sedId)) {
            $request->sessionInfo->fingerPrint = $sedId;
        }

        $request->byjunoDetails->byjunoPaymentMethod = $paymentMethod->getAdditionalInformation('payment_plan');
        if ($paymentMethod->getAdditionalInformation('payment_send') == 'postal') {
            $request->byjunoDetails->invoiceDeliveryType = "POSTAL";
        } else {
            $request->byjunoDetails->invoiceDeliveryType = "EMAIL";
        }

        $customerConsents = new CustomerConsents();
        $customerConsents->consentType = "BYJUNO-TC";
        $customerConsents->consentProvidedAt = "MERCHANT";
        $customerConsents->consentDate = CembraPayCheckoutAutRequest::Date();
        $methods = $this->getMethodsMapping();
        $customerConsents->consentReference = $methods[$paymentMethod->getAdditionalInformation('payment_plan')]["link"];

        $request->customerConsents = Array($customerConsents);

        $request->merchantDetails->transactionChannel = "WEB";
        $request->merchantDetails->integrationModule = "CembraPay Checkout Magento 2 module 0.0.1";

        return $request;
    }

    public function createMagentoShopRequestCheckout(Order $order,
                                                          Order\Payment $paymentMethod, $webShopProfile)
    {

        $request = new CembraPayCheckoutChkRequest();
        $request->merchantId = $this->_scopeConfig->getValue('cembrapaycheckoutsettings/cembrapaycheckout_setup/merchantid', ScopeInterface::SCOPE_STORE, $webShopProfile);
        $request->requestMsgType = self::$MESSAGE_CHK;
        $request->requestMsgId = CembraPayCheckoutChkRequest::GUID();
        $request->requestMsgDateTime = CembraPayCheckoutChkRequest::Date();
        $request->merchantOrderRef = $order->getRealOrderId();
        $request->amount = number_format($order->getGrandTotal(), 2, '.', '') * 100;
        $request->currency = $order->getOrderCurrencyCode();

        $reference = $order->getCustomerId();
        if (empty($reference)) {
            $request->custDetails->merchantCustRef = "guest_" . $order->getId();
            $request->custDetails->loggedIn = false;
        } else {
            $request->custDetails->merchantCustRef = (String)$order->getCustomerId();
            $request->custDetails->loggedIn = true;
        }
        if ($order->getBillingAddress()->getCompany() && $this->_scopeConfig->getValue('cembrapaycheckoutsettings/cembrapaycheckout_setup/businesstobusiness', ScopeInterface::SCOPE_STORE, $webShopProfile) == '1') {
            $request->custDetails->custType = self::$CUSTOMER_BUSINESS;
        } else {
            $request->custDetails->custType = self::$CUSTOMER_PRIVATE;
        }
        $request->custDetails->firstName = (String)$order->getBillingAddress()->getFirstname();
        $request->custDetails->lastName = (String)$order->getBillingAddress()->getLastname();
        $request->custDetails->language = (String)substr($this->_resolver->getLocale(), 0, 2);

        $b = $order->getCustomerDob();
        if (!empty($b)) {
            try {
                $dobObject = new \DateTime($b);
                if ($dobObject != null) {
                    $request->custDetails->dateOfBirth = $dobObject->format('Y-m-d');
                }
            } catch (\Exception $e) {

            }
        }

        $g = $order->getCustomerGender();
        $request->custDetails->salutation = self::$GENTER_UNKNOWN;

        $genderEntity = null;
        try {
            $genderEntity = $this->_customerMetadata->getAttributeMetadata('gender');
        } catch (\Exception $e) {
        }

        if ($genderEntity != null && $genderEntity->isVisible()) {
            if (!empty($g)) {
                if ($g == '1') {
                    $request->custDetails->salutation = self::$GENTER_MALE;
                } else if ($g == '2') {
                    $request->custDetails->salutation = self::$GENTER_FEMALE;
                }
            }
        }

        $gender_male_possible_prefix_array = $this->_scopeConfig->getValue('cembrapaycheckoutsettings/cembrapaycheckout_setup/gender_male_possible_prefix',
            ScopeInterface::SCOPE_STORE, $webShopProfile);
        $gender_female_possible_prefix_array = $this->_scopeConfig->getValue('cembrapaycheckoutsettings/cembrapaycheckout_setup/gender_female_possible_prefix',
            ScopeInterface::SCOPE_STORE, $webShopProfile);
        $gender_male_possible_prefix = explode(";", strtolower($gender_male_possible_prefix_array));
        $gender_female_possible_prefix = explode(";", strtolower($gender_female_possible_prefix_array));
        if ($genderEntity != null && $genderEntity->isVisible()) {
            if (in_array(strtolower($order->getBillingAddress()->getPrefix()), $gender_male_possible_prefix)) {
                $request->custDetails->salutation = self::$GENTER_MALE;
            } else if (in_array(strtolower($order->getBillingAddress()->getPrefix()), $gender_female_possible_prefix)) {
                $request->custDetails->salutation = self::$GENTER_FEMALE;
            }
        }

        $billingStreet = $order->getBillingAddress()->getStreet();
        $billingStreet = implode("", $billingStreet);

        $request->billingAddr->addrFirstLine = (String)$billingStreet;
        $request->billingAddr->postalCode = (String)$order->getBillingAddress()->getPostcode();
        $request->billingAddr->town = (String)$order->getBillingAddress()->getCity();
        $request->billingAddr->country = strtoupper($order->getBillingAddress()->getCountryId());

        $request->custContacts->phoneMobile = (String)trim($order->getBillingAddress()->getTelephone(), '-');
        $request->custContacts->phonePrivate = (String)trim($order->getBillingAddress()->getTelephone(), '-');
        $request->custContacts->phoneBusiness = (String)trim($order->getBillingAddress()->getTelephone(), '-');
        $request->custContacts->email = (String)$order->getBillingAddress()->getEmail();

        if (!$order->getIsVirtual()) {
            $request->deliveryDetails->deliveryDetailsDifferent = false;
            $request->deliveryDetails->deliveryMethod = self::$DELIVERY_POST;
            $request->deliveryDetails->deliveryFirstName = $this->nullToString($order->getShippingAddress()->getFirstname());
            $request->deliveryDetails->deliverySecondName = $this->nullToString($order->getShippingAddress()->getLastname());
            if ($order->getShippingAddress()->getCompany() != '' && $this->_scopeConfig->getValue('cembrapaycheckoutsettings/cembrapaycheckout_setup/businesstobusiness', ScopeInterface::SCOPE_STORE, $webShopProfile) == '1') {
                $request->deliveryDetails->deliveryCompanyName = $this->nullToString($order->getShippingAddress()->getCompany());
            }
            $request->deliveryDetails->deliverySalutation = null;

            $shippingStreet = $order->getShippingAddress()->getStreet();
            $shippingStreet = implode("", $shippingStreet);

            $request->deliveryDetails->deliveryAddrFirstLine = trim((String)$shippingStreet);
            $request->deliveryDetails->deliveryAddrPostalCode = $this->nullToString($order->getShippingAddress()->getPostcode());
            $request->deliveryDetails->deliveryAddrTown = $this->nullToString($order->getShippingAddress()->getCity());
            $request->deliveryDetails->deliveryAddrCountry = strtoupper($order->getShippingAddress()->getCountryId());

        } else {
            $request->deliveryDetails->deliveryDetailsDifferent = false;
            $request->deliveryDetails->deliveryMethod = self::$DELIVERY_VIRTUAL;
        }

        $request->order->basketItemsGoogleTaxonomies = Array();
        $request->order->basketItemsPrices = Array();

        $sedId = $this->_checkoutSession->getTmxSession();
        if ($this->_scopeConfig->getValue('cembrapaycheckoutsettings/cembrapaycheckout_setup/tmxenabled', ScopeInterface::SCOPE_STORE) == '1' && !empty($sedId)) {
            $request->sessionInfo->fingerPrint = $sedId;
        }

        $request->byjunoDetails->byjunoPaymentMethod = $paymentMethod->getAdditionalInformation('payment_plan');
        if ($paymentMethod->getAdditionalInformation('payment_send') == 'postal') {
            $request->byjunoDetails->invoiceDeliveryType = "POSTAL";
        } else {
            $request->byjunoDetails->invoiceDeliveryType = "EMAIL";
        }

        $customerConsents = new CustomerConsents();
        $customerConsents->consentType = "BYJUNO-TC";
        $customerConsents->consentProvidedAt = "MERCHANT";
        $customerConsents->consentDate = CembraPayCheckoutChkRequest::Date();
        $methods = $this->getMethodsMapping();
        $customerConsents->consentReference = $methods[$paymentMethod->getAdditionalInformation('payment_plan')]["link"];

        $request->customerConsents = Array($customerConsents);

        $request->merchantDetails->returnUrlError = $this->_urlBuilder->getUrl('cembrapaycheckoutcore/checkout/cancel');
        $request->merchantDetails->returnUrlSuccess = $this->_urlBuilder->getUrl('checkout/onepage/success');
        $request->merchantDetails->transactionChannel = "WEB";
        $request->merchantDetails->integrationModule = "CembraPay Checkout Magento 2 module 0.0.1";

        return $request;
    }

    function authorizationResponse($response)
    {
        $responseObject = json_decode($response);
        $result = new CembraPayCheckoutAuthorizationResponse();
        if (empty($responseObject->processingStatus)) {
            $result->processingStatus = self::$REQUEST_ERROR;
        } else {
            $result->processingStatus = $responseObject->processingStatus;
            if ($responseObject->processingStatus == self::$AUTH_OK) {
                $result->transactionId = $responseObject->transactionId;
            }
        }
        return $result;
    }

    function checkoutResponse($response)
    {
        $responseObject = json_decode($response);
        $result = new CembraPayCheckoutChkResponse();
        if (empty($responseObject->processingStatus)) {
            $result->processingStatus = self::$REQUEST_ERROR;
        } else {
            $result->processingStatus = $responseObject->processingStatus;
            if ($responseObject->processingStatus == self::$CHK_OK) {
                $result->transactionId = $responseObject->transactionId;
                $result->redirectUrlCheckout = $responseObject->redirectUrlCheckout;
            }
        }
        return $result;
    }

    function settleResponse($response)
    {
        $responseObject = json_decode($response);
        $result = new CembraPayCheckoutSettleResponse();
        if (empty($responseObject->processingStatus)) {
            $result->processingStatus = self::$REQUEST_ERROR;
        } else {
            if ($responseObject->processingStatus == self::$SETTLE_OK) {
                // TODO if need
                $result->processingStatus = $responseObject->processingStatus;
                $result->transactionId = $responseObject->transactionId;
            } else {
                $result->processingStatus = $responseObject->processingStatus;
            }
        }
        return $result;
    }

    function creditResponse($response)
    {
        $responseObject = json_decode($response);
        $result = new CembraPayCheckoutCreditResponse();
        if (empty($responseObject->processingStatus)) {
            $result->processingStatus = self::$REQUEST_ERROR;
        } else {
            if ($responseObject->processingStatus == self::$CREDIT_OK) {
                // TODO if need
                $result->processingStatus = $responseObject->processingStatus;
                $result->transactionId = $responseObject->transactionId;
            } else {
                $result->processingStatus = $responseObject->processingStatus;
            }
        }
        return $result;
    }

    /*function CreateMagentoShopRequestCreditCheck(\Magento\Quote\Model\Quote $quote)
    {
        $request = new \CembraPayCheckout\CembraPayCheckoutCore\Helper\Api\CembraPayRequest();
        $request->setClientId($this->_scopeConfig->getValue('cembrapaycheckoutsettings/cembrapaycheckout_setup/clientid', \Magento\Store\Model\ScopeInterface::SCOPE_STORE));
        $request->setUserID($this->_scopeConfig->getValue('cembrapaycheckoutsettings/cembrapaycheckout_setup/userid', \Magento\Store\Model\ScopeInterface::SCOPE_STORE));
        $request->setPassword($this->_scopeConfig->getValue('cembrapaycheckoutsettings/cembrapaycheckout_setup/password', \Magento\Store\Model\ScopeInterface::SCOPE_STORE));
        $request->setVersion("1.00");
        try {
            $request->setRequestEmail($this->_scopeConfig->getValue('cembrapaycheckoutsettings/cembrapaycheckout_setup/mail', \Magento\Store\Model\ScopeInterface::SCOPE_STORE));
        } catch (\Exception $e) {

        }

        $b = $quote->getCustomerDob();
        if (!empty($b)) {
            try {
                $dobObject = new \DateTime($b);
                if ($dobObject != null) {
                    $request->setDateOfBirth($dobObject->format('Y-m-d'));
                }
            } catch (\Exception $e) {

            }
        }
        $gender_male_possible_prefix_array = $this->_scopeConfig->getValue('cembrapaycheckoutsettings/cembrapaycheckout_setup/gender_male_possible_prefix',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
        $gender_female_possible_prefix_array = $this->_scopeConfig->getValue('cembrapaycheckoutsettings/cembrapaycheckout_setup/gender_female_possible_prefix',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE);

        $gender_male_possible_prefix = explode(";", strtolower($gender_male_possible_prefix_array));
        $gender_female_possible_prefix = explode(";", strtolower($gender_female_possible_prefix_array));

        $g = $quote->getCustomerGender();
        $request->setGender('0');
        if ($this->_customerMetadata->getAttributeMetadata('gender')->isVisible()) {
            if (!empty($g)) {
                if ($g == '1') {
                    $request->setGender('1');
                } else if ($g == '2') {
                    $request->setGender('2');
                }
            }
        }
        if ($this->_customerMetadata->getAttributeMetadata('prefix')->isVisible()) {
            if (in_array(strtolower($quote->getBillingAddress()->getPrefix()), $gender_male_possible_prefix)) {
                $request->setGender('1');
            } else if (in_array(strtolower($quote->getBillingAddress()->getPrefix()), $gender_female_possible_prefix)) {
                $request->setGender('2');
            }
        }

        $billingStreet = $quote->getBillingAddress()->getStreet();
        $billingStreet = implode("", $billingStreet);
        $requestId = uniqid((String)$quote->getEntityId() . "_");
        $request->setRequestId($requestId);
        $reference = $quote->getCustomerId();
        if (empty($reference)) {
            $request->setCustomerReference("guest_" . $quote->getId());
        } else {
            $request->setCustomerReference($quote->getCustomerId());
        }
        $request->setFirstName((String)$quote->getBillingAddress()->getFirstname());
        $request->setLastName((String)$quote->getBillingAddress()->getLastname());

        $request->setFirstLine(trim((String)$billingStreet));
        $request->setCountryCode(strtoupper($quote->getBillingAddress()->getCountryId()));
        $request->setPostCode((String)$quote->getBillingAddress()->getPostcode());
        $request->setTown((String)$quote->getBillingAddress()->getCity());
        $request->setFax((String)trim($quote->getBillingAddress()->getFax(), '-'));
        $request->setLanguage((String)substr($this->_resolver->getLocale(), 0, 2));

        if ($quote->getBillingAddress()->getCompany()) {
            $request->setCompanyName1($quote->getBillingAddress()->getCompany());
        }

        $request->setTelephonePrivate((String)trim($quote->getBillingAddress()->getTelephone(), '-'));
        $request->setEmail((String)$quote->getBillingAddress()->getEmail());

        $extraInfo["Name"] = 'ORDERCLOSED';
        $extraInfo["Value"] = 'NO';
        $request->setExtraInfo($extraInfo);

        $extraInfo["Name"] = 'ORDERAMOUNT';
        $extraInfo["Value"] = number_format($quote->getGrandTotal(), 2, '.', '');
        $request->setExtraInfo($extraInfo);

        $extraInfo["Name"] = 'ORDERCURRENCY';
        $extraInfo["Value"] = $quote->getQuoteCurrencyCode();
        $request->setExtraInfo($extraInfo);

        $extraInfo["Name"] = 'IP';
        $extraInfo["Value"] = $this->getClientIp();
        $request->setExtraInfo($extraInfo);

        $sedId = $this->_checkoutSession->getTmxSession();
        if ($this->_scopeConfig->getValue('cembrapaycheckoutsettings/cembrapaycheckout_setup/tmxenabled', \Magento\Store\Model\ScopeInterface::SCOPE_STORE) == '1' && !empty($sedId)) {
            $extraInfo["Name"] = 'DEVICE_FINGERPRINT_ID';
            $extraInfo["Value"] = $sedId;
            $request->setExtraInfo($extraInfo);
        }

        if (!$quote->isVirtual()) {
            $shippingStreet = $quote->getShippingAddress()->getStreet();
            $shippingStreet = implode("", $shippingStreet);

            $extraInfo["Name"] = 'DELIVERY_FIRSTLINE';
            $extraInfo["Value"] = trim((String)$shippingStreet);
            $request->setExtraInfo($extraInfo);

            $extraInfo["Name"] = 'DELIVERY_HOUSENUMBER';
            $extraInfo["Value"] = '';
            $request->setExtraInfo($extraInfo);

            $extraInfo["Name"] = 'DELIVERY_COUNTRYCODE';
            $extraInfo["Value"] = strtoupper($quote->getShippingAddress()->getCountryId());
            $request->setExtraInfo($extraInfo);

            $extraInfo["Name"] = 'DELIVERY_POSTCODE';
            $extraInfo["Value"] = $this->nullToString($quote->getShippingAddress()->getPostcode());
            $request->setExtraInfo($extraInfo);

            $extraInfo["Name"] = 'DELIVERY_TOWN';
            $extraInfo["Value"] = $this->nullToString($quote->getShippingAddress()->getCity());
            $request->setExtraInfo($extraInfo);

            if ($quote->getShippingAddress()->getCompany() != '' && $this->_scopeConfig->getValue('cembrapaycheckoutsettings/cembrapaycheckout_setup/businesstobusiness', \Magento\Store\Model\ScopeInterface::SCOPE_STORE) == '1') {

                $extraInfo["Name"] = 'DELIVERY_COMPANYNAME';
                $extraInfo["Value"] = $this->nullToString($quote->getShippingAddress()->getCompany());
                $request->setExtraInfo($extraInfo);

                $extraInfo["Name"] = 'DELIVERY_FIRSTNAME';
                $extraInfo["Value"] = '';
                $request->setExtraInfo($extraInfo);

                $extraInfo["Name"] = 'DELIVERY_LASTNAME';
                $extraInfo["Value"] = $this->nullToString($quote->getShippingAddress()->getCompany());
                $request->setExtraInfo($extraInfo);

            } else {

                $extraInfo["Name"] = 'DELIVERY_FIRSTNAME';
                $extraInfo["Value"] = $this->nullToString($quote->getShippingAddress()->getFirstname());
                $request->setExtraInfo($extraInfo);

                $extraInfo["Name"] = 'DELIVERY_LASTNAME';
                $extraInfo["Value"] = $this->nullToString($quote->getShippingAddress()->getLastname());
                $request->setExtraInfo($extraInfo);
            }
        }

        $extraInfo["Name"] = 'RISKOWNER';
        $extraInfo["Value"] = 'IJ';
        $request->setExtraInfo($extraInfo);

        $extraInfo["Name"] = 'CONNECTIVTY_MODULE';
        $extraInfo["Value"] = 'CembraPay Checkout Magento 2 module 1.7.4';
        $request->setExtraInfo($extraInfo);
        return $request;
    }
    */

    function CreateMagentoShopRequestCredit(Order $order, $amount, $invoiceId, $webshopProfile, $tx)
    {


        $request = new CembraPayCheckoutCreditRequest();
        $request->merchantId = $this->_scopeConfig->getValue('cembrapaycheckoutsettings/cembrapaycheckout_setup/merchantid', ScopeInterface::SCOPE_STORE, $webshopProfile);
        $request->requestMsgType = self::$MESSAGE_CNL;
        $request->requestMsgId = CembraPayCheckoutAutRequest::GUID();
        $request->requestMsgDateTime = CembraPayCheckoutAutRequest::Date();
        $request->transactionId = $tx;
        $request->merchantOrderRef = $order->getRealOrderId();
        $request->amount = number_format($amount, 2, '.', '') * 100;
        $request->currency = $order->getOrderCurrencyCode();
        $request->settlementDetails->merchantInvoiceRef = $invoiceId;
        return $request;
        /*
        $request = new CembraPayS5Request();
        $request->setClientId($this->_scopeConfig->getValue('cembrapaycheckoutsettings/cembrapaycheckout_setup/clientid', ScopeInterface::SCOPE_STORE, $webshopProfile));
        $request->setUserID($this->_scopeConfig->getValue('cembrapaycheckoutsettings/cembrapaycheckout_setup/userid', ScopeInterface::SCOPE_STORE, $webshopProfile));
        $request->setPassword($this->_scopeConfig->getValue('cembrapaycheckoutsettings/cembrapaycheckout_setup/password', ScopeInterface::SCOPE_STORE, $webshopProfile));
        $request->setVersion("1.00");
        try {
            $request->setRequestEmail($this->_scopeConfig->getValue('cembrapaycheckoutsettings/cembrapaycheckout_setup/mail', ScopeInterface::SCOPE_STORE, $webshopProfile));
        } catch (\Exception $e) {

        }
        $request->setRequestId(uniqid((String)$order->getIncrementId() . "_"));

        $request->setOrderId($order->getIncrementId());
        $reference = $order->getCustomerId();
        if (empty($reference)) {
            $request->setClientRef("guest_" . $order->getId());
        } else {
            $request->setClientRef($order->getCustomerId());
        }
        $orderDateString = \Zend_Locale_Format::getDate(
            $order->getCreatedAt(),
            array(
                'date_format' => \Magento\Framework\Stdlib\DateTime::DATE_INTERNAL_FORMAT,
            )
        );
        $request->setTransactionDate($orderDateString["year"] . "-" . $orderDateString["month"] . '-' . $orderDateString["day"]);
        $request->setTransactionAmount(number_format($amount, 2, '.', ''));
        $request->setTransactionCurrency($order->getOrderCurrencyCode());
        $request->setTransactionType($transactionType);
        $request->setAdditional2($invoiceId);
        if ($transactionType == "EXPIRED") {
            $request->setOpenBalance("0");
        }

        return $request;
        */
    }

    function CreateMagentoShopRequestS5Paid(Order $order, $amount, $transactionType, $invoiceId, $webshopProfile, $tx)
    {


        $request = new CembraPayCheckoutSettleRequest();
        $request->merchantId = $this->_scopeConfig->getValue('cembrapaycheckoutsettings/cembrapaycheckout_setup/merchantid', ScopeInterface::SCOPE_STORE, $webshopProfile);
        $request->requestMsgType = self::$MESSAGE_SET;
        $request->requestMsgId = CembraPayCheckoutAutRequest::GUID();
        $request->requestMsgDateTime = CembraPayCheckoutAutRequest::Date();
        $request->transactionId = $tx;
        $request->merchantOrderRef = $order->getRealOrderId();
        $request->amount = number_format($order->getGrandTotal(), 2, '.', '') * 100;
        $request->currency = $order->getOrderCurrencyCode();
        $request->settlementDetails->merchantInvoiceRef = $invoiceId;
        return $request;
        /*
        $request = new CembraPayS5Request();
        $request->setClientId($this->_scopeConfig->getValue('cembrapaycheckoutsettings/cembrapaycheckout_setup/clientid', ScopeInterface::SCOPE_STORE, $webshopProfile));
        $request->setUserID($this->_scopeConfig->getValue('cembrapaycheckoutsettings/cembrapaycheckout_setup/userid', ScopeInterface::SCOPE_STORE, $webshopProfile));
        $request->setPassword($this->_scopeConfig->getValue('cembrapaycheckoutsettings/cembrapaycheckout_setup/password', ScopeInterface::SCOPE_STORE, $webshopProfile));
        $request->setVersion("1.00");
        try {
            $request->setRequestEmail($this->_scopeConfig->getValue('cembrapaycheckoutsettings/cembrapaycheckout_setup/mail', ScopeInterface::SCOPE_STORE, $webshopProfile));
        } catch (\Exception $e) {

        }
        $request->setRequestId(uniqid((String)$order->getIncrementId() . "_"));

        $request->setOrderId($order->getIncrementId());
        $reference = $order->getCustomerId();
        if (empty($reference)) {
            $request->setClientRef("guest_" . $order->getId());
        } else {
            $request->setClientRef($order->getCustomerId());
        }
        $orderDateString = \Zend_Locale_Format::getDate(
            $order->getCreatedAt(),
            array(
                'date_format' => \Magento\Framework\Stdlib\DateTime::DATE_INTERNAL_FORMAT,
            )
        );
        $request->setTransactionDate($orderDateString["year"] . "-" . $orderDateString["month"] . '-' . $orderDateString["day"]);
        $request->setTransactionAmount(number_format($amount, 2, '.', ''));
        $request->setTransactionCurrency($order->getOrderCurrencyCode());
        $request->setTransactionType($transactionType);
        $request->setAdditional2($invoiceId);
        if ($transactionType == "EXPIRED") {
            $request->setOpenBalance("0");
        }

        return $request;
        */
    }
}
