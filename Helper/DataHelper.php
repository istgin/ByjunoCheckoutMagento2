<?php

namespace CembraPayCheckout\CembraPayCheckoutCore\Helper;

use CembraPayCheckout\CembraPayCheckoutCore\Helper\Api\CembraPayAzure;
use CembraPayCheckout\CembraPayCheckoutCore\Helper\Api\CembraPayCheckoutAuthorizationResponse;
use CembraPayCheckout\CembraPayCheckoutCore\Helper\Api\CembraPayCheckoutAutRequest;
use CembraPayCheckout\CembraPayCheckoutCore\Helper\Api\CembraPayCheckoutCancelRequest;
use CembraPayCheckout\CembraPayCheckoutCore\Helper\Api\CembraPayCheckoutCancelResponse;
use CembraPayCheckout\CembraPayCheckoutCore\Helper\Api\CembraPayCheckoutChkRequest;
use CembraPayCheckout\CembraPayCheckoutCore\Helper\Api\CembraPayCheckoutChkResponse;
use CembraPayCheckout\CembraPayCheckoutCore\Helper\Api\CembraPayCheckoutCreditRequest;
use CembraPayCheckout\CembraPayCheckoutCore\Helper\Api\CembraPayCheckoutCreditResponse;
use CembraPayCheckout\CembraPayCheckoutCore\Helper\Api\CembraPayCheckoutScreeningResponse;
use CembraPayCheckout\CembraPayCheckoutCore\Helper\Api\CembraPayCheckoutSettleRequest;
use CembraPayCheckout\CembraPayCheckoutCore\Helper\Api\CembraPayCheckoutSettleResponse;
use CembraPayCheckout\CembraPayCheckoutCore\Helper\Api\CembraPayCommunicator;
use CembraPayCheckout\CembraPayCheckoutCore\Helper\Api\CembraPayGetStatusRequest;
use CembraPayCheckout\CembraPayCheckoutCore\Helper\Api\CembraPayGetStatusResponse;
use CembraPayCheckout\CembraPayCheckoutCore\Helper\Api\CembraPayLoginDto;
use CembraPayCheckout\CembraPayCheckoutCore\Helper\Api\CustomerConsents;
use Magento\Framework\App\Config\ReinitableConfigInterface;
use Magento\Framework\App\ObjectManager;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Invoice;
use Magento\Store\Model\ScopeInterface;

class DataHelper extends \Magento\Framework\App\Helper\AbstractHelper
{
    public static $SINGLEINVOICE = 'SINGLE-INVOICE';
    public static $CEMBRAPAYINVOICE = 'BYJUNO-INVOICE';

    public static $INSTALLMENT_3 = 'INSTALLMENT_3';
    public static $INSTALLMENT_4 = 'INSTALLMENT_4';
    public static $INSTALLMENT_6 = 'INSTALLMENT_6';
    public static $INSTALLMENT_12 = 'INSTALLMENT_12';
    public static $INSTALLMENT_24 = 'INSTALLMENT_24';
    public static $INSTALLMENT_36 = 'INSTALLMENT_36';
    public static $INSTALLMENT_48 = 'INSTALLMENT_48';

    public static $MESSAGE_SCREENING = 'SCR';
    public static $MESSAGE_AUTH = 'AUT';
    public static $MESSAGE_SET = 'SET';
    public static $MESSAGE_CNL = 'CNT';
    public static $MESSAGE_CAN = 'CAN';
    public static $MESSAGE_CHK = 'CHK';
    public static $MESSAGE_STATUS = 'TST';

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
    public static $CANCEL_OK = 'SUCCESS';
    public static $CHK_OK = 'SUCCESS';
    public static $GET_OK = 'SUCCESS';
    public static $GET_OK_TRANSACTION_STATUSES = ['AUTHORIZED', 'SETTLED', 'PARTIALLY SETTLED'];


    public static $REQUEST_ERROR = 'REQUEST_ERROR';

    public static $allowedByjunoPaymentMethods;

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
    /* @var $_writerInterface \Magento\Framework\App\Config\Storage\WriterInterface */
    public $_writerInterface;
    /**
     * Reinitable Config Model.
     *
     * @var ReinitableConfigInterface
     */
    private $_reinitableConfig;

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
     * @var \Magento\Sales\Model\ResourceModel\Order\CollectionFactory
     */
    public $orderCollectionFactory;

