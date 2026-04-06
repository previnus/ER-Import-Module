<?php
if (!defined('_PS_VERSION_'))
    exit;

function upgrade_module_1_2_5($object)
{
    $sets = Configuration::get('CSI_REMOTECATALOG_CSISET');
    return Configuration::updateValue('CSI_REMOTECATALOG_CSISET', str_replace('csi-connect','csic', $sets));
}
