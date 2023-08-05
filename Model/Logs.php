<?php

namespace CembraPayCheckout\CembraPayCheckoutCore\Model;

use Magento\Framework\Model\AbstractModel;

class Logs extends AbstractModel
{
    /**
     * Define resource model
     */
    protected function _construct()
    {
        $this->_init('CembraPayCheckout\CembraPayCheckoutCore\Model\Resource\Logs');
    }
}
