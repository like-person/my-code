<?

//Галлямов Д.Р. like-person@mail.ru, icq: 222-811-798
//Скрипт управления процессами, исполнителями по процессу и шаблонами заданий для исполнителей по процессу

@$op1 = $_GET['op1'];
@$id = (int) $_GET['id'];
$type = 14;
$color = '';

switch ($op1) {
    case 'isp_add':
        /* Добавление исполнителя (for ajax) */
        $in = (int) $_GET['isp'];
        if ($id > 0) {
            $isp = '';
            list($inpt) = $database->getArrayOfQuery("select rubric_ex from cprice_rubric where ID_RUBRIC=" . $id);
            if (!empty($inpt)) {
                $list = explode("&", $inpt);
                if (isset($_GET['ctrl']))
                    $isps = explode("|", @$list[1]);
                else
                    $isps = explode("|", $list[0]);
                if ($in > 0) {
                    $isps[] = $in;
                    $isp = implode("|", $isps);
                } else {
                    $new_arr = array();
                    foreach ($isps as $item)
                        if ($item != abs($in))
                            $new_arr[] = $item;
                    $isp = implode("|", $new_arr);
                }
                if (isset($_GET['ctrl']))
                    $isp = $list[0] . '&' . $isp;
                else
                    $isp = $isp . '&' . @$list[1];
            }elseif ($in > 0) {
                if (isset($_GET['ctrl']))
                    $isp = '&' . $in;
                else
                    $isp = $in . '&';
            }
            $database->query("UPDATE cprice_rubric set rubric_ex='$isp' WHERE ID_RUBRIC=" . $id);
            die();
        }
        break;
    case 'list':
        /* Данные по процессу (for ajax) */
        if ($id > 0) {
            list($inpt) = $database->getArrayOfQuery("select rubric_ex from cprice_rubric where ID_RUBRIC=" . $id);
            $list_isps = '';
            $list_ctrl = '';
            $list = explode("&", $inpt);
            $isps = explode("|", $list[0]);
            foreach ($isps as $isp) {
                if ($isp > 0) {
                    $name = getFeatData(916, $isp);
                    $list_isps .= $name . '; ';
                }
            }
            print $list_isps . '|';
            if (isset($list[1])) {
                $isps = explode("|", $list[1]);
                foreach ($isps as $isp) {
                    if ($isp > 0) {
                        $name = getFeatData(916, $isp);
                        $list_ctrl .= $name . '; ';
                    }
                }
            }
            print $list_ctrl . '|';
            $data = getData($id, "", "", array(955, 956));
            $opis = '';
            $comm = '';
            foreach ($data as $gid => $vals) {
                if (!empty($vals[955]))
                    $comm .= '<div><b>' . $vals[955] . ':</b> ' . nl2br($vals[956]) . '</div>';
                else
                    $opis .= nl2br($vals[956]) . '<br/>';
            }
            print '<div class="opis">' . $opis . '</div>';
            print $comm;
            die();
        }
        break;
    case 'shabls':
        /* Шаблоны (for ajax) */
        if ($id > 0) {
            combase();
            $res = $database->query("select * from templateTasks where proces='$id' && deleted=0");
            while ($row = mysql_fetch_array($res)) {
                print '<tr class="dt"><td>' . nl2br($row['text_task']) . '</td><td>' . $row['priority'] . '</td><td>' . $row['interval_work'] . '</td>' .
                        '<td><a href="#' . $row['id'] . '"><img src="../engine/data/system_skin/images/b_edit.gif" alt="ред." title="редактировать" /></a> <a href="#" onclick="delsh(this,' . $row['id'] . ');return false;"><img src="../engine/data/system_skin/images/b_delete.gif" alt="удал." title="Удалить" /></a></td></tr>';
            }
            die();
        }
        break;
    case 'delsh':
        /* Удалить шаблон (for ajax) */
        if ($id > 0) {
            combase();
            $res = $database->query("update templateTasks set deleted=1 where id='$id'", false);
            die();
        }
        break;
    case 'add (for ajax)':
        /* Добавление комментария к процессу */
        $msg = iconv("UTF-8", "WINDOWS-1251", $_POST['msg']);
        if (!empty($_POST['auth']))
            $auth = iconv("UTF-8", "WINDOWS-1251", $_POST['auth']);
        if ($id > 0 && !empty($msg)) {
            $data = array();
            if (!empty($auth))
                $data[955] = $auth;
            $data[956] = $msg;
            insertData($id, $data);
            $msg = stripslashes($msg);
            print (!empty($auth) ? '<div><b>' . $auth . ':</b> ' . nl2br($msg) . '</div>' : nl2br($msg) . '<br/>');
            die();
        }
        break;
    case 'addsh (for ajax)':
        /* Добавление шаблона */
        $msg = iconv("UTF-8", "WINDOWS-1251", $_POST['msg']);
        $prior = iconv("UTF-8", "WINDOWS-1251", $_POST['prior']);
        $interv = iconv("UTF-8", "WINDOWS-1251", $_POST['interv']);
        if ($id > 0 && !empty($msg) && !empty($prior) && !empty($interv)) {
            combase();
            $database->query("INSERT INTO templateTasks (proces, text_task, priority, interval_work) values ('$id','$msg','$prior','$interv')");
            $id = $database->id();
            $msg = stripslashes($msg);
            print '<tr class="dt"><td>' . nl2br($msg) . '</td><td>' . $prior . '</td><td>' . $interv . '</td><td>' .
                    '<a href="#' . $id . '"><img src="../engine/data/system_skin/images/b_edit.gif" alt="ред." title="редактировать" /></a> <a class="del" href="#" onclick="delsh(this,' . $id . ');return false;"><img src="../engine/data/system_skin/images/b_delete.gif" alt="удал." title="Удалить" /></a></td></tr>';
            die();
        }
        break;
    default:
        /* Список процессов */
        print '<h2>Технологическая карта процессов</h2>';

        function print_rub($pid = 0, $i = 0) {
            global $database, $type;
            $i++;
            $res = $database->query("select ID_RUBRIC,rubric_name from cprice_rubric where rubric_deleted=0 && rubric_visible=1 && rubric_parent=$pid  && rubric_type=" . $type . " order by rubric_pos,rubric_name");
            $out = '';
            while ($row = mysql_fetch_array($res)) {
                $childs = print_rub($row[0], $i);
                $opis = '';
                if (empty($childs)) {
                    $data = getData($row[0], "1", "", array(956), true);
                    if (count($data) == 0)
                        $opis = ' <span>&lt;без описания&gt;</span>';
                }
                $out .= '<div class="head' . (empty($childs) ? ' ch' : ' hd') . '">' . (empty($childs) ? ' <a href="#" id="l' . $row[0] . '" title="' . (empty($opis) ? 'Просмотреть описание' : 'Добавить описание или комментарий') . '" class="com">' . $row[1] . $opis . '</a> <a href="#" id="sh' . $row[0] . '" title="Шаблоны заданий по процессу" class="shabl">шабл.</a>' : $row[1]) . '</div>';
                $out .= $childs;
            }
            if (!empty($out))
                $out = '<div class="rt r' . ($i - 1) . '">' . $out . '</div>' . "\r\n";
            return $out;
        }

        print print_rub();
        if ($_USER['id'] > 0) {
            combase();
            $line_u = $database->getArrayOfQuery("select user_name, user_sname from " . DB_PREFIX . "users WHERE ID_USER='" . $_USER['id'] . "' && user_deleted=0");
            $user = $line_u[0] . ' ' . $line_u[1];
            otherbase(5);
        } else
            $user = 'Администратор';
        $orderby = '';
        $limit = '';
        $features = array(916);
        $fvalues = false;
        $uslovia = array();
        $data = array();
        if (count($features) == 0) {
            $res_feat = $database->query("
				SELECT " . DB_PREFIX . "features.ID_FEATURE
				FROM " . DB_PREFIX . "features NATURAL JOIN " . DB_PREFIX . "rubric_features
				WHERE ID_RUBRIC=361 and feature_deleted=0
				ORDER BY rubricfeature_pos
			");
            while (list($feature_id) = mysql_fetch_array($res_feat)) {
                $features[] = $feature_id;
            }
        }
        $add_tbl = '';
        $add_sql = '';
        if (count($uslovia) > 0) {
            $add_tbl = 'natural join cprice_goods_features';
            $add_sql = " && (";
            foreach ($uslovia as $fid => $fval) {
                $add_sql .= "(ID_FEATURE='" . $fid . "' && goodfeature_value='" . $fval . "') || ";
            }
            $add_sql = substr($add_sql, 0, -4) . ")";
        }
        $res = $database->query("
			SELECT ID_GOOD, good_url
			FROM " . DB_PREFIX . "rubric_goods NATURAL JOIN " . DB_PREFIX . "goods $add_tbl
			WHERE ID_RUBRIC=361 && good_deleted=0 " . $add_sql
                . (empty($orderby) ? " ORDER BY rubricgood_pos, ID_GOOD" : " ORDER BY " . $orderby)
                . (empty($limit) ? "" : " LIMIT " . $limit)
        );

        while (list($good_id, $url) = mysql_fetch_array($res)) {
            $data[$good_id]['url'] = $url;
            foreach ($features as $feature_id) {
                if ($feature_id > 0) {
                    if ($fvalues)
                        $data[$good_id][$feature_id] = getFeatureValue($good_id, $feature_id);
                    else
                        $data[$good_id][$feature_id] = getFeatureText($good_id, $feature_id, false, true);
                }
            }
        }

        function cmp($a, $b) {
            if ($a[916] == $b[916]) {
                return 0;
            }
            return ($a[916] < $b[916]) ? -1 : 1;
        }

        uksort($data, "cmp");
        $dolz = '';
        foreach ($data as $gid => $vals)
            $dolz.='<option value="' . $gid . '">' . $vals[916] . '</option>';
        $jss = <<<TXT
var user='$user';
function delsh(link,id)
{
	if(confirm('Удалить шаблон задания?'))
	{
		$.ajax({
		   	url: '?pg=proces&op1=delsh&id='+id,
			success: function(msg){
				if(msg!='')alert(msg);
				$(link).closest("tr").remove();
			}
	   	 });
	}
	return false;
}
$(document).ready(function() {
	$(".rt div.rt").hide();
	$(".head").click(function() {
		 $(this).next(".rt").slideToggle("slow");
	    $(this).toggleClass("active");
	});
	$("#show_all").click(function() {		$(".rt").show();	});
	$("a.com").click(function() {
		var div = $(this).closest(".head");
		var id = $(this).attr("id").substr(1);
		if(div.find(".comment").text()!='')
		{
			div.find("div").remove();
			div.find("hr").remove();
		}
		else
		{
			div.find("div").remove();
			div.find("hr").remove();

			var link = $(this);
			$.ajax({
			   	url: '?pg=proces&op1=list&id='+id,
				success: function(msg){
					arr = msg.split("|");
		   	 		div.append('<div id="com'+id+'" class="comment">'+
		   	 			'<div class="isp"><b>Исполнители:</b> <span>'+arr[0]+'</span> <select class="isps" name="dolz" onchange="add_dolz('+id+',this,0);"><option value="0"></option><optgroup label="Выберите, чтобы добавить:" class="add">$dolz</optgroup><optgroup label="Выберите, чтобы удалить:" class="del"></optgroup></select></div>'+
		   	 			'<div class="isp"><b>Контроль:</b> <span>'+arr[1]+'</span> <select class="ctrls" name="dolz" onchange="add_dolz('+id+',this,1);"><option value="0"></option><optgroup label="Выберите, чтобы добавить:" class="add">$dolz</optgroup><optgroup label="Выберите, чтобы удалить:" class="del"></optgroup></select></div>'+
		   	 			arr[2]+'</div><div class="form" id="frm'+id+'"><textarea name="txt" cols="45" rows="5"></textarea><br/><input type="button" value="добавить комментарий" onclick="add('+id+',true);" /><input type="button" value="добавить описание" onclick="add('+id+',false);" /></div>');
			        list = arr[0].split("; ");
			        $.each(list, function(index, value) {
					  if(value!="")
					  {
					  	val = $("#com"+id+" select.isps option:contains('"+value+"')").val();
					  	$("#com"+id+" select.isps option:contains('"+value+"')").remove();
					  	$("#com"+id+" select.isps optgroup.del").append( $('<option value="-'+val+'">'+value+'</option>'));
					  }
					});
			        list = arr[1].split("; ");
			        $.each(list, function(index, value) {
					  if(value!="")
					  {
					  	val = $("#com"+id+" select.ctrls option:contains('"+value+"')").val();
					  	$("#com"+id+" select.ctrls option:contains('"+value+"')").remove();
					  	$("#com"+id+" select.ctrls optgroup.del").append( $('<option value="-'+val+'">'+value+'</option>'));
					  }
					});
				}
		   	 });
		}
		return false;
	});
	$("a.shabl").click(function() {
		var id = $(this).attr("id").substr(2);
		var div = $(this).closest(".head");
		if(div.find(".shabls").text()!='')
		{
			div.find("div").remove();
			div.find("hr").remove();
		}
		else
		{
			div.find("div").remove();
			div.find("hr").remove();
			$.ajax({
			   	url: '?pg=proces&op1=shabls&id='+id,
				success: function(msg){
					div.append('<div id="shabl'+id+'" class="shabls"><table class="list"><tr><th>Шаблон задания</th><th>Приоритет</th><th>Время</th><th>Действие</th></tr>'+
						msg+
						'<tr class="addform"><td><textarea name="txt" cols="45" rows="5"></textarea></td>'+
						'<td><input type="text" name="prior" value="" size="5" /></td>'+
						'<td><input type="text" name="interv" value="0.00" size="5" /></td>'+
						'<td><input type="button" value="добавить" onclick="addsh('+id+');" /></td><tr></table></div>');
				}
		   	 });
		}
	    return false;
	});
});
function add_dolz(id,select,ctrl)
{
	var div = $(select).closest("div.isp");
	var val = $(select).val();
	var url_ctr = '';
	if(ctrl>0)url_ctr ='&ctrl=1';
	if(val!=0)
	{
		$.ajax({
		   	url: '?pg=proces&op1=isp_add&id='+id+'&isp='+val+url_ctr,
			success: function(){
				var name = $(select).find("option:selected").text();
				$(select).find("option:selected").remove();
				if(val>0)
				{
					div.find("span").append(name+'; ');
				  	$(select).find("optgroup.del").append( $('<option value="-'+val+'">'+name+'</option>'));
				}
				else
				{
					$(select).find("optgroup.add").append( $('<option value="'+Math.abs(val)+'">'+name+'</option>'));
					div.find("span").empty();
					var isp = '';
					$(select).find("optgroup.del option").each(function() {isp = isp + this.text+'; ';});
					div.find("span").html(isp);
				}
				$(select).find("option:first").attr("selected", "selected");
			}
	   	 });
	}
}
function add(id,comment)
{
	var text = $("#frm"+id+" textarea").val();
   	if(text!='')
   	{
   		$("#frm"+id+" textarea").val("");
   		if(comment) datas = 'auth='+encodeURIComponent(user)+'&msg='+encodeURIComponent(text);
   		else datas = 'msg='+encodeURIComponent(text);
		$.ajax({
		   	url: '?pg=proces&op1=add&id='+id,
		   	type: "POST",
		   	data: datas,
			success: function(msg){
				if(comment)	$("#com"+id).append(msg);
				else $("#com"+id+" .opis").append(msg);
				$("#l"+id+" span").remove();
			}
	   	 });
   	}
}
function addsh(id)
{
   	var text = $("#shabl"+id+" textarea").val();
   	var prior = $("#shabl"+id+" input:text[name=prior]").val();
   	var interv = $("#shabl"+id+" input:text[name=interv]").val();
   	if(text!='')
   	{
   		$("#shabl"+id+" textarea").val("");
   		datas = 'msg='+encodeURIComponent(text)+'&prior='+encodeURIComponent(prior)+'&interv='+encodeURIComponent(interv);
		$.ajax({
		   	url: '?pg=proces&op1=addsh&id='+id,
		   	type: "POST",
		   	data: datas,
			success: function(msg){
				$("#shabl"+id+" table tr.addform").before(msg);
			}
	   	 });
   	}
   	return false;
}
TXT;
        teAddJSScript($jss);
        $css = <<<TXT
.r0 .head {font:bold 16px Verdana;color:#000;}
.r1 .head {font:bold 14px Verdana;color:#000080}
.r2 .head {font:bold 12px Verdana;color:#000;}
.r3 .head {font:12px Verdana;color:#000}
.rt {margin:5px 0 5px 20px}
.rt a {text-decoration:none;font:inherit;color:inherit;}
.rt a.com span {color:#CC0000;}
.rt a.shabl {color:#00f;}
div.shabls {width:500px;}
.rt hr {margin-bottom:5px;}
.hd {cursor:pointer;}
#show_all {margin-top:30px;cursor:pointer;}
.form {text-align:right;width:330px;}
.form textarea {width:330px;font-size:12px;}
.comment {font-weight:normal;color:#5C814B;font-size:12px;}
.comment div {margin-bottom:5px;}
.isp {color:#FF8000;}
TXT;
        teAddCSSCode($css);

        print '<div id="show_all">Отобразить все процессы</div>';
        break;
}
combase();
?>