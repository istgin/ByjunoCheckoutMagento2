<?php
namespace Byjuno\ByjunoCore\Model\Source;

use Byjuno\ByjunoCore\Helper\Api\CembraPayAzure;
use Byjuno\ByjunoCore\Helper\DataHelper;
use Magento\Backend\Block\Template\Context;
use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;

class AccessTokenLive extends Field
{
    /**
     * Return element html
     *
     * @param  AbstractElement $element
     * @return string
     */
    protected function _getElementHtml(AbstractElement $element)
    {
        $access_token_live = $this->_scopeConfig->getValue('cembrapaycheckoutsettings/cembrapaycheckout_setup/access_token_live', \Magento\Store\Model\ScopeInterface::SCOPE_STORE) ?? "";
        $tkn = explode(DataHelper::$tokenSeparator, $access_token_live);
        $color = 'ddffdf';
        $token = "";
        if (!empty($tkn[1])) {
            $token = $tkn[1];
        }
        if (!CembraPayAzure::validToken($token)) {
            $color = 'FFE5E6';
        }
        return '<div style="word-break: break-all; background-color: #'.$color.'; padding: 10px 5px 10px 5px">'.$token.'</div>';
    }

}
