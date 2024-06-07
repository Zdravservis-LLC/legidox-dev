<?
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");

global $APPLICATION;
global $USER;
global $diskOverride;

use Bitrix\Main\Config\Option;
use Bitrix\Main\Page\Asset;
use Bitrix\Disk;
use Bitrix\Iblock\ElementTable;
use Bitrix\Main\Type\DateTime;
use LEGIDOX\CLegidoxCore;


CModule::IncludeModule('disk') || die ('Модуль диска не установлен');
CModule::IncludeModule('legidox') || die ('Модуль LegiDox не установлен');

$APPLICATION->SetTitle("Загрузка документа");

$strLegidoxCSSPath = '/legidox/css/legidox.css';

if (is_file($_SERVER["DOCUMENT_ROOT"] . $strLegidoxCSSPath)) {
    Asset::getInstance()->addCss($strLegidoxCSSPath);
}

$intLegiStorageID = intval(Option::get('legidox', 'storage_id', "0"));

if (!$intLegiStorageID || $intLegiStorageID == 0) {
    die("Хранилище документов LegiDox не инициализировано. Пожалуйста, напишите в поддержку!");
}

$arAlerts = array();

// Check if the form was submitted
if ($_SERVER["REQUEST_METHOD"] == "POST")
{
    $docName = $_POST["doc_name"];

    $isVerified = isset($_POST["doc_verified"]) && $_POST["doc_verified"] === "on";
    $isPublished = isset($_POST["doc_published"]) && $_POST["doc_published"] === "on";

    $dateDocExpireDate = DateTime::createFromPhp(new \DateTime($_POST["doc_expiredate"]));
    $dateApproveDeadline = DateTime::createFromPhp(new \DateTime($_POST["doc_approve_deadline"]));
    $arDocApprovers = $_POST["doc_approvers"];

    $addDepartmentHeads = isset($_POST["add_department_heads"]) && $_POST["add_department_heads"] === "on";

    //Check if it is unique
    if (!CLegidoxCore::isUnique($docName, $intUploadedFileID))
    {
        $arAlerts[] = [
            'STYLE' => 'danger',
            'TEXT' => 'Файл с названием ' . $docName . ' уже существует в базе'
        ];
    } else
    {
        $selectedTags = $_POST["tags"]; // Array of selected tag IDs

        $objUserBadge = CLegidoxCore::getUserBadge($USER->GetID());
        $strBBUserLink = "[URL=" . $objUserBadge["LINK"] . "]" . $objUserBadge["FULL_NAME"] . "[/URL]";

        $docOwner = null;
        $docWatchers = [];

        if (isset($_REQUEST['doc_owner']) && intval($_REQUEST['doc_owner']) > 0) {
            $docOwner = $_REQUEST['doc_owner'];
        }

        if (isset($_REQUEST['doc_watchers']) && is_array($_REQUEST['doc_watchers']) && count($_REQUEST['doc_watchers']) > 0) {
            $docWatchers = $_REQUEST['doc_watchers'];
        }

        $uploadedFileID = false;

        // File upload process
        $storageId = intval(Option::get('legidox', 'storage_id', "0"));
        if ($storageId > 0) {
            $storage = Disk\Storage::getById($storageId);
            if ($storage) {
                $fileArray = $_FILES["file_input"];

                // generate fakename
                $fileExt = pathinfo($fileArray['name'], PATHINFO_EXTENSION);
                if (!$fileExt) {
                    $fileExt = "unknown";
                }

                $fileArray['name'] = md5($fileArray['name'] . rand() . time()) . "." . $fileExt;

                // Create a new file in the Disk storage
                $arFile = CFile::MakeFileArray($fileArray["tmp_name"]);
                $file = $storage->uploadFile($arFile, array(
                    'NAME' => $fileArray["name"],
                    'CREATED_BY' => ($docOwner)?:$USER->GetID()
                ), array(), true);
                $uploadedFileID = $file->getId(); // Get the uploaded file's ID
            }
        }

        // Create a new entry in the 'LD_FILES' infoblock
        $arFields = array(
            "NAME" => $docName,
            "PROPERTY_VALUES" => array(
                "LD_FILE_ID" => $uploadedFileID, // ID of the uploaded file in Disk
                "LD_TAG_ID" => $selectedTags, // Array of selected tag IDs
                "LD_DOC_OWNER" => ($docOwner)?:$USER->GetID(),
                "LD_DOC_WATCHERS" => ($docWatchers)?:[]
            )
        );

        if ($addDepartmentHeads) {
            $arFields["PROPERTY_VALUES"]["LD_NOTIFY_HEADS"] = 1;
        } else {
            $arFields["PROPERTY_VALUES"]["LD_NOTIFY_HEADS"] = 0;
        }

        if ($isVerified) {
            $arFields["PROPERTY_VALUES"]["LD_DOC_VERIFIED"] = 1;
        } else {
            $arFields["PROPERTY_VALUES"]["LD_DOC_VERIFIED"] = 0;
        }

        if ($isPublished) {
            $arFields["PROPERTY_VALUES"]["LD_DOC_PUBLISHED"] = 1;
        } else {
            $arFields["PROPERTY_VALUES"]["LD_DOC_PUBLISHED"] = 0;
        }

        if ($dateApproveDeadline) {
            $arFields["PROPERTY_VALUES"]["LD_DOC_APPROVE_DEADLINE"] = $dateApproveDeadline;
        }

        if ($dateDocExpireDate) {
            $arFields["PROPERTY_VALUES"]["LD_DOC_EXPIREDATE"] = $dateDocExpireDate;
        }

        if (is_array($arDocApprovers) && count($arDocApprovers) > 0) {
            $arFields["PROPERTY_VALUES"]["LD_DOC_APPROVERS"] = $arDocApprovers;
        } else {
            $arFields["PROPERTY_VALUES"]["LD_DOC_APPROVERS"] = [];
        }

        $iblockID = false;

        $iblockCode = 'LD_FILES';

        $iblockFilter = array(
            'CODE' => $iblockCode
        );

        $iblockList = CIBlock::GetList(array(), $iblockFilter);
        if ($iblock = $iblockList->Fetch()) {
            $iblockID = $iblock['ID'];
        }

        if ($iblockID) {
            $iblockElement = new \CIBlockElement;
            $arFields['IBLOCK_ID'] = $iblockID;
            $docEntryID = $iblockElement->Add($arFields, false, false, false);

            if ($docEntryID) {
                $arAlerts[] = [
                    'STYLE' => 'success',
                    'TEXT' => 'Документ успешно загружен!'
                ];
                $docTags = "";

                $docEntry = CLegidoxCore::getDocumentParamsByID($uploadedFileID);

                foreach ($docEntry["DOC_TAGS"] as $docTag) {
                    $docTags .= $docTag["NAME"] . "[BR]";
                }

                $strBBnotifn = $strBBUserLink . ": [BR]Загружен новый документ: [BR][URL=/legidox/file/"
                    . $docEntry["FILENAME"] . "]"
                    . $docEntry["DOCUMENT_NAME"]
                    . "[/URL][BR][BR]Теги:[BR] "
                    . $docTags . "[BR]";

                CLegidoxCore::sendNotification(
                    intval($docEntry["FILE_ID"]),
                    $strBBnotifn,
                    $docEntry,
                    true,
                    true
                );
            } else {
                $arAlerts[] = [
                    'STYLE' => 'danger',
                    'TEXT' => 'Ошибка загрузки документа: ' . $iblockElement->LAST_ERROR
                ];
            }
        }
    }
}

