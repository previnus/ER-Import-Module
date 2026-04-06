<?php

namespace CSI\RemoteCatalog\Iterators;

class KeyedArrayIterator extends \IteratorIterator
{
    private $keys;

    public function rewind()
    {
        parent::rewind();
        $this->keys = parent::current();
        parent::next();
    }

    public function current()
    {
        return array_combine($this->keys, parent::current());
    }

    public function getKeys()
    {
        return $this->keys;
    }
}