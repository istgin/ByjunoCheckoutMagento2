<?php

namespace ByjunoCheckout\ByjunoCheckoutCore\Block\Adminhtml;

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
        $this->_blockGroup = 'ByjunoCheckout_ByjunoCheckoutCore';
        parent::_construct();
        $this->removeButton('add');
    }
}
