<?php

namespace ByjunoCheckout\ByjunoCheckoutCore\Model;

use Magento\Framework\Model\AbstractModel;

class Logs extends AbstractModel
{
    /**
     * Define resource model
     */
    protected function _construct()
    {
        $this->_init('ByjunoCheckout\ByjunoCheckoutCore\Model\Resource\Logs');
    }
}
