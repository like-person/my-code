<?
// Программирование без использования фреймворка
/*Скрипт переноса товаров в новый каталог, приведение к новой версии*/
$type=$_REQUEST['type'];
$type_new = 8;
$rubric_old=$_REQUEST['rubric_old'];
print "<div align=\"center\">";
print "<h2>Приведение к новому рубрикатору ver3</h2>";

/*Рекурсивная функция формирования списка рубрик*/
function rec_rubs($type, $rubric_id=0, $rname_old='')
{
	global $database;
	$res = $database->query("SELECT ID_RUBRIC, rubric_name FROM ".DB_PREFIX."rubric WHERE rubric_visible=1 and rubric_deleted=0 and rubric_type=$type && rubric_parent=".$rubric_id." order by rubric_pos");
	if( !mysql_num_rows($res) ) return '<label style="display:block"><input type="radio" name="rubric_new" value="'.$rubric_id.'" /> '.$rname_old.'</label> ';
	$rubrics = '';
	while(list($rid,$rname)=mysql_fetch_array($res)){
		$rubrics .= rec_rubs($type, $rid, (!empty($rname_old)?$rname_old.' / ':'').$rname);
	}
	return $rubrics;
}

if( empty($_POST['setrubrics']) )
{
	#Шаг 1. Форма выбора товаров и рубрик
	print "<h2>Шаг-1 Выбор товаров из рубрики " . getRubricName($rubric_old) . " для перемещения</h2><br/>";
	print "<font color='green'>Выберите товары для перемещения</font><br/>";
	print "<form method=\"POST\">";
	print "<table><tr>";
	$iii=0;
	$res = $database->query("SELECT ID_RUBRIC, rubric_name FROM ".DB_PREFIX."rubric WHERE rubric_visible=1 and rubric_deleted=0 and rubric_type=$type && rubric_parent=".$rubric_old);
	$rubrics = '';
	while(list($rid,$rname)=mysql_fetch_array($res)){
		$goods = array();
		$res_good = $database->query("select ID_GOOD FROM cprice_goods natural join cprice_rubric_goods where ID_RUBRIC=$rid && good_visible=1 && good_deleted=0");
		while ($row_good = mysql_fetch_array($res_good)) {
			$goods[] = $row_good[0];
		}		
		if( count($goods)>0 ) $rubrics .= '<label style="display:block"><input type="checkbox" name="rubrics['.$rid.']" value="'.implode("|", $goods).'" checked /> '.$rname.'</label> ';
	}
	print "<td valign='top'><div>".$rubrics."</div></td>";
	$res = $database->query("SELECT ID_RUBRIC_TYPE,rubrictype_name FROM ".DB_PREFIX."rubric_types WHERE rubrictype_visible=1 and rubrictype_deleted=0");
	$ss ="<div>Куда переместить в новом каталоге?</div>";
	$ss .= "<div>".rec_rubs($type_new)."</div>";
	print "<td valign='top'>".$ss."</td>";
	print "</tr></table>";
	print "<input type='hidden' name='rubric_old' value='$rubric_old'>";
	//Рубрики выбраны -да
	print "<input type='hidden' name='setrubrics' value='1'>";
	//Закладка
	print "<input type='hidden' name='type' value='$type'>";
	print "<input type='submit' value='Далее'>";
	print "</form>";
}
else
{
	# Шаг 2. Перемещаем выбранные товары
	$rubric_new = intval($_REQUEST['rubric_new']);
        $rubrics = $_POST['rubrics'];
    	if(count($rubrics)>0 && $rubric_new>0)
        {
                foreach($rubrics as $rub_id=>$goods)
                {
			$arr_goods = explode("|", $goods);
			$database->query("update cprice_rubric_goods set ID_RUBRIC=$rubric_new where ID_RUBRIC=".$rub_id." && ID_GOOD IN (".implode(",", $arr_goods).")", false);
			$id_good_main = 0;
			foreach ($arr_goods as $good_id) {
				if($id_good_main>0)
				{
					$database->query("update cprice_goods set id_group=$id_good_main where ID_GOOD=".$good_id);
				}else 
				{
					$id_good_main = $good_id;					
				}
			}
                }
	}
	teRedirect("?pg=rubric&type=$type&rubric_id=".$rubric_old);
}
print "</div>";
?>