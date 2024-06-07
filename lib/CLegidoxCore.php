<?php
namespace LEGIDOX;

// Disk module
\CModule::IncludeModule('disk');
use \Bitrix\Disk;

global $ALFRED_CHATBOT_AVAILABLE;

if (\CModule::includeModule('alfred')) {
    $ALFRED_CHATBOT_AVAILABLE = true;
} else {
    $ALFRED_CHATBOT_AVAILABLE = false;
}

use ALFRED\CAlfredCore;

// Locale
use Bitrix\Main\ArgumentException;
use Bitrix\Main\ArgumentNullException;
use Bitrix\Main\ArgumentOutOfRangeException;
use Bitrix\Main\Localization\Loc;
use Bitrix\Main\NotImplementedException;
use Bitrix\Main\Config\Option;
use Bitrix\Main\UrlRewriter;
use Bitrix\Seo\Engine\Bitrix;
use Bitrix\Tasks\Item\Task;
use Bitrix\Main\Type\DateTime;
use CComponentEngine;
use CFile;
use CSite;
use CUser;
use CAgent;

Loc::loadMessages(__FILE__);

class CLegidoxCore
{

    public static function deleteStorage(int $storage): bool
    {
        global $USER;
        try {
            $storage = Disk\Storage::getById($storage);
            if ($storage)
            {
                return $storage->delete($USER->GetID());
            }
        } catch (NotImplementedException $e) {
            return false;
        }
        return false;
    }
    public static function getCommonStoragesArray(): array {
        $arrCommonStorages = Array();

        $arOrder = Array(
            'sort' => 'asc'
        );
        $arFilter = Array();

        try {
            $arStorList = Disk\Storage::getList($arFilter, $arOrder)->fetchAll();

            foreach ($arStorList as $arStorage) {
                if ($arStorage['ENTITY_TYPE'] == "Bitrix\Disk\ProxyType\Common")
                {
                    $arrCommonStorages[$arStorage['ID']] = $arStorage;
                    $arrCommonStorages[$arStorage['ID']]['BASE_URL'] = unserialize($arStorage['ENTITY_MISC_DATA'])['BASE_URL'];
                }
            }

        } catch (NotImplementedException $e) {
            return [];
        }

        return $arrCommonStorages;
    }

    public static function createStorage(): int
    {
        // Prepare params
        $storageParams = Array(
            'NAME' => 'LegiDox',
            'ENTITY_ID' => 'legidox_storage_s1',
            'SITE_ID' => 's1'
        );

        $storageRights = Array();

        // Create common storage
        $diskDriver = Disk\Driver::getInstance();

        try {
            $obStorage = $diskDriver->addCommonStorage($storageParams, $storageRights);
        } catch (ArgumentException $e) {
            return false;
        }

        if ($obStorage) {
            $arFields = Array (
                'CONDITION' => '#^/legidox/#',
                'RULE' => '',
                'ID' => 'bitrix:disk.common',
                'PATH' => '/legidox/index.php',
                'SORT' => 100,
            );

            try {
                UrlRewriter::add('s1', $arFields);
            } catch (ArgumentNullException $e) {
                return false;
            }

            // Set up storage
            $obStorage->changeBaseUrl('/legidox/');
            $intStorageID = $obStorage->getId();

            Option::set('legidox', 'storage_id', strval($intStorageID));
            return $intStorageID;
        } else {
            return false;
        }
    }

    public static function isUnique(string $docName, ?int $currentDocEntryID = null): bool
    {
        $isUnique = true;

        $filter = array(
            "IBLOCK_ID" => (int) \CIBlock::GetList([], ['CODE' => 'LD_FILES', 'TYPE' => 'LEGIDOX_DOC'])->Fetch()['ID'],
            "NAME" => $docName,
        );

        if ($currentDocEntryID)
        {
            $filter["!PROPERTY_LD_FILE_ID"] = $currentDocEntryID;
        }

        $res = \CIBlockElement::GetList([], $filter);

        if ($res->Fetch()) {
            $isUnique = false;
        }

        return $isUnique;
    }

    public static function getStorage(): ?Disk\Storage
    {
        $storageId = intval(Option::get('legidox', 'storage_id', "0"));
        if ($storageId > 0) {
            try {
                return Disk\Storage::getById($storageId);
            } catch (NotImplementedException $e) {
                return null;
            }
        } else {
            return null;
        }
    }

