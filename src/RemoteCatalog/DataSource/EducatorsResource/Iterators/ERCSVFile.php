<?php

namespace CSI\RemoteCatalog\DataSource\EducatorsResource\Iterators;

use CSI\RemoteCatalog\Iterators\CSVFile;

class ERCSVFile extends CSVFile
{
    protected $dependents = [];

    public function current()
    {
        $data = parent::current();
        foreach ($this->dependents as $source) {
            $data = array_merge($data, $source->getProduct($data['Part ID']));
        }
        return $data;
    }

    public function __construct($file, $delimiter = null, array $dependents = [])
    {
        $this->dependents = $dependents;

        parent::__construct($file, $delimiter);
    }
}