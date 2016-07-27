<?php

/**
 * Magento
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@magentocommerce.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade Magento to newer
 * versions in the future. If you wish to customize Magento for your
 * needs please refer to http://www.magentocommerce.com for more information.
 *
 * @category    BluePay
 * @package     BluePay_CreditCard
 * @copyright   Copyright (c) 2016 BluePay Processing, LLC (http://www.bluepay.com)
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
 
namespace BluePay\Payment\Model\Source;

use Magento\Customer\Controller\RegistryConstants;
use Magento\Framework\Registry;
use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory;

class StoredAccounts extends \Magento\Eav\Model\Entity\Attribute\Source\AbstractSource
{
    /**
     * Option values
     */
    const VALUE_YES = 1;

    const VALUE_NO = 0;

    const VALUE_BLANK = '';

    /**
     * @var \Magento\Eav\Model\ResourceModel\Entity\AttributeFactory
     */
    protected $_eavAttrEntity;

    protected $_coreRegistry = null;

    protected $_categoryCollection;

    protected $_customerRepository;

    protected $_customerRegistry;

    protected $_customer = null;

    /**
     * @param \Magento\Eav\Model\ResourceModel\Entity\AttributeFactory $eavAttrEntity
     * @codeCoverageIgnore
     */
    public function __construct(
        \Magento\Eav\Model\ResourceModel\Entity\AttributeFactory $eavAttrEntity,
        \Magento\Framework\Registry $registry,
        \Magento\Catalog\Model\ResourceModel\Category\CollectionFactory $categoryCollection,
         \Magento\Customer\Model\ResourceModel\CustomerRepository $customerRepository,
         \Magento\Customer\Model\CustomerRegistry $customerRegistry
    ) {
        $this->_eavAttrEntity = $eavAttrEntity;
        $this->_coreRegistry = $registry;
        $this->_categoryCollection = $categoryCollection;
        $this->_customerRepository = $customerRepository;
        $this->_customerRegistry = $customerRegistry;
    }

    /**
     * Retrieve all options array
     *
     * @return array
     */
    public function getAllOptions()
    {
        $attributeCode = $this->getAttribute()->getAttributeCode();
        $customerId = $this->_coreRegistry->registry(RegistryConstants::CURRENT_CUSTOMER_ID);
        if ($customerId && $this->_customer != '1' && $attributeCode == 'bluepay_stored_accts') {
            $this->_customer = '1';
            $customer = $this->_customerRegistry->retrieve($customerId);
            $customerData = $customer->getDataModel();
            $paymentAcctString = $customerData->getCustomAttribute($attributeCode) ? $customerData->getCustomAttribute($attributeCode)->getValue() : '';
            if (strpos($paymentAcctString, '|') !== FALSE) {
                $this->_options = [];
                $paymentAccts = explode('|',$paymentAcctString);
                foreach($paymentAccts as $paymentAcct) {
                    if (strlen($paymentAcct) < 2)
                        continue;
                    $paymentAccount = explode(',',$paymentAcct);
                    $val = ['label' => __($paymentAccount[0]), 'value' => $paymentAccount[1]];
                    array_push($this->_options,$val);
                }
                return $this->_options;
            }
        }
        if ($this->_options == null) {
            $this->_options = [
                ['label' => __('-No payment accounts found-'), 'value' => self::VALUE_BLANK]
            ];
        }
        return $this->_options;
    }

    /**
     * Retrieve option array
     *
     * @return array
     */
    public function getOptionArray()
    {
        $_options = [];
        foreach ($this->getAllOptions() as $option) {
            $_options[$option['value']] = $option['label'];
        }
        return $_options;
    }

    /**
     * Get a text for option value
     *
     * @param string|int $value
     * @return string|false
     */
    public function getOptionText($value)
    {
        $options = $this->getAllOptions();
        foreach ($options as $option) {
            if ($option['value'] == $value) {
                return $option['label'];
            }
        }
        return false;
    }

    /**
     * Retrieve flat column definition
     *
     * @return array
     */
    public function getFlatColumns()
    {
        $attributeCode = $this->getAttribute()->getAttributeCode();

        return [
            $attributeCode => [
                'unsigned' => false,
                'default' => null,
                'extra' => null,
                'type' => \Magento\Framework\DB\Ddl\Table::TYPE_SMALLINT,
                'length' => 1,
                'nullable' => true,
                'comment' => $attributeCode . ' column',
            ],
        ];
    }

    /**
     * Retrieve Indexes(s) for Flat
     *
     * @return array
     */
    public function getFlatIndexes()
    {
        $indexes = [];

        $index = 'IDX_' . strtoupper($this->getAttribute()->getAttributeCode());
        $indexes[$index] = ['type' => 'index', 'fields' => [$this->getAttribute()->getAttributeCode()]];

        return $indexes;
    }

    /**
     * Retrieve Select For Flat Attribute update
     *
     * @param int $store
     * @return \Magento\Framework\DB\Select|null
     */
    public function getFlatUpdateSelect($store)
    {
        return $this->_eavAttrEntity->create()->getFlatUpdateSelect($this->getAttribute(), $store);
    }

    /**
     * Get a text for index option value
     *
     * @param  string|int $value
     * @return string|bool
     */
    public function getIndexOptionText($value)
    {
        switch ($value) {
            case self::VALUE_YES:
                return 'Yes';
            case self::VALUE_NO:
                return 'No';
        }

        return parent::getIndexOptionText($value);
    }

    /**
     * Add Value Sort To Collection Select
     *
     * @param \Magento\Eav\Model\Entity\Collection\AbstractCollection $collection
     * @param string $dir
     *
     * @return \Magento\Eav\Model\Entity\Attribute\Source\Boolean
     */
    public function addValueSortToCollection($collection, $dir = \Magento\Framework\DB\Select::SQL_ASC)
    {
        $attributeCode = $this->getAttribute()->getAttributeCode();
        $attributeId = $this->getAttribute()->getId();
        $attributeTable = $this->getAttribute()->getBackend()->getTable();

        if ($this->getAttribute()->isScopeGlobal()) {
            $tableName = $attributeCode . '_t';
            $collection->getSelect()
                ->joinLeft(
                    [$tableName => $attributeTable],
                    "e.entity_id={$tableName}.entity_id"
                    . " AND {$tableName}.attribute_id='{$attributeId}'"
                    . " AND {$tableName}.store_id='0'",
                    []
                );
            $valueExpr = $tableName . '.value';
        } else {
            $valueTable1 = $attributeCode . '_t1';
            $valueTable2 = $attributeCode . '_t2';
            $collection->getSelect()
                ->joinLeft(
                    [$valueTable1 => $attributeTable],
                    "e.entity_id={$valueTable1}.entity_id"
                    . " AND {$valueTable1}.attribute_id='{$attributeId}'"
                    . " AND {$valueTable1}.store_id='0'",
                    []
                )
                ->joinLeft(
                    [$valueTable2 => $attributeTable],
                    "e.entity_id={$valueTable2}.entity_id"
                    . " AND {$valueTable2}.attribute_id='{$attributeId}'"
                    . " AND {$valueTable2}.store_id='{$collection->getStoreId()}'",
                    []
                );
            $valueExpr = $collection->getConnection()->getCheckSql(
                $valueTable2 . '.value_id > 0',
                $valueTable2 . '.value',
                $valueTable1 . '.value'
            );
        }

        $collection->getSelect()->order($valueExpr . ' ' . $dir);
        return $this;
    }
}
