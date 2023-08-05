<?php

namespace CembraPayCheckout\CembraPayCheckoutCore\Block\Adminhtml;

use Magento\Backend\Block\Widget\Grid\Container;

class Logs extends Container
{
    /**
     * Constructor
     *
     * @return void
     */
    protected function _construct()
    {
        $this->_controller = 'adminhtml_logs';
        $this->_blockGroup = 'CembraPayCheckout_CembraPayCheckoutCore';
        parent::_construct();
        $this->removeButton('add');
    }
}
