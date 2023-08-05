<?php

namespace CembraPayCheckout\CembraPayCheckoutCore\Model\Resource\Logs;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

class Collection extends AbstractCollection
{
    /**
     * Define model & resource model
     */
    protected function _construct()
    {
        $this->_init(
            'CembraPayCheckout\CembraPayCheckoutCore\Model\Logs',
            'CembraPayCheckout\CembraPayCheckoutCore\Model\Resource\Logs'
        );
    }
}
