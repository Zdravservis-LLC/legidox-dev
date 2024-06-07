<?php

use Bitrix\Main\Localization\Loc;
use Bitrix\Main\ModuleManager;
use Bitrix\Main\EventManager;

Loc::loadMessages(__FILE__);

class legidox extends CModule
{
    protected array $MODULE_FILELIST = [];
    protected array $MODULE_CLEANLIST = [];

    public function __construct()
    {
        global $arModuleFileList, $arModuleCleaningList;
        if (is_file(__DIR__ . '/version.php')) {
            include_once(__DIR__ . '/version.php');

            if (is_file(__DIR__ . '/filelist.php')) {
                include(__DIR__ . '/filelist.php');
                    $this->MODULE_FILELIST = $arModuleFileList;
                    $this->MODULE_CLEANLIST = $arModuleCleaningList;
            }

            $this->MODULE_ID = get_class($this);
            if (isset($arModuleVersion)) {
                $this->MODULE_VERSION = $arModuleVersion['VERSION'];
            }
            $this->MODULE_VERSION_DATE = $arModuleVersion['VERSION_DATE'];
            $this->MODULE_NAME = Loc::getMessage('LEGIDOX_MODULE_NAME');
            $this->MODULE_DESCRIPTION = Loc::getMessage('LEGIDOX_MODULE_DESCRIPTION');

            $this->PARTNER_NAME = Loc::getMessage('LEGIDOX_PARTNER_NAME');
            $this->PARTNER_URI = Loc::getMessage('LEGIDOX_PARTNER_URI');
        } else {
            CAdminMessage::showMessage(
                Loc::getMessage('LEGIDOX_VERSION_FILE_NOT_FOUND') . ' version.php'
            );
        }
    }

    public function doInstall() {

        global $APPLICATION;

        if (
            CheckVersion(ModuleManager::getVersion('main'), '14.00.00')
        ) {
            $this->installFiles();
            $this->installDB();
            ModuleManager::registerModule($this->MODULE_ID);
            $this->installEvents();
        } else {
            CAdminMessage::showMessage(
                Loc::getMessage('LEGIDOX_MODULE_INSTALL_VERSION_ERROR')
            );
            return;
        }

        $APPLICATION->includeAdminFile(
            Loc::getMessage('LEGIDOX_MODULE_INSTALL_TITLE').' «'.Loc::getMessage('LEGIDOX_MODULE_NAME').'»',
            __DIR__.'/step.php'
        );
    }

    function InstallDB()
    {
        require_once (__DIR__.'/iblocks.php');
        $success = installIblockScheme();
        return $success;
    }

    public function installEvents(): bool
    {
        /* Регистрация REST методов */
        $evMan = EventManager::getInstance();

        return true;
    }

    public function uninstallEvents(): bool
    {
        /* Очистка REST методов */
        $evMan = EventManager::getInstance();

        return true;
    }

    public function doUninstall(): bool
    {
        global $APPLICATION;

        $this->uninstallFiles();
        $this->uninstallDB();
        $this->uninstallEvents();

        ModuleManager::unRegisterModule($this->MODULE_ID);

        $APPLICATION->includeAdminFile(
            Loc::getMessage('LEGIDOX_MODULE_UNINSTALL_TITLE').' «'.Loc::getMessage('LEGIDOX_MODULE_NAME').'»',
            __DIR__.'/unstep.php'
        );

        return true;
    }
    // section File and folder operations

    public function installFiles()
    {
        $success = false;
        $arFileList = $this->MODULE_FILELIST;
        if (count($arFileList) > 0) {
            foreach ($arFileList as $arFileListItem) {
                $src_dir = $arFileListItem['src'];
                $dst_dir = $arFileListItem['dst'];
                $success = CopyDirFiles($src_dir, $dst_dir, true, true);
            }
        }
        return $success;
    }

    public function uninstallFiles()
    {
        // TODO: Удивительно, но работает без указания корневой папки сайта. Проверить надежность
        $arModuleCleaningList = $this->MODULE_CLEANLIST;
        if (count($arModuleCleaningList) > 0) {
            foreach ($arModuleCleaningList as $arPurgeItem) {
                $purge_dir = $arPurgeItem['dir'];
                $res = DeleteDirFilesEx($purge_dir);
            }
        }
        return true;
    }

    // end section

    public function uninstallDB(bool $confirm = false)
    {
        if ($confirm) {
            require_once (__DIR__.'/iblocks.php');
            $success = unInstallIblockScheme();
        }
        return $success;
    }
}
