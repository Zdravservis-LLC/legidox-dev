<?php
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

$APPLICATION->SetTitle("Согласование");

$strLegidoxCSSPath = '/legidox/css/legidox.css';

if (is_file($_SERVER["DOCUMENT_ROOT"] . $strLegidoxCSSPath)) {
    Asset::getInstance()->addCss($strLegidoxCSSPath);
}

$canEdit = false;
$isVerified = false;
$isPublished = false;
$docEntry = [];
$arAlerts = [];
$userID = intval($USER->GetID());
$normacontrollerID = COption::GetOptionInt('legidox', 'U_NORMACONTROL_ID', '0');
$arNormacontrollerBadge = CLegidoxCore::getUserBadge($normacontrollerID);

if (isset($_REQUEST["file_id"]) && intval($_REQUEST["file_id"]) > 0) {
    $docEntry = CLegidoxCore::getDocumentParamsByID(intval($_REQUEST["file_id"]));
}
$arApproversArray = [];
if (is_array($docEntry) && isset($docEntry["DOC_APPROVERS"]) && is_array($docEntry["DOC_APPROVERS"])) {
    $arApproversArray = $docEntry["DOC_APPROVERS"];
}

$docApprovers = CLegidoxCore::convertEntitiesToUserIDs($arApproversArray);

if (isset($docEntry["DOC_VERIFIED"]) && intval($docEntry["DOC_VERIFIED"]) > 0) {
    $isVerified = true;
}

if (isset($docEntry["DOC_PUBLISHED"]) && intval($docEntry["DOC_PUBLISHED"]) > 0) {
    $isPublished = true;
}

if (
    in_array(strval($userID), $docApprovers)
    || $docEntry["DOC_OWNER"] == strval($userID)
    || $docEntry["DOC_AUTHOR"] == strval($userID)
    || $userID == $normacontrollerID
)
{
    $canEdit = true;
}

// Starting output buffer
ob_start();

if (!$canEdit || count($docEntry) == 0):
?>

<div class="container h-100" id="legidox-tree-view">
    <div class="row h-100">
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

$approveTable = CLegidoxCore::getApproveTable($docEntry["IBLOCK_ENTRY_ID"]);

if (!in_array($docEntry["DOC_OWNER"], $docApprovers)) {
    array_unshift($docApprovers, $docEntry["DOC_OWNER"]);
}

$approversDetails = [];
foreach ($docApprovers as $approverId) {
    if (!empty($approverId)) {
        $approversDetails[] = CLegidoxCore::getUserBadge((int)$approverId);
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if ($userID == $normacontrollerID && isset($_POST['publishDocument'])) {
        if ($_POST['publishDocument'] == 'approve' && $userID == $normacontrollerID) {
            if ($isVerified) {
                $arResult = CLegidoxCore::publishDocument(
                    intval($docEntry["IBLOCK_ENTRY_ID"]),
                    $userID
                );
            } else {
                $arResult = [
                    'comment' => 'Нельзя опубликовать документ без одобрения владельца!'
                ];
            }
        } elseif ($_POST['publishDocument'] == 'rework' && $userID == $normacontrollerID) {
            $arResult = CLegidoxCore::recallDocument(
                intval($docEntry["IBLOCK_ENTRY_ID"]),
                $userID
            );
        }
    } else {
        // Fetch existing table
        $approveTable = CLegidoxCore::getApproveTable($docEntry["IBLOCK_ENTRY_ID"]);

        // Get document owner's ID
        $intDocOwner = intval($docEntry["DOC_OWNER"]);

        // Update based on current user input
        $currentUserId = $userID;
        $approveTable[$currentUserId] = [
            'approved' => isset($_POST['approved'][$currentUserId]) ? true : false,
            'comment' => $_POST['comment'][$currentUserId] ?? ''
        ];

        // Check if document owner approved the doc
        if (
            isset($approveTable[$intDocOwner])
            && isset($approveTable[$intDocOwner]['approved']))
        {
            if ($approveTable[$intDocOwner]['approved'] == true) {
                $isVerified = true;
            } else {
                $isVerified = false;
            }
        }

        // Save updated data
        $arResult = CLegidoxCore::updateDocumentVerification(
            $docEntry["IBLOCK_ENTRY_ID"],
            $isVerified,
            intval($USER->GetID()),
            $approveTable
        );
    }
    if (is_array($arResult) && isset($arResult['success']))
    {
        $arAlerts[] = [
            'STYLE' => 'success',
            'TEXT' => 'Данные обновлены'
        ];
    } else {
        $arAlert = [
            'STYLE' => 'danger',
            'TEXT' => 'Ошибка обновления данных'
        ];
        if (isset($arResult['comment'])) {
            $arAlert['TEXT'] = 'Ошибка обновления данных - ' . $arResult['comment'];
        }
        $arAlerts[] = $arAlert;
    }
}

$APPLICATION->SetTitle("Согласование {$docEntry["DOCUMENT_NAME"]}");

?>

<? foreach ($arAlerts as $arAlert):?>
    <div class="alert alert-<?=$arAlert['STYLE']?> alert-dismissible fade show" role="alert">
        <?=$arAlert['TEXT']?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Скрыть"></button>
    </div>
<? endforeach;?>
<div class="container pt-3">
    <div class="row d-flex flex-column align-items-left">
        <div class="col-12">
            <form method="post" enctype="multipart/form-data">
                <div class="mt-4 p-3 border rounded">
                    <h5>Согласование <?=$docEntry["DOCUMENT_NAME"]?></h5>
                    <div>
                        <?php if($isVerified): ?>
                            <h5><span class="badge bg-warning text-dark">Утвержден владельцем</span></h5>
                        <?php endif; ?>
                        <?php if($isPublished): ?>
                            <h5><span class="badge bg-success">Опубликован</span></h5>
                        <?php endif; ?>
                    </div>
                    <div class="table-responsive">
                        <table class="table">
                            <thead class="thead-light">
                            <tr>
                                <th></th>
                                <th class="text-center"></th>
                                <th class="text-center">Комментарий</th>
                                <th class="text-center">Утверждено</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($approversDetails as $approver): ?>
                                <?php
                                $checked = "";
                                $comment = "";
                                $rowClass = ($approver['ID'] == $docEntry["DOC_OWNER"]) ? "ld-doc-owner-row" : ""; // Add 'owner-row' class if it's the document owner
                                if (isset($approveTable[$approver['ID']])) {
                                    $checked = $approveTable[$approver['ID']]['approved'] ? "checked" : "";
                                    $comment = htmlspecialchars($approveTable[$approver['ID']]['comment']);
                                }
                                ?>
                                <tr class="align-middle <?= $rowClass ?>">
                                    <td><img src="<?= htmlspecialchars($approver['PHOTO']) ?>" alt="Фото" class="img-fluid rounded-circle" style="width: 50px; height: 50px;"></td>
                                    <td class="text-center"><a href="<?= htmlspecialchars($approver['LINK']) ?>"><?= htmlspecialchars($approver['FULL_NAME']) ?></a></td>
                                    <td class="text-center">
                                    <textarea
                                        class="form-control"
                                        name="comment[<?= $approver['ID'] ?>]"
                                        <?php if ($approver['ID'] != $userID): ?>
                                            disabled
                                        <?php endif; ?>
                                    ><?= $comment ?></textarea>
                                    </td>
                                    <td class="text-center">
                                        <input
                                            type="checkbox"
                                            class="form-check-input"
                                            name="approved[<?= $approver['ID'] ?>]"
                                            <?= $checked ?>
                                            <?php if ($approver['ID'] != $userID): ?>
                                                disabled
                                            <?php endif; ?>
                                        >
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <button type="submit" class="btn btn-success">Сохранить</button>
                </div>
                <?php if ($userID == $normacontrollerID): ?>
                <!-- Publication Section -->
                <div class="mt-4 p-3 border rounded">
                    <h5>Публикация документа</h5>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="publishCheckbox">
                        <label class="form-check-label" for="publishCheckbox">
                            Данные верны
                        </label>
                    </div>
                    <div class="mt-3">
                        <button type="submit" name="publishDocument" value="approve" class="btn btn-success" id="approveButton" disabled>Опубликовать</button>
                        <button type="submit" name="publishDocument" value="rework" class="btn btn-warning" id="reworkButton" disabled>На доработку</button>
                    </div>
                </div>

                <!-- Additional Scripts for Dynamic Button Activation -->
                <script>
                    document.addEventListener('DOMContentLoaded', function() {
                        const publishCheckbox = document.getElementById('publishCheckbox');
                        const approveButton = document.getElementById('approveButton');
                        const reworkButton = document.getElementById('reworkButton');

                        function updateButtonStates() {
                            approveButton.disabled = !publishCheckbox.checked;
                            reworkButton.disabled = publishCheckbox.checked;
                        }

                        publishCheckbox.addEventListener('change', updateButtonStates);
                        updateButtonStates(); // Initial update on page load
                    });
                </script>
                <?php else:?>
                    <div class="mt-4 p-3 border rounded">
                        <h5>Ответственные за проверку документа:</h5>
                        <div>
                            <span><img src="<?= htmlspecialchars($arNormacontrollerBadge['PHOTO']) ?>" alt="Фото" class="img-fluid rounded-circle" style="width: 50px; height: 50px;"></span>
                            <span class="text-center"><a href="<?= htmlspecialchars($arNormacontrollerBadge['LINK']) ?>"><?= htmlspecialchars($arNormacontrollerBadge['FULL_NAME']) ?></a></span>
                        </div>
                    </div>
                <?php endif; ?>
            </form>
        </div>
    </div>
</div>

<?
endif;

// Getting generated content
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
    <div class="container-fluid vh-100 py-2">
        <?
        echo($pageContent);
        ?>
    </div>
</div>
<script type="text/javascript">
    window.addEventListener('keydown', (e) => {
        if (e.key === "F5" && BX.SidePanel.Instance.opened) {
            e.preventDefault();
            let err = BX.SidePanel.Instance.reload();
            console.log(err);
        }
    });

    window.addEventListener('submit', (e) => {
            if (BX && BX.SidePanel.Instance.opened) {
                BX.SidePanel.Instance.close();
            }
        }
    );
</script>
</body>
    </html><?
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