$arTagNodes = CLegidoxCore::getTagNodes();

// Build a form
?>

<div id="form-wrapper" class="bootstrap-iso">
    <? foreach ($arAlerts as $arAlert):?>
    <div class="alert alert-<?=$arAlert['STYLE']?> alert-dismissible fade show" role="alert">
        <?=$arAlert['TEXT']?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Скрыть"></button>
    </div>
    <? endforeach;?>
    <div class="container pt-3">
        <div class="row d-flex flex-column align-items-left">
            <div class="col-12 col-md-8 ld-upload-form">
                <form method="post" enctype="multipart/form-data">
                    <h5>Новый документ</h5>
                    <div class="form-group pb-3">
                        <label for="file_input" class="form-label">Файл документа:<span style='color: red;'> *</span></label>
                        <input
                            class="form-control"
                            type="file"
                            id="file_input"
                            name="file_input"
                            required
                        >
                    </div>
                    <div class="card pb-1 mb-3">
                        <div class="card-body">
                            <h5 class="card-title">Теги</h5>
                            <p class="card-subtitle mb-2 text-muted">Пожалуйста, выберите теги, соответствующие данному документу:</p>
                            <? foreach ($arTagNodes as $arParam): ?>
                            <div class="form-group pb-3">
                                <label for="tags_<?=$arParam['CODE']?>" class="form-label"><?=$arParam['NAME']?><?=($arParam["REQUIRED"] == "Y")?"<span style='color: red;'> *</span>":""?></label>
                                <select name="tags['<?=$arParam['CODE']?>']" id="tags_<?=$arParam['CODE']?>" class="form-select form-select-sm" <?=($arParam["REQUIRED"] == "Y")?"required":""?> >
                                    <option selected disabled value="">Выберите <?=$arParam['NAME']?>...</option>
                                    <? foreach ($arParam['ITEMS'] as $id => $name): ?>
                                    <option value="<?=$id?>"><?=$name?></option>
                                    <? endforeach;?>
                                </select>
                            </div>
                            <? endforeach; ?>
                            <!-- /iter -->
                        </div>
                    </div>
                    <div class="form-group pb-3">
                        <label for="descriptive-name">Описание документа:<span style='color: red;'> *</span></label>
                        <input
                            type="text"
                            name="descriptive-name"
                            id="descriptive-name"
                            class="form-control"
                            placeholder="Введите описание документа"
                            required
                        >
                    </div>
                    <div class="form-group pb-3">
                        <label for="doc_name">Предпросмотр:</label>
                        <input
                            type="text"
                            name="doc_name"
                            id="doc_name"
                            class="form-control"
                            placeholder="Выберите теги и введите описание документа в вышестоящих полях"
                            required
                            readonly
                        >
                    </div>

                    <div class="card pb-1 mb-3">
                        <div class="card-body">
                            <h5 class="card-title">Пользователи</h5>
                            <div class="form-group pb-3">
                                <label for="doc_owner" class="form-label">Владелец документа: <span style='color: red;'> *</span></label>
                                <?
                                $APPLICATION->IncludeComponent(
                                    'bitrix:main.user.selector',
                                    '',
                                    [
                                        "ID" => "doc_owner",
                                        "API_VERSION" => 3,
                                        "LIST" => [$docEntry["DOC_OWNER"]],
                                        "LAZYLOAD" => "Y",
                                        "INPUT_NAME" => "doc_owner",
                                        "USE_SYMBOLIC_ID" => false,
                                        "BUTTON_SELECT_CAPTION" => "Выберите сотрудника (начните печатать для поиска)",
                                        "SELECTOR_OPTIONS" =>
                                            [
                                                'enableUsers' => 'Y',
                                                'lazyLoad' => 'Y',
                                                'enableAll' => 'N',
                                                'enableEmpty' => 'N',
                                                'enableUserManager' => 'Y',
                                                'userSearchArea' => 'I',
                                                'departmentSelectDisable' => 'Y',
                                                'allowUserSearch' => 'Y',
                                                'allowSearchEmailUsers' => 'N',
                                                'allowEmailInvitation' => 'N',
                                                'enableSonetgroups' => 'N',
                                            ]
                                    ]
                                );
                                ?>
                            </div>
                            <script type="text/javascript">
                                BX.ready(function () {
                                    const docOwnerInput = document.querySelector('#doc_owner');
                                    const docTypeSelect = document.getElementById('tags_LD_TAG_DOCTYPE');
                                    const companySelect = document.getElementById('tags_LD_TAG_COMPANY');
                                    const docNameInput = document.getElementById('doc_name');
                                    const descriptiveNameField = document.getElementById('descriptive-name');

                                    // Hidden dummy-field of rich B24 user selector, must be required=true for
                                    // form validation to actually work

                                    if (docOwnerInput) {
                                        docOwnerInput.setAttribute('required', 'true');
                                        docOwnerInput.setAttribute('type', 'text');
                                        docOwnerInput.classList.add('visually-hidden');
                                    }

                                    // Function to update the document name field
                                    function updateDocName() {
                                        const selectedOptions = [];

                                        // Document type prefix
                                        if (docTypeSelect && docTypeSelect.selectedIndex !== 0) {
                                            selectedOptions.push(docTypeSelect.options[docTypeSelect.selectedIndex].text);
                                        }

                                        // Descriptive name content
                                        if (descriptiveNameField.value !== "") {
                                            selectedOptions.push(descriptiveNameField.value);
                                        }

                                        // Company name postfix
                                        if (companySelect && companySelect.selectedIndex !== 0) {
                                            selectedOptions.push(companySelect.options[companySelect.selectedIndex].text);
                                        }
                                        const docName = selectedOptions.join('_');

                                        // Update the doc_name input field with the assembled document name
                                        docNameInput.value = docName;

                                        // Constantly update this field, that's important
                                        setTimeout(updateDocName, 200);
                                    }

                                    updateDocName();

                                    // Prevent form to submit on enter
                                    $("form").keypress(function(e){
                                        if(e.keyCode == 13) {
                                            e.preventDefault();
                                            return false;
                                        }
                                    })
                                });
                            </script>
                            <div class="form-group pb-3">
                                <label for="doc_watchers" class="form-label">Информируемые лица:</label>
                                <?
                                $APPLICATION->IncludeComponent(
                                    'bitrix:main.user.selector',
                                    '',
                                    [
                                        "ID" => "doc_watchers",
                                        "LAZYLOAD" => "Y",
                                        "API_VERSION" => 3,
                                        "LIST" => [],
                                        "INPUT_NAME" => "doc_watchers[]",
                                        "USE_SYMBOLIC_ID" => true,
                                        "BUTTON_SELECT_CAPTION" => "Выберите сотрудников, группы или отделы (начните печатать для поиска)",
                                        "SELECTOR_OPTIONS" =>
                                            [
                                                'enableUsers' => 'Y',
                                                'lazyLoad' => 'Y',
                                                'enableAll' => 'N',
                                                'enableEmpty' => 'N',
                                                'enableUserManager' => 'Y',
                                                'userSearchArea' => 'I',
                                                'departmentSelectDisable' => 'N',
                                                'allowUserSearch' => 'Y',
                                                'allowSearchEmailUsers' => 'N',
                                                'allowEmailInvitation' => 'N',
                                                'enableSonetgroups' => 'Y',
                                            ]
                                    ]
                                );
                                ?>
                            </div>
                            <div class="form-group pb-3">
                                <div class="form-check">
                                    <input
                                        class="form-check-input"
                                        type="checkbox"
                                        id="add_department_heads"
                                        name="add_department_heads"
                                        <?=($bNotifyHeads == 0)?:"checked"?>
                                    >
                                    <label class="form-check-label" for="add_department_heads">
                                        Добавить руководителей подразделений
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
<!-- Document approval workflow -->
                    <div class="card pb-1 mb-3">
                        <div class="card-body">
                            <h5 class="card-title">Документооборот</h5>
                            <div class="form-group pb-3">
                                <label for="doc_verification_priority" class="form-label">Приоритет верификации документа:<span style='color: red;'> *</span></label>
                                <select id="doc_verification_priority" name="doc_verification_priority" class="form-control">
                                    <option value="high">Высокий приоритет</option>
                                    <option value="medium">Средний приоритет</option>
                                    <option value="low" selected>Низкий приоритет</option>
                                </select>
                                <input type="hidden" class="form-control" id="doc_approve_deadline" name="doc_approve_deadline">
                            </div>
                            <div class="form-check form-switch pb-3">
                                <input class="form-check-input" type="checkbox" id="doc-no-expire">
                                <label class="form-check-label" for="doc-no-expire">Бессрочный документ</label>
                                <script type="text/javascript">
                                    document.addEventListener('DOMContentLoaded', function() {
                                        var chkNoExpire = document.querySelector('#doc-no-expire');
                                        var lblExpiration = document.querySelector('#expiredate-label');
                                        var inpExpireDate = document.querySelector('#doc_expiredate');
                                        chkNoExpire.addEventListener('change', function(evt) {
                                            if (evt.target.checked) {
                                                lblExpiration.innerHTML = "Дата напоминания об актуализации данных: ";
                                                inpExpireDate.removeAttribute("required");
                                            } else {
                                                lblExpiration.innerHTML = "Дата истечения срока действия документа:<span style='color: red;'> *</span>";
                                                inpExpireDate.setAttribute("required");
                                            }
                                        });
                                    });
                                </script>
                            </div>
                            <div class="form-group pb-3">
                                <label for="doc_expiredate" class="form-label" id="expiredate-label">Дата истечения срока действия документа:<span style='color: red;'> *</span></label>
                                <input type="date" class="form-control" id="doc_expiredate" name="doc_expiredate" value="<?=$dateDocExpireDate?>" required>
                            </div>
                            <div class="form-group pb-3">
                                <label for="doc_watchers" class="form-label">Лица, принимающие участие в разработке документа:</label>
                                <?
                                $APPLICATION->IncludeComponent(
                                    'bitrix:main.user.selector',
                                    '',
                                    [
                                        "ID" => "doc_approvers",
                                        "LAZYLOAD" => "Y",
                                        "API_VERSION" => 3,
                                        "LIST" => $arDocApprovers,
                                        "INPUT_NAME" => "doc_approvers[]",
                                        "USE_SYMBOLIC_ID" => true,
                                        "BUTTON_SELECT_CAPTION" => "Выберите сотрудников, группы или отделы (начните печатать для поиска)",
                                        "SELECTOR_OPTIONS" =>
                                            [
                                                'enableUsers' => 'Y',
                                                'lazyLoad' => 'Y',
                                                'enableAll' => 'N',
                                                'enableEmpty' => 'N',
                                                'enableUserManager' => 'Y',
                                                'userSearchArea' => 'I',
                                                'departmentSelectDisable' => 'N',
                                                'allowUserSearch' => 'Y',
                                                'allowSearchEmailUsers' => 'N',
                                                'allowEmailInvitation' => 'N',
                                                'enableSonetgroups' => 'Y',
                                            ]
                                    ]
                                );
                                ?>
                            </div>
                        </div>
                        <script>
                            document.addEventListener('DOMContentLoaded', function() {
                                const prioritySelect = document.getElementById('doc_verification_priority');
                                const approvalDeadline = document.getElementById('doc_approve_deadline');
                                const expireDate = document.getElementById('doc_expiredate');

                                function updateApprovalDeadline() {
                                    const today = new Date();
                                    let newDate;

                                    switch (prioritySelect.value) {
                                        case 'high':
                                            newDate = new Date(today.setDate(today.getDate() + 2));
                                            break;
                                        case 'medium':
                                            newDate = new Date(today.setDate(today.getDate() + 5));
                                            break;
                                        case 'low':
                                            newDate = new Date(today.setDate(today.getDate() + 8));
                                            break;
                                        default:
                                            newDate = new Date(today.setDate(today.getDate() + 8));
                                    }

                                    approvalDeadline.value = newDate.toISOString().split('T')[0]; // Format YYYY-MM-DD
                                }

                                function setExpireDate() {
                                    const today = new Date();
                                    const nextYear = new Date(today.setFullYear(today.getFullYear() + 1));
                                    expireDate.value = nextYear.toISOString().split('T')[0]; // Format YYYY-MM-DD
                                }

                                // Initial update on page load
                                updateApprovalDeadline();
                                setExpireDate();

                                // Update on priority change
                                prioritySelect.addEventListener('change', updateApprovalDeadline);
                            });
                        </script>
                    </div>
<!-- Document approval workflow END -->
                    <div class="form-group py-3">
                        <button type="submit" class="btn btn-success w-100">Сохранить документ</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>


<?php


?>
