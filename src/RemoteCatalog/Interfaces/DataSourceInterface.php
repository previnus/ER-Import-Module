<?php

namespace CSI\RemoteCatalog\Interfaces;

interface DataSourceInterface
{
    public function getProduct($key);
    public function getProducts();
}