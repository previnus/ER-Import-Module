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
        $values = parent::current();
        if (!is_array($values) || count($this->keys) !== count($values)) {
            return false;
        }
        return array_combine($this->keys, $values);
    }

    public function getKeys()
    {
        return $this->keys;
    }
}