    /**
     * @var \CembraPayCheckout\CembraPayCheckoutCore\Helper\Api\CembraPayAzure
     */
    public $cembraPayAzure;

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
        $methods = array(
            self::$CEMBRAPAYINVOICE => array(
                "value" => self::$CEMBRAPAYINVOICE,
                "name" => $this->_scopeConfig->getValue("cembrapayinvoicesettings/cembrapaycheckout_invoice_partial/name", ScopeInterface::SCOPE_STORE),
                "link" => $this->_scopeConfig->getValue("cembrapayinvoicesettings/cembrapaycheckout_invoice_partial/link", ScopeInterface::SCOPE_STORE)
            ),
            self::$SINGLEINVOICE => array(
                "value" => self::$SINGLEINVOICE,
                "name" => $this->_scopeConfig->getValue("cembrapayinvoicesettings/cembrapaycheckout_single_invoice/name", ScopeInterface::SCOPE_STORE),
                "link" => $this->_scopeConfig->getValue("cembrapayinvoicesettings/cembrapaycheckout_single_invoice/link", ScopeInterface::SCOPE_STORE)
            ),
            self::$INSTALLMENT_3 => array(
                "value" => self::$INSTALLMENT_3,
                "name" => $this->_scopeConfig->getValue("cembrapayinstallmentsettings/cembrapaycheckout_installment_3installment/name", ScopeInterface::SCOPE_STORE),
                "link" => $this->_scopeConfig->getValue("cembrapayinstallmentsettings/cembrapaycheckout_installment_3installment/link", ScopeInterface::SCOPE_STORE)
            ),
            self::$INSTALLMENT_4 => array(
                "value" => self::$INSTALLMENT_4,
                "name" => $this->_scopeConfig->getValue("cembrapayinstallmentsettings/cembrapaycheckout_installment_4installment/name", ScopeInterface::SCOPE_STORE),
                "link" => $this->_scopeConfig->getValue("cembrapayinstallmentsettings/cembrapaycheckout_installment_4installment/link", ScopeInterface::SCOPE_STORE)
            ),
            self::$INSTALLMENT_6 => array(
                "value" => self::$INSTALLMENT_6,
                "name" => $this->_scopeConfig->getValue("cembrapayinstallmentsettings/cembrapaycheckout_installment_6installment/name", ScopeInterface::SCOPE_STORE),
                "link" => $this->_scopeConfig->getValue("cembrapayinstallmentsettings/cembrapaycheckout_installment_6installment/link", ScopeInterface::SCOPE_STORE)
            ),
            self::$INSTALLMENT_12 => array(
                "value" => self::$INSTALLMENT_12,
                "name" => $this->_scopeConfig->getValue("cembrapayinstallmentsettings/cembrapaycheckout_installment_12installment/name", ScopeInterface::SCOPE_STORE),
                "link" => $this->_scopeConfig->getValue("cembrapayinstallmentsettings/cembrapaycheckout_installment_12installment/link", ScopeInterface::SCOPE_STORE)
            ),
            self::$INSTALLMENT_24 => array(
                "value" => self::$INSTALLMENT_24,
                "name" => $this->_scopeConfig->getValue("cembrapayinstallmentsettings/cembrapaycheckout_installment_24installment/name", ScopeInterface::SCOPE_STORE),
                "link" => $this->_scopeConfig->getValue("cembrapayinstallmentsettings/cembrapaycheckout_installment_24installment/link", ScopeInterface::SCOPE_STORE)
            ),
            self::$INSTALLMENT_36 => array(
                "value" => self::$INSTALLMENT_36,
                "name" => $this->_scopeConfig->getValue("cembrapayinstallmentsettings/cembrapaycheckout_installment_36installment/name", ScopeInterface::SCOPE_STORE),
                "link" => $this->_scopeConfig->getValue("cembrapayinstallmentsettings/cembrapaycheckout_installment_36installment/link", ScopeInterface::SCOPE_STORE)
            ),
            self::$INSTALLMENT_48 => array(
                "value" => self::$INSTALLMENT_48,
                "name" => $this->_scopeConfig->getValue("cembrapayinstallmentsettings/cembrapaycheckout_installment_48installment/name", ScopeInterface::SCOPE_STORE),
                "link" => $this->_scopeConfig->getValue("cembrapayinstallmentsettings/cembrapaycheckout_installment_48installment/link", ScopeInterface::SCOPE_STORE)
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

    function getTransactionForOrder($orderId, $tx)
    {
        if ($tx == "CHK") {
            return $this->_cembrapayLogger->getChkTransaction($orderId);
        } else {
            return $this->_cembrapayLogger->getAuthTransaction($orderId);
        }
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
        \Magento\Quote\Api\CartRepositoryInterface $quoteRepository,
        \Magento\Sales\Model\ResourceModel\Order\CollectionFactory $orderCollectionFactory,
        \CembraPayCheckout\CembraPayCheckoutCore\Helper\Api\CembraPayAzure $cembraPayAzure,
        \Magento\Framework\App\Config\Storage\WriterInterface $writerInterface,
        ReinitableConfigInterface $reinitableConfig
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
        $this->orderCollectionFactory = $orderCollectionFactory;
        $this->cembraPayAzure = $cembraPayAzure;
        $this->_writerInterface = $writerInterface;
        $this->_reinitableConfig = $reinitableConfig;
    }

    function getPendingOrders()
    {
        $methodInvoice = "cembrapaycheckout_invoice";
        $methodInstallemnt = "cembrapaycheckout_installment";
        $information = "%\"chk_executed_ok\":\"true\"%";
        $subQuery = new \Zend_Db_Expr(sprintf("(SELECT parent_id FROM sales_order_payment WHERE (method = '%s' || method = '%s') AND additional_information like '%s')",
            $methodInvoice,
            $methodInstallemnt,
            $information));

        $orderCollection = $this->orderCollectionFactory
            ->create()
            ->addAttributeToSelect('*')
            ->addAttributeToFilter('status', ['eq' => "pending"])
            ->addFieldToFilter('entity_id', [
                'in' => $subQuery,
            ]);


        return $orderCollection;
    }

    protected $_savedUser = array(
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

    public function getEnabledMethods()
    {
        $methodsAvailable = array();
        if ($this->_scopeConfig->getValue("cembrapayinvoicesettings/cembrapaycheckout_single_invoice/active", ScopeInterface::SCOPE_STORE)) {
            $methodsAvailable[] = DataHelper::$SINGLEINVOICE;
        }

        if ($this->_scopeConfig->getValue("cembrapayinvoicesettings/cembrapaycheckout_invoice_partial/active", ScopeInterface::SCOPE_STORE)) {
            $methodsAvailable[] = DataHelper::$CEMBRAPAYINVOICE;
        }

        if ($this->_scopeConfig->getValue("cembrapayinstallmentsettings/cembrapaycheckout_installment_3installment/active", ScopeInterface::SCOPE_STORE)) {
            $methodsAvailable[] = DataHelper::$INSTALLMENT_3;
        }

        if ($this->_scopeConfig->getValue("cembrapayinstallmentsettings/cembrapaycheckout_installment_4installment/active", ScopeInterface::SCOPE_STORE)) {
            $methodsAvailable[] = DataHelper::$INSTALLMENT_4;
        }

        if ($this->_scopeConfig->getValue("cembrapayinstallmentsettings/cembrapaycheckout_installment_6installment/active", ScopeInterface::SCOPE_STORE)) {
            $methodsAvailable[] = DataHelper::$INSTALLMENT_6;
        }

        if ($this->_scopeConfig->getValue("cembrapayinstallmentsettings/cembrapaycheckout_installment_12installment/active", ScopeInterface::SCOPE_STORE)) {
            $methodsAvailable[] = DataHelper::$INSTALLMENT_12;
        }

        if ($this->_scopeConfig->getValue("cembrapayinstallmentsettings/cembrapaycheckout_installment_24installment/active", ScopeInterface::SCOPE_STORE)) {
            $methodsAvailable[] = DataHelper::$INSTALLMENT_24;
        }

        if ($this->_scopeConfig->getValue("cembrapayinstallmentsettings/cembrapaycheckout_installment_36installment/active", ScopeInterface::SCOPE_STORE)) {
            $methodsAvailable[] = DataHelper::$INSTALLMENT_36;
        }

        if ($this->_scopeConfig->getValue("cembrapayinstallmentsettings/cembrapaycheckout_installment_48installment/active", ScopeInterface::SCOPE_STORE)) {
            $methodsAvailable[] = DataHelper::$INSTALLMENT_48;
        }
        return $methodsAvailable;
    }

    /* @var $quote \Magento\Quote\Model\Quote */
    public function GetCreditStatus($quote, $methods)
    {
        if ($quote == null) {
            return true;
        }
        $objectManager = ObjectManager::getInstance();
        $state = $objectManager->get('Magento\Framework\App\State');
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
            $allowedByjunoPaymentMethods = $this->_checkoutSession->getScreeningStatus();
            if (empty($allowedByjunoPaymentMethods)) {
                $allowedByjunoPaymentMethods = Array();
            }
            try {
                $request = $this->CreateMagentoShopRequestScreening($quote);
                if ($request->amount == 0) {
                    return false;
                }
                $arrCheck = array(
                    "FirstName" => $request->custDetails->firstName,
                    "LastName" => $request->custDetails->lastName,
                    "CountryCode" => $request->billingAddr->country,
                    "Town" => $request->billingAddr->town
                );
                foreach ($arrCheck as $arrK => $arrV) {
                    if (empty($arrV)) {
                        return false;
                    }
                }
                if (!$this->isTheSame($request) || empty($allowedByjunoPaymentMethods)) {
                    $CembraPayRequestName = $request->requestMsgType;
                    $json = $request->createRequest();
                    $cembrapayCommunicator = new CembraPayCommunicator($this->cembraPayAzure);
                    $mode = $this->_scopeConfig->getValue('cembrapaycheckoutsettings/cembrapaycheckout_setup/currentmode', ScopeInterface::SCOPE_STORE);
                    if ($mode == 'live') {
                        $cembrapayCommunicator->setServer('live');
                    } else {
                        $cembrapayCommunicator->setServer('test');
                    }
                    $response = $cembrapayCommunicator->sendScreeningRequest($json, $this->getAccessData(), function ($object, $token) {
                        $object->saveToken($token);
                    });

                    if ($response) {
                        /* @var $responseRes CembraPayCheckoutScreeningResponse */
                        $responseRes = $this->screeningResponse($response);
                        $allowedByjunoPaymentMethods = $responseRes->screeningDetails->allowedByjunoPaymentMethods;
                        $this->saveLog($json, $response, $responseRes->processingStatus, $CembraPayRequestName,
                            $request->custDetails->firstName, $request->custDetails->lastName, $request->requestMsgId,
                            $request->billingAddr->postalCode, $request->billingAddr->town, $request->billingAddr->country, $request->billingAddr->addrFirstLine, $responseRes->transactionId, "-");
                    } else {
                        $this->saveLog($json, $response, "Query error", $CembraPayRequestName,
                            $request->custDetails->firstName, $request->custDetails->lastName, $request->requestMsgId,
                            $request->billingAddr->postalCode, $request->billingAddr->town, $request->billingAddr->country, $request->billingAddr->addrFirstLine, "-", "-");
                    }

                    $this->_savedUser = array(
                        "FirstName" => $request->custDetails->firstName,
                        "LastName" => $request->custDetails->lastName,
                        "FirstLine" => $request->billingAddr->addrFirstLine,
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
                    $this->_checkoutSession->setScreeningStatus($allowedByjunoPaymentMethods);
                }
                DataHelper::$allowedByjunoPaymentMethods = $allowedByjunoPaymentMethods;
                foreach ($methods as $method) {
                    foreach ($allowedByjunoPaymentMethods as $st) {
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

    public function saveToken($token) {
        $this->_writerInterface->save('cembrapaycheckoutsettings/cembrapaycheckout_setup/access_token', $token);
        $this->_reinitableConfig->reinit();
    }

    public function getAccessData() {
        $accessData = new CembraPayLoginDto();
        $accessData->helperObject = $this;
        $accessData->timeout = (int)$this->_scopeConfig->getValue('cembrapaycheckoutsettings/cembrapaycheckout_setup/timeout', ScopeInterface::SCOPE_STORE);
        $accessData->username = $this->_scopeConfig->getValue('cembrapaycheckoutsettings/cembrapaycheckout_setup/cembrapaylogin', ScopeInterface::SCOPE_STORE);
        $accessData->password = $this->_scopeConfig->getValue('cembrapaycheckoutsettings/cembrapaycheckout_setup/cembrapaypassword', ScopeInterface::SCOPE_STORE);
        $accessData->audience = $this->_scopeConfig->getValue('cembrapaycheckoutsettings/cembrapaycheckout_setup/audience', ScopeInterface::SCOPE_STORE);
        $accessData->accessToken = $this->_scopeConfig->getValue('cembrapaycheckoutsettings/cembrapaycheckout_setup/access_token');
        return $accessData;
    }

    public function getAccessDataWebshop($webShopId) {
        $accessData = new CembraPayLoginDto();
        $accessData->helperObject = $this;
        $accessData->timeout = (int)$this->_scopeConfig->getValue('cembrapaycheckoutsettings/cembrapaycheckout_setup/timeout', ScopeInterface::SCOPE_STORE, $webShopId);
        $accessData->username = $this->_scopeConfig->getValue('cembrapaycheckoutsettings/cembrapaycheckout_setup/cembrapaylogin', ScopeInterface::SCOPE_STORE, $webShopId);
        $accessData->password = $this->_scopeConfig->getValue('cembrapaycheckoutsettings/cembrapaycheckout_setup/cembrapaypassword', ScopeInterface::SCOPE_STORE, $webShopId);
        $accessData->audience = $this->_scopeConfig->getValue('cembrapaycheckoutsettings/cembrapaycheckout_setup/audience', ScopeInterface::SCOPE_STORE, $webShopId);
        $accessData->accessToken = $this->_scopeConfig->getValue('cembrapaycheckoutsettings/cembrapaycheckout_setup/access_token');
        return $accessData;
    }

    public function isTheSame(CembraPayCheckoutAutRequest $request)
    {
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
            $request->custDetails->merchantCustRef = (string)$quote->getCustomerId();
            $request->custDetails->loggedIn = true;
        }
        if ($quote->getBillingAddress()->getCompany()
            && $this->_scopeConfig->getValue('cembrapaycheckoutsettings/cembrapaycheckout_setup/businesstobusiness', ScopeInterface::SCOPE_STORE) == '1') {
            $request->custDetails->custType = self::$CUSTOMER_BUSINESS;
        } else {
            $request->custDetails->custType = self::$CUSTOMER_PRIVATE;
        }
        $request->custDetails->firstName = (string)$quote->getBillingAddress()->getFirstname();
        $request->custDetails->lastName = (string)$quote->getBillingAddress()->getLastname();
        $request->custDetails->language = (string)substr($this->_resolver->getLocale(), 0, 2);
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
        $gender_male_possible_prefix = explode(";", strtolower($gender_male_possible_prefix_array ?? ""));
        $gender_female_possible_prefix = explode(";", strtolower($gender_female_possible_prefix_array ?? ""));
        if ($genderEntity != null && $genderEntity->isVisible()) {
            if (in_array(strtolower($quote->getBillingAddress()->getPrefix() ?? ""), $gender_male_possible_prefix)) {
                $request->custDetails->salutation = self::$GENTER_MALE;
            } else if (in_array(strtolower($quote->getBillingAddress()->getPrefix() ?? ""), $gender_female_possible_prefix)) {
                $request->custDetails->salutation = self::$GENTER_FEMALE;
            }
        }

        $billingStreet = $quote->getBillingAddress()->getStreet();
        $billingStreet = implode("", $billingStreet);

        $request->billingAddr->addrFirstLine = (string)$billingStreet;
        $request->billingAddr->postalCode = (string)$quote->getBillingAddress()->getPostcode();
        $request->billingAddr->town = (string)$quote->getBillingAddress()->getCity();
        $request->billingAddr->country = strtoupper($quote->getBillingAddress()->getCountryId() ?? "");

        $request->custContacts->phoneMobile = (string)trim($quote->getBillingAddress()->getTelephone(), '-');
        $request->custContacts->phoneBusiness = (string)trim($quote->getBillingAddress()->getTelephone(), '-');
        $request->custContacts->phonePrivate = (string)trim($quote->getBillingAddress()->getTelephone(), '-');
        $request->custContacts->email = (string)$quote->getBillingAddress()->getEmail();

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

            $request->deliveryDetails->deliveryAddrFirstLine = trim((string)$shippingStreet);
            $request->deliveryDetails->deliveryAddrPostalCode = $this->nullToString($quote->getShippingAddress()->getPostcode());
            $request->deliveryDetails->deliveryAddrTown = $this->nullToString($quote->getShippingAddress()->getCity());
            $request->deliveryDetails->deliveryAddrCountry = strtoupper($quote->getShippingAddress()->getCountryId() ?? "");

        } else {
            $request->deliveryDetails->deliveryDetailsDifferent = false;
            $request->deliveryDetails->deliveryMethod = self::$DELIVERY_VIRTUAL;
        }

        $sedId = $this->_checkoutSession->getTmxSession();
        if ($this->_scopeConfig->getValue('cembrapaycheckoutsettings/cembrapaycheckout_setup/tmxenabled', ScopeInterface::SCOPE_STORE) == '1' && !empty($sedId)) {
            $request->sessionInfo->fingerPrint = $sedId;
        }

        $customerConsents = new CustomerConsents();
        $customerConsents->consentType = "BYJUNO-TC";
        $customerConsents->consentProvidedAt = "MERCHANT";
        $customerConsents->consentDate = CembraPayCheckoutAutRequest::Date();
        $customerConsents->consentReference = "MERCHANT DATA PRIVACY";
        $request->customerConsents = array($customerConsents);

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
                if (!empty($responseObject->screeningDetails) && !empty(!empty($responseObject->screeningDetails->allowedByjunoPaymentMethods))) {
                    $result->screeningDetails->allowedByjunoPaymentMethods = $responseObject->screeningDetails->allowedByjunoPaymentMethods;
                }
            } else {
                $result->processingStatus = $responseObject->processingStatus;
            }
        }
        return $result;
    }

    public function createMagentoShopRequestAuthorization(Order $order,
                                                          Order\Payment $paymentMethod,
                                                          $gender_custom, $dob_custom, $pref_lang, $b2b_uid, $agree_tc, $webShopProfile)
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
            $request->custDetails->merchantCustRef = (string)$order->getCustomerId();
            $request->custDetails->loggedIn = true;
        }
        if ($order->getBillingAddress()->getCompany() && $this->_scopeConfig->getValue('cembrapaycheckoutsettings/cembrapaycheckout_setup/businesstobusiness', ScopeInterface::SCOPE_STORE, $webShopProfile) == '1') {
            $request->custDetails->custType = self::$CUSTOMER_BUSINESS;
        } else {
            $request->custDetails->custType = self::$CUSTOMER_PRIVATE;
        }
        $request->custDetails->firstName = (string)$order->getBillingAddress()->getFirstname();
        $request->custDetails->lastName = (string)$order->getBillingAddress()->getLastname();
        if (!empty($pref_lang)) {
            $request->custDetails->language = (string)$pref_lang;
        } else {
            $request->custDetails->language = (string)substr($this->_resolver->getLocale(), 0, 2);
        }

        if (!empty($b2b_uid)) {
            $request->custDetails->companyRegNum = (string)$b2b_uid;
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
        $gender_male_possible_prefix = explode(";", strtolower($gender_male_possible_prefix_array ?? ""));
        $gender_female_possible_prefix = explode(";", strtolower($gender_female_possible_prefix_array ?? ""));
        if ($genderEntity != null && $genderEntity->isVisible()) {
            if (in_array(strtolower($order->getBillingAddress()->getPrefix() ?? ""), $gender_male_possible_prefix)) {
                $request->custDetails->salutation = self::$GENTER_MALE;
            } else if (in_array(strtolower($order->getBillingAddress()->getPrefix() ?? ""), $gender_female_possible_prefix)) {
                $request->custDetails->salutation = self::$GENTER_FEMALE;
            }
        }

        if (!empty($gender_custom)) {
            if (in_array(strtolower($gender_custom ?? ""), $gender_male_possible_prefix)) {
                $request->custDetails->salutation = self::$GENTER_MALE;
            } else if (in_array(strtolower($gender_custom ?? ""), $gender_female_possible_prefix)) {
                $request->custDetails->salutation = self::$GENTER_FEMALE;
            }
        }

        $billingStreet = $order->getBillingAddress()->getStreet();
        $billingStreet = implode("", $billingStreet);

        $request->billingAddr->addrFirstLine = (string)$billingStreet;
        $request->billingAddr->postalCode = (string)$order->getBillingAddress()->getPostcode();
        $request->billingAddr->town = (string)$order->getBillingAddress()->getCity();
        $request->billingAddr->country = strtoupper($order->getBillingAddress()->getCountryId() ?? "");

        $request->custContacts->phoneMobile = (string)trim($order->getBillingAddress()->getTelephone(), '-');
        $request->custContacts->phonePrivate = (string)trim($order->getBillingAddress()->getTelephone(), '-');
        $request->custContacts->phoneBusiness = (string)trim($order->getBillingAddress()->getTelephone(), '-');
        $request->custContacts->email = (string)$order->getBillingAddress()->getEmail();

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

            $request->deliveryDetails->deliveryAddrFirstLine = trim((string)$shippingStreet);
            $request->deliveryDetails->deliveryAddrPostalCode = $this->nullToString($order->getShippingAddress()->getPostcode());
            $request->deliveryDetails->deliveryAddrTown = $this->nullToString($order->getShippingAddress()->getCity());
            $request->deliveryDetails->deliveryAddrCountry = strtoupper($order->getShippingAddress()->getCountryId() ?? "");

        } else {
            $request->deliveryDetails->deliveryDetailsDifferent = false;
            $request->deliveryDetails->deliveryMethod = self::$DELIVERY_VIRTUAL;
        }

        $request->order->basketItemsGoogleTaxonomies = array();
        $request->order->basketItemsPrices = array();

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
        if ($agree_tc) {
            $customerConsents = new CustomerConsents();
            $customerConsents->consentType = "BYJUNO-TC";
            $customerConsents->consentProvidedAt = "MERCHANT";
            $customerConsents->consentDate = CembraPayCheckoutAutRequest::Date();
            $methods = $this->getMethodsMapping();
            $link = $methods[$paymentMethod->getAdditionalInformation('payment_plan')]["link"];
            $exLink = explode("/", $link);
            $consentReference = end($exLink);
            if (empty($consentReference) && isset($exLink[count($exLink) - 1])) {
                $consentReference = $exLink[count($exLink) - 2];
            }
            $customerConsents->consentReference = $consentReference;
            $request->customerConsents = array($customerConsents);
        }
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
            $request->custDetails->merchantCustRef = (string)$order->getCustomerId();
            $request->custDetails->loggedIn = true;
        }
        if ($order->getBillingAddress()->getCompany() && $this->_scopeConfig->getValue('cembrapaycheckoutsettings/cembrapaycheckout_setup/businesstobusiness', ScopeInterface::SCOPE_STORE, $webShopProfile) == '1') {
            $request->custDetails->custType = self::$CUSTOMER_BUSINESS;
        } else {
            $request->custDetails->custType = self::$CUSTOMER_PRIVATE;
        }
        $request->custDetails->firstName = (string)$order->getBillingAddress()->getFirstname();
        $request->custDetails->lastName = (string)$order->getBillingAddress()->getLastname();
        $request->custDetails->language = (string)substr($this->_resolver->getLocale(), 0, 2);

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
        $gender_male_possible_prefix = explode(";", strtolower($gender_male_possible_prefix_array ?? ""));
        $gender_female_possible_prefix = explode(";", strtolower($gender_female_possible_prefix_array ?? ""));
        if ($genderEntity != null && $genderEntity->isVisible()) {
            if (in_array(strtolower($order->getBillingAddress()->getPrefix() ?? ""), $gender_male_possible_prefix)) {
                $request->custDetails->salutation = self::$GENTER_MALE;
            } else if (in_array(strtolower($order->getBillingAddress()->getPrefix() ?? ""), $gender_female_possible_prefix)) {
                $request->custDetails->salutation = self::$GENTER_FEMALE;
            }
        }

        $billingStreet = $order->getBillingAddress()->getStreet();
        $billingStreet = implode("", $billingStreet);

        $request->billingAddr->addrFirstLine = (string)$billingStreet;
        $request->billingAddr->postalCode = (string)$order->getBillingAddress()->getPostcode();
        $request->billingAddr->town = (string)$order->getBillingAddress()->getCity();
        $request->billingAddr->country = strtoupper($order->getBillingAddress()->getCountryId() ?? "");

        $request->custContacts->phoneMobile = (string)trim($order->getBillingAddress()->getTelephone(), '-');
        $request->custContacts->phonePrivate = (string)trim($order->getBillingAddress()->getTelephone(), '-');
        $request->custContacts->phoneBusiness = (string)trim($order->getBillingAddress()->getTelephone(), '-');
        $request->custContacts->email = (string)$order->getBillingAddress()->getEmail();

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

            $request->deliveryDetails->deliveryAddrFirstLine = trim((string)$shippingStreet);
            $request->deliveryDetails->deliveryAddrPostalCode = $this->nullToString($order->getShippingAddress()->getPostcode());
            $request->deliveryDetails->deliveryAddrTown = $this->nullToString($order->getShippingAddress()->getCity());
            $request->deliveryDetails->deliveryAddrCountry = strtoupper($order->getShippingAddress()->getCountryId() ?? "");

        } else {
            $request->deliveryDetails->deliveryDetailsDifferent = false;
            $request->deliveryDetails->deliveryMethod = self::$DELIVERY_VIRTUAL;
        }

        $request->order->basketItemsGoogleTaxonomies = array();
        $request->order->basketItemsPrices = array();

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

        $request->customerConsents = array($customerConsents);

        $request->merchantDetails->returnUrlError = $this->_urlBuilder->getUrl('cembrapaycheckoutcore/checkout/cancel');
        $request->merchantDetails->ReturnUrlCancel = $this->_urlBuilder->getUrl('cembrapaycheckoutcore/checkout/cancel');
        $request->merchantDetails->returnUrlSuccess = $this->_urlBuilder->getUrl('cembrapaycheckoutcore/checkout/success');

        $request->merchantDetails->transactionChannel = "WEB";
        $request->merchantDetails->integrationModule = "CembraPay Checkout Magento 2 module 0.0.1";

        return $request;
    }

