<?php

namespace CSI\RemoteCatalog\DataSource\EducatorsResource;

use CSI\RemoteCatalog\DataSource\EducatorsResource\Iterators\ERCSVFile;
use CSI\RemoteCatalog\DataSource;
use \Defuse\Crypto\Crypto;

class EducatorsResource extends DataSource
{
    protected $products;
    protected $dependents;

    public $sources = array(
        'primary' => [ 'local' => '/tmp/ermain.csv', 'remote' => 'eroptiona/Content/ER Content.csv'],
        'pricing' => [ 'local' => '/tmp/erpricing.csv', 'remote' => 'Daily Files/F100_D.txt'],
        'quantity' => [ 'local' => '/tmp/erquantity.csv', 'remote' => 'eroptiona/Daily Files/ERQTY.csv'],
        'discontinued' => ['local' => '/tmp/erdiscontinued.csv', 'remote' => 'eroptiona/Daily Files/ERDisc.csv'],
        'supersedes' => ['local' => '/tmp/ersupersedes.csv', 'remote' => 'eroptiona/Daily Files/Supersedes.csv']
    );

    public function __construct()
    {
        // ER Has changed the location of the pricing file at least once
        // And it seems it might be different for each person who uses
        // Adding ability to override the price file location.
        if (\Configuration::get('CSI_REMOTECATALOG_ERPRICEF')) {
            $this->sources['pricing']['remote'] = \Configuration::get('CSI_REMOTECATALOG_ERPRICEF');
        }
    }

    public function getProduct($key)
    {
        // TODO: Implement getProduct() method.
    }

    public function getProducts()
    {
        return $this->products;
    }

    public function build()
    {
        $this->dependents[] = new Pricing($this->sources['pricing']['local']);
        $this->dependents[] = new Quantity($this->sources['quantity']['local']);
        $this->load($this->sources['primary']['local']);
    }

    protected function load($csvfile)
    {
        $this->products = new ERCSVFile($csvfile, ',', $this->dependents);
    }

    public function fetchSources()
    {
        $host = Crypto::decryptWithPassword(\Configuration::get('CSI_REMOTECATALOG_ERFTP'), _COOKIE_KEY_);
        $user = Crypto::decryptWithPassword(\Configuration::get('CSI_REMOTECATALOG_ERFTPU'), _COOKIE_KEY_);
        $pass = Crypto::decryptWithPassword(\Configuration::get('CSI_REMOTECATALOG_ERFTPP'), _COOKIE_KEY_);

        \PrestaShopLogger::addLog('CSI RemoteCatalog: Starting FTP source download', 1);
        $ftp = ftp_connect($host);
        if ($ftp && ftp_login($ftp, $user,$pass)) {
            ftp_pasv($ftp, true);
            foreach ($this->sources as $key => $source) {
                \PrestaShopLogger::addLog(sprintf('CSI RemoteCatalog: Downloading source "%s" from %s', $key, $source['remote']), 1);
                $result = ftp_get($ftp, $source['local'], $source['remote'], FTP_BINARY);
                if ($result) {
                    $size = @filesize($source['local']);
                    \PrestaShopLogger::addLog(sprintf('CSI RemoteCatalog: Downloaded source "%s" to %s (%s bytes)', $key, $source['local'], $size !== false ? $size : 'unknown'), 1);
                } else {
                    \PrestaShopLogger::addLog(sprintf('CSI RemoteCatalog: Failed downloading source "%s" from %s', $key, $source['remote']), 2);
                }
            }
            \PrestaShopLogger::addLog('CSI RemoteCatalog: FTP source download complete', 1);
        } else {
            \PrestaShopLogger::addLog('CSI RemoteCatalog: FTP login failed', 3);
        }

        return $this;
    }
}