    public static function sendNotification (
        int $intBitrixDiskFileID,
        string $strBBCodeChatMessage,
        array $docEntry = [],
        bool $bOnlyWorkflowAttendants = false,
        bool $bIncludeNormacontroller = false
    ): bool
    {
        // Error prevention
        global $ALFRED_CHATBOT_AVAILABLE;
        if (!$ALFRED_CHATBOT_AVAILABLE) {
            return false;
        }


        if (count($docEntry) == 0) {
            if ($intBitrixDiskFileID == 0) {
                return false;
            } else {
                $obFile = Disk\File::getById($intBitrixDiskFileID);
                if (!($obFile instanceof Disk\File)) {
                    return false;
                }
            }
            $docEntry = self::getDocumentParamsByID($intBitrixDiskFileID);
        }


        if (!isset($docEntry) || count($docEntry) == 0) {
            return false;
        }

        // Collecting user IDs for broadcast message
        $arUserIDs = [];

        // Adding document owner to list
        $arUserIDs[] = $docEntry["DOC_OWNER"];

        // Adding document author to list
        $arUserIDs[] = $docEntry["DOC_AUTHOR"];

        // Adding normacontroller
        if ($bIncludeNormacontroller) {
            $normacontrollerID = \COption::GetOptionInt('legidox', 'U_NORMACONTROL_ID', '0');
            if ($normacontrollerID && $normacontrollerID > 0) {
                $arUserIDs[] = strval($normacontrollerID);
            }
        }

        // Adding document watchers to list
        if (
            !$bOnlyWorkflowAttendants
            && isset($docEntry["DOC_WATCHERS"])
            && count($docEntry["DOC_WATCHERS"]) > 0
        ) {
            $arWatcherUsers = self::convertEntitiesToUserIDs($docEntry["DOC_WATCHERS"]);
            if (isset($arWatcherUsers) && count($arWatcherUsers) > 0) {
                $arUserIDs = array_merge($arUserIDs, $arWatcherUsers);
                if (
                    isset($docEntry["DOC_NOTIFY_HEADS"])
                    && ($docEntry["DOC_NOTIFY_HEADS"] == 1 || $docEntry["DOC_NOTIFY_HEADS"] == "1"))
                {
                    $arUserIDs = array_merge($arUserIDs, self::convertEntitiesToUserIDs(
                        self::getBitrixDepartmentHeads()
                    ));
                }
            }
        }

        // Add document approvers to list
        if (isset($docEntry["DOC_APPROVERS"]) && count($docEntry["DOC_APPROVERS"]) > 0) {
            $arApproverUsers = self::convertEntitiesToUserIDs($docEntry["DOC_APPROVERS"]);
            if (isset($arApproverUsers) && count($arApproverUsers) > 0) {
                $arUserIDs = array_merge($arUserIDs, $arApproverUsers);
            }
        }

        // Making array distinct
        $arUserIDs = array_unique($arUserIDs);

        foreach ($arUserIDs as $strUserID) {
            $arMessage = [
                'KEYBOARD' => [],
                'DIALOG_ID' => intval($strUserID),
                'MESSAGE' => $strBBCodeChatMessage
            ];
            self::deferSendMessageToUser($arMessage);
        }

        return true;
    }

    public static function deferSendMessageToUser($arParams): bool
    {
        $strSerializedParams = base64_encode(serialize($arParams));
        $agentName = "LEGIDOX\CLegidoxCore::deferSendMessageAgent('".$strSerializedParams."');";
        $existingAgent = CAgent::GetList([], ['NAME' => $agentName])->Fetch();
        if ($existingAgent) {
            return false;
        }
        $result = CAgent::Add([
            'NAME' => $agentName,
            'MODULE_ID' => 'legidox',
            'ACTIVE' => 'Y',
            'AGENT_INTERVAL' => 0,
            'NEXT_EXEC' => ConvertTimeStamp(time(), 'FULL'),
            'USER_AGENT' => '',
            'IS_PERIOD' => 'N', // Not a repeating agent
        ]);

        return (bool)$result;
    }

    public static function deferSendMessageAgent(string $strSerializedParams): string
    {
        $arParams = unserialize(base64_decode($strSerializedParams));
        CAlfredCore::sendMessageToUser($arParams);
        return '';
    }

    public static function getFiles(int $userID = 0): ?array
    {
        $storage = self::getStorage();
        if ($storage) {
            $folder = $storage->getRootObject();
            $secContext = null;
            if ($userID > 0) {
                $secContext = $folder->getStorage()->getSecurityContext($userID);
            } else {
                $secContext = $folder->getStorage()->getCurrentUserSecurityContext();
            }

            if (!$secContext) {return null;}
            $res = $folder->getChildren($secContext);

            // Removing all folders
            foreach ($res as $idx => $obj) {
                if ($obj instanceof Disk\Folder) {
                    unset($res[$idx]);
                }
            }

            return $res;

        } else {
            return null;
        }
    }

    public static function getTagFields(array $tagOptions): string
    {
        $html = '';
        $html .= '<div class="bootstrap-iso">';
        foreach ($tagOptions as $tagType => $tags) {
            $html .= '<div class="mt-3">'.$tagType.'</div>';
            $html .= '<select name="tags['.$tagType.']">';
            foreach ($tags as $tagID => $tagName) {
                $html .= '<option value="'.$tagID.'">'.$tagName.'</option>';
            }
            $html .= '</select><br>';
        }
        $html .= '</div>';
        return $html;
    }