    public function createMagentoShopRequestGetTransaction($transactionId, $webShopProfile)
    {
        $request = new CembraPayGetStatusRequest();
        $request->merchantId = $this->_scopeConfig->getValue('cembrapaycheckoutsettings/cembrapaycheckout_setup/merchantid', ScopeInterface::SCOPE_STORE, $webShopProfile);
        $request->requestMsgType = self::$MESSAGE_STATUS;
        $request->requestMsgId = CembraPayCheckoutChkRequest::GUID();
        $request->requestMsgDateTime = CembraPayCheckoutChkRequest::Date();
        $request->transactionId = $transactionId;

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

    function getTransactionResponse($response)
    {
        $responseObject = json_decode($response);
        $result = new CembraPayGetStatusResponse();
        if (empty($responseObject->transactionStatus->transactionStatus)) {
            $result->transactionStatus->transactionStatus= self::$REQUEST_ERROR;
        } else {
            $result->requestMerchantId = $responseObject->requestMerchantId;
            $result->requestMsgType = $responseObject->transactionId;
            $result->requestMsgId = $responseObject->requestMsgType;
            $result->requestMsgDateTime = $responseObject->requestMsgDateTime;
            $result->replyMsgId = $responseObject->replyMsgId;
            $result->replyMsgDateTime = $responseObject->replyMsgDateTime;
            $result->isTokenDeleted = !empty($responseObject->isTokenDeleted) ? $responseObject->isTokenDeleted : false;
            $result->merchantOrderRef = $responseObject->merchantOrderRef;
            $result->transactionStatus->transactionStatus = $responseObject->transactionStatus->transactionStatus;
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

    function cancelResponse($response)
    {
        $responseObject = json_decode($response);
        $result = new CembraPayCheckoutCancelResponse();
        if (empty($responseObject->processingStatus)) {
            $result->processingStatus = self::$REQUEST_ERROR;
        } else {
            if ($responseObject->processingStatus == self::$CANCEL_OK) {
                // TODO if need
                $result->processingStatus = $responseObject->processingStatus;
                $result->transactionId = $responseObject->transactionId;
            } else {
                $result->processingStatus = $responseObject->processingStatus;
            }
        }
        return $result;
    }

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
    }

    function CreateMagentoShopRequestCancel(Order $order, $amount, $webshopProfile, $tx)
    {
        $request = new CembraPayCheckoutCancelRequest();
        $request->merchantId = $this->_scopeConfig->getValue('cembrapaycheckoutsettings/cembrapaycheckout_setup/merchantid', ScopeInterface::SCOPE_STORE, $webshopProfile);
        $request->requestMsgType = self::$MESSAGE_CAN;
        $request->requestMsgId = CembraPayCheckoutAutRequest::GUID();
        $request->requestMsgDateTime = CembraPayCheckoutAutRequest::Date();
        $request->transactionId = $tx;
        $request->merchantOrderRef = $order->getRealOrderId();
        $request->amount = number_format($amount, 2, '.', '') * 100;
        $request->currency = $order->getOrderCurrencyCode();
        return $request;
    }

}
