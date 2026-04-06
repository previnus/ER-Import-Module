<?php

namespace CSI\RemoteCatalog\Interfaces;

use CSI\RemoteCatalog\DataSource;

interface TranslatorInterface
{
    public function __construct(DataSource $source);
    public function convert();
}