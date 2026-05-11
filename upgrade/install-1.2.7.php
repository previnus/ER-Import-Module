<?php
if (!defined('_PS_VERSION_'))
    exit;

function upgrade_module_1_2_7($object)
{
    // Generate a secure cron token for existing installations that predate 1.2.7.
    // New installs get this token created in install(); upgrades get it here.
    if (!Configuration::get('CSI_REMOTECATALOG_CRON_TOKEN')) {
        return Configuration::updateValue('CSI_REMOTECATALOG_CRON_TOKEN', bin2hex(random_bytes(16)));
    }
    return true;
}
