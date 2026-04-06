<?php
namespace CSI\RemoteCatalog\DataSource\EducatorsResource;

use CSI\RemoteCatalog\DataSource\EducatorsResource\Iterators\DiscontinuedCSVFile;
use CSI\RemoteCatalog\DataSource;

class Discontinued extends DataSource
{
    protected $products;

    public function getProduct($key)
    {
        // Not Used
        return null;
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
        $discontinued = new DiscontinuedCSVFile($csvfile, ',');
        $cachedDiscontinued = new \CachingIterator($discontinued, \CachingIterator::FULL_CACHE);
        foreach($cachedDiscontinued as $qty) {} //Iterating to fill the cache
        $this->products = $cachedDiscontinued->getCache();
    }

}