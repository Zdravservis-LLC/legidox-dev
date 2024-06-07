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

$APPLICATION->SetTitle("Редактирование тегов");

$strLegidoxCSSPath = '/legidox/css/legidox.css';

if (is_file($_SERVER["DOCUMENT_ROOT"] . $strLegidoxCSSPath)) {
    Asset::getInstance()->addCss($strLegidoxCSSPath);
}

$intLegiStorageID = intval(Option::get('legidox', 'storage_id', "0"));

if (!$intLegiStorageID || $intLegiStorageID == 0) {
    die("Хранилище документов LegiDox не инициализировано. Пожалуйста, напишите в поддержку!");
}

$arAlerts = array();

$arTagNodes = CLegidoxCore::getTagNodes();

$normacontrollerID = COption::GetOptionInt('legidox', 'U_NORMACONTROL_ID', '0');

$docEntry = [];

$userID = intval($USER->GetID());

if (isset($_REQUEST["file_id"]) && intval($_REQUEST["file_id"]) > 0) {
    $docEntry = CLegidoxCore::getDocumentParamsByID(intval($_REQUEST["file_id"]));
}

$canEdit = false;
$canDelete = false;

if (
    $userID == intval($docEntry["DOC_OWNER"])
    || $userID == intval($docEntry["DOC_AUTHOR"])
    || $USER->IsAdmin()
    || $userID == $normacontrollerID
) {
    $canEdit = true;
    $canDelete = true;
}

$arDocWatchers = [];
if (isset($docEntry["DOC_WATCHERS"]) && count($docEntry["DOC_WATCHERS"]) > 0) {
    $arDocWatchers = $docEntry["DOC_WATCHERS"];
}

// Cleaning the watchers array
$arDocWatchers = array_filter($arDocWatchers);

$arDocApprovers = [];
if (isset($docEntry["DOC_APPROVERS"]) && count($docEntry["DOC_APPROVERS"]) > 0) {
    $arDocApprovers = $docEntry["DOC_APPROVERS"];
}

// Cleaning the Approvers array
$arDocApprovers = array_filter($arDocApprovers);

$bNotifyHeads = ($docEntry["DOC_NOTIFY_HEADS"])?:0;

// Converting legacy watchers format into new one
foreach ($arDocWatchers as $id => $watcherID) {
    $filter = preg_replace('/[^0-9]/', '', $watcherID);
    if ($watcherID == $filter) {
        $arDocWatchers[$id] = "U" . $watcherID;
    }
}

// Getting additional document workflow data
$dateDocExpireDate = ($docEntry["DOC_EXPIREDATE"])
    ? date("Y-m-d", strtotime($docEntry["DOC_EXPIREDATE"]))
    : "";
$dateApproveDeadline = ($docEntry["DOC_APPROVE_DEADLINE"])
    ? date("Y-m-d", strtotime($docEntry["DOC_APPROVE_DEADLINE"]))
    : "";
$isDocVerified = (isset($docEntry["DOC_VERIFIED"]) && intval($docEntry["DOC_VERIFIED"]) > 0)?"checked":"";
$isDocPublished = (isset($docEntry["DOC_PUBLISHED"]) && intval($docEntry["DOC_PUBLISHED"]) > 0)?"checked":"";

ob_start();

if (!$canEdit || count($docEntry) == 0):
?>

    <div class="container h-100" id="legidox-tree-view">
        <div class="row vh-100">
            <div
                class="col-12 h-100 d-flex flex-column align-items-center justify-content-center"
                id="ld-edit-is-forbidden"
            >
                <i class="fa fa-times-circle ld-muted-text-color ld-big-fa-icon d-block p-3"></i>
                <h3 class="ld-muted-text-color d-block">
                    <?=(count($docEntry) > 0)?"Недостаточно прав":"Документ не найден" ?>
                </h3>
            </div>
        </div>
    </div>

<?

else:

if ($_SERVER["REQUEST_METHOD"] == "POST"):
    $isSuccess = false;
    $isFieldsCorrect = true;

    $addDepartmentHeads = isset($_POST["add_department_heads"]) && $_POST["add_department_heads"] === "on";
    $isVerified = isset($_POST["doc_verified"]) && $_POST["doc_verified"] === "on";
    $isPublished = isset($_POST["doc_published"]) && $_POST["doc_published"] === "on";

    $objUserBadge = CLegidoxCore::getUserBadge($USER->GetID());
    $strBBUserLink = "[URL=" . $objUserBadge["LINK"] . "]" . $objUserBadge["FULL_NAME"] . "[/URL]";

    $docName = $_POST["doc_name"];
    $selectedTags = $_POST["tags"]; // Array of selected tag IDs

    $docOwner = "";
    $docWatchers = [];

    if (isset($_REQUEST['doc_owner']) && intval($_REQUEST['doc_owner']) > 0) {
        $docOwner = $_REQUEST['doc_owner'];
    } else {
        $isFieldsCorrect = false;
    }

    if (isset($_REQUEST['doc_watchers']) && is_array($_REQUEST['doc_watchers']) && count($_REQUEST['doc_watchers']) > 0) {
        $docWatchers = $_REQUEST['doc_watchers'];
    }

    $intUploadedFileID = intval($_REQUEST["file_id"]);
    $intIblockDocEntry = intval($_REQUEST["doc_entry"]);

    if ($intUploadedFileID == 0 || $intIblockDocEntry == 0) {
        $isFieldsCorrect = false;
    }

    if ($canDelete && isset($_REQUEST['op_mode']) && $_REQUEST['op_mode'] == 'destroy-file') {
        // Really destroying file

        $obFile = Disk\File::getById($docEntry["FILE_ID"]);
        $bFileDeleted = false;
        $bIBElementCleared = false;

        if ($obFile && $obFile instanceof Disk\File) {
            $bFileDeleted = $obFile->delete($USER->GetID());
        } else {
            $arAlerts[] = [
                'STYLE' => 'danger',
                'TEXT' => 'Не удалось найти файл документа'
            ];
        }

        If ($bFileDeleted) {
            // Now cleaning iblock
            // TODO: Здесь можно повесить событие удаления если хэндлер не отработает

            if (CIBlockElement::Delete($docEntry["IBLOCK_ENTRY_ID"])) {

                $docTags = "";

                foreach ($docEntry["DOC_TAGS"] as $docTag) {
                    $docTags .= $docTag["NAME"] . "[BR]";
                }

                $strBBnotifn = $strBBUserLink . ": [BR]Произведено удаление документа: [BR][B]"
                    . $docEntry["DOCUMENT_NAME"]
                    . "[/B][BR][BR]Теги:[BR] "
                    . $docTags . "[BR]";

                CLegidoxCore::sendNotification(intval($docEntry["FILE_ID"]), $strBBnotifn, $docEntry);

                LocalRedirect('/legidox/');
            } else {
                $arAlerts[] = [
                    'STYLE' => 'danger',
                    'TEXT' => 'Не удалось удалить запись о документе'
                ];
            }
        } else {
            $arAlerts[] = [
                'STYLE' => 'danger',
                'TEXT' => 'Не удалось удалить файл документа'
            ];
        }
    }

    if (empty($_POST['doc_approve_deadline']) || empty($_POST['doc_expiredate'])) {
        $isFieldsCorrect = false;
        $arAlerts[] = [
            'STYLE' => 'danger',
            'TEXT' => 'Не указаны крайние сроки в блоке "Документооборот"'
        ];
    }

    $dateDocExpireDate = DateTime::createFromPhp(new \DateTime($_POST["doc_expiredate"]));
    $dateApproveDeadline = DateTime::createFromPhp(new \DateTime($_POST["doc_approve_deadline"]));
    $arDocApprovers = $_POST["doc_approvers"];

    //Check if it is unique
    if (!CLegidoxCore::isUnique($docName, $intUploadedFileID)) {
        $isFieldsCorrect = false;
        $arAlerts[] = [
            'STYLE' => 'danger',
            'TEXT' => 'Файл с названием ' . $docName . ' уже существует в базе'
        ];
    }

    if ($isFieldsCorrect) {
        $arFields = array(
            "NAME" => $docName,
            'IBLOCK_ID' => (int) \CIBlock::GetList([], ['CODE' => 'LD_FILES', 'TYPE' => 'LEGIDOX_DOC'])->Fetch()['ID'],
            "PROPERTY_VALUES" => array(
                "LD_FILE_ID" => strval($intUploadedFileID), // ID of the uploaded file in Disk
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

        $editor = new CIBlockElement();
        $isSuccess = $editor->Update($intIblockDocEntry, $arFields);

        if($isSuccess) {
            $docTags = "";
            $docEntry = CLegidoxCore::getDocumentParamsByID($docEntry["FILE_ID"]);

            foreach ($docEntry["DOC_TAGS"] as $docTag) {
                $docTags .= $docTag["NAME"] . "[BR]";
            }

            $strBBnotifn = $strBBUserLink . ": [BR]Обновлены свойства документа: [BR][URL=/legidox/file/"
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

            $arAlerts[] = [
                'STYLE' => 'success',
                'TEXT' => 'Изменения сохранены!'
            ];

            //LocalRedirect('/legidox/file/' . $docEntry["FILENAME"]);
        } else {
            $arAlerts[] = [
                'STYLE' => 'warning',
                'TEXT' => 'Проверьте правильность заполнения обязательных полей!'
            ];
        }
    }

endif; //if ($_SERVER["REQUEST_METHOD"] == "POST"):

$APPLICATION->SetTitle("Редактирование тегов - " . $docEntry["DOCUMENT_NAME"]);

// Build a form
//section Form static content
?>

<? foreach ($arAlerts as $arAlert):?>
<div class="alert alert-<?=$arAlert['STYLE']?> alert-dismissible fade show" role="alert">
    <?=$arAlert['TEXT']?>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Скрыть"></button>
</div>
<? endforeach;?>
<div class="container pt-3">
    <div class="row d-flex flex-column align-items-left">
        <? if(!$isDocPublished): ?>
        <div class="col-12 col-md-8 ld-upload-form">
            <form method="post" enctype="multipart/form-data" class="needs-validation" novalidate>
                <input type="hidden" name="doc_entry" id="doc_entry" value="<?=$docEntry["IBLOCK_ENTRY_ID"]?>">
                <h5>Редактирование тегов</h5>
                <div class="form-group pb-3">
                    <label for="doc_name">Имя документа: <span style='color: red;'> *</span></label>
                    <input
                        type="text"
                        name="doc_name"
                        id="doc_name"
                        class="form-control"
                        value="<?=$docEntry["DOCUMENT_NAME"]?>"
                        placeholder="Введите название документа"
                        required
                    >
                    <div id="doc_name_feedback" class="invalid-feedback">
                        Это имя документа уже используется.
                    </div>
                </div>
                <div class="card pb-1 mb-3">
                    <div class="card-body">
                        <h5 class="card-title">Теги</h5>
                        <p class="card-subtitle mb-2 text-muted">Пожалуйста, выберите теги, соответствующие данному документу:</p>
                        <? foreach ($arTagNodes as $arParam): ?>
                        <div class="form-group pb-3">
                            <label for="tags_<?=$arParam['CODE']?>" class="form-label"><?=$arParam['NAME']?><?=($arParam["REQUIRED"] == "Y")?"<span style='color: red;'> *</span>":""?></label>
                            <select name="tags['<?=$arParam['CODE']?>']" id="tags_<?=$arParam['CODE']?>" class="form-select form-select-sm" <?=($arParam["REQUIRED"] == "Y")?"required":""?> >
                                <option <?=($id == intval($docEntry["DOC_TAGS"][$arParam['CODE']]["ID"]))?"":"selected"?> disabled value="">Выберите <?=$arParam['NAME']?>...</option>
                                <? foreach ($arParam['ITEMS'] as $id => $name): ?>
                                <option <?=($id == intval($docEntry["DOC_TAGS"][$arParam['CODE']]["ID"]))?"selected":""?> value="<?=$id?>"><?=$name?></option>
                                <? endforeach;?>
                            </select>
                        </div>
                        <? endforeach; ?>
                        <!-- /iter -->
                    </div>
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
                                var docOwnerInput = document.querySelector('#doc_owner');
                                if (docOwnerInput) {
                                    docOwnerInput.setAttribute('required', 'true');
                                    docOwnerInput.setAttribute('type', 'text');
                                    docOwnerInput.classList.add('visually-hidden');
                                }
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
                                    "LIST" => $arDocWatchers,
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
                        <div class="form-group pb-3 d-none">
                            <label for="doc_approve_deadline" class="form-label">Крайний срок верификации:<span style='color: red;'> *</span></label>
                            <input type="date" class="form-control" id="doc_approve_deadline" name="doc_approve_deadline" value="<?=$dateApproveDeadline?>">
                        </div>
                        <div class="form-group pb-3">
                            <label for="doc_expiredate" class="form-label">Дата истечения срока действия документа:<span style='color: red;'> *</span></label>
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
                            const expireDate = document.getElementById('doc_expiredate');

                            function isValidDate(dateString) {
                                const regEx = /^\d{4}-\d{2}-\d{2}$/;
                                if (!dateString.match(regEx)) return false;  // Invalid format
                                const d = new Date(dateString);
                                const dNum = d.getTime();
                                if (!dNum && dNum !== 0) return false; // NaN value, Invalid date
                                return d.toISOString().slice(0,10) === dateString;
                            }

                            function setExpireDate() {
                                if (expireDate.value === '' || !isValidDate(expireDate.value)) {
                                    const today = new Date();
                                    const nextYear = new Date(today.setFullYear(today.getFullYear() + 1));
                                    expireDate.value = nextYear.toISOString().split('T')[0]; // Format YYYY-MM-DD
                                }
                            }

                            setExpireDate();
                        });
                    </script>
                </div>
