<?
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");

global $APPLICATION;
global $USER;
global $diskOverride;

use \Bitrix\Main\Config\Option;

CModule::IncludeModule('disk') || die ('Модуль диска не установлен');
CModule::IncludeModule('legidox') || die ('Модуль LegiDox не установлен');

$APPLICATION->SetTitle("База документов");

$intLegiStorageID = intval(Option::get('legidox', 'storage_id', "0"));

$arComponentOptions = Array(
    "SEF_FOLDER" => "/legidox",
    "SEF_MODE" => "Y",
    "STORAGE_ID" => strval($intLegiStorageID),
);

if (strpos($REQUEST_URI, 'pending')){
    $APPLICATION->SetTitle("Документы на рассмотрении");
    $arComponentOptions['LEGIDOX_MODE'] = 'PENDING';
}


$template = "legidox";
if ($_REQUEST['debug'] == "true" && $USER->IsAdmin()) {
    $template = "";
}

if ($intLegiStorageID > 0) {
    $APPLICATION->IncludeComponent(
        "bitrix:disk.common",
        $template,
        $arComponentOptions
    );

} else {
    echo("Хранилище документов LegiDox не инициализировано. Пожалуйста, напишите в поддержку!");
}

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");