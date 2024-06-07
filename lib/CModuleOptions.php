<?php

namespace LEGIDOX;

use CAdminFileDialog;
use CAdminTabControl;
use COption;

/**
 * CModuleoptions class by Unnamed author 104863
 * https://dev.1c-bitrix.ru/community/webdev/user/104863/blog/5296/
 * generates option pages for custom modules
 *
 */
class CModuleOptions
{
    public $arCurOptionValues = array();

    private $module_id = '';
    private $arTabs = array();
    private $arGroups = array();
    private $arOptions = array();
    private $need_access_tab = false;

    public function __construct($module_id, $arTabs, $arGroups, $arOptions, $need_access_tab = false)
    {
        $this->module_id = $module_id;
        $this->arTabs = $arTabs;
        $this->arGroups = $arGroups;
        $this->arOptions = $arOptions;
        $this->need_access_tab = $need_access_tab;

        if($need_access_tab)
            $this->arTabs[] = array(
                'DIV' => 'edit_access_tab',
                'TAB' => 'Права доступа',
                'ICON' => '',
                'TITLE' => 'Настройка прав доступа'
            );

        if($_REQUEST['update'] == 'Y' && check_bitrix_sessid())
            $this->SaveOptions();

        $this->GetCurOptionValues();
    }

    private function SaveOptions()
    {
        foreach($this->arOptions as $opt => $arOptParams)
        {
            if($arOptParams['TYPE'] != 'CUSTOM')
            {
                $val = $_REQUEST[$opt];

                if($arOptParams['TYPE'] == 'CHECKBOX' && $val != 'Y')
                    $val = 'N';
                elseif(is_array($val))
                    $val = serialize($val);

                COption::SetOptionString($this->module_id, $opt, $val);
            }
        }
    }

    private function GetCurOptionValues()
    {
        foreach($this->arOptions as $opt => $arOptParams)
        {
            if($arOptParams['TYPE'] != 'CUSTOM')
            {
                $this->arCurOptionValues[$opt] = COption::GetOptionString($this->module_id, $opt, $arOptParams['DEFAULT']);
                if(in_array($arOptParams['TYPE'], array('MSELECT')))
                    $this->arCurOptionValues[$opt] = unserialize($this->arCurOptionValues[$opt]);
            }
        }
    }

