<?php
if (!defined('_PS_VERSION_')) {
    exit;
}

use CSI\RemoteCatalog\DataSource\EducatorsResource\CronTasks as ERCronTasks;
use Defuse\Crypto\Crypto;

class Csi_RemoteCatalog extends Module
{
    public function __construct()
    {
        $this->name = 'csi_remotecatalog';
        $this->bootstrap = true;
        $this->tab = 'quick_bulk_update';
        $this->need_instance = 0;
        $this->ps_versions_compliancy = array('min' => '1.7', 'max' => _PS_VERSION_);
        $this->version = '1.2.6';
        $this->author = 'Symeon Quimby';

        parent::__construct();

        $this->displayName = $this->l('Remote Catalog Data');
        $this->description = $this->l('Keep shop up to date with remote data source. Currently supports Educators Resource and Essendant.');
    }

    public function install()
    {
        if (!parent::install()
            || !$this->registerHook('actionCronJob')
            || !$this->createTab('AdminTriggerCsiImport')
            || !$this->createTab('AdminTriggerRemoteCatalogCron')
        ) {
            return false;
        }
        if (!Configuration::get('CSI_REMOTECATALOG_CRON_TOKEN')) {
            Configuration::updateValue('CSI_REMOTECATALOG_CRON_TOKEN', bin2hex(random_bytes(16)));
        }
        return true;
    }

    public function uninstall()
    {
        $tabNames = ['AdminTriggerCsiImport', 'AdminTriggerRemoteCatalogCron'];
        foreach ($tabNames as $className) {
            $tabId = (int)Tab::getIdFromClassName($className);
            if ($tabId) {
                $tab = new Tab($tabId);
                $tab->delete();
            }
        }
        $configKeys = [
            'CSI_REMOTECATALOG_ENABLE', 'CSI_REMOTECATALOG_TRUNCATE',
            'CSI_REMOTECATALOG_ER_ACTIVE', 'CSI_REMOTECATALOG_ERFTP',
            'CSI_REMOTECATALOG_ERFTPU', 'CSI_REMOTECATALOG_ERFTPP',
            'CSI_REMOTECATALOG_ERPRICEF', 'CSI_REMOTECATALOG_ER_HIDE_STOCK',
            'CSI_REMOTECATALOG_EXTRAWEIGHT', 'CSI_REMOTECATALOG_EXTRAPERCENT',
            'CSI_REMOTECATALOG_CRON_TOKEN',
        ];
        foreach ($configKeys as $key) {
            Configuration::deleteByName($key);
        }
        return parent::uninstall();
    }

    public function createTab(string $class_name)
    {
        $tab = new Tab();
        $tab->active = 1;
        $tab->class_name = $class_name;
        $tab->name = array();
        foreach (Language::getLanguages(true) as $lang) {
            $tab->name[$lang['id_lang']] = $class_name;
        }
        // Do Not create Menu Item; parent_id = -1.
        $tab->id_parent = -1;

        $tab->module = $this->name;

        return $tab->add();
    }