<!-- Document approval workflow END -->
                <div class="form-group py-3 d-flex flex-row justify-content-between">
                    <button type="submit" class="btn btn-success" id="submit-btn">Сохранить документ</button>
                </div>
                <div id="form-error-message" class="text-danger" style="display: none;">
                    Пожалуйста, исправьте ошибки на форме
                </div>
            </form>
        </div>
        <? else: ?>
        <div class="container" id="legidox-tree-view">
            <div class="row">
                <div
                    class="col-12 h-100 d-flex flex-column align-items-center justify-content-center"
                    id="ld-edit-is-forbidden"
                >
                    <i class="fa fa-times-circle ld-muted-text-color ld-big-fa-icon d-block p-3"></i>
                    <h5 class="ld-muted-text-color d-block">
                        Опубликованный документ может быть только удален.
                    </h5>
                </div>
            </div>
        </div>
        <? endif; //if(!$isPublished): ?>
        <? if($canDelete): ?>
        <div class="col-12 col-md-8 ld-upload-form mt-5">
            <form method="post" enctype="multipart/form-data">
                <input type="hidden" name="doc_entry" id="doc_entry" value="<?=$docEntry["IBLOCK_ENTRY_ID"]?>">
                <input type="hidden" name="op_mode" id="op_mode" value="destroy-file">
                <div class="card pb-1 mb-3">
                    <div class="card-body">
                        <h5 class="card-title">Удаление документа</h5>
                        <div class="form-group pb-1">
                            <p>
                                Данное действие безвозвратно удалит файл документа,
                                все прошлые версии документа и саму запись документа из архива базы документов.
                                <br><br>
                                <em>Данное действие доступно авторам, владельцам документа и администраторам Портала</em>
                                <br><br>
                                Вы уверены, что хотите продолжить?
                            </p>
                        </div>
                        <div class="form-group pb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" value="" id="confirm_delete" name="confirm_delete" required>
                                <label class="form-check-label" for="confirm_delete">
                                    Подтверждаю уничтожение документа
                                </label>
                            </div>
                        </div>
                        <div class="form-group pb-1">
                            <button type="submit" class="btn btn-danger">Уничтожить документ</button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
        <? endif; //if($canDelete): ?>
    </div>
</div>

<?php

endif; //if (!$canEdit || count($docEntry) == 0):

$pageContent = ob_get_clean();

// Introducing slider wrapper

if (isset($_REQUEST["IFRAME"]) && $_REQUEST["IFRAME"] === "Y")
{
    $APPLICATION->RestartBuffer();
    ?>
    <!DOCTYPE html>
    <html>
        <head>
            <?$APPLICATION->ShowHead(); ?>
        </head>
        <body>
            <div id="form-wrapper" class="bootstrap-iso legidox-bs-wrapper">
                <div class="container-fluid py-2 bg-light">
                    <?
                    echo($pageContent);
                    ?>
                </div>
            </div>
            <script type="text/javascript">
                function checkFormValidity() {
                    var form = document.querySelector('form');
                    var submitButton = document.getElementById('submit-btn');
                    var errorMessage = document.getElementById('form-error-message');

                    if (form.checkValidity() === false) {
                        submitButton.classList.remove('btn-success');
                        submitButton.classList.add('btn-secondary');
                        errorMessage.style.display = 'block';
                    } else {
                        submitButton.classList.remove('btn-secondary');
                        submitButton.classList.add('btn-success');
                        errorMessage.style.display = 'none';
                    }
                }

                $(document).ready(function() {
                    $('#doc_name').on('input', function() {
                        var docName = $(this).val();
                        var currentDocID = <?=$docEntry['FILE_ID']?>;

                        if (docName.length > 0) {
                            $.ajax({
                                url: '/legidox/tools/ajax.php',
                                type: 'POST',
                                data: {
                                    mode: 'check_unique',
                                    doc_name: docName,
                                    doc_id: currentDocID
                                },
                                success: function(response) {
                                    if (response.data.is_unique) {
                                        $('#doc_name')[0].setCustomValidity('');
                                        $('#doc_name').addClass('is-valid').removeClass('is-invalid');
                                    } else {
                                        $('#doc_name')[0].setCustomValidity('Это имя документа уже используется!');
                                        $('#doc_name').addClass('is-invalid').removeClass('is-valid');
                                    }
                                    checkFormValidity();
                                }
                            });
                        } else {
                            $('#doc_name')[0].setCustomValidity('');
                            $('#doc_name').removeClass('is-valid is-invalid');
                            checkFormValidity();
                        }
                    });
                });

                window.addEventListener('keydown', (e) => {
                    if (e.key === "F5" && BX.SidePanel.Instance.opened) {
                        e.preventDefault();
                        let err = BX.SidePanel.Instance.reload();
                        console.log(err);
                    }
                });

                window.addEventListener('submit', (e) => {
                    var form = e.target;
                    if (form.checkValidity() === false) {
                        e.preventDefault();
                        e.stopPropagation();
                        form.classList.add('was-validated');
                        checkFormValidity();
                    } else {
                        if (BX && BX.SidePanel.Instance.opened) {
                            BX.SidePanel.Instance.close();
                        }
                    }
                });
            </script>
        </body>
    </html>
    <?
}
else
{
    ?>
    <div id="form-wrapper" class="bootstrap-iso legidox-bs-wrapper">
        <?
        echo($pageContent);
        ?>
    </div>
    <?
}

