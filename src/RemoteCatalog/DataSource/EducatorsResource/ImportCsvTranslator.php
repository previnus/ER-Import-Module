<?php

namespace CSI\RemoteCatalog\DataSource\EducatorsResource;

use CSI\RemoteCatalog\DataSource;
use CSI\RemoteCatalog\Interfaces\TranslatorInterface;

class ImportCsvTranslator implements TranslatorInterface
{
    public $source;
    public $csvMap = 'no|reference|no|no|no|category|no|ean13|no|no|weight|length|width|height|no|no|name|manufacturer|no|no|description|no|no|no|no|no|no|no|no|no|no|no|no|no|no|no|no|no|no|no|no|no|no|no|no|no|no|no|no|no|no|no|no|no|no|no|no|no|no|no|no|no|no|no|no|no|no|no|no|no|no|no|no|no|image|no|no|price_tex|wholesale_price|quantity';
    public $importMap = 'reference|category|ean13|weight|depth|width|height|name|manufacturer|description|image|price_tex|wholesale_price|quantity|features|delete_existing_images|active|description_short|additional_shipping_cost';
    public $multiValueSep = '|';

    public function __construct(DataSource $source)
    {
        $this->source = $source;
    }

    public function convert()
    {
        return $this->convertToPrestashopCSV();
    }

    public function convertToPrestashopCSV()
    {
        $path = _PS_ADMIN_DIR_ . '/import/';
        $file = 'erimport.csv';
        $fp = fopen($path . $file, 'w');
        \PrestaShopLogger::addLog(sprintf('CSI RemoteCatalog: Writing translated import CSV to %s%s', $path, $file), 1);

        $extraWeightAmount   = \Configuration::get('CSI_REMOTECATALOG_EXTRAWEIGHT');
        $extraFreightPercent = \Configuration::get('CSI_REMOTECATALOG_EXTRAPERCENT');
        $keys = explode('|', $this->csvMap);

        foreach ($this->source->getProducts() as $data) {
            $row = array_combine($keys, $data);
            unset($row['no']);

            $additionalShipping = 0.00;
            if ((float)$row['weight'] >= $extraWeightAmount && $extraFreightPercent) {
                $additionalShipping = $row['price_tex'] * ($extraFreightPercent / 100);
            }

            $row['name']                    = $row['manufacturer'] . ' ' . $this->cleanName($row['name']);
            $row['category']                = $this->mergeCategories($data);
            $row['features']                = $this->buildFeatures($data);
            $row['ean13']                   = $this->cleanEan13($row['ean13']);
            $row['delete_existing_images']  = 1;
            $row['active']                  = empty($row['price_tex']) ? 0 : 1;
            $row['short_desc']              = $this->buildShortDescription($row['description']);
            $row['description']             = $this->buildLongDescription($data);
            $row['additional_shipping_cost'] = $additionalShipping;
            fputcsv($fp, $row, ';');
        }

        fclose($fp);
        \PrestaShopLogger::addLog(sprintf('CSI RemoteCatalog: Finished writing translated import CSV (%s%s)', $path, $file), 1);
        return $file;
    }

    private function buildShortDescription($data)
    {
        if (mb_strlen($data) >= 800) {
            $truncated = mb_substr($data, 0, 800);
            $pos = mb_strrpos($truncated, '.');
            $data = ($pos !== false) ? mb_substr($truncated, 0, $pos + 1) : $truncated;
        }
        return $data;
    }

    private function buildLongDescription($data)
    {
        $description = $data['SuggestedLongDescription'];
        $bullets = array_filter([
            $data['BulletPoint1'],
            $data['BulletPoint2'],
            $data['BulletPoint3'],
            $data['BulletPoint4'],
            $data['BulletPoint5'],
            $data['BulletPoint6'],
        ]);
        if (count($bullets)) {
            $description .= '<br/><br/><ul>';
            foreach ($bullets as $bullet) {
                $description .= '<li>' . htmlspecialchars($bullet, ENT_QUOTES, 'UTF-8') . '</li>';
            }
            $description .= '</ul>';
        }
        return $description;
    }

    private function buildFeatures($data)
    {
        return implode($this->multiValueSep, array_merge(
            $this->buildGradeFeatures($data),
            $this->buildAgeFeatures($data)
        ));
    }

    private function buildGradeFeatures($data)
    {
        return ['Grade: ' . $data['Grade']];
    }

    private function buildAgeFeatures($data)
    {
        return ['Age: ' . $data['Age']];
    }

    private function mergeCategories($data)
    {
        // Anchor to the "Home" root so PrestaShop can resolve non-unique category names
        $category['home'] = 'Home';
        $category['main'] = ucwords(str_replace('/', ' ', $data['Web Category']));
        $category['sub']  = ucwords(str_replace('/', ' ', $data['Web Product Type']));
        return implode('/', $category) . $this->multiValueSep . implode('/', [$category['home'], $category['main']]);
    }

    private function cleanName($name)
    {
        if (!\Validate::isCatalogName($name)) {
            // PrestaShop rejects: # = { } ; < >
            $replaceMap = [
                '#' => 'NUMBER',
                '=' => 'EQUALS',
                '{' => '',
                '}' => '',
                ';' => '',
                '<' => '',
                '>' => '',
            ];
            return str_replace(array_keys($replaceMap), array_values($replaceMap), $name);
        }
        return $name;
    }

    private function cleanEan13($data)
    {
        $data = trim($data, "'");
        if (strlen($data) > 13) {
            return '';
        }
        return $data;
    }
}
