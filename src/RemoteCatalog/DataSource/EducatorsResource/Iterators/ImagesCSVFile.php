<?php

namespace CSI\RemoteCatalog\DataSource\EducatorsResource\Iterators;

use CSI\RemoteCatalog\Iterators\CSVFile;

class ImagesCSVFile extends CSVFile
{
    public function current()
    {
        $data = parent::current();
        return ['Part Id' => $data['Part_Id'], 'image' => $data['Cloud_L']];
    }
}