    public static function getUserBadge(int $userID): array {
        // За основу берем обычный объект пользователя
        $arUser = CUser::GetByID($userID)->Fetch();
        $arUser['FULL_NAME'] = CUser::FormatName(
            CSite::GetNameFormat(false),
            $arUser,
            true,
            false
        );
        $urlTemplate = SITE_DIR.'company/personal/user/#user_id#/';
        $arUser['LINK'] = CComponentEngine::MakePathFromTemplate(
            $urlTemplate,
            array('user_id' => $userID)
        );
        $strPhotoUrl = CFile::ResizeImageGet(
            $arUser['PERSONAL_PHOTO'],
            array(
                'width' => 100,
                'height'=> 100
            ),
            BX_RESIZE_IMAGE_EXACT
        )['src'];

        $arUser['PHOTO'] = ($strPhotoUrl)?:'/bitrix/images/disk/default_avatar.png';
        return $arUser;
    }

    public static function getGroupBadge(int $groupID): array {
        $urlTemplate = SITE_DIR.'workgroups/group/#group_id#/';
        $arGroup['LINK'] = CComponentEngine::MakePathFromTemplate(
            $urlTemplate,
            array('group_id' => $groupID)
        );

        $arGroup['PHOTO'] = '/bitrix/images/disk/default_groupe.png';

        if (\CModule::IncludeModule('socialnetwork')) {
            $arSgInfo = \CSocNetGroup::getById($groupID);
            $arGroup['FULL_NAME'] = $arSgInfo["NAME_FORMATTED"];
        }

        return $arGroup;
    }

    public static function getDeptBadge(int $deptID): array {
        $urlTemplate = SITE_DIR.
            'local/tools/structor/structor_pages.php?set_filter_structure=Y&structure_UF_DEPARTMENT=#dept_id#';
        $arDept['LINK'] = CComponentEngine::MakePathFromTemplate(
            $urlTemplate,
            array('dept_id' => $deptID)
        );

        $arDept['PHOTO'] = '/bitrix/images/disk/default_groupe.png';

        $arDept['FULL_NAME'] = "<Подразделение не найдено>";

        if (\CModule::IncludeModule('intranet')) {
            $res = \CIBlockSection::GetList([], [
                'ID' => $deptID,
                'IBLOCK_ID' => \COption::GetOptionInt('intranet', 'iblock_structure', 3)
            ])->Fetch();
            $arDept['FULL_NAME'] = $res['NAME'];
        }

        return $arDept;
    }

    public static function getWatchersListHTML($arDocParams, bool $bShowApproversList = false): string {
        $strWatchListHTML = "";

        if ($bShowApproversList) {
            $arWatchers = $arDocParams['DOC_APPROVERS'];
        } else {
            $arWatchers = $arDocParams['DOC_WATCHERS'];
        }

        $bNotifyHeads = isset($arDocParams["DOC_NOTIFY_HEADS"]) && intval($arDocParams["DOC_NOTIFY_HEADS"]) == 1;
        foreach ($arWatchers as $entity) {
            $matches = [];
            $arResult = [];
            if (preg_match('/^([A-Z]+)(\d+)$/', $entity, $matches)) {
                $type = $matches[1];
                $id = $matches[2];

                switch ($type) {
                    case 'U':
                        // User entity
                        $arResult = self::getUserBadge(intval($id));
                        $arResult['BADGE_STYLE'] = "ld-badge-user";
                        break;

                    case 'SG':
                        // Social network group entity
                        $arResult = self::getGroupBadge(intval($id));
                        $arResult['BADGE_STYLE'] = "ld-badge-group";
                        break;

                    case 'DR':
                        // Department entity
                        $arResult = self::getDeptBadge(intval($id));
                        $arResult['BADGE_STYLE'] = "ld-badge-dept";
                        break;
                }
            }
            if (isset($arResult['FULL_NAME']) && isset($arResult['LINK'])) {
                $strWatchListHTML .= "<li>
                <a href='".$arResult['LINK']."'>
                <span class='badge bg-primary ".$arResult['BADGE_STYLE']."'>".$arResult['FULL_NAME']."</span>
                </a>
                </li>";
            }
        }
        if ($bNotifyHeads) {
            $strWatchListHTML .= "<li>
                <span class='badge bg-success'>Оповещаются руководители подразделений</span>
                </li>";
        }
        return $strWatchListHTML;
    }

