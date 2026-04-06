<?php

namespace CSI\RemoteCatalog\DataSource\EducatorsResource\Iterators;

use CSI\RemoteCatalog\Iterators\CSVFile;

class QuantityCSVFile extends CSVFile
{
    public function current()
    {
        $data = parent::current();
        return ['Part Id' => $data['Part Id'], 'quantity' => $data['Available']];
    }
}