<?php

$_LEGIDOX_IBLOCK_STRUCTURE = [
    [
        'MODE' => 'TYPE',
        'STRUCTURE' => [
            'ID' => 'LEGIDOX_DOC',
            'SECTIONS' => 'N',
            'IN_RSS' => 'N',
            'SORT' => 100,
            'LANG' => array(
                'ru' => array(
                    'NAME' => 'Документы LegiDox',
                    'SECTION_NAME' => '',
                    'ELEMENT_NAME' => ''
                ),
                'en' => array(
                    'NAME' => 'LegiDox documents',
                    'SECTION_NAME' => '',
                    'ELEMENT_NAME' => ''
                )
            )
        ]
    ],
    [
        'MODE' => 'TYPE',
        'STRUCTURE' => [
            'ID' => 'LEGIDOX_TAG',
            'SECTIONS' => 'N',
            'IN_RSS' => 'N',
            'SORT' => 100,
            'LANG' => array(
                'ru' => array(
                    'NAME' => 'Теги LegiDox',
                    'SECTION_NAME' => '',
                    'ELEMENT_NAME' => ''
                ),
                'en' => array(
                    'NAME' => 'LegiDox tags',
                    'SECTION_NAME' => '',
                    'ELEMENT_NAME' => ''
                )
            )
        ]
    ],
    [
        'MODE' => 'IBLOCK',
        'STRUCTURE' => [
            'LID' => "s1",
            'CODE' => 'LD_FILES',
            'API_CODE' => 'XLDFILES',
            'REST_ON' => "Y",
            'IBLOCK_TYPE_ID' => 'LEGIDOX_DOC',
            'NAME' => 'Файловая запись',
            'ACTIVE' => 'Y',
            'RSS_ACTIVE' => 'N',
            'INDEX_ELEMENT' => 'Y',
            'INDEX_SECTION' => 'Y',
            'SITE_ID' => 's1',
            'GROUP_ID' => [
                '2' => 'R',
                '1' => 'X',
            ],
            'PROPERTIES' => [
                'LD_FILE_ID' => [
                    'CODE' => 'LD_FILE_ID',
                    'XML_ID' => 'X_LD_FILE_ID',
                    'NAME' => 'ИД файла',
                    'ACTIVE' => 'Y',
                    'PROPERTY_TYPE' => 'N',
                    'MULTIPLE' => 'N',
                    'SORT' => '700',
                ],
                'LD_TAG_ID' => [
                    'CODE' => 'LD_TAG_ID',
                    'XML_ID' => 'X_LD_TAG_ID',
                    'NAME' => 'Теги',
                    'ACTIVE' => 'Y',
                    'PROPERTY_TYPE' => 'N',
                    'MULTIPLE' => 'Y',
                    'SORT' => '700',
                ],
                'LD_DOC_WATCHERS' => [
                    'CODE' => 'LD_DOC_WATCHERS',
                    'XML_ID' => 'X_LD_DOC_WATCHERS',
                    'NAME' => 'Наблюдатели',
                    'ACTIVE' => 'Y',
                    'PROPERTY_TYPE' => 'S',
                    'MULTIPLE' => 'Y',
                    'SORT' => '700',
                ],
                'LD_DOC_OWNER' => [
                    'CODE' => 'LD_DOC_OWNER',
                    'XML_ID' => 'X_LD_DOC_OWNER',
                    'NAME' => 'Владелец документа',
                    'ACTIVE' => 'Y',
                    'PROPERTY_TYPE' => 'S',
                    'USER_TYPE' => 'UserID',
                    'MULTIPLE' => 'N',
                    'SORT' => '700',
                ],
                'LD_NOTIFY_HEADS' => [
                    'CODE' => 'LD_NOTIFY_HEADS',
                    'XML_ID' => 'X_LD_NOTIFY_HEADS',
                    'NAME' => 'Оповещать всех руководителей',
                    'ACTIVE' => 'Y',
                    'PROPERTY_TYPE' => 'N',
                    'MULTIPLE' => 'N',
                    'SORT' => '700',
                ],
                'LD_DOC_APPROVERS' => [
                    'CODE' => 'LD_DOC_APPROVERS',
                    'XML_ID' => 'X_LD_DOC_APPROVERS',
                    'NAME' => 'Утверждают',
                    'ACTIVE' => 'Y',
                    'PROPERTY_TYPE' => 'S',
                    'MULTIPLE' => 'Y',
                    'SORT' => '700',
                ],
                'LD_DOC_APPROVE_SERIALIZED' => [
                    'CODE' => 'LD_DOC_APPROVE_SERIALIZED',
                    'XML_ID' => 'X_LD_DOC_APPROVE_SERIALIZED',
                    'NAME' => 'Таблица утверждения',
                    'ACTIVE' => 'Y',
                    'PROPERTY_TYPE' => 'S',
                    'MULTIPLE' => 'N',
                    'SORT' => '700',
                ],
                'LD_DOC_APPROVE_DEADLINE' => [
                    'CODE' => 'LD_DOC_APPROVE_DEADLINE',
                    'XML_ID' => 'X_LD_DOC_APPROVE_DEADLINE',
                    'NAME' => 'Крайний срок для верификации',
                    'ACTIVE' => 'Y',
                    'PROPERTY_TYPE' => 'S',
                    'USER_TYPE' => 'Date',
                    'MULTIPLE' => 'N',
                    'SORT' => '700',
                ],
                'LD_DOC_EXPIREDATE' => [
                    'CODE' => 'LD_DOC_EXPIREDATE',
                    'XML_ID' => 'X_LD_DOC_EXPIREDATE',
                    'NAME' => 'Срок действия документа',
                    'ACTIVE' => 'Y',
                    'PROPERTY_TYPE' => 'S',
                    'USER_TYPE' => 'Date',
                    'MULTIPLE' => 'N',
                    'SORT' => '700',
                ],
                'LD_DOC_VERIFIED' => [
                    'CODE' => 'LD_DOC_VERIFIED',
                    'XML_ID' => 'X_LD_DOC_VERIFIED',
                    'NAME' => 'Документ верифицирован',
                    'ACTIVE' => 'Y',
                    'PROPERTY_TYPE' => 'N',
                    'MULTIPLE' => 'N',
                    'SORT' => '700',
                ],
                'LD_DOC_PUBLISHED' => [
                    'CODE' => 'LD_DOC_PUBLISHED',
                    'XML_ID' => 'X_LD_DOC_PUBLISHED',
                    'NAME' => 'Документ опубликован',
                    'ACTIVE' => 'Y',
                    'PROPERTY_TYPE' => 'N',
                    'MULTIPLE' => 'N',
                    'SORT' => '700',
                ],
            ]
        ]
    ],
    [
        'MODE' => 'IBLOCK',
        'STRUCTURE' => [
            'LID' => "s1",
            'CODE' => 'LD_TAG_COMPANY',
            'API_CODE' => 'XLDCOMPANY',
            'SORT' => "100",
            'REST_ON' => "Y",
            'IBLOCK_TYPE_ID' => 'LEGIDOX_TAG',
            'NAME' => 'Юрлицо',
            'ACTIVE' => 'Y',
            'RSS_ACTIVE' => 'N',
            'INDEX_ELEMENT' => 'Y',
            'INDEX_SECTION' => 'Y',
            'SITE_ID' => 's1',
            'GROUP_ID' => [
                '2' => 'R',
                '1' => 'X',
            ],
            'PROPERTIES' => [
            ]
        ]
    ],
    [
        'MODE' => 'IBLOCK',
        'STRUCTURE' => [
            'LID' => "s1",
            'CODE' => 'LD_TAG_BPROCESS',
            'API_CODE' => 'XLDBPROCESS',
            'SORT' => "200",
            'REST_ON' => "Y",
            'IBLOCK_TYPE_ID' => 'LEGIDOX_TAG',
            'NAME' => 'Бизнес-процесс',
            'ACTIVE' => 'Y',
            'RSS_ACTIVE' => 'N',
            'INDEX_ELEMENT' => 'Y',
            'INDEX_SECTION' => 'Y',
            'SITE_ID' => 's1',
            'GROUP_ID' => [
                '2' => 'R',
                '1' => 'X',
            ],
            'PROPERTIES' => [
            ]
        ]
    ],
    [
        'MODE' => 'IBLOCK',
        'STRUCTURE' => [
            'LID' => "s1",
            'CODE' => 'LD_TAG_DOCTYPE',
            'API_CODE' => 'XLDDOCTYPE',
            'SORT' => "300",
            'REST_ON' => "Y",
            'IBLOCK_TYPE_ID' => 'LEGIDOX_TAG',
            'NAME' => 'Тип документа',
            'ACTIVE' => 'Y',
            'RSS_ACTIVE' => 'N',
            'INDEX_ELEMENT' => 'Y',
            'INDEX_SECTION' => 'Y',
            'SITE_ID' => 's1',
            'GROUP_ID' => [
                '2' => 'R',
                '1' => 'X',
            ],
            'PROPERTIES' => [
            ]
        ]
    ]
];