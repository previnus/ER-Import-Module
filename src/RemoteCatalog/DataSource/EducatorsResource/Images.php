<?php
namespace CSI\RemoteCatalog\DataSource\EducatorsResource;

use CSI\RemoteCatalog\DataSource;
use CSI\RemoteCatalog\DataSource\EducatorsResource\Iterators\ImagesCSVFile;

class Images extends DataSource
{
    protected $products;

    public function getProduct($key)
    {
        $item = array_search($key, array_column($this->products, 'Part Id'));
        if (is_numeric($item)) {
            // $this->products is a n1 indexed array. Its 0 index has been removed (header row).
            // Using array_column to search on, creates a new temporary array that is n0 indexed.
            // So our item key searches is off by one.  Make sure to compensate.
            return $this->products[$item + 1];
        }
        return ['image' => null];
    }

    public function getProducts()
    {
        return $this->products;
    }

    public function __construct($file)
    {
        $this->loadExcel($file);
    }

    protected function load($csvfile)
    {
            $images = new ImagesCSVFile($csvfile, ';');
            $cachedImages = new \CachingIterator($images, \CachingIterator::FULL_CACHE);
            foreach($cachedImages as $image) {} //Iterating to fill the cache
            $this->products = $cachedImages->getCache();
    }

    protected function loadExcel($excelfile)
    {
        $reader_excel = \PHPExcel_IOFactory::createReaderForFile($excelfile);
        $reader_excel->setReadDataOnly(true);
        $excel_file = $reader_excel->load($excelfile);

        $csv_writer = \PHPExcel_IOFactory::createWriter($excel_file, 'CSV');

        $csv_writer->setSheetIndex(0);
        $csv_writer->setDelimiter(';');
        $csv_writer->save($excelfile . '.csv');

        $this->load($excelfile . '.csv');
    }
}