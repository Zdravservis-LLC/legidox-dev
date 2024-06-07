<?php

CModule::IncludeModule('legidox');
\Bitrix\Main\UI\Extension::load('ui.entity-selector');
use Bitrix\Main\HttpApplication;
use LEGIDOX\CModuleOptions;

use Bitrix\Main\Localization\Loc;
Loc::loadMessages(__FILE__);

$request = HttpApplication::getInstance()->getContext()->getRequest();
$module_id = htmlspecialchars($request['mid'] != '' ? $request['mid'] : $request['id']);

$showRightsTab = true;

$arTabs = array(
    array(
        'DIV' => 'legidox-maintenance',
        'TAB' => Loc::getMessage('LEGIDOX_SETTINGS'),
        'ICON' => '',
        'TITLE' => Loc::getMessage('LEGIDOX_SETTINGS')
    )
);

$arGroups = array(
    'COMMON' => array('TITLE' => Loc::getMessage('LEGIDOX_SETTINGS_COMMON'), 'TAB' => 1)
);

$arOptions = array(
    'U_NORMACONTROL_ID' => array(
        'GROUP' => 'COMMON',
        'TITLE' => Loc::getMessage('LEGIDOX_U_NORMACONTROL'),
        'SIZE' => 6,
        'MAXLENGTH' => 6,
        'TYPE' => 'INT',
        'BUTTON_TEXT' => Loc::getMessage('LEGIDOX_U_NORMACONTROL_CHOOSE'),
        'SORT' => '',
        'NOTES' => Loc::getMessage('LEGIDOX_U_NORMACONTROL_HINT')
    )
);

$options = new CModuleOptions($module_id, $arTabs, $arGroups, $arOptions, $showRightsTab);
$options->ShowHTML();
echo($htmlCustomMonitor);
