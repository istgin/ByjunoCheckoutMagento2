<?php
/**
 * Created by PhpStorm.
 * User: Igor
 * Date: 08.12.2016
 * Time: 22:12
 */

namespace Byjuno\ByjunoCore\Setup;

use Magento\Framework\DB\Ddl\Table;
use Magento\Framework\Setup\UpgradeDataInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;
/**
 * @codeCoverageIgnore
 */
class UpgradeData implements UpgradeDataInterface
{
    /**
     * {@inheritdoc}
     */
    public function upgrade(ModuleDataSetupInterface $setup, ModuleContextInterface $context)
    {
        $installer = $setup;
        $setup->startSetup();

        if(!$context->getVersion()) {
            //no previous version found, installation, InstallSchema was just executed
            //be careful, since everything below is true for installation !
        }

        if (version_compare($context->getVersion(), '1.0.1') < 0) {
            //code to upgrade to 1.0.1
            //no changes
        }

        if (version_compare($context->getVersion(), '1.0.2') < 0) {
            //code to upgrade to 1.0.2
            //no changes
        }

        if (version_compare($context->getVersion(), '1.0.3') < 0) {
            //code to upgrade to 1.0.3
            //no changes
        }

        if (version_compare($context->getVersion(), '1.0.4') < 0) {
            //code to upgrade to 1.0.4
            //no changes
        }

        if (version_compare($context->getVersion(), '1.0.5') < 0) {
            //code to upgrade to 1.0.5
            //no changes
        }

        if (version_compare($context->getVersion(), '1.0.6') < 0) {
            //code to upgrade to 1.0.6
            //no changes
        }

        if (version_compare($context->getVersion(), '1.0.7') < 0) {
            //code to upgrade to 1.0.7
            //no changes
        }

        if (version_compare($context->getVersion(), '1.0.8') < 0) {
            //code to upgrade to 1.0.8
            //no changes
        }

        if (version_compare($context->getVersion(), '1.0.9') < 0) {
            //code to upgrade to 1.0.9
            //no changes
        }

        if (version_compare($context->getVersion(), '1.1.0') < 0) {
            //code to upgrade to 1.1.0
            //no changes
        }

        if (version_compare($context->getVersion(), '1.1.1') < 0) {
            //code to upgrade to 1.1.1
            //no changes
        }

        if (version_compare($context->getVersion(), '1.1.2') < 0) {
            //code to upgrade to 1.1.2
            //no changes
        }

        if (version_compare($context->getVersion(), '1.2.0') < 0) {
            //code to upgrade to 1.2.0
            //no changes
        }

        if (version_compare($context->getVersion(), '1.2.1') < 0) {
            //code to upgrade to 1.2.1
            //no changes
        }

        if (version_compare($context->getVersion(), '1.3.0') < 0) {
            //code to upgrade to 1.3.0
            //no changes
        }

        if (version_compare($context->getVersion(), '1.4.0') < 0) {
            //code to upgrade to 1.4.0
            //no changes
        }

        if (version_compare($context->getVersion(), '1.4.1') < 0) {
            //code to upgrade to 1.4.1
            //no changes
        }

        if (version_compare($context->getVersion(), '1.5.0') < 0) {
            //code to upgrade to 1.5.0
            //no changes
        }

        if (version_compare($context->getVersion(), '1.5.1') < 0) {
            //code to upgrade to 1.5.1
            //no changes
        }

        if (version_compare($context->getVersion(), '1.5.2') < 0) {
            //code to upgrade to 1.5.2
            //no changes
        }

        if (version_compare($context->getVersion(), '1.6.0') < 0) {
            //code to upgrade to 1.5.2
            //no changes
        }

        if (version_compare($context->getVersion(), '1.7.0') < 0) {
            //code to upgrade to 1.5.2
            //no changes
        }

        if (version_compare($context->getVersion(), '1.7.2') < 0) {
            //code to upgrade to 1.5.2
            //no changes
        }

        if (version_compare($context->getVersion(), '1.7.3') < 0) {
            //code to upgrade to 1.5.2
            //no changes
        }

        if (version_compare($context->getVersion(), '1.7.4') < 0) {
            //code to upgrade to 1.5.2
            //no changes
        }

        if (version_compare($context->getVersion(), '1.7.5') < 0) {
            //code to upgrade to 1.7.5
            //no changes
        }

        if (version_compare($context->getVersion(), '1.7.6') < 0) {
            //code to upgrade to 1.7.6
            //no changes
        }

        if (version_compare($context->getVersion(), '1.7.7') < 0) {
            //code to upgrade to 1.7.7
            //no changes
        }

        if (version_compare($context->getVersion(), '1.8.0') < 0) {
            //code to upgrade to 1.7.7
            //no changes
        }

        if (version_compare($context->getVersion(), '1.8.1') < 0) {
            //code to upgrade to 1.8.1
            //no changes
        }

        if (version_compare($context->getVersion(), '1.8.2') < 0) {
            //code to upgrade to 1.8.2
            //no changes
        }

        if (version_compare($context->getVersion(), '3.0.1') < 0) {
            //code to upgrade to 3.0.1
            //no changes
            $tableName = $installer->getTable('cembrapaycheckout_log');
            // Check if the table already exists

            if ($installer->getConnection()->isTableExists($tableName) != true) {
                // Create tutorial_simplenews table
                $table = $installer->getConnection()
                    ->newTable($tableName)
                    ->addColumn(
                        'id',
                        Table::TYPE_INTEGER,
                        null,
                        [
                            'identity' => true,
                            'unsigned' => true,
                            'nullable' => false,
                            'primary' => true
                        ],
                        'ID'
                    )
                    ->addColumn(
                        'firstname',
                        Table::TYPE_TEXT,
                        null,
                        ['nullable' => false, 'default' => ''],
                        'First name'
                    )
                    ->addColumn(
                        'lastname',
                        Table::TYPE_TEXT,
                        null,
                        ['nullable' => false, 'default' => ''],
                        'Last name'
                    )
                    ->addColumn(
                        'town',
                        Table::TYPE_TEXT,
                        null,
                        ['nullable' => false, 'default' => ''],
                        'Town'
                    )
                    ->addColumn(
                        'postcode',
                        Table::TYPE_TEXT,
                        null,
                        ['nullable' => false, 'default' => ''],
                        'Post code'
                    )
                    ->addColumn(
                        'street',
                        Table::TYPE_TEXT,
                        null,
                        ['nullable' => false, 'default' => ''],
                        'Street'
                    )
                    ->addColumn(
                        'country',
                        Table::TYPE_TEXT,
                        null,
                        ['nullable' => false, 'default' => ''],
                        'Country'
                    )
                    ->addColumn(
                        'ip',
                        Table::TYPE_TEXT,
                        null,
                        ['nullable' => false, 'default' => ''],
                        'IP'
                    )
                    ->addColumn(
                        'status',
                        Table::TYPE_TEXT,
                        null,
                        ['nullable' => false, 'default' => ''],
                        'Status'
                    )
                    ->addColumn(
                        'request_id',
                        Table::TYPE_TEXT,
                        null,
                        ['nullable' => false, 'default' => ''],
                        'Request id'
                    )
                    ->addColumn(
                        'type',
                        Table::TYPE_TEXT,
                        null,
                        ['nullable' => false, 'default' => ''],
                        'Request type'
                    )
                    ->addColumn(
                        'error',
                        Table::TYPE_TEXT,
                        null,
                        ['nullable' => false, 'default' => ''],
                        'Error'
                    )
                    ->addColumn(
                        'response',
                        Table::TYPE_TEXT,
                        null,
                        ['nullable' => false, 'default' => ''],
                        'Response'
                    )
                    ->addColumn(
                        'request',
                        Table::TYPE_TEXT,
                        null,
                        ['nullable' => false, 'default' => ''],
                        'Request'
                    )
                    ->addColumn(
                        'order_id',
                        Table::TYPE_TEXT,
                        null,
                        ['nullable' => false, 'default' => ''],
                        'Request id'
                    )
                    ->addColumn(
                        'transaction_id',
                        Table::TYPE_TEXT,
                        null,
                        ['nullable' => false, 'default' => ''],
                        'Request id'
                    )
                    ->addColumn(
                        'creation_date',
                        Table::TYPE_DATETIME,
                        null,
                        ['nullable' => false],
                        'Created At'
                    )
                    ->setComment('CembraPay request table')
                    ->setOption('type', 'InnoDB')
                    ->setOption('charset', 'utf8');
                $installer->getConnection()->createTable($table);
            }
        }

        $setup->endSetup();
    }
}
