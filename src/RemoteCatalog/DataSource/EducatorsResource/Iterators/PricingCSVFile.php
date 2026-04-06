<?php

namespace CSI\RemoteCatalog\DataSource\EducatorsResource\Iterators;

use CSI\RemoteCatalog\Iterators\CSVFile;

class PricingCSVFile extends CSVFile
{
    public function current()
    {
        $data = parent::current();
        return ['Part Id' => $data['Part'], 'price' => $data['Retail Price'], 'cost' => $data['Price']];
    }
}