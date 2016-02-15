<?php

/**
 * Class Demac_MultiLocationInventory_Model_Stock_Status_Index
 */
class Demac_MultiLocationInventory_Model_Stock_Status_Index
    extends Mage_Core_Model_Abstract
{
    /**
     * Init
     */
    protected function _construct()
    {
        parent::_construct();
        $this->_init('demac_multilocationinventory/stock_status_index');
    }


    /**
     * Processes the actual reindex.
     *
     * @param bool $productIds If FALSE all products will be indexed
     */
    public function reindex($productIds = false)
    {
        if($productIds !== false && !is_array($productIds) && is_numeric($productIds)) {
            $productIds = array($productIds);
        }

        //Create Missing Rows
        $this->getResource()->createMissingStockRows($productIds);
        $this->getResource()->createMissingStockIndexRows($productIds);

        $write = Mage::getSingleton('core/resource')->getConnection('core_write');
        //Add associated products (parent products, child products, etc)
        if($productIds !== false) {
            $associatedProductIds = $this->getAssociatedProducts($productIds);
            $productIds           = array_merge($productIds, $associatedProductIds);
            //Update multi location inventory stock status index table for specified products
            $write->exec('CALL DEMAC_MLI_REINDEX_SET("'.implode(',', $productIds).'")');
            // MOD SMCD 27 Jan 16 - add a bundle index process if the skus belong to bundles or configs
           foreach ( $productIds as $pId ) {
               $t_parentIds = Mage::getModel('bundle/product_type')->getParentIdsByChild($pId);
               if ( sizeof( $t_parentIds )  > 0 ) {
                 $write->exec('CALL XPRAC_REINDEX_ALL_BUN()');
                 break; // only need to find one!
               }
            }
        } else {
            //Update multi location inventory stock status index table globally.
            $write->exec('CALL DEMAC_MLI_REINDEX_ALL()');
            // MOD SMCD 27 Jan 16 - add a bundle index process
            $write->exec('CALL XPRAC_REINDEX_ALL_BUN()');
        }

        //Update core stock status table.
        $this->getResource()->updateCoreStockStatus($productIds);

        //Update core stock item table
        $this->getResource()->updateCoreStockItem($productIds);
    }


    /**
     * Gets parent products.
     *
     * @param Array $productIds
     *
     * @return Array
     */
    public function getAssociatedProducts($productIds)
    {
        $parentProductIds =
            Mage::getModel('catalog/product_link')
                ->getCollection()
                ->addFieldToFilter(
                    'product_id',
                    array(
                        'in' => $productIds
                    )
                )
                ->getColumnValues('linked_product_id');

        return $parentProductIds;
    }
}