    public function ShowHTML()
    {
        global $APPLICATION;

        $arP = array();

        foreach($this->arGroups as $group_id => $group_params)
            $arP[$group_params['TAB']][$group_id] = array();

        if(is_array($this->arOptions))
        {
            foreach($this->arOptions as $option => $arOptParams)
            {
                $val = $this->arCurOptionValues[$option];

                if($arOptParams['SORT'] < 0 || !isset($arOptParams['SORT']))
                    $arOptParams['SORT'] = 0;

                $label = (isset($arOptParams['TITLE']) && $arOptParams['TITLE'] != '') ? $arOptParams['TITLE'] : '';
                $opt = htmlspecialchars($option);

                switch($arOptParams['TYPE'])
                {
                    case 'CHECKBOX':
                        $input = '<input type="checkbox" name="'.$opt.'" id="'.$opt.'" value="Y"'.($val == 'Y' ? ' checked' : '').' '.($arOptParams['REFRESH'] == 'Y' ? 'onclick="document.forms[\''.$this->module_id.'\'].submit();"' : '').' />';
                        break;
                    case 'TEXT':
                        if(!isset($arOptParams['COLS']))
                            $arOptParams['COLS'] = 25;
                        if(!isset($arOptParams['ROWS']))
                            $arOptParams['ROWS'] = 5;
                        $input = '<textarea rows="'.$type[1].'" cols="'.$arOptParams['COLS'].'" rows="'.$arOptParams['ROWS'].'" name="'.$opt.'">'.htmlspecialchars($val).'</textarea>';
                        if($arOptParams['REFRESH'] == 'Y')
                            $input .= '<input type="submit" name="refresh" value="OK" />';
                        break;
                    case 'SELECT':
                        $input = SelectBoxFromArray($opt, $arOptParams['VALUES'], $val, '', '', ($arOptParams['REFRESH'] == 'Y' ? true : false), ($arOptParams['REFRESH'] == 'Y' ? $this->module_id : ''));
                        if($arOptParams['REFRESH'] == 'Y')
                            $input .= '<input type="submit" name="refresh" value="OK" />';
                        break;
                    case 'MSELECT':
                        $input = SelectBoxMFromArray($opt.'[]', $arOptParams['VALUES'], $val);
                        if($arOptParams['REFRESH'] == 'Y')
                            $input .= '<input type="submit" name="refresh" value="OK" />';
                        break;
                    case 'COLORPICKER':
                        if(!isset($arOptParams['FIELD_SIZE']))
                            $arOptParams['FIELD_SIZE'] = 25;
                        ob_start();
                        echo 	'<input id="__CP_PARAM_'.$opt.'" name="'.$opt.'" size="'.$arOptParams['FIELD_SIZE'].'" value="'.htmlspecialchars($val).'" type="text" style="float: left;" '.($arOptParams['FIELD_READONLY'] == 'Y' ? 'readonly' : '').' />
								<script>
									function onSelect_'.$opt.'(color, objColorPicker)
									{
										var oInput = BX("__CP_PARAM_'.$opt.'");
										oInput.value = color;
									}
								</script>';
                        $APPLICATION->IncludeComponent('bitrix:main.colorpicker', '', Array(
                            'SHOW_BUTTON' => 'Y',
                            'ID' => $opt,
                            'NAME' => 'Выбор цвета',
                            'ONSELECT' => 'onSelect_'.$opt
                        ), false
                        );
                        $input = ob_get_clean();
                        if($arOptParams['REFRESH'] == 'Y')
                            $input .= '<input type="submit" name="refresh" value="OK" />';
                        break;
                    case 'FILE':
                        if(!isset($arOptParams['FIELD_SIZE']))
                            $arOptParams['FIELD_SIZE'] = 25;
                        if(!isset($arOptParams['BUTTON_TEXT']))
                            $arOptParams['BUTTON_TEXT'] = '...';
                        CAdminFileDialog::ShowScript(Array(
                            'event' => 'BX_FD_'.$opt,
                            'arResultDest' => Array('FUNCTION_NAME' => 'BX_FD_ONRESULT_'.$opt),
                            'arPath' => Array(),
                            'select' => 'F',
                            'operation' => 'O',
                            'showUploadTab' => true,
                            'showAddToMenuTab' => false,
                            'fileFilter' => '',
                            'allowAllFiles' => true,
                            'SaveConfig' => true
                        ));
                        $input = 	'<input id="__FD_PARAM_'.$opt.'" name="'.$opt.'" size="'.$arOptParams['FIELD_SIZE'].'" value="'.htmlspecialchars($val).'" type="text" style="float: left;" '.($arOptParams['FIELD_READONLY'] == 'Y' ? 'readonly' : '').' />
									<input value="'.$arOptParams['BUTTON_TEXT'].'" type="button" onclick="window.BX_FD_'.$opt.'();" />
									<script>
										setTimeout(function(){
											if (BX("bx_fd_input_'.strtolower($opt).'"))
												BX("bx_fd_input_'.strtolower($opt).'").onclick = window.BX_FD_'.$opt.';
										}, 200);
										window.BX_FD_ONRESULT_'.$opt.' = function(filename, filepath)
										{
											var oInput = BX("__FD_PARAM_'.$opt.'");
											if (typeof filename == "object")
												oInput.value = filename.src;
											else
												oInput.value = (filepath + "/" + filename).replace(/\/\//ig, \'/\');
										}
									</script>';
                        if($arOptParams['REFRESH'] == 'Y')
                            $input .= '<input type="submit" name="refresh" value="OK" />';
                        break;
                    case 'CUSTOM':
                        $input = $arOptParams['VALUE'];
                        break;
                    default:
                        if(!isset($arOptParams['SIZE']))
                            $arOptParams['SIZE'] = 25;
                        if(!isset($arOptParams['MAXLENGTH']))
                            $arOptParams['MAXLENGTH'] = 255;
                        $input = '<input type="'.($arOptParams['TYPE'] == 'INT' ? 'number' : 'text').'" size="'.$arOptParams['SIZE'].'" maxlength="'.$arOptParams['MAXLENGTH'].'" value="'.htmlspecialchars($val).'" name="'.htmlspecialchars($option).'" />';
                        if($arOptParams['REFRESH'] == 'Y')
                            $input .= '<input type="submit" name="refresh" value="OK" />';
                        break;
                }

                if(isset($arOptParams['NOTES']) && $arOptParams['NOTES'] != '')
                    $input .= 	'<div class="notes">
									<table cellspacing="0" cellpadding="0" border="0" class="notes">
										<tbody>
											<tr class="top">
												<td class="left"><div class="empty"></div></td>
												<td><div class="empty"></div></td>
												<td class="right"><div class="empty"></div></td>
											</tr>
											<tr>
												<td class="left"><div class="empty"></div></td>
												<td class="content">
													'.$arOptParams['NOTES'].'
												</td>
												<td class="right"><div class="empty"></div></td>
											</tr>
											<tr class="bottom">
												<td class="left"><div class="empty"></div></td>
												<td><div class="empty"></div></td>
												<td class="right"><div class="empty"></div></td>
											</tr>
										</tbody>
									</table>
								</div>';

                $arP[$this->arGroups[$arOptParams['GROUP']]['TAB']][$arOptParams['GROUP']]['OPTIONS'][] = $label != '' ? '<tr><td valign="top" width="40%">'.$label.'</td><td valign="top" nowrap>'.$input.'</td></tr>' : '<tr><td valign="top" colspan="2" align="center">'.$input.'</td></tr>';
                $arP[$this->arGroups[$arOptParams['GROUP']]['TAB']][$arOptParams['GROUP']]['OPTIONS_SORT'][] = $arOptParams['SORT'];
            }

            $tabControl = new CAdminTabControl('tabControl', $this->arTabs);
            $tabControl->Begin();
            echo '<form name="'.$this->module_id.'" method="POST" action="'.$APPLICATION->GetCurPage().'?mid='.$this->module_id.'&lang='.LANGUAGE_ID.'" enctype="multipart/form-data">'.bitrix_sessid_post();

            foreach($arP as $tab => $groups)
            {
                $tabControl->BeginNextTab();

                foreach($groups as $group_id => $group)
                {
                    if(sizeof($group['OPTIONS_SORT']) > 0)
                    {
                        echo '<tr class="heading"><td colspan="2">'.$this->arGroups[$group_id]['TITLE'].'</td></tr>';

                        array_multisort($group['OPTIONS_SORT'], $group['OPTIONS']);
                        foreach($group['OPTIONS'] as $opt)
                            echo $opt;
                    }
                }
            }

            if($this->need_access_tab)
            {
                $tabControl->BeginNextTab();
                $module_id = $this->module_id;
                require_once($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/admin/group_rights.php");
            }

            $tabControl->Buttons();

            echo 	'<input type="hidden" name="update" value="Y" />
					<input type="submit" name="save" value="Сохранить" />
					<input type="reset" name="reset" value="Отменить" />
					</form>';

            $tabControl->End();
        }
    }

    public static function test() {
        return "allons-ey!";
    }
}

/* Usage
$module_id = 'my_company_code.my_module_id';

require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/'.$module_id.'/include.php');
IncludeModuleLangFile($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/'.$module_id.'/options.php');

$showRightsTab = true;
$arSel = array('REFERENCE_ID' => array(1, 3, 5, 7), 'REFERENCE' => array('Значение 1', 'Значение 2', 'Значение 3', 'Значение 4'));

$arTabs = array(
   array(
      'DIV' => 'edit1',
      'TAB' => 'Настройки',
      'ICON' => '',
      'TITLE' => 'Настройки'
   )
);

$arGroups = array(
   'MAIN' => array('TITLE' => 'Имя группы', 'TAB' => 0)
);

$arOptions = array(
   'TEST_0' => array(
      'GROUP' => 'MAIN',
      'TITLE' => 'Строка',
      'TYPE' => 'STRING',
      'DEFAULT' => 'Значение по-умолчанию',
      'SORT' => '0',
      'NOTES' => 'Это подсказка к полю "Строка".'
   ),
   'TEST_1' => array(
      'GROUP' => 'MAIN',
      'TITLE' => 'Число',
      'TYPE' => 'INT',
      'DEFAULT' => '0',
      'SORT' => '1',
      'REFRESH' => 'Y',
      'NOTES' => 'Это подсказка к полю "Число". У данного поля установлен параметр REFRESH = "Y"'
   ),
   'TEST_2' => array(
      'GROUP' => 'MAIN',
      'TITLE' => 'Текст',
      'TYPE' => 'TEXT',
      'DEFAULT' => '',
      'SORT' => '2',
      'COLS' => 40,
      'ROWS' => 15,
      'NOTES' => 'Это подсказка к полю "Текст". У данного поля установлен параметр COLS = "40", ROWS = "15"'
   ),
   'TEST_2' => array(
      'GROUP' => 'MAIN',
      'TITLE' => 'Текст',
      'TYPE' => 'TEXT',
      'DEFAULT' => '',
      'SORT' => '2',
      'COLS' => 40,
      'ROWS' => 15,
      'NOTES' => 'Это подсказка к полю "Текст". У данного поля установлен параметр COLS = "40", ROWS = "15"'
   ),
   'TEST_3' => array(
      'GROUP' => 'MAIN',
      'TITLE' => 'Флажок',
      'TYPE' => 'CHECKBOX',
      'REFRESH' => 'Y',
      'SORT' => '3'
   ),
   'TEST_4' => array(
      'GROUP' => 'MAIN',
      'TITLE' => 'Список',
      'TYPE' => 'SELECT',
      'VALUES' => $arSel,
      'SORT' => '4'
   ),
   'TEST_5' => array(
      'GROUP' => 'MAIN',
      'TITLE' => 'Список с множественным выбором',
      'TYPE' => 'MSELECT',
      'VALUES' => $arSel,
      'SORT' => '5'
   ),
   'TEST_6' => array(
      'GROUP' => 'MAIN',
      'TITLE' => 'Файл',
      'TYPE' => 'FILE',
      'BUTTON_TEXT' => 'Выбери-ка файл',
      'SORT' => '6',
      'NOTES' => 'Это поле "Файл".'
   ),
   'TEST_7' => array(
      'GROUP' => 'MAIN',
      'TITLE' => 'Выбор цвета',
      'TYPE' => 'COLORPICKER',
      'SORT' => '7'
   ),
   'TEST_8' => array(
      'GROUP' => 'MAIN',
      'TITLE' => '',
      'TYPE' => 'CUSTOM',
      'VALUE' => '<span>Это текст в параметре <b>VALUE</b></span>',
      'SORT' => '8',
      'NOTES' => 'Настраиваемое поле без параметра TITLE'
   )
);

Конструктор класса CModuleOptions
$module_id - ID модуля
$arTabs - массив вкладок с параметрами
$arGroups - массив групп параметров
$arOptions - собственно сам массив, содержащий параметры
$showRightsTab - определяет надо ли показывать вкладку с настройками прав доступа к модулю ( true / false )


$opt = new CModuleOptions($module_id, $arTabs, $arGroups, $arOptions, $showRightsTab);
$opt->ShowHTML();

*/