<?php

namespace CSI\RemoteCatalog\Traits;

trait GetProductIdByReference {
    // Wish there was something in the core for this
    // There isn't so this will have to do

    public static function getProductIdByReference($reference)
    {
        if (empty($reference)) {
            return 0;
        }

        $query = new \DbQuery();
        $query->select('p.id_product');
        $query->from('product', 'p');
        $query->where('p.reference = \''.pSQL($reference).'\'');

        return (int)\Db::getInstance(_PS_USE_SQL_SLAVE_)->getValue($query);
    }

    // HashMap reference => product_id
    // Use when need to lookup product id's in a large loop instead of bombarding the db with lookup queries
    // Orders of magnitude faster
    public static function getProductReferenceMap()
    {
        $query = new \DbQuery();
        $query->select('p.id_product, p.reference');
        $query->from('product', 'p');
        return array_column(\Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($query), 'id_product', 'reference');
    }
}