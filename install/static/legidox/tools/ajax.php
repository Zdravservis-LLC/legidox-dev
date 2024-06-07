<?php
global $APPLICATION;
global $USER;

define('PUBLIC_AJAX_MODE', true);
define('STOP_STATISTICS', true);
define('NO_AGENT_CHECK', true);

require_once($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php');

if (!CModule::IncludeModule('legidox')) {
    echo json_encode(["error" => "Модуль legidox не установлен"], JSON_UNESCAPED_UNICODE);
};

use LEGIDOX\CLegidoxCore;

$response = [
    "data" => [],
    "status" => 403
];

if (isset($_REQUEST['mode'])):

$strPageMode = 'PUBLISHED';
if (isset($_REQUEST['page_mode']) && $_REQUEST['page_mode'] == 'PENDING') {
    $strPageMode = $_REQUEST['page_mode'];
}

//region Document tree generation
if ($_REQUEST['mode'] == 'get_tree'):
    $priorityTag = (isset($_REQUEST['priority_tag']))?$_REQUEST['priority_tag']:"";
    $customSorting = $_REQUEST['custom_sorting'] == "true";

    // Check if we have comma-separated list instead of single tag
    $arCandidateSorter = explode(",", $priorityTag);
    $arMySort = [];

    if ($customSorting && count($arCandidateSorter) > 0) {
        $priorityTag = null;
        $arMySort = $arCandidateSorter;
    }
    $response["data"] = CLegidoxCore::generateTagTree($priorityTag, $arMySort, $strPageMode);
    $response["status"] = 200;
endif; // if ($_REQUEST['mode'] == 'get_tree'):
//endregion

//region Unique name checking
if ($_REQUEST['mode'] == 'check_unique' && isset($_REQUEST['doc_name']) && $_REQUEST['doc_name'] !== ""):
    $currentDocID = (intval($_REQUEST['doc_id']))?:null;
    $response["data"]["is_unique"] = CLegidoxCore::isUnique($_REQUEST['doc_name'], $currentDocID);
    $response["status"] = 200;
endif;

endif; // if (isset($_REQUEST['mode'])):

header('Content-Type: application/json; charset=utf-8');
echo json_encode($response, JSON_UNESCAPED_UNICODE);;
?>
