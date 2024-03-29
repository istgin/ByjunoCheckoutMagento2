<?php
/**
 * Created by PhpStorm.
 * User: Igor
 * Date: 15.10.2016
 * Time: 18:38
 */
namespace Byjuno\ByjunoCore\Model\Source;

use Byjuno\ByjunoCore\Helper\DataHelper;
use Magento\Framework\Data\Form\Element\AbstractElement;

class ExplainScreening implements \Magento\Config\Model\Config\CommentInterface
{

    protected $dataHelper;

    public function __construct(
        DataHelper $dataHelper
    ) {
        $this->dataHelper = $dataHelper;
    }

    public function getCommentText($elementValue)
    {
        $cembrapaycheckout_screening_explain = $this->dataHelper->_scopeConfig->getValue('cembrapaycheckoutsettings/cembrapaycheckout_setup/screeningbeforeshow', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
        $message = 'Enable screening request before show CembraPay Checkout payment methods';
        if ($cembrapaycheckout_screening_explain == 1) {
            $message = 'Enable screening request before show CembraPay Checkout payment methods<br><br>if "Screening before show payment" is enabled by the merchant,  the merchant explicitly agrees and warrants the following:<br /><br />
-        The merchant (as data controller) assigns CembraPay (as data processor) to perform a credit-check (screening) on its behalf. In this case the end-customers data (name, address, date of birth, contact information (email, telephone number) as well as IP-Address, Used Proxy Servers, etc.) is automatically being sent from the merchant to CembraPay and further to Intrum as subprocessor. CembraPay confirms or rejects to the merchant the availability of CembraPay payment solution for the specific customer.<br /><br />
-        The merchant must clearly and transparently inform the end-customers about the data processing by CembraPay and Intrum and obtain (if legally required) their consent. It is in the sole responsibility of the merchant to assess the legality of this data processing and the merchant must ensure at all times full compliance with applicable data protection regulations.<br />
';
        }
        return $message;
    }
}