    public function getContent()
    {
        $output = '';
        if (Tools::isSubmit('submit'.$this->name)) {
            // Process post
            $active = Tools::getValue('CSI_REMOTECATALOG_ENABLE');
            Configuration::updateValue('CSI_REMOTECATALOG_ENABLE', $active);
            $truncate = Tools::getValue('CSI_REMOTECATALOG_TRUNCATE');
            Configuration::updateValue('CSI_REMOTECATALOG_TRUNCATE', $truncate);

            Configuration::updateValue('CSI_REMOTECATALOG_ER_ACTIVE', Tools::getValue('CSI_REMOTECATALOG_ER_ACTIVE'));

            // Only overwrite encrypted credentials if a new non-empty value is submitted
            $erftp = Tools::getValue('CSI_REMOTECATALOG_ERFTP');
            if (!empty($erftp)) {
                Configuration::updateValue('CSI_REMOTECATALOG_ERFTP', Crypto::encryptWithPassword($erftp, _COOKIE_KEY_));
            }
            $erftpu = Tools::getValue('CSI_REMOTECATALOG_ERFTPU');
            if (!empty($erftpu)) {
                Configuration::updateValue('CSI_REMOTECATALOG_ERFTPU', Crypto::encryptWithPassword($erftpu, _COOKIE_KEY_));
            }
            $erftpp = Tools::getValue('CSI_REMOTECATALOG_ERFTPP');
            if (!empty($erftpp)) {
                Configuration::updateValue('CSI_REMOTECATALOG_ERFTPP', Crypto::encryptWithPassword($erftpp, _COOKIE_KEY_));
            }

            $erpricefile = Tools::getValue('CSI_REMOTECATALOG_ERPRICEF');
            Configuration::updateValue('CSI_REMOTECATALOG_ERPRICEF', $erpricefile);
            Configuration::updateValue('CSI_REMOTECATALOG_ER_HIDE_STOCK', Tools::getValue('CSI_REMOTECATALOG_ER_HIDE_STOCK'));

            $extraFreight = (float)Tools::getValue('CSI_REMOTECATALOG_EXTRAWEIGHT');
            Configuration::updateValue('CSI_REMOTECATALOG_EXTRAWEIGHT', $extraFreight);
            $extraPercent = (float)Tools::getValue('CSI_REMOTECATALOG_EXTRAPERCENT');
            Configuration::updateValue('CSI_REMOTECATALOG_EXTRAPERCENT', $extraPercent);

            $output .= $this->displayConfirmation($this->l('Settings updated'));
        }
        return $output.$this->displayForm();

    }

    public function displayForm()
    {
        // Get default language
        $default_lang = (int)Configuration::get('PS_LANG_DEFAULT');

        // Init Fields form array
        $fields_form[0]['form'] = array(
            'legend' => array(
                'title' => $this->l('Settings'),
            ),
            'description' => $this->l('Remote Catalog Data Settings'),
            'input' => array(
                array(
                    'type' => 'switch',
                    'label' => $this->l('Enabled'),
                    'name' => 'CSI_REMOTECATALOG_ENABLE',
                    'is_name' => true,
                    'values' => array(
                        array(
                            'id' => 'active_on',
                            'value' => 1,
                            'label' => $this->l('Yes')
                        ),
                        array(
                            'id' => 'active_off',
                            'value' => 0,
                            'label' => $this->l('No')
                        )
                    )
                ),
                array( //TODO: Maybe this configuration item needs moving to a datasource specific setting?
                    'type' => 'switch',
                    'label' => $this->l('Truncate Products on Import?'),
                    'name' => 'CSI_REMOTECATALOG_TRUNCATE',
                    'is_name' => true,
                    'values' => array(
                        array(
                            'id' => 'truncate_on',
                            'value' => 1,
                            'label' => $this->l('Yes')
                        ),
                        array(
                            'id' => 'truncate_off',
                            'value' => 0,
                            'label' => $this->l('No')
                        )
                    )
                ),
                array(
                    'type' => 'text',
                    'label' => $this->l('Extra Freight Weight'),
                    'hint' => $this->l('Weight value at which product will be considered "Extra Freight".'),
                    'desc' => $this->l('Weight value at which product will be considered "Extra Freight".'),
                    'suffix' => 'lbs',
                    'class' => 'col-sm-2',
                    'name' => 'CSI_REMOTECATALOG_EXTRAWEIGHT',
                    'is_name' => true,
                ),
                array(
                    'type' => 'text',
                    'label' => $this->l('Extra Freight Percent'),
                    'hint' => $this->l('Percentage of product price you would like to charge as additional shipping for products that exceed the Extra Freight Weight.'),
                    'desc' => $this->l('Percentage of product price you would like to charge as additional shipping for products that exceed the Extra Freight Weight.'),
                    'suffix' => '%',
                    'class' => 'col-sm-2',
                    'name' => 'CSI_REMOTECATALOG_EXTRAPERCENT',
                    'is_name' => true,
                ),
            ),
        );

        $description = $this->l('Educators Resource Settings');
        $description .= "<p><a href='{$this->context->link->getAdminLink('AdminTriggerCsiImport', true, [], ['import'=>'true'])}'>{$this->l('Manually Trigger Import')}</a></p>";
        $fields_form[1]['form'] = array(
            'legend' => array(
                'title' => $this->l('Educators Resource'),
            ),
            'description' => $description,
            'input' => array(
                array(
                    'type' => 'switch',
                    'label' => $this->l('Enabled'),
                    'name' => 'CSI_REMOTECATALOG_ER_ACTIVE',
                    'is_name' => true,
                    'values' => array(
                        array(
                            'id' => 'eractive_on',
                            'value' => 1,
                            'label' => $this->l('Yes')
                        ),
                        array(
                            'id' => 'eractive_off',
                            'value' => 0,
                            'label' => $this->l('No')
                        )
                    )
                ),
                array(
                    'type' => 'text',
                    'label' => $this->l('FTP Host'),
                    'name' => 'CSI_REMOTECATALOG_ERFTP',
                    'is_name' => true,
                ),
                array(
                    'type' => 'text',
                    'label' => $this->l('User'),
                    'name' => 'CSI_REMOTECATALOG_ERFTPU',
                    'is_name' => true,
                ),
                array(
                    'type' => 'text',
                    'label' => $this->l('Pass'),
                    'name' => 'CSI_REMOTECATALOG_ERFTPP',
                    'is_name' => true,
                ),
                array(
                    'type' => 'text',
                    'label' => $this->l('Path to Remote Pricing File'),
                    'hint' => $this->l('Default is "Daily Files/F100_D.txt".  Enter FTP Path to remote pricing file here if your pricing file is different.'),
                    'desc' => $this->l('Default is "Daily Files/F100_D.txt".  Enter FTP Path to remote pricing file here if your pricing file is different.'),
                    'name' => 'CSI_REMOTECATALOG_ERPRICEF',
                    'is_name' => true,
                ),
                array(
                    'type' => 'switch',
                    'label' => $this->l('Hide Out of Stock Items'),
                    'name' => 'CSI_REMOTECATALOG_ER_HIDE_STOCK',
                    'is_name' => true,
                    'values' => array(
                        array(
                            'id' => 'erhidestock_on',
                            'value' => 1,
                            'label' => $this->l('Yes')
                        ),
                        array(
                            'id' => 'erhidestock_off',
                            'value' => 0,
                            'label' => $this->l('No')
                        )
                    )
                ),
            ),
        );

        $fields_form[100]['form'] = array(
            'submit' => array(
                'title' => $this->l('Save'),
                'class' => 'button'
            )
        );

        $helper = new HelperForm();

        // Module, token and currentIndex
        $helper->module = $this;
        $helper->name_controller = $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->currentIndex = AdminController::$currentIndex.'&configure='.$this->name;

        // Language
        $helper->default_form_language = $default_lang;
        $helper->allow_employee_form_lang = $default_lang;

        // Title and toolbar
        $helper->title = $this->displayName;
        $helper->show_toolbar = true;        // false -> remove toolbar
        $helper->toolbar_scroll = true;      // yes - > Toolbar is always visible on the top of the screen.
        $helper->submit_action = 'submit'.$this->name;
        $helper->toolbar_btn = array(
            'save' =>
                array(
                    'desc' => $this->l('Save'),
                    'href' => AdminController::$currentIndex.'&configure='.$this->name.'&save'.$this->name.
                        '&token='.Tools::getAdminTokenLite('AdminModules'),
                ),
            'back' => array(
                'href' => AdminController::$currentIndex.'&token='.Tools::getAdminTokenLite('AdminModules'),
                'desc' => $this->l('Back to list')
            )
        );

        // Load current value
        $helper->fields_value['CSI_REMOTECATALOG_ENABLE'] = Configuration::get('CSI_REMOTECATALOG_ENABLE');
        $helper->fields_value['CSI_REMOTECATALOG_TRUNCATE'] = Configuration::get('CSI_REMOTECATALOG_TRUNCATE');
        $helper->fields_value['CSI_REMOTECATALOG_ER_ACTIVE'] = Configuration::get('CSI_REMOTECATALOG_ER_ACTIVE');
        $helper->fields_value['CSI_REMOTECATALOG_ERPRICEF'] = Configuration::get('CSI_REMOTECATALOG_ERPRICEF');
        $helper->fields_value['CSI_REMOTECATALOG_ER_HIDE_STOCK'] = Configuration::get('CSI_REMOTECATALOG_ER_HIDE_STOCK');
        $helper->fields_value['CSI_REMOTECATALOG_EXTRAWEIGHT'] = Configuration::get('CSI_REMOTECATALOG_EXTRAWEIGHT');
        $helper->fields_value['CSI_REMOTECATALOG_EXTRAPERCENT'] = Configuration::get('CSI_REMOTECATALOG_EXTRAPERCENT');

        if ($erftp = Configuration::get('CSI_REMOTECATALOG_ERFTP')) {
            $helper->fields_value['CSI_REMOTECATALOG_ERFTP'] = Crypto::decryptWithPassword($erftp, _COOKIE_KEY_);
        }
        if($erftpu = Configuration::get('CSI_REMOTECATALOG_ERFTPU')) {
            $helper->fields_value['CSI_REMOTECATALOG_ERFTPU'] = Crypto::decryptWithPassword($erftpu, _COOKIE_KEY_);
        }
        if ($erftpp = Configuration::get('CSI_REMOTECATALOG_ERFTPP')) {
            $helper->fields_value['CSI_REMOTECATALOG_ERFTPP'] = Crypto::decryptWithPassword($erftpp, _COOKIE_KEY_);
        }
  
        return $helper->generateForm($fields_form);

    }

