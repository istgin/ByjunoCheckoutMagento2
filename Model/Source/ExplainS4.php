<?php
/**
 * Created by PhpStorm.
 * User: Igor
 * Date: 15.10.2016
 * Time: 18:38
 */
namespace ByjunoCheckout\ByjunoCheckoutCore\Model\Source;

use Magento\Backend\Block\Template\Context;
use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;

class ExplainS4 extends Field
{
    /**
     * Return element html
     *
     * @param  AbstractElement $element
     * @return string
     */
    protected function _getElementHtml(AbstractElement $element)
    {
        $byjunocheckout_s4_explain = $this->_scopeConfig->getValue('byjunocheckoutsettings/byjunocheckout_setup/byjunosettletransacton', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
        $message = 'Settle Transaction must be delivered to Byjuno Checkout manually or from ERP system';
        $color = 'FFE5E6';
        if ($byjunocheckout_s4_explain == 1) {
            $message = 'Settle Transaction will be sent to Byjuno Checkout when new Invoice is created on the order';
            $color = 'ddffdf';
        }
        return '<div style="white-space: nowrap; background-color: #'.$color.'; padding: 10px 5px 10px 5px">'.$message.'</div>';
    }

}
