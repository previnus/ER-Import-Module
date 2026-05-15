<?php

namespace CSI\RemoteCatalog\DataSource\EducatorsResource\Iterators;

use CSI\RemoteCatalog\Iterators\CSVFile;

class ERCSVFile extends CSVFile
{
    protected $dependents = [];

    public function current()
    {
        $data = parent::current();
        // ER catalog CSVs are inconsistent: some use 'Part ID', others 'Part Id'
        $partId = $data['Part ID'] ?? $data['Part Id'] ?? null;
        foreach ($this->dependents as $source) {
            $data = array_merge($data, $source->getProduct($partId));
        }
        return $data;
    }

    public function __construct($file, $delimiter = null, array $dependents = [])
    {
        $this->dependents = $dependents;

        parent::__construct($file, $delimiter);
    }
}