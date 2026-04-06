<?php

namespace CSI\RemoteCatalog;

use CSI\RemoteCatalog\Interfaces\DataSourceInterface;

abstract class DataSource implements DataSourceInterface
{
    public $collection;

    abstract public function getProduct($key);
    abstract public function getProducts();

}