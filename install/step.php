<?php
use Bitrix\Main\Localization\Loc;
global $APPLICATION;

Loc::loadMessages(__FILE__);

if (!check_bitrix_sessid()) {
    return;
}

if ($errorException = $APPLICATION->getException()) {
    CAdminMessage::showMessage(
        Loc::getMessage('LEGIDOX_MODULE_INSTALL_FAILED').': '.$errorException->GetString()
    );
} else {
    CAdminMessage::showNote(
        Loc::getMessage('LEGIDOX_MODULE_INSTALL_SUCCESS')
    );
}
?>

<form action="<?= $APPLICATION->getCurPage(); ?>"> <!-- Кнопка возврата к списку модулей -->
    <input type="hidden" name="lang" value="<?= LANGUAGE_ID; ?>" />
    <input type="submit" value="<?= Loc::getMessage('LEGIDOX_MODULE_RETURN_MODULES'); ?>">
</form>