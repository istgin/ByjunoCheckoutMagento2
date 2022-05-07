<?php

namespace ByjunoCheckout\ByjunoCheckoutCore\Helper;

use ByjunoCheckout\ByjunoCheckoutCore\Helper\Api\ByjunoCheckoutAuthorizationResponse;
use ByjunoCheckout\ByjunoCheckoutCore\Helper\Api\ByjunoCheckoutRequest;
use ByjunoCheckout\ByjunoCheckoutCore\Helper\Api\ByjunoCheckoutScreeningResponse;
use ByjunoCheckout\ByjunoCheckoutCore\Helper\Api\CustomerConsents;
use Magento\Quote\Model\Quote\Payment;
use Magento\Sales\Model\Order;
use Magento\Store\Model\ScopeInterface;

class DataHelper extends \Magento\Framework\App\Helper\AbstractHelper
{

    public static $SINGLEINVOICE = 'SINGLE-INVOICE';
    public static $BYJUNOINVOICE = 'BYJUNO-INVOICE';

    public static $MESSAGE_SCREENING = 'SCR';
    public static $MESSAGE_AUTH = 'AUT';

    public static $CUSTOMER_PRIVATE = 'P';
    public static $CUSTOMER_BUSINESS = 'C';


    public static $GENTER_UNKNOWN = 'N';
    public static $GENTER_MALE = 'M';
    public static $GENTER_FEMALE = 'F';


    public static $DELIVERY_POST = 'POST';
    public static $DELIVERY_VIRTUAL = 'DIGITAL';

    public static $SCREENING_OK = 'SCREENING-APPROVED';

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
    public $_byjunoOrderSender;
    public $_byjunoCreditmemoSender;
    public $_byjunoInvoiceSender;
    public $_byjunoLogger;
    public $_objectManager;
    public $_configLoader;
    public $_customerMetadata;

    /**
     * @var \Psr\Log\LoggerInterface
     */
    public $_loggerPsr;

    /**
     * @var \ByjunoCheckout\ByjunoCheckoutCore\Helper\Api\ByjunoCommunicator
     */
    public $_communicator;

    public function getMethodsMapping()
    {
        $methods = Array(
            self::$SINGLEINVOICE => Array(
                "value" => self::$SINGLEINVOICE,
                "name" => $this->_scopeConfig->getValue("byjunoinvoicesettings/byjunocheckout_invoice_partial/name", ScopeInterface::SCOPE_STORE),
                "link" => $this->_scopeConfig->getValue("byjunoinvoicesettings/byjunocheckout_invoice_partial/link", ScopeInterface::SCOPE_STORE)
            ),
            self::$BYJUNOINVOICE => Array(
                "value" => self::$BYJUNOINVOICE,
                "name" => $this->_scopeConfig->getValue("byjunoinvoicesettings/byjunocheckout_single_invoice/name", ScopeInterface::SCOPE_STORE),
                "link" => $this->_scopeConfig->getValue("byjunoinvoicesettings/byjunocheckout_single_invoice/link", ScopeInterface::SCOPE_STORE)
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
        $data = array('firstname' => $firstName,
            'lastname' => $lastName,
            'postcode' => $postcode,
            'town' => $town,
            'country' => $country,
            'street1' => $street1,
            'status' => $status,
            'request_id' => $requestId,
            'error' => '',
            'request' => $json_string11,
            'response' => $json_string22,
            'type' => $type,
            'order_id' => $orderId,
            'transaction_id' => $transactionId,
            'ip' => $this->getClientIp());

        $this->_byjunoLogger->log($data);
    }

    function saveS4Log(Order $order, ByjunoS4Request $request, $xml_request, $xml_response, $status, $type)
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

        $this->_byjunoLogger->log($data);
    }

    function saveS5Log(Order $order, ByjunoS5Request $request, $xml_request, $xml_response, $status, $type)
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

        $this->_byjunoLogger->log($data);
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
        $addrMethod = $this->_scopeConfig->getValue('byjunocheckoutsettings/advanced/ip_detect_string', ScopeInterface::SCOPE_STORE);
        if (!empty($addrMethod) && !empty($_SERVER[$addrMethod])) {
            $ipaddress = $_SERVER[$addrMethod];
        }
        return $ipaddress;
    }

