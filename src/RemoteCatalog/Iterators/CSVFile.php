<?php

namespace CSI\RemoteCatalog\Iterators;

class CSVFile extends KeyedArrayIterator
{
    public function __construct($file, $delimiter = null)
    {
        parent::__construct(new \SplFileObject($file));
        $this->setFlags(\SplFileObject::READ_CSV | \SplFileObject::READ_AHEAD | \SplFileObject::SKIP_EMPTY | \SplFileObject::DROP_NEW_LINE);
        if ($delimiter) {
            $this->setCsvControl($delimiter, '"', '\\');
        }
    }
}