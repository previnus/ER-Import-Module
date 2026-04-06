<?php

use CSI\RemoteCatalog\DataSource\EducatorsResource\CronTasks;

class AdminTriggerCsiImportController extends ModuleAdminController
{
    public function __construct()
    {
        if (Tools::getValue('import') != 'true') {
            die('Invalid token');
        }

        parent::__construct();
        // If no employee setup one for import.
        if (!Validate::isLoadedObject($this->context->employee)) {
            $this->context->employee = new Employee(1);
        }
    }

    public function postProcess()
    {
        require_once(__DIR__ . '/../../vendor/autoload.php');
        ignore_user_abort(true);
        set_time_limit(0);

        \PrestaShopLogger::addLog('CSI RemoteCatalog: Manual import triggered via AdminTriggerCsiImportController', 1);
        $cron = new CronTasks();
        if ($cron->isActive()) $cron->runMonthly();
        \PrestaShopLogger::addLog('CSI RemoteCatalog: Manual import controller execution finished', 1);
    }

}
