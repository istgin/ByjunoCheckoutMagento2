<?php
/**
 * Created by PhpStorm.
 * User: Igor
 * Date: 15.10.2016
 * Time: 15:43
 */

namespace ByjunoCheckout\ByjunoCheckoutCore\Model\Source;

use Magento\Framework\Option\ArrayInterface;

class AllowPayment implements ArrayInterface
{
    protected $_categoryHelper;

    public function __construct(\Magento\Catalog\Helper\Category $catalogCategory)
    {
        $this->_categoryHelper = $catalogCategory;
    }


    /*
     * Option getter
     * @return array
     */
    public function toOptionArray()
    {


        $arr = $this->toArray();
        $ret = [];

        foreach ($arr as $key => $value)
        {

            $ret[] = [
                'value' => $key,
                'label' => $value
            ];
        }

        return $ret;
    }

    /*
     * Get options in "key-value" format
     * @return array
     */
    public function toArray()
    {
        $catagoryList = array();
        $catagoryList["0"] = 'Allowed when screening approved. In case if screening disabled - always allowed';
        $catagoryList["1"] = 'Always allowed';
        $catagoryList["2"] = 'Denied';
        return $catagoryList;
    }

}
