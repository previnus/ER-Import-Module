<?php


namespace CSI\RemoteCatalog\DataSource\EducatorsResource\Iterators;

use CSI\RemoteCatalog\Iterators\CSVFile;

class DiscontinuedCSVFile extends CSVFile
{
    public function current()
    {
        $data = parent::current();
        return $data['Part ID'] ?? $data['Part Id'];
    }
}