    public static function getTagNodes(): array
    {
        // Get the available tag types and their values
        $oTagTypes = \CIBlock::GetList(
            array("SORT" => "ASC"),
            array("TYPE" => "LEGIDOX_TAG", "ACTIVE" => "Y"),
            true
        );

        $arTagNodes = array();
        while ($arTagType = $oTagTypes->Fetch()) {
            $oTagItems = \CIBlockElement::GetList(
                array("SORT" => "ASC"),
                array("IBLOCK_ID" => $arTagType["ID"], "ACTIVE" => "Y"),
                false,
                false,
                array("ID", "NAME")
            );

            $arTagItems = array();

            while ($arTagItem = $oTagItems->Fetch()) {
                $arTagItems[$arTagItem['ID']] = $arTagItem['NAME'];
            }
            $intSortIdx = intval($arTagType['SORT']);

            // LD_TAG_DOCTYPE always comes first
            if ($arTagType['CODE'] == 'LD_TAG_DOCTYPE') {
                $intSortIdx = $intSortIdx / 100;
                $arTagType['REQUIRED'] = "Y";
            } else {
                $arTagType['REQUIRED'] = "N";
            }

            $arTagNodes[$arTagType['CODE']] = array(
                "NAME" => $arTagType['NAME'],
                "CODE" => $arTagType['CODE'],
                "SORT" => $intSortIdx,
                "REQUIRED" => $arTagType['REQUIRED'],
                "ITEMS" => $arTagItems
            );
        }

        // Sort Nodes array by sub-array SORT value
        usort($arTagNodes, function($a, $b) {
            return $a['SORT'] <=> $b['SORT'];
        });

        return $arTagNodes;
    }

    public static function getTagTypes(): array {
        $arResult = [];
        $oTagTypes = \CIBlock::GetList(
            array("SORT" => "ASC"),
            array("TYPE" => "LEGIDOX_TAG", "ACTIVE" => "Y"),
            true
        );

        while ($arTagType = $oTagTypes->GetNext(true, false)) {
            $arResult[$arTagType['CODE']] = [
                "ID" => $arTagType['ID'],
                "CODE" => $arTagType['CODE'],
                "NAME" => $arTagType['NAME'],
                "API_CODE" => $arTagType['API_CODE']
            ];
        }

        // Adding meta tags
        $arResult['LD_META_AUTHOR'] = [
            "ID" => null,
            "CODE" => 'LD_META_AUTHOR',
            "NAME" => 'Автор документа',
            "API_CODE" => null
        ];

        $arResult['LD_META_OWNER'] = [
            "ID" => null,
            "CODE" => 'LD_META_OWNER',
            "NAME" => 'Владелец документа',
            "API_CODE" => null
        ];

        return $arResult;
    }

    public static function getFilesIblockId()
    {
        $iblockCode = 'LD_FILES';

        $iblockFilter = array(
            'CODE' => $iblockCode
        );

        $iblockList = \CIBlock::GetList(array(), $iblockFilter);
        if ($iblock = $iblockList->Fetch()) {
            return $iblock['ID'];
        }

        return false;
    }

    public static function getDocumentParamsByID(int $docID): array
    {
        $arResult = [];
        $arTagTypes = self::getTagTypes();
        $obFile = Disk\File::getById($docID);
        $fileName = "";

        if ($obFile instanceof Disk\File) {
            $fileName = $obFile->getName();
        } else {
            return [];
        }

        $iblockID = self::getFilesIblockId();
        $docEntry = \CIBlockElement::GetList(
            array(),
            array("IBLOCK_ID" => $iblockID, "PROPERTY_LD_FILE_ID" => $docID),
            false,
            false,
            array(
                "ID",
                "NAME",
                "CREATED_BY",
                "PROPERTY_LD_TAG_ID",
                "PROPERTY_LD_DOC_OWNER",
                "PROPERTY_LD_DOC_WATCHERS",
                "PROPERTY_LD_NOTIFY_HEADS",
                "PROPERTY_LD_DOC_APPROVERS",
                "PROPERTY_LD_DOC_APPROVE_DEADLINE",
                "PROPERTY_LD_DOC_EXPIREDATE",
                "PROPERTY_LD_DOC_VERIFIED",
                "PROPERTY_LD_DOC_PUBLISHED",
            )
        )->Fetch();

        if ($docEntry) {
            $docName = $docEntry["NAME"];
            $ibEntryID = $docEntry["ID"];
            $tagIds = Array();

            $res = \CIBlockElement::GetProperty(
                $iblockID,
                $docEntry["ID"],
                "sort",
                "asc",
                ["CODE" => "LD_TAG_ID"]
            );

            while ($obj = $res->GetNext()) {
                $tagIds[] = $obj['VALUE'];
            }

            $docOwner = strval(
                \CIBlockElement::GetProperty(
                    $iblockID,
                    $docEntry["ID"],
                    "sort",
                    "asc",
                    ["CODE" => "LD_DOC_OWNER"])->Fetch()["VALUE"]
            );

            $docOwnerName = CUser::FormatName(
                CSite::GetNameFormat(false),
                CUser::GetByID($docOwner)->Fetch(),
                true,
                false
            );

            $docAuthor = $docEntry["CREATED_BY"];

            $docAuthorName = CUser::FormatName(
                CSite::GetNameFormat(false),
                CUser::GetByID($docAuthor)->Fetch(),
                true,
                false
            );

            // Get document watchers
            $arWatchers = [];
            $obWatchers = \CIBlockElement::GetProperty(
                $iblockID,
                $docEntry["ID"],
                "sort",
                "asc",
                ["CODE" => "LD_DOC_WATCHERS"]
            );

            while ($obWatcher = $obWatchers->GetNext()) {
                if ($obWatcher['VALUE'] !== "") {
                    $arWatchers[] = strval($obWatcher['VALUE']);
                }
            }

            // Get document approvers
            $arApprovers = [];
            $obApprovers = \CIBlockElement::GetProperty(
                $iblockID,
                $docEntry["ID"],
                "sort",
                "asc",
                ["CODE" => "LD_DOC_APPROVERS"]
            );

            while ($obApprover = $obApprovers->GetNext()) {
                if ($obApprover['VALUE'] !== "") {
                    $arApprovers[] = strval($obApprover['VALUE']);
                }
            }

            $tagValues = self::getTagValues($tagIds);

            $arWatchUsers = self::convertEntitiesToUserIDs($arWatchers);
            $strWatchUsers = "";

            foreach ($arWatchUsers as $userID) {
                $arUser = \CUser::GetByID($userID)->Fetch();
                if (isset($arUser['LAST_NAME'])) {
                    $strWatchUsers .= mb_strtolower($arUser['LAST_NAME']) . " ";
                }
            }


            // Adding meta information
            $tagValues['LD_META_AUTHOR'] = [
                "NAME" => $docAuthorName . " (авт.)",
                "SORT" => "500"
            ];

            $tagValues['LD_META_OWNER'] = [
                "NAME" => $docOwnerName . " (влад.)",
                "SORT" => "500"
            ];

            $res = array(
                "FILE_ID" => strval($docID),
                "IBLOCK_ENTRY_ID" => $ibEntryID,
                "DOCUMENT_NAME" => $docName,
                "FILENAME" => $fileName,
                "DOC_TAGS" => $tagValues,
                "DOC_OWNER" => $docOwner,
                "DOC_AUTHOR" => $docAuthor,
                "DOC_WATCHERS" => $arWatchers,
                "DOC_WATCH_LNAMES" => $strWatchUsers,
                "DOC_NOTIFY_HEADS" => ($docEntry["PROPERTY_LD_NOTIFY_HEADS_VALUE"])?:0,
                "DOC_APPROVERS" => $arApprovers,
                "DOC_APPROVE_DEADLINE" => ($docEntry["PROPERTY_LD_DOC_APPROVE_DEADLINE_VALUE"])?:null,
                "DOC_EXPIREDATE" => ($docEntry["PROPERTY_LD_DOC_EXPIREDATE_VALUE"])?:null,
                "DOC_VERIFIED" => ($docEntry["PROPERTY_LD_DOC_VERIFIED_VALUE"])?:0,
                "DOC_PUBLISHED" => ($docEntry["PROPERTY_LD_DOC_PUBLISHED_VALUE"])?:0
            );
            $arResult = (!empty($res) && count($res) > 0)?$res:[];
        }

        return $arResult;
    }
    public static function getDocIDByDocElementID (int $docElementID): ?int
    {
        $res = \CIBlockElement::GetList(
            [],
            ["ID" => $docElementID],
            false,
            false,
            ['PROPERTY_LD_FILE_ID']
        );
        $docParams = $res->Fetch();
        if (isset($docParams["PROPERTY_LD_FILE_ID_VALUE"])) {
            return intval($docParams["PROPERTY_LD_FILE_ID_VALUE"]);
        }
        return null;
    }

    private static function getTagValues($tagIds): array
    {
        // Fetch tag values using the provided tag IDs
        $tagValues = array();

        foreach ($tagIds as $tagId) {
            $tagEntry = \CIBlockElement::GetList(
                array(),
                array("ID" => $tagId),
                false,
                false,
                array("ID", "IBLOCK_CODE", "NAME")
            )->Fetch();

            $tagType = \CIBlock::GetList("asc", ['CODE' => $tagEntry['IBLOCK_CODE']])->Fetch();

            if ($tagEntry) {
                $tagCode = strtoupper($tagEntry["IBLOCK_CODE"]);
                $tagName = $tagEntry["NAME"];
                $tagValues[$tagCode]["ID"] = $tagEntry["ID"];
                $tagValues[$tagCode]["NAME"] = $tagName;
                $tagValues[$tagCode]["SORT"] = $tagType['SORT'];
            }
        }

        return $tagValues;
    }

    public static function generateTagTree(
        string $selectedTagCode = null,
        array $arTagSort = [],
        string $strMode = 'PUBLISHED'
    ): array
    {
        $tagTree = array();

        $arTagLib = array();
        $arTagTypes = self::getTagTypes();
        $arTagIndex = array();

        // Fetch all document IDs from storage
        $obFiles = self::getFiles();

        // Get all file IDs from that result
        $arFiles = [];

        /** @var $obFile Disk\File */

        // Iterate over all files in storage
        foreach ($obFiles as $obFile) {
            $fileID = $obFile->getId();
            $arFile = self::getDocumentParamsByID($fileID);

            $intMode = 0;
            if ($strMode == 'PUBLISHED')
            {
                $intMode = 1;
            }

            if ($arFile["DOC_PUBLISHED"] == $intMode) {
                if (!empty($arFile)) {
                    // Consolidate tags from all files into an array
                    foreach ($arFile['DOC_TAGS'] as $strTagCode => $arTagData) {
                        if (!in_array($arTagData['NAME'], ($arTagLib[$strTagCode])?:[])) {
                            $arTagLib[$strTagCode][] = $arTagData['NAME'];
                        }
                        $arTagIndex[intval($arTagData['SORT'])] = $strTagCode;
                    }
                    $arFiles[$arFile['FILE_ID']] = $arFile;
                }
            } else {
                continue;
            }
        }

        if ($idx = array_search($selectedTagCode, $arTagIndex)) {
            unset($arTagIndex[$idx]);
            $arTagIndex[$idx - 10000] = $selectedTagCode;
        }

        // Sort tag index
        ksort($arTagIndex);

        // If we have tag sorter array - use it instead of what we've built
        $arTrueTagSort = [];
        $arKnownTagKeys = [];

        foreach ($arTagTypes as $key => $value) {$arKnownTagKeys[] = $key;}

        if (count($arTagSort) > 0) {
            foreach ($arTagSort as $strTagCandidate) {
                if (in_array($strTagCandidate, ($arKnownTagKeys)?:[]) && !in_array($strTagCandidate, ($arTrueTagSort)?:[])) {
                    $arTrueTagSort[] = $strTagCandidate;
                }
            }
        }

        $arTagIndex = (count($arTrueTagSort) > 0)?$arTrueTagSort:$arTagIndex;

        foreach ($arFiles as $arFile) {
            $fileTags = $arFile['DOC_TAGS'];
            $fileId = $arFile['FILE_ID'];

            $tagLevels = array();
            foreach ($arTagIndex as $sortIndex => $tagCode) {
                if (isset($fileTags[$tagCode]) && isset($fileTags[$tagCode]['NAME']) && $fileTags[$tagCode]['NAME'] !== "") {
                    $tagName = $fileTags[$tagCode]['NAME'];
                } else {
                    $tagName = $arTagTypes[$tagCode]['NAME'] . " - не указано";
                }
                $tagLevels[] = $tagName;
            }

            // Generate a nested array for the current file
            $nestedArray = self::generateNestedArray($fileId, $arFile, $tagLevels);

            // Merge the nested array into the tagTree
            $tagTree = self::mergeArrays($tagTree, $nestedArray);
        }

        return $tagTree;
    }

    private static function generateNestedArray($fileId, $fileDetails, $tagLevels): array
    {
        // Recursive function to generate a nested array for a file

        if (empty($tagLevels)) {
            return array($fileId => $fileDetails);
        }

        $tagLevel = array_shift($tagLevels);

        return array(
            $tagLevel => self::generateNestedArray($fileId, $fileDetails, $tagLevels)
        );
    }

    private static function mergeArrays($array1, $array2)
    {
        // Когда array_merge_recursive недостаточно...

        foreach ($array2 as $key => $value) {
            if (is_array($value) && isset($array1[$key]) && is_array($array1[$key])) {
                $array1[$key] = self::mergeArrays($array1[$key], $value);
            } else {
                $array1[$key] = $value;
            }
        }

        return $array1;
    }

    /**
     * Convert array of entities to user IDs.
     *
     * @param array $entities Array of entities (e.g., ["SG4637", "SG628", "U4006", "U4060", "DR726", "DR292"]).
     * @return array Array of user IDs.
     */
    public static function convertEntitiesToUserIDs(array $entities): array
    {
        $userIDs = [];

        foreach ($entities as $entity) {
            $matches = [];
            if (preg_match('/^([A-Z]+)(\d+)$/', $entity, $matches)) {
                $type = $matches[1];
                $id = $matches[2];

                switch ($type) {
                    case 'U':
                        // User entity
                        $userIDs[] = $id;
                        break;

                    case 'SG':
                        // Social network group entity
                        if (\CModule::IncludeModule('socialnetwork')) {
                            $sg_ulist = \CSocNetTools::GetGroupUsers($id);
                            if (isset($sg_ulist) && count($sg_ulist) > 0) {
                                $userIDs = array_merge($userIDs, $sg_ulist);
                            }
                            break;
                        }
                        break;

                    case 'DR':
                        // Department entity
                        if (\CModule::IncludeModule('intranet')) {
                            $dr_ulist = Array();

                            $res = \CIntranetUtils::getDepartmentEmployees([$id], true);

                            while ($arUser = $res->GetNext()) {
                                $dr_ulist[] = $arUser["ID"];
                            }

                            if (isset($dr_ulist) && count($dr_ulist) > 0) {
                                $userIDs = array_merge($userIDs, $dr_ulist);
                            }
                        }
                        break;
                }
            } else {
                $userIDs[] = strval(intval($entity));
            }
        }

        // Remove duplicates
        $userIDs = array_unique($userIDs);

        return $userIDs;
    }

    public static function getBitrixDepartmentHeads()
    {
        $departmentIBlockId = \COption::GetOptionInt('intranet', 'iblock_structure', 3);

        $departmentHeads = [];

        $departmentResult = \CIBlockSection::GetList(
            [],
            ['IBLOCK_ID' => $departmentIBlockId],
            false,
            ['ID', 'UF_HEAD']
        );

        while ($department = $departmentResult->Fetch()) {
            $departmentHeadId = $department['UF_HEAD'];

            if (!empty($departmentHeadId)) {
                $formattedHeadId = 'U' . $departmentHeadId;
                $departmentHeads[] = $formattedHeadId;
            }
        }

        return $departmentHeads;
    }

    public static function getApproveTable(int $docID): array
    {
        $iblockID = self::getFilesIblockId();
        if (!$iblockID) {
            return []; // IBlock ID couldn't be fetched
        }

        $propertyCode = 'LD_DOC_APPROVE_SERIALIZED';
        $properties = \CIBlockElement::GetProperty($iblockID, $docID, array("sort" => "asc"), array("CODE" => $propertyCode));
        if ($prop = $properties->Fetch()) {
            $arData = unserialize($prop['VALUE']);
            if (is_array($arData)) {
                return $arData;
            } else {
                return [];
            }
        }
        return [];
    }

    public static function updateApproveTable(int $docID, array $arFields): array
    {
        $iblockID = self::getFilesIblockId();
        if (!$iblockID) {
            return ['error' => 'IBlock ID could not be retrieved'];
        }

        $propertyCode = 'LD_DOC_APPROVE_SERIALIZED';
        $serializedData = serialize($arFields);

        $CIBlockElement = new \CIBlockElement;
        $propValues = array($propertyCode => $serializedData);
        $properties = array('PROPERTY_VALUES' => $propValues);

        $CIBlockElement->SetPropertyValuesEx($docID, $iblockID, $propValues);
        $strLastError = $CIBlockElement->LAST_ERROR;

        if (isset($strLastError) && $strLastError !== '') {
            return ['error' => 'Failed to update data', 'comment' => $strLastError];
        } else {
            return ['success' => 'Data updated successfully'];
        }
    }

    public static function updateDocumentVerification(
        int $docID,
        bool $bVerified = true,
        int $fromUserID,
        array $approveTable
    ): array
    {
        $arResult = [];
        $iblockID = self::getFilesIblockId();
        $normacontrollerID = \COption::GetOptionInt('legidox', 'U_NORMACONTROL_ID', '0');
        $docEntry = [];
        $documentIDNum = self::getDocIDByDocElementID($docID);
        if ($documentIDNum) {
            $docEntry = self::getDocumentParamsByID($documentIDNum);
        }

        $strFromIDComment = null;

        if (
            isset($approveTable[$fromUserID])
            && isset($approveTable[$fromUserID]['comment'])
            && $approveTable[$fromUserID]['comment'] !== ""
        ) {
            $strFromIDComment = $approveTable[$fromUserID]['comment'];
            $strFromIDComment = str_replace(array("\r\n", "\r", "\n"), '[BR]', $strFromIDComment);
        }

        $intVerified = 0;
        if ($bVerified) {
            $intVerified = 1;
        }

        if (!$iblockID) {
            return ['error' => 'IBlock ID could not be retrieved'];
        }

        $CIBlockElement = new \CIBlockElement;
        $propValues = array(
            'LD_DOC_VERIFIED' => $intVerified
        );

        $CIBlockElement->SetPropertyValuesEx($docID, $iblockID, $propValues);
        $strLastError = $CIBlockElement->LAST_ERROR;

        if (isset($strLastError) && $strLastError !== '') {
            return ['error' => 'Failed to verify document', 'comment' => $strLastError];
        } else {
            $arResult = self::updateApproveTable($docID, $approveTable);
            if (count($docEntry) > 0) {

                $objUserBadge = self::getUserBadge($fromUserID);
                $strBBUserLink = "[URL=" . $objUserBadge["LINK"] . "]" . $objUserBadge["FULL_NAME"] . "[/URL]";

                $docStatus = ($bVerified)?"Утвержден владельцем":"Не утвержден владельцем";
                $docComment = ($strFromIDComment)?"[B]Комментарий: [/B][BR]" . $strFromIDComment:"";

                $strBBnotifn = $strBBUserLink . ": [BR][B]Процесс утверждения документа:[/B][BR][URL=/legidox/file/"
                    . $docEntry["FILENAME"] . "]"
                    . $docEntry["DOCUMENT_NAME"]
                    . "[/URL][BR][BR]"
                    . "[B]Текущий статус документа:[/B] " . $docStatus
                    . "[BR][BR]"
                    . $docComment;

                self::sendNotification(
                    intval($docEntry["FILE_ID"]),
                    $strBBnotifn,
                    $docEntry,
                    true,
                    true
                );

                if ($bVerified) {
                    self::createNormacontrolTask($docEntry);
                }
            }
            return $arResult;
        }
    }

