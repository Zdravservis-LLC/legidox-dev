<?php
if(!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED!==true) die();
/** @var array $arParams */
/** @var array $arResult */
/** @global CMain $APPLICATION */
/** @global CUser $USER */
/** @global CDatabase $DB */
/** @var CBitrixComponentTemplate $this */
/** @var string $templateName */
/** @var string $templateFile */
/** @var string $templateFolder */
/** @var string $componentPath */
/** @var \Bitrix\Disk\Internals\BaseComponent $component */
?>

<div class='bx-disk-container posr'>
	<?php
	global $APPLICATION;
	$componentParameters = array_merge(
		array_intersect_key(
			$arResult,
			array(
				'STORAGE' => true,
				'PATH_TO_FOLDER_LIST' => true,
				'PATH_TO_FILE_VIEW' => true,
				'PATH_TO_DISK_START_BIZPROC' => true,
				'PATH_TO_DISK_TASK' => true,
				'PATH_TO_FILE_HISTORY' => true,
			)
		),
		array(
			'STORAGE' => $arResult['STORAGE'],
			'FILE_ID' => $arResult['VARIABLES']['FILE_ID'],
			'RELATIVE_PATH' => $arResult['VARIABLES']['RELATIVE_PATH'],
		)
	);

	if (isset($_POST['SIDEPANEL_OVERRIDE']) && $_POST['SIDEPANEL_OVERRIDE'] == 'Y') {
		$APPLICATION->IncludeComponent(
			'bitrix:disk.sidepanel.wrapper',
			"",
			array(
				'POPUP_COMPONENT_NAME' => 'bitrix:disk.file.view',
				'POPUP_COMPONENT_TEMPLATE_NAME' => "legidox",
				'POPUP_COMPONENT_PARAMS' => $componentParameters,
			)
		);
	} else {
		$APPLICATION->IncludeComponent(
			'bitrix:disk.file.view',
			"legidox",
			$componentParameters
		);
	}


	?>
</div>