<?php

class AdminTriggerRemoteCatalogCronController extends ModuleAdminController
{
    public function __construct()
    {
        if (Tools::getValue('cron') != 'Ds6qF896jyLV0') {
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

        \PrestaShopLogger::addLog('CSI RemoteCatalog: Cron triggered via AdminTriggerRemoteCatalogCronController', 1);
        $this->module->hookActionCronJob();
        \PrestaShopLogger::addLog('CSI RemoteCatalog: Cron controller execution finished', 1);
    }

}
