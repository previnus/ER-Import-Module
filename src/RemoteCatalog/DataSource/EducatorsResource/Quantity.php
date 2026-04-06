<?php
namespace CSI\RemoteCatalog\DataSource\EducatorsResource;

use CSI\RemoteCatalog\DataSource\EducatorsResource\Iterators\QuantityCSVFile;
use CSI\RemoteCatalog\DataSource;

class Quantity extends DataSource
{
    protected $products;

    public function getProduct($key)
    {
        $item = array_search($key, array_column($this->products, 'Part Id'));
        if (is_numeric($item)) {
            // $this->products is a n1 indexed array. Its 0 index has been removed (header row).
            // Using array_column to search on, creates a new temporary array that is n0 indexed.
            // So our item key searches is off by one.  Make sure to compensate.
            return $this->products[$item + 1];
        }
        return ['quantity' => null];
    }

    public function getProducts()
    {
        return $this->products;
    }

    public function __construct($file)
    {
        $this->load($file);
    }

    protected function load($csvfile)
    {
        $quantities = new QuantityCSVFile($csvfile, ',');
        $cachedQuantities = new \CachingIterator($quantities, \CachingIterator::FULL_CACHE);
        foreach($cachedQuantities as $qty) {} //Iterating to fill the cache
        $this->products = $cachedQuantities->getCache();
    }

}