<?php
if (!defined('_PS_VERSION_'))
    exit;

function upgrade_module_1_2_5($object)
{
    // Previously migrated CSI_REMOTECATALOG_CSISET which no longer exists.
    // Nothing to do for this version.
    return true;
}
