<?php

namespace Byjuno\ByjunoCore\Block;


use Byjuno\ByjunoCore\Helper\DataHelper;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory;

class Threatmetrix extends Template
{

    /* @var $_helper DataHelper */
    private $_helper;
    /**
     * @param Context $context
     * @param CollectionFactory $salesOrderCollection
     * @param \Magento\GoogleAnalytics\Helper\Data $googleAnalyticsData
     * @param array $data
     */
    public function __construct(
        Context $context,
        CollectionFactory $salesOrderCollection,
        DataHelper $helper,
        array $data = []
    ) {
        $this->_helper = $helper;
        $this->_salesOrderCollection = $salesOrderCollection;
        parent::__construct($context, $data);
    }

    public function isAvailable()
    {
        $tmxSession = $this->_helper->_checkoutSession->getTmxSession();
        if (empty($tmxSession)) {
            $this->_helper->_checkoutSession->setTmxSession($this->_helper->_checkoutSession->getSessionId());
        }
        if ($this->_helper->_scopeConfig->getValue('cembrapaycheckoutsettings/cembrapaycheckout_setup/tmxenabled', \Magento\Store\Model\ScopeInterface::SCOPE_STORE) == 1 &&
            $this->_helper->_scopeConfig->getValue('cembrapaycheckoutsettings/cembrapaycheckout_setup/tmxkey', \Magento\Store\Model\ScopeInterface::SCOPE_STORE) != '' &&
            empty($tmxSession)) {

            return true;
        }
        return false;
    }

    public function getOrgId()
    {
        return $this->_helper->_scopeConfig->getValue('cembrapaycheckoutsettings/cembrapaycheckout_setup/tmxkey', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
    }

    public function getSessionId()
    {
        return $this->_helper->_checkoutSession->getTmxSession();
    }

    protected function _toHtml()
    {
        return parent::_toHtml();
    }
}
