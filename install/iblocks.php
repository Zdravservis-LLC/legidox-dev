<?php
global $APPLICATION;

use Bitrix\Main\SiteTable;
use Bitrix\Iblock\IblockTable;

$siteIds = [];
$siteList = SiteTable::getList(['filter' => ['ACTIVE' => 'Y'], 'select' => ['LID']]);
while ($site = $siteList->fetch()) {
    $siteIds[] = $site['LID'];
}

function installIblockScheme()
{
    require_once (__DIR__.'/iblock_structure.php');

    $arCreatedCache = [];

    if (!isset($_LEGIDOX_IBLOCK_STRUCTURE)) {
        throw new \Bitrix\Main\SystemException('Не найден файл с описанием инфоблоков - iblock_structure.php');
    }

    $arStructure = $_LEGIDOX_IBLOCK_STRUCTURE;

    foreach ($arStructure as $blockdef) {
        if (!isset($blockdef['MODE'])) {
            throw new \Bitrix\Main\SystemException('Неправильное описание структуры инфоблоков');
        } else if
        (
            !isset($blockdef['STRUCTURE'])
            || !is_array($blockdef['STRUCTURE'])
            || count($blockdef['STRUCTURE']) == 0
        ) {
            throw new \Bitrix\Main\SystemException('Один или более элементов инфоблока содержит пустую структуру');
        } else {
            $res = [];
            switch ($blockdef['MODE']) {
                case 'TYPE':
                    $res = updateIBlockType($blockdef['STRUCTURE']);
                    break;
                case 'IBLOCK':
                    $res = updateIBlock($blockdef['STRUCTURE']);
                    break;
                default:
                    throw new \Bitrix\Main\SystemException('Нераспознанный режим создания инфоблока');
            }
            if (!is_array($res) || count($res) == 0 || !isset($res['ID'])) {
                throw new \Bitrix\Main\SystemException('Один или более инфоблоков создан или обновлен с ошибкой');
            }
        }
    }

    return true;
}

function updateIBlockType(array $arFields): array
{
    $arResult = [];
    $strResID = "";

    $iblockTypeExists = CIBlockType::GetByID($arFields['ID'])->Fetch();
    $obBlocktype = new CIBlockType();
    if (!$iblockTypeExists) {
        $strResID = $obBlocktype->Add($arFields);
    } else {
        $success = $obBlocktype->Update(strval($iblockTypeExists['ID']), $arFields);
        if ($success) {
            $strResID = $arFields['ID'];
        }
    }

    $arCreatedType = CIblockType::GetByID($strResID)->Fetch();
    if (is_array($arCreatedType) && count($arCreatedType) > 0) {
        $arResult = $arCreatedType;
    } else {
        throw new \Bitrix\Main\SystemException('Не вышло создать тип инфоблока ' . $strResID);
    }

    return $arResult;
}

function updateIBlock(array $arFields): array
{
    if (!isset($arFields['PROPERTIES'])) {
        throw new \Bitrix\Main\SystemException('В схеме инфоблока отсутствует тег PROPERTIES. Продолжение невозможно.');
    }
    if (!isset($arFields['CODE'])) {
        throw new \Bitrix\Main\SystemException('В схеме инфоблока отсутствует тег CODE. Продолжение невозможно.');
    }

    $editor = new CIBlock();
    $arResult = [];
    $arIBlock = IblockTable::getList([
        'filter' => ['CODE' => $arFields['CODE']],
        'select' => ['ID']
    ])->fetch();

    $arIblockProps = $arFields['PROPERTIES'];
    unset($arFields['PROPERTIES']);

    $intIBlockID = 0;
    if (isset($arIBlock) && is_array($arIBlock) && count($arIBlock) > 0 && isset($arIBlock['ID'])) {
        $intIBlockID = intval($arIBlock['ID']);
    }

    if ($intIBlockID == 0) {
        $intIBlockID = $editor->Add($arFields);
        if (!$intIBlockID || intval($intIBlockID) == 0) {
            throw new \Bitrix\Main\SystemException('Не вышло создать инфоблок '
                . $arFields['CODE']
                . ': ' . $editor->LAST_ERROR);
        }
    } else {
        $res = $editor->Update($intIBlockID, $arFields);
        if (!$res) {
            throw new \Bitrix\Main\SystemException('Не вышло обновить инфоблок '
                . $arFields['CODE']
                . ': ' . $editor->LAST_ERROR);
        }
    }

    if (is_array($arIblockProps) && count($arIblockProps) > 0) {
        $propertyEditor = new CIBlockProperty();

        foreach ($arIblockProps as $arPropFields) {
            $propertyCode = $arPropFields['CODE'];
            $propertyExists = false;

            $propertyList = CIBlockProperty::GetList([], [
                'IBLOCK_ID' => $intIBlockID,
                'CODE' => $propertyCode
            ]);

            if ($property = $propertyList->Fetch()) {
                $propertyExists = true;
                $res = $propertyEditor->Update($property['ID'], $arPropFields);
            } else {
                $arPropFields['IBLOCK_ID'] = $intIBlockID;
                $res = $propertyEditor->Add($arPropFields);
            }

            if (!$res) {
                throw new \Bitrix\Main\SystemException('Ошибка при ' . ($propertyExists ? 'обновлении' : 'создании') . ' свойства: ' . $propertyEditor->LAST_ERROR);
            }
        }
    }

    $arResult = CIBlock::GetByID($intIBlockID)->Fetch();

    return $arResult;
}

function unInstallIblockScheme()
{
    $iblockTypes = ["LEGIDOX_DOC", "LEGIDOX_TAG"]; // Array of iblock CODEs

    foreach ($iblockTypes as $iblockType) {
        $orm = IblockTable::getList([
            'filter' => ['IBLOCK_TYPE_ID' => $iblockType],
            'select' => ['ID']
        ]);

        while ($result = $orm->fetch()) {
            if ($result) {
                $iblockId = $result['ID'];
                $iblockObject = new CIBlock;
                $res = $iblockObject->Delete($iblockId);
                if (!$res) {
                    throw new \Bitrix\Main\SystemException('Не вышло удалить инфоблок'
                        . $iblockObject->LAST_ERROR);
                }
            }
        }

        $iblockTypeExists = CIBlockType::GetByID($iblockType)->Fetch();
        if ($iblockTypeExists) {
            $res = CIBlockType::Delete($iblockType);
            if (!$res) {
                throw new \Bitrix\Main\SystemException('Не вышло удалить тип инфоблока ' . $iblockType);
            }
        }
    }

    return true;
}