    public function hookActionCronJob()
    {
        // TODO: Adapt for Multiple Data Sources.
        if(!Configuration::get('CSI_REMOTECATALOG_ENABLE')) {
            return;
        }
        \PrestaShopLogger::addLog('CSI RemoteCatalog: Cron job hook triggered', 1);
        require_once(__DIR__ . '/vendor/autoload.php');

        // Setup an employee context so the product import doesn't randomly fail
        $this->context->employee = new Employee(1);

        $cronTasks = [new ERCronTasks()];
        // If first day of month, 1AM; do monthly tasks
        if (date('jG') === "11") {
            \PrestaShopLogger::addLog('CSI RemoteCatalog: Starting monthly task window', 1);
            foreach ($cronTasks as $task) {
                if ($task->isActive()) $task->runMonthly();
            }
            Search::indexation(); // Rebuild Search Index
            \PrestaShopLogger::addLog('CSI RemoteCatalog: Monthly task window complete', 1);
        }

        // Sun 1am Do weekly
        if (date('DG') === "Sun1") {
            \PrestaShopLogger::addLog('CSI RemoteCatalog: Starting weekly task window', 1);
            foreach ($cronTasks as $task) {
                if ($task->isActive()) $task->runWeekly();
            }
            \PrestaShopLogger::addLog('CSI RemoteCatalog: Weekly task window complete', 1);
        }

        // 1AM, Do Daily Tasks
        if (date('G') === "1") {
            \PrestaShopLogger::addLog('CSI RemoteCatalog: Starting daily task window', 1);
            foreach ($cronTasks as $task) {
                if ($task->isActive()) $task->runDaily();
            }
            \PrestaShopLogger::addLog('CSI RemoteCatalog: Daily task window complete', 1);
        }

        // Do the hourly tasks
        \PrestaShopLogger::addLog('CSI RemoteCatalog: Starting hourly task window', 1);
        foreach ($cronTasks as $task) {
            if ($task->isActive()) $task->runHourly();
        }
        \PrestaShopLogger::addLog('CSI RemoteCatalog: Hourly task window complete', 1);
    }

    public function getCronFrequency()
    {
        // -1 is equivalent to cron's *
        return [
            'hour'=>-1, 'day'=>-1, 'month'=>-1, 'day_of_week'=>-1
        ];
    }
}
