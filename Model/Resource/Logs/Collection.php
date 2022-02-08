<?php

namespace ByjunoCheckout\ByjunoCheckoutCore\Model\Resource\Logs;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

class Collection extends AbstractCollection
{
    /**
     * Define model & resource model
     */
    protected function _construct()
    {
        $this->_init(
            'ByjunoCheckout\ByjunoCheckoutCore\Model\Logs',
            'ByjunoCheckout\ByjunoCheckoutCore\Model\Resource\Logs'
        );
    }
}