    public static function publishDocument(int $docID, int $fromUserID)
    {
        $iblockID = self::getFilesIblockId();
        $normacontrollerID = \COption::GetOptionInt('legidox', 'U_NORMACONTROL_ID', '0');
        $docEntry = [];
        $documentIDNum = self::getDocIDByDocElementID($docID);
        if ($documentIDNum) {
            $docEntry = self::getDocumentParamsByID($documentIDNum);
        }

        if (!$iblockID) {
            return ['error' => 'IBlock ID could not be retrieved'];
        }

        $CIBlockElement = new \CIBlockElement;
        $propValues = array(
            'LD_DOC_PUBLISHED' => 1
        );

        $CIBlockElement->SetPropertyValuesEx($docID, $iblockID, $propValues);
        $strLastError = $CIBlockElement->LAST_ERROR;

        if (isset($strLastError) && $strLastError !== '') {
            return ['error' => 'Failed to publish document', 'comment' => $strLastError];
        } else {
            if (count($docEntry) > 0) {
                $objUserBadge = self::getUserBadge($fromUserID);
                $strBBUserLink = "[URL=" . $objUserBadge["LINK"] . "]" . $objUserBadge["FULL_NAME"] . "[/URL]";

                $strBBnotifn = $strBBUserLink . ": [BR]Новая публикация документа: [BR][URL=/legidox/file/"
                    . $docEntry["FILENAME"] . "]"
                    . $docEntry["DOCUMENT_NAME"]
                    . "[/URL]";

                self::sendNotification(intval($docEntry["FILE_ID"]), $strBBnotifn, $docEntry);
            }
            return ['success' => 'Document published successfully'];
        }
    }

    public static function recallDocument(int $docID, int $fromUserID)
    {
        $iblockID = self::getFilesIblockId();
        $normacontrollerID = \COption::GetOptionInt('legidox', 'U_NORMACONTROL_ID', '0');
        $docEntry = [];
        $documentIDNum = self::getDocIDByDocElementID($docID);
        if ($documentIDNum) {
            $docEntry = self::getDocumentParamsByID($documentIDNum);
        }

        if (!$iblockID) {
            return ['error' => 'IBlock ID could not be retrieved'];
        }

        $CIBlockElement = new \CIBlockElement;
        $propValues = array(
            'LD_DOC_VERIFIED' => 0,
            'LD_DOC_PUBLISHED' => 0
        );

        $CIBlockElement->SetPropertyValuesEx($docID, $iblockID, $propValues);
        $strLastError = $CIBlockElement->LAST_ERROR;

        if (isset($strLastError) && $strLastError !== '') {
            return ['error' => 'Failed to recall document', 'comment' => $strLastError];
        } else {
            if (count($docEntry) > 0) {

                $objUserBadge = self::getUserBadge($fromUserID);
                $strBBUserLink = "[URL=" . $objUserBadge["LINK"] . "]" . $objUserBadge["FULL_NAME"] . "[/URL]";

                $strBBnotifn = $strBBUserLink . ": [BR]Документ отозван на доработку: [BR][URL=/legidox/file/"
                    . $docEntry["FILENAME"] . "]"
                    . $docEntry["DOCUMENT_NAME"]
                    . "[/URL]";

                self::sendNotification(intval($docEntry["FILE_ID"]), $strBBnotifn, $docEntry, true);
            }
            return ['success' => 'Document recalled successfully'];
        }
    }

    public static function createNormacontrolTask(array $docEntry, bool $explicitlySendEmail = false)
    {
        // Ensure the tasks module is included
        if (!\CModule::includeModule('tasks')) {
            return false;
        }

        $normacontrollerID = \COption::GetOptionInt('legidox', 'U_NORMACONTROL_ID', '0');
        $documentLink = "/legidox/file/{$docEntry["FILENAME"]}";

        // Convert deadline date to Bitrix format
        $deadlineDate = DateTime::createFromTimestamp(strtotime($docEntry["DOC_APPROVE_DEADLINE"]))->toString();

        $oTaskItem = new Task(0, $normacontrollerID);
        $oTaskItem['TITLE'] = "Проверка документа: {$docEntry['DOCUMENT_NAME']}";
        $oTaskItem['DESCRIPTION'] = "<p>Пожалуйста, проверьте документ по следующей ссылке:</p><p><a href=\"{$documentLink}\">{$docEntry['DOCUMENT_NAME']}</a></p>";
        $oTaskItem['RESPONSIBLE_ID'] = $normacontrollerID;
        $oTaskItem['DEADLINE'] = $deadlineDate;
        $rTaskResponse = $oTaskItem->save();
        $success = $rTaskResponse->isSuccess();

        if ($success && $explicitlySendEmail) {
            \CEvent::Send(
                'LEGIDOX_DOCUMENT_VERIFICATION_TASK',
                SITE_ID,
                [
                    'EMAIL_TO' => \CUser::GetEmailByID($normacontrollerID),
                    'DOCUMENT_NAME' => $docEntry['DOCUMENT_NAME'],
                    'DOCUMENT_LINK' => $documentLink,
                    'DEADLINE' => $deadlineDate,
                ]
            );
        }

        return $success;
    }
}
