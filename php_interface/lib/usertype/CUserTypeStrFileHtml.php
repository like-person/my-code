<?php

namespace lib\usertype;

use Bitrix\Main\Loader,
    Bitrix\Main\Localization\Loc,
    Bitrix\Iblock,
    Bitrix\Main\Page\Asset;

/**
 * Реализация кастомного типа свойства файл+строка+HTML/Text
 * Class CUserTypeStrFileHtml
 * @package lib\usertype
 */
class CUserTypeStrFileHtml
{
    /**
     * Метод возвращает массив описания собственного типа свойств
     * @return array
     */
    public static function GetUserTypeDescription()
    {
        return array(
            'USER_TYPE_ID' => 'user_strfilehtml', 
            'USER_TYPE' => 'STRFILEHTML',
            'CLASS_NAME' => __CLASS__,
            'DESCRIPTION' => 'Кастомное свойство файл+строка+HTML/Text',
            'PROPERTY_TYPE' => Iblock\PropertyTable::TYPE_STRING,
            'ConvertToDB' => [__CLASS__, 'ConvertToDB'],
            'ConvertFromDB' => [__CLASS__, 'ConvertFromDB'],
            'GetPropertyFieldHtml' => [__CLASS__, 'GetPropertyFieldHtml'],
            'GetPropertyFieldHtmlMulty' => [__CLASS__, 'GetPropertyFieldHtmlMulty'],
        );
    }
    /**
     * Представление формы редактирования множественного значения
     * @param $arUserField
     * @param $arHtmlControl
     */
    public static function GetPropertyFieldHtmlMulty($arProperty, $arValues, $strHTMLControlName)
	{
        $table_id = md5($strHTMLControlName["VALUE"]);
        $js = '
        <script type="module">
        ';
		foreach ($arValues as $intPropertyValueID => $arGroupValue)
		{
            $arValue = unserialize(htmlspecialcharsback($arGroupValue["VALUE"]));

			$return .= '<tr><td>';

			$return .= 'Строка:<br/><input type="text" name="'.htmlspecialcharsbx($strHTMLControlName["VALUE"]."[$intPropertyValueID][STRING]").'" size="'.$arProperty["COL_COUNT"].'" value="'.htmlspecialcharsEx($arValue["STRING"]).'" /><br/><br/>';
            $fileName = $fileSrc = '';
            if ($arValue["FILE"] > 0 && $fileArr = \CFile::GetFileArray($arValue["FILE"])) {
                $fileName = $fileArr['ORIGINAL_NAME'];
                $fileSrc = $fileArr['SRC'];
                $js .= '
                    let dt = new DataTransfer();
                    let response = await fetch(\''.$fileSrc.'\')

                    if (response.ok) {
                        let blob = await response.blob();
                        const file = new File([blob], \''.$fileName.'\')
                        dt.items.add(file)
                    } else {
                        console.log("Error HTTP: " + response.status);
                    }
                    document.getElementById(\'file'.$intPropertyValueID.'\').files = dt.files;
                    BX.fireEvent(BX(\'file'.$intPropertyValueID.'\'), \'change\');
                ';
            }
            $return .= 'Файл:<br/><input type="file" id="file'.$intPropertyValueID.'" name="'.htmlspecialcharsbx($strHTMLControlName["VALUE"]."[$intPropertyValueID][FILE]").'" /><br/><br/>';
            
            $return .= 'HTML/TEXT:<br/><textarea name="'.htmlspecialcharsbx($strHTMLControlName["VALUE"]."[$intPropertyValueID][TEXT]").'" cols="'.$arProperty["COL_COUNT"].'" rows="5">'.htmlspecialcharsEx($arValue["TEXT"]).'</textarea>';
			$return .= '</td></tr>';
		}
        $js .= '</script>';

		$return .= '<tr><td>';
        $return .= 'Строка:<br/><input type="text" name="'.htmlspecialcharsbx($strHTMLControlName["VALUE"]."[n0][STRING]").'" size="'.$arProperty["COL_COUNT"].'" value="" /><br/><br/>';
        $return .= 'Файл:<br/><input type="file" name="'.htmlspecialcharsbx($strHTMLControlName["VALUE"]."[n0][FILE]").'" value="" /><br/><br/>';
        $return .= 'HTML/TEXT:<br/><textarea name="'.htmlspecialcharsbx($strHTMLControlName["VALUE"]."[n0][TEXT]").'" cols="'.$arProperty["COL_COUNT"].'" rows="5"></textarea>';
		$return .= '</td></tr>';

		$return .= '<tr><td><input type="button" value="Добавить" onClick="BX.IBlock.Tools.addNewRow(\'tb'.$table_id.'\')"></td></tr>';
        Asset::getInstance()->addString($js);
        return $return;
	}
    /**
     * Конвертация данных перед сохранением в БД
     * @param $arProperty
     * @param $value
     * @return mixed
     */
    public static function ConvertToDB($arProperty, $value)
    {
		$return = array();
		if(isset($value["VALUE"]["STRING"]))
		{
			if ($value['VALUE']['STRING'] != '' || $value['VALUE']['FILE']!='' || $value['VALUE']['TEXT']!='')
            {
                try {
                    if (is_array($value['VALUE']['FILE']) && $value['VALUE']['FILE']['size'] > 0) {
                        $fid = \CFile::SaveFile($value['VALUE']['FILE'], "iblock");
                        $value['VALUE']['FILE'] = $fid;
                    }
                    $return['VALUE'] = base64_encode(serialize($value['VALUE']));
                } catch(Bitrix\Main\ObjectException $exception) {
                    echo $exception->getMessage();
                }
            }
		}
		

        return $return;
    }

    /**
     * Конвертируем данные при извлечении из БД
     * @param $arProperty
     * @param $value
     * @param string $format
     * @return mixed
     */
    public static function ConvertFromDB($arProperty, $value, $format = '')
    {
        if ($value['VALUE'] != '')
        {
            try {
                $value['VALUE'] = base64_decode($value['VALUE']);
            } catch(Bitrix\Main\ObjectException $exception) {
                echo $exception->getMessage();
            }
        }

        return $value;
    }
}