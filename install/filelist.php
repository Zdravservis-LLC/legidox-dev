<?php
$arModuleFileList = Array(
    [
        "src" => __DIR__ . "/static/",
        "dst" => $_SERVER['DOCUMENT_ROOT']
    ],
    [
        "src" => __DIR__ . "/overrides/",
        "dst" => $_SERVER['DOCUMENT_ROOT'] . '/local/'
    ]
);

$arModuleCleaningList = Array(
    [
        "desc" => "Папка с файлами публичных страниц",
        "dir" => "/legidox/"
    ],
    [
        "desc" => "Оверрайд шаблона disk.common",
        "dir" => "/local/templates/.default/components/bitrix/disk.common/legidox/"
    ],
    [
        "desc" => "Оверрайд шаблона disk.folder.list",
        "dir" => "/local/templates/.default/components/bitrix/disk.folder.list/legidox/"
    ],
    [
        "desc" => "Оверрайд окна просмотра файлов",
        "dir" => "/local/templates/.default/components/bitrix/disk.file.view/legidox/"
    ]
);