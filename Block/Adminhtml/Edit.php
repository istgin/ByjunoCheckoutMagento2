<?php

namespace Byjuno\ByjunoCore\Block\Adminhtml;


class Edit extends \Magento\Backend\Block\Widget\Container
{
    /**
     * Constructor
     *
     * @return void
     */
    protected function _construct()
    {
        $this->_controller = 'adminhtml_edit';
        $this->_blockGroup = 'Byjuno_ByjunoCore';
        parent::_construct();
    }

    protected function _toHtml()
    {
        try {
            $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
            $logger = $objectManager->get('\Byjuno\ByjunoCore\Model\Logs');
            $logview = $logger->load($this->getRequest()->getParam('id'));
                $html = '
            <a href="javascript:history.go(-1)">Back to log</a>
            <h1>Input & output JSON</h1>
            <table width="50%">
                <tr>
                    <td>Input</td>
                    <td>Response</td>
                </tr>
                <tr>
                    <td width="50%" style="border: 1px solid #CCCCCC; padding: 5px; vertical-align: top;"><code style="width: 100%; word-wrap: break-word; white-space: pre-wrap;">' . $logview->getData("request") . '</code></td>
                    <td width="50%" style="border: 1px solid #CCCCCC; padding: 5px; vertical-align: top;"><code style="width: 100%; word-wrap: break-word; white-space: pre-wrap;">' . $logview->getData("response") . '</code></td>
                </tr>
            </table>';
        } catch(\Exception $e)
        {
            $html = '
            <a href="javascript:history.go(-1)">Back to log</a><br /><br />
            Error with JSON';
        }
        return $html;
    }

}
