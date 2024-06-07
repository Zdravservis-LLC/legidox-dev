<?php
\Bitrix\Main\Loader::registerAutoLoadClasses(
    'legidox',
    [
        '\LEGIDOX\CLegidoxCore' => 'lib/CLegidoxCore.php',
        '\LEGIDOX\CModuleOptions' => 'lib/CModuleOptions.php'
    ]
);