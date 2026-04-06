<?php

namespace CSI\RemoteCatalog\DataSource\EducatorsResource;

use CSI\RemoteCatalog\Interfaces\CronTasksInterface;
use CSI\RemoteCatalog\Traits\GetProductIdByReference;

class CronTasks implements CronTasksInterface
{
    use GetProductIdByReference;
    private $_source;

    public function __construct()
    {
        $this->_source = new EducatorsResource();
        if ($this->isActive()) {
            \PrestaShopLogger::addLog('CSI RemoteCatalog: CronTasks initialized and datasource active', 1);
            $this->_source->fetchSources();
        } else {
            \PrestaShopLogger::addLog('CSI RemoteCatalog: CronTasks initialized but datasource inactive', 2);
        }
    }

    public function runDaily()
    {
        \PrestaShopLogger::addLog('CSI RemoteCatalog: Starting daily datasource tasks', 1);
        $this->updatePricing();
        $this->disableDiscontinued();
        \PrestaShopLogger::addLog('CSI RemoteCatalog: Daily datasource tasks complete', 1);
    }

    public function runMonthly()
    {
        \PrestaShopLogger::addLog('CSI RemoteCatalog: Starting monthly datasource tasks', 1);
        $this->updateFromRemote();
        \PrestaShopLogger::addLog('CSI RemoteCatalog: Monthly datasource tasks complete', 1);
    }

    public function runWeekly()
    {
    }

    public function runHourly()
    {
        \PrestaShopLogger::addLog('CSI RemoteCatalog: Starting hourly datasource tasks', 1);
        $this->updateQuantity();
        \PrestaShopLogger::addLog('CSI RemoteCatalog: Hourly datasource tasks complete', 1);
    }

    public function isActive()
    {
        return \Configuration::get('CSI_REMOTECATALOG_ER_ACTIVE');
    }

    public function updateFromRemote()
    {
        ini_set('max_execution_time', 0);

        \PrestaShopLogger::addLog('CSI RemoteCatalog: Starting build stage', 1);
        $this->_source->build();
        \PrestaShopLogger::addLog('CSI RemoteCatalog: Build stage complete', 1);

        $importTranslator = new ImportCsvTranslator($this->_source);
        \PrestaShopLogger::addLog('CSI RemoteCatalog: Starting CSV translation stage', 1);
        $importCsvFile = $importTranslator->convert();
        \PrestaShopLogger::addLog(sprintf('CSI RemoteCatalog: CSV translation complete (%s)', $importCsvFile), 1);

        // The import controller expects certain request parameters to be set.
        $importParams = [
            'import' => 'import',
            'skip' => 0,
            'csv' =>  $importCsvFile,
            'entity' => 1,
            'iso_lang' => 'en',
            'match_ref' => 1,
            'separator' => ';',
            'multiple_value_separator' => $importTranslator->multiValueSep,
            'type_value' => explode('|', $importTranslator->importMap),
            'truncate' => \Configuration::get('CSI_REMOTECATALOG_TRUNCATE'),
        ];
        $_POST = array_merge($_POST, $importParams);
        unset($importTranslator); // Free up all the memory from building the import file so the importer can use it!
        // Trigger import process.
        \PrestaShopLogger::addLog('CSI RemoteCatalog: Handing over to AdminImportController', 1);
        $importController = new \AdminImportController();
        $importController->postProcess();
        \PrestaShopLogger::addLog('CSI RemoteCatalog: Import flow finished', 1);

    }

    public function updatePricing()
    {
        \PrestaShopLogger::addLog('CSI RemoteCatalog: Starting pricing update', 1);
        $map = self::getProductReferenceMap();
        $erPricing = new Pricing($this->_source->sources['pricing']['local']);
        foreach($erPricing->getProducts() as $pricing) {
            $product_id = $map[$pricing['Part Id']];
            if ($product_id) {
                $product = new \Product($product_id);
                $product->price = $pricing['price'];
                $product->wholesale_price = $pricing['cost'];
                $product->save();
            }
        }
        \PrestaShopLogger::addLog('CSI RemoteCatalog: Pricing update complete', 1);
    }

    public function updateQuantity()
    {
        \PrestaShopLogger::addLog('CSI RemoteCatalog: Starting quantity update', 1);
        $map = self::getProductReferenceMap();
        $erQuantity = new Quantity($this->_source->sources['quantity']['local']);
        foreach($erQuantity->getProducts() as $quantity) {
            $product_id = $map[$quantity['Part Id']] ?? null;
            if ($product_id) {
                \StockAvailable::setQuantity($product_id, 0, (int)$quantity['quantity'], null, false);
                if (\Configuration::get('CSI_REMOTECATALOG_ER_HIDE_STOCK')) {
                    $visibility = ((int)$quantity['quantity']) ? 'both' : 'none' ;
                    $product = new \Product($product_id);
                    if ($product->visibility !== $visibility) {
                        $product->setFieldsToUpdate(['visibility' => true]);
                        $product->visibility = $visibility;
                        $product->update();
                    }
                }
            }
        }
        \PrestaShopLogger::addLog('CSI RemoteCatalog: Quantity update complete', 1);
    }

    public function disableDiscontinued()
    {
        \PrestaShopLogger::addLog('CSI RemoteCatalog: Starting discontinued/superseded disable pass', 1);
        $map = self::getProductReferenceMap();
        $discontinued = new Discontinued($this->_source->sources['discontinued']['local']);
        $supersedes = new Discontinued($this->_source->sources['supersedes']['local']);
        $list = array_merge($discontinued->getProducts(), $supersedes->getProducts());
        foreach($list as $reference) {
            $product_id = (array_key_exists($reference, $map) ? $map[$reference] : null);
            if ($product_id) {
                $product = new \Product($product_id);
                if (\Validate::isLoadedObject($product) && $product->active !== "0") {
                    $product->active = 0;
                    $product->save();
                }
            }
        }
        \PrestaShopLogger::addLog('CSI RemoteCatalog: Discontinued/superseded disable pass complete', 1);
    }
}