    function getByjunoErrorMessage($status, $paymentType = 'b2c')
    {
        $message = '';
        if ($status == 10 && $paymentType == 'b2b') {
            if (substr($this->_resolver->getLocale(), 0, 2) == 'en') {
                $message = 'Company is not found in Register of Commerce';
            } else if (substr($this->_resolver->getLocale(), 0, 2) == 'fr') {
                $message = 'La société n‘est pas inscrit au registre du commerce';
            } else if (substr($this->_resolver->getLocale(), 0, 2) == 'it') {
                $message = 'L‘azienda non é registrata nel registro di commercio';
            } else {
                $message = 'Die Firma ist nicht im Handelsregister eingetragen';
            }
        } else {
            $message = $this->_scopeConfig->getValue('byjunocheckoutsettings/localization/byjunocheckout_fail_message', ScopeInterface::SCOPE_STORE);
        }
        return $message;
    }

    public function saveStatusToOrder(Order $order)
    {
        $order->addStatusHistoryComment('<b>Byjuno Checkout status: OK</b>');
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
        \ByjunoCheckout\ByjunoCheckoutCore\Helper\Api\ByjunoCommunicator $communicator,
        \ByjunoCheckout\ByjunoCheckoutCore\Helper\ByjunoOrderSender $byjunoOrderSender,
        \ByjunoCheckout\ByjunoCheckoutCore\Helper\ByjunoCreditmemoSender $byjunoCreditmemoSender,
        \ByjunoCheckout\ByjunoCheckoutCore\Helper\ByjunoInvoiceSender $byjunoInvoiceSender,
        \Magento\Sales\Model\Order\Email\Sender\OrderSender $originalOrderSender,
        \ByjunoCheckout\ByjunoCheckoutCore\Helper\Api\ByjunoLogger $byjunoLogger,
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
        $this->_byjunoLogger = $byjunoLogger;
        $this->_byjunoOrderSender = $byjunoOrderSender;
        $this->_originalOrderSender = $originalOrderSender;
        $this->_byjunoCreditmemoSender = $byjunoCreditmemoSender;
        $this->_byjunoInvoiceSender = $byjunoInvoiceSender;
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

    function byjunoIsStatusOk($status, $position)
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

    function CreateMagentoShopRequestOrderQuote(\Magento\Quote\Model\Quote $quote,
                                                Payment $paymentmethod,
                                                $gender_custom, $dob_custom, $pref_lang, $b2b_uid, $webshopProfile)
    {
        /*
                $request = new \ByjunoCheckout\ByjunoCheckoutCore\Helper\Api\ByjunoRequest();
                $request->setClientId($this->_scopeConfig->getValue('byjunocheckoutsettings/byjunocheckout_setup/clientid', \Magento\Store\Model\ScopeInterface::SCOPE_STORE, $webshopProfile));
                $request->setUserID($this->_scopeConfig->getValue('byjunocheckoutsettings/byjunocheckout_setup/userid', \Magento\Store\Model\ScopeInterface::SCOPE_STORE, $webshopProfile));
                $request->setPassword($this->_scopeConfig->getValue('byjunocheckoutsettings/byjunocheckout_setup/password', \Magento\Store\Model\ScopeInterface::SCOPE_STORE, $webshopProfile));
                $request->setVersion("1.00");
                try {
                    $request->setRequestEmail($this->_scopeConfig->getValue('byjunocheckoutsettings/byjunocheckout_setup/mail', \Magento\Store\Model\ScopeInterface::SCOPE_STORE, $webshopProfile));
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
                $gender_male_possible_prefix_array = $this->_scopeConfig->getValue('byjunocheckoutsettings/byjunocheckout_setup/gender_male_possible_prefix',
                    \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
                $gender_female_possible_prefix_array = $this->_scopeConfig->getValue('byjunocheckoutsettings/byjunocheckout_setup/gender_female_possible_prefix',
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
                if ($this->_scopeConfig->getValue('byjunocheckoutsettings/byjunocheckout_setup/tmxenabled', \Magento\Store\Model\ScopeInterface::SCOPE_STORE) == '1' && !empty($sedId)) {
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

                    if ($quote->getShippingAddress()->getCompany() != '' && $this->_scopeConfig->getValue('byjunocheckoutsettings/byjunocheckout_setup/businesstobusiness', \Magento\Store\Model\ScopeInterface::SCOPE_STORE) == '1') {

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
                $extraInfo["Value"] = 'Byjuno Checkout Magento 2 module 1.7.4';
                $request->setExtraInfo($extraInfo);
                return $request;
        */
        return null;
    }

    function CreateMagentoShopRequestPaid(Order $order,
                                          \Magento\Sales\Model\Order\Payment $paymentmethod,
                                          $gender_custom, $dob_custom, $transaction, $riskOwner, $pref_lang, $b2b_uid, $webshopProfile)
    {

        $request = new \ByjunoCheckout\ByjunoCheckoutCore\Helper\Api\ByjunoRequest();
        $request->setClientId($this->_scopeConfig->getValue('byjunocheckoutsettings/byjunocheckout_setup/clientid', ScopeInterface::SCOPE_STORE, $webshopProfile));
        $request->setUserID($this->_scopeConfig->getValue('byjunocheckoutsettings/byjunocheckout_setup/userid', ScopeInterface::SCOPE_STORE, $webshopProfile));
        $request->setPassword($this->_scopeConfig->getValue('byjunocheckoutsettings/byjunocheckout_setup/password', ScopeInterface::SCOPE_STORE, $webshopProfile));
        $request->setVersion("1.00");
        try {
            $request->setRequestEmail($this->_scopeConfig->getValue('byjunocheckoutsettings/byjunocheckout_setup/mail', ScopeInterface::SCOPE_STORE, $webshopProfile));
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

        $gender_male_possible_prefix_array = $this->_scopeConfig->getValue('byjunocheckoutsettings/byjunocheckout_setup/gender_male_possible_prefix',
            ScopeInterface::SCOPE_STORE);
        $gender_female_possible_prefix_array = $this->_scopeConfig->getValue('byjunocheckoutsettings/byjunocheckout_setup/gender_female_possible_prefix',
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
        $txid_extrainfo = $this->_scopeConfig->getValue('byjunocheckoutsettings/byjunocheckout_setup/txid_extrainfo',
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
        if ($this->_scopeConfig->getValue('byjunocheckoutsettings/byjunocheckout_setup/tmxenabled', ScopeInterface::SCOPE_STORE) == '1' && !empty($sedId)) {
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

            if ($order->getShippingAddress()->getCompany() != '' && $this->_scopeConfig->getValue('byjunocheckoutsettings/byjunocheckout_setup/businesstobusiness', ScopeInterface::SCOPE_STORE) == '1') {

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
        $extraInfo["Value"] = 'Byjuno Checkout Magento 2 module 1.7.4';
        $request->setExtraInfo($extraInfo);

        return $request;

    }

    function CreateMagentoShopRequestS4Paid(Order $order, \Magento\Sales\Model\Order\Invoice $invoice, $webshopProfile)
    {
        $request = new ByjunoS4Request();
        $request->setClientId($this->_scopeConfig->getValue('byjunocheckoutsettings/byjunocheckout_setup/clientid', ScopeInterface::SCOPE_STORE, $webshopProfile));
        $request->setUserID($this->_scopeConfig->getValue('byjunocheckoutsettings/byjunocheckout_setup/userid', ScopeInterface::SCOPE_STORE, $webshopProfile));
        $request->setPassword($this->_scopeConfig->getValue('byjunocheckoutsettings/byjunocheckout_setup/password', ScopeInterface::SCOPE_STORE, $webshopProfile));
        $request->setVersion("1.00");
        try {
            $request->setRequestEmail($this->_scopeConfig->getValue('byjunocheckoutsettings/byjunocheckout_setup/mail', ScopeInterface::SCOPE_STORE, $webshopProfile));
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

        $request = new ByjunoCheckoutRequest();
        $request->merchantId = $this->_scopeConfig->getValue('byjunocheckoutsettings/byjunocheckout_setup/merchantid', ScopeInterface::SCOPE_STORE);
        $request->requestMsgType = self::$MESSAGE_SCREENING;
        $request->requestMsgId = ByjunoCheckoutRequest::GUID();
        $request->requestMsgDateTime = ByjunoCheckoutRequest::Date();
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
        if ($quote->getBillingAddress()->getCompany()) {
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

        $gender_male_possible_prefix_array = $this->_scopeConfig->getValue('byjunocheckoutsettings/byjunocheckout_setup/gender_male_possible_prefix',
            ScopeInterface::SCOPE_STORE);
        $gender_female_possible_prefix_array = $this->_scopeConfig->getValue('byjunocheckoutsettings/byjunocheckout_setup/gender_female_possible_prefix',
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
        $request->custContacts->email = (String)$quote->getBillingAddress()->getEmail();

        if (!$quote->isVirtual()) {
            $request->deliveryDetails->deliveryDetailsDifferent = false;
            $request->deliveryDetails->deliveryMethod = self::$DELIVERY_POST;
            $request->deliveryDetails->deliveryFirstName = $this->nullToString($quote->getShippingAddress()->getFirstname());
            $request->deliveryDetails->deliverySecondName = $this->nullToString($quote->getShippingAddress()->getLastname());
            if ($quote->getShippingAddress()->getCompany() != '' && $this->_scopeConfig->getValue('byjunocheckoutsettings/byjunocheckout_setup/businesstobusiness', ScopeInterface::SCOPE_STORE) == '1') {
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
        if ($this->_scopeConfig->getValue('byjunocheckoutsettings/byjunocheckout_setup/tmxenabled', ScopeInterface::SCOPE_STORE) == '1' && !empty($sedId)) {
            $request->sessionInfo->fingerPrint = $sedId;
        }


        //$request->byjunoDetails->byjunoProductType = "SINGLE-INVOICE";
        //$request->byjunoDetails->invoiceDeliveryType = "EMAIL";

        $request->merchantDetails->transactionChannel = "WEB";
        $request->merchantDetails->integrationModule = "Byjuno Checkout Magento 2 module 0.0.1";

        return $request;
    }

    function screeningResponse($response)
    {

        $responseObject = json_decode($response);
        $result = new ByjunoCheckoutScreeningResponse();
        if ($responseObject->processingStatus == self::$SCREENING_OK) {
            $result->merchantCustRef = $responseObject->merchantCustRef;
            $result->processingStatus = $responseObject->processingStatus;
            $result->replyMsgDateTime = $responseObject->replyMsgDateTime;
            $result->replyMsgId = $responseObject->replyMsgId;
            $result->requestMsgDateTime = $responseObject->requestMsgDateTime;
            $result->requestMsgId = $responseObject->requestMsgId;
            $result->transactionId = $responseObject->transactionId;
            if (!empty($responseObject->screeningDetails) && !empty(!empty($responseObject->screeningDetails->allowedByjunoPaymentMethods))) {
                $result->screeningDetails->allowedByjunoPaymentMethods = $responseObject->screeningDetails->allowedByjunoPaymentMethods;
            }
        } else {
            $result->processingStatus = $responseObject->processingStatus;
        }
        return $result;
    }

    public function createMagentoShopRequestAuthorization(Order $order,
                                                          \Magento\Sales\Model\Order\Payment $paymentMethod,
                                                          $gender_custom, $dob_custom, $pref_lang, $b2b_uid, $webShopProfile)
    {

        $request = new ByjunoCheckoutRequest();
        $request->merchantId = $this->_scopeConfig->getValue('byjunocheckoutsettings/byjunocheckout_setup/merchantid', ScopeInterface::SCOPE_STORE, $webShopProfile);
        $request->requestMsgType = self::$MESSAGE_AUTH;
        $request->requestMsgId = ByjunoCheckoutRequest::GUID();
        $request->requestMsgDateTime = ByjunoCheckoutRequest::Date();
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
        if ($order->getBillingAddress()->getCompany()) {
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

        $gender_male_possible_prefix_array = $this->_scopeConfig->getValue('byjunocheckoutsettings/byjunocheckout_setup/gender_male_possible_prefix',
            ScopeInterface::SCOPE_STORE, $webShopProfile);
        $gender_female_possible_prefix_array = $this->_scopeConfig->getValue('byjunocheckoutsettings/byjunocheckout_setup/gender_female_possible_prefix',
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
        $request->custContacts->email = (String)$order->getBillingAddress()->getEmail();

        if (!$order->getIsVirtual()) {
            $request->deliveryDetails->deliveryDetailsDifferent = false;
            $request->deliveryDetails->deliveryMethod = self::$DELIVERY_POST;
            $request->deliveryDetails->deliveryFirstName = $this->nullToString($order->getShippingAddress()->getFirstname());
            $request->deliveryDetails->deliverySecondName = $this->nullToString($order->getShippingAddress()->getLastname());
            if ($order->getShippingAddress()->getCompany() != '' && $this->_scopeConfig->getValue('byjunocheckoutsettings/byjunocheckout_setup/businesstobusiness', ScopeInterface::SCOPE_STORE) == '1') {
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
        if ($this->_scopeConfig->getValue('byjunocheckoutsettings/byjunocheckout_setup/tmxenabled', ScopeInterface::SCOPE_STORE) == '1' && !empty($sedId)) {
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
        $customerConsents->consentDate = ByjunoCheckoutRequest::Date();
        $methods = $this->getMethodsMapping();
        $customerConsents->consentReference = $methods[$paymentMethod->getAdditionalInformation('payment_plan')]["link"];

        $request->customerConsents = Array($customerConsents);

        $request->merchantDetails->transactionChannel = "WEB";
        $request->merchantDetails->integrationModule = "Byjuno Checkout Magento 2 module 0.0.1";

        return $request;
    }

    function authorizationResponse($response)
    {

        $result = new ByjunoCheckoutAuthorizationResponse();
        $result->merchantCustRef = "responseObject->merchantCustRef";
        $result->processingStatus = "SUCCESS";
        $result->replyMsgDateTime = "responseObject->replyMsgDateTime";
        $result->replyMsgId = "responseObject->replyMsgId";
        $result->requestMsgDateTime = "responseObject->requestMsgDateTime";
        $result->requestMsgId = "responseObject->requestMsgId";
        $result->transactionId = "responseObject->transactionId";
        return $result;
        //TODO due error
        /*
        $responseObject = json_decode($response);
        $result = new ByjunoCheckoutScreeningResponse();
        if ($responseObject->processingStatus == self::$SCREENING_OK) {
            $result->merchantCustRef = $responseObject->merchantCustRef;
            $result->processingStatus = $responseObject->processingStatus;
            $result->replyMsgDateTime = $responseObject->replyMsgDateTime;
            $result->replyMsgId = $responseObject->replyMsgId;
            $result->requestMsgDateTime = $responseObject->requestMsgDateTime;
            $result->requestMsgId = $responseObject->requestMsgId;
            $result->transactionId = $responseObject->transactionId;
            if (!empty($responseObject->screeningDetails) && !empty(!empty($responseObject->screeningDetails->allowedByjunoPaymentMethods))) {
                $result->screeningDetails->allowedByjunoPaymentMethods = $responseObject->screeningDetails->allowedByjunoPaymentMethods;
            }
        } else {
            $result->processingStatus = $responseObject->processingStatus;
        }
        return $result;
        */
    }

    /*function CreateMagentoShopRequestCreditCheck(\Magento\Quote\Model\Quote $quote)
    {
        $request = new \ByjunoCheckout\ByjunoCheckoutCore\Helper\Api\ByjunoRequest();
        $request->setClientId($this->_scopeConfig->getValue('byjunocheckoutsettings/byjunocheckout_setup/clientid', \Magento\Store\Model\ScopeInterface::SCOPE_STORE));
        $request->setUserID($this->_scopeConfig->getValue('byjunocheckoutsettings/byjunocheckout_setup/userid', \Magento\Store\Model\ScopeInterface::SCOPE_STORE));
        $request->setPassword($this->_scopeConfig->getValue('byjunocheckoutsettings/byjunocheckout_setup/password', \Magento\Store\Model\ScopeInterface::SCOPE_STORE));
        $request->setVersion("1.00");
        try {
            $request->setRequestEmail($this->_scopeConfig->getValue('byjunocheckoutsettings/byjunocheckout_setup/mail', \Magento\Store\Model\ScopeInterface::SCOPE_STORE));
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
        $gender_male_possible_prefix_array = $this->_scopeConfig->getValue('byjunocheckoutsettings/byjunocheckout_setup/gender_male_possible_prefix',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
        $gender_female_possible_prefix_array = $this->_scopeConfig->getValue('byjunocheckoutsettings/byjunocheckout_setup/gender_female_possible_prefix',
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
        if ($this->_scopeConfig->getValue('byjunocheckoutsettings/byjunocheckout_setup/tmxenabled', \Magento\Store\Model\ScopeInterface::SCOPE_STORE) == '1' && !empty($sedId)) {
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

            if ($quote->getShippingAddress()->getCompany() != '' && $this->_scopeConfig->getValue('byjunocheckoutsettings/byjunocheckout_setup/businesstobusiness', \Magento\Store\Model\ScopeInterface::SCOPE_STORE) == '1') {

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
        $extraInfo["Value"] = 'Byjuno Checkout Magento 2 module 1.7.4';
        $request->setExtraInfo($extraInfo);
        return $request;
    }
    */

    function CreateMagentoShopRequestS5Paid(Order $order, $amount, $transactionType, $invoiceId = '', $webshopProfile)
    {

        $request = new ByjunoS5Request();
        $request->setClientId($this->_scopeConfig->getValue('byjunocheckoutsettings/byjunocheckout_setup/clientid', ScopeInterface::SCOPE_STORE, $webshopProfile));
        $request->setUserID($this->_scopeConfig->getValue('byjunocheckoutsettings/byjunocheckout_setup/userid', ScopeInterface::SCOPE_STORE, $webshopProfile));
        $request->setPassword($this->_scopeConfig->getValue('byjunocheckoutsettings/byjunocheckout_setup/password', ScopeInterface::SCOPE_STORE, $webshopProfile));
        $request->setVersion("1.00");
        try {
            $request->setRequestEmail($this->_scopeConfig->getValue('byjunocheckoutsettings/byjunocheckout_setup/mail', ScopeInterface::SCOPE_STORE, $webshopProfile));
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
    }
}
