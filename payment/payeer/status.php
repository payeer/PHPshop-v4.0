<?php
function UpdateNumOrder($uid)
{
	$last_num = substr($uid, -2);
	$total = strlen($uid);
	$ferst_num = substr($uid, 0, ($total-2));
	return $ferst_num . "-" . $last_num;
}

$SysValue = parse_ini_file("../../phpshop/inc/config.ini", 1);

while(list($section,$array) = each($SysValue))
{
	while(list($key,$value) = each($array))
	{
		$SysValue['other'][chr(73) . chr(110) . chr(105) . ucfirst(strtolower($section)) . ucfirst(strtolower($key))] = $value;
	}
}

if (isset($_POST["m_operation_id"]) && isset($_POST["m_sign"]))
{
	$m_amount = $_POST["m_amount"];

	$m_orderid = $_POST["m_orderid"];
	
	$m_sign = $_POST["m_sign"];

	$m_shop = $_POST["m_shop"];

	$m_key = $SysValue['payeer']['secret_key'];
	
	$status_success = $SysValue['payeer']['status_success'];
	
	$status_fail = $SysValue['payeer']['status_fail'];
	
	$email_error = $SysValue['payeer']['emailerror'];
	
	$arHash = array(
		$_POST['m_operation_id'],
		$_POST['m_operation_ps'],
		$_POST['m_operation_date'],
		$_POST['m_operation_pay_date'],
		$_POST['m_shop'],
		$_POST['m_orderid'],
		$_POST['m_amount'],
		$_POST['m_curr'],
		$_POST['m_desc'],
		$_POST['m_status'],
		$m_key
	);
	
	$sign_hash = strtoupper(hash('sha256', implode(":", $arHash)));
	
	$list_ip_str = str_replace(' ', '', $SysValue['payeer']['ipfilter']);

	if (!empty($list_ip_str)) 
	{
		$list_ip = explode(',', $list_ip_str);
		$this_ip = $_SERVER['REMOTE_ADDR'];
		$this_ip_field = explode('.', $this_ip);
		$list_ip_field = array();
		$i = 0;
		$valid_ip = FALSE;
		foreach ($list_ip as $ip)
		{
			$ip_field[$i] = explode('.', $ip);
			if ((($this_ip_field[0] ==  $ip_field[$i][0]) or ($ip_field[$i][0] == '*')) and
				(($this_ip_field[1] ==  $ip_field[$i][1]) or ($ip_field[$i][1] == '*')) and
				(($this_ip_field[2] ==  $ip_field[$i][2]) or ($ip_field[$i][2] == '*')) and
				(($this_ip_field[3] ==  $ip_field[$i][3]) or ($ip_field[$i][3] == '*')))
				{
					$valid_ip = TRUE;
					break;
				}
			$i++;
		}
	}
	else
	{
		$valid_ip = TRUE;
	}

	$log_text =
		"--------------------------------------------------------\n" .
		"operation id		" . $_POST["m_operation_id"] . "\n" .
		"operation ps		" . $_POST["m_operation_ps"] . "\n" .
		"operation date		" . $_POST["m_operation_date"] . "\n" .
		"operation pay date	" . $_POST["m_operation_pay_date"] . "\n" .
		"shop				" . $_POST["m_shop"] . "\n" .
		"order id			" . $_POST["m_orderid"] . "\n" .
		"amount				" . $_POST["m_amount"] . "\n" .
		"currency			" . $_POST["m_curr"] . "\n" .
		"description		" . base64_decode($_POST["m_desc"]) . "\n" .
		"status				" . $_POST["m_status"] . "\n" .
		"sign				" . $_POST["m_sign"] . "\n\n";

	if (!empty($SysValue['payeer']['paylog']))
	{
		file_put_contents($_SERVER['DOCUMENT_ROOT'] . $SysValue['payeer']['paylog'], $log_text, FILE_APPEND);
	}

	$new_uid = UpdateNumOrder($_POST["m_orderid"]);
	
	@mysql_connect ($SysValue['connect']['host'], $SysValue['connect']['user_db'],  $SysValue['connect']['pass_db']) or 
			@die("" . PHPSHOP_error(101, $SysValue['my']['error_tracer']) . "");
		
	mysql_select_db ($SysValue['connect']['dbase']) or 
		@die("" . PHPSHOP_error(102, $SysValue['my']['error_tracer']) . "");
	
	if ($_POST["m_sign"] == $sign_hash && $_POST['m_status'] == "success" && $valid_ip)
	{
		$sql = "select uid from " . $SysValue['base']['table_name1'] . " where uid='$new_uid'";
		$result = mysql_query($sql);
		$row = mysql_fetch_array($result);
		$uid = $row['uid'];

		if($uid == $new_uid)
		{
			$sql = "INSERT INTO " . $SysValue['base']['table_name33'] . " VALUES 
				('$m_orderid','Payeer','$m_amount','" . date("U") . "')";
			
			$result = mysql_query($sql);
			
			$sql = "UPDATE " . $SysValue['base']['table_name1'] . " SET statusi='$status_success' WHERE uid='$new_uid'";
			
            $result = mysql_query($sql);
			
			exit ($m_orderid . "|success");
		}
		else 
		{
			$sql = "UPDATE " . $SysValue['base']['table_name1'] . " SET statusi='$status_fail' WHERE uid='$new_uid'";
			
            $result = mysql_query($sql);
			
			exit ($m_orderid . "|error");
		}
	}
	else
	{
		$sql = "UPDATE " . $SysValue['base']['table_name1'] . " SET statusi='$status_fail' WHERE uid='$new_uid'";
		$result = mysql_query($sql);
		
		$to = $email_error;
		$subject = "Ошибка оплаты";
		$message = "Не удалось провести платёж через систему Payeer по следующим причинам:\n\n";
		
		if ($_POST["m_sign"] != $sign_hash)
		{
			$message .= " - Не совпадают цифровые подписи\n";
		}
		
		if ($_POST['m_status'] != "success")
		{
			$message .= " - Cтатус платежа не является success\n";
		}
		
		if (!$valid_ip)
		{
			$message .= " - ip-адрес не является доверенным\n";
			$message .= "   доверенные ip-адреса: " . $SysValue['payeer']['ipfilter'] . "\n";
			$message .= "   текущий ip-адрес: " . $_SERVER['REMOTE_ADDR'] . "\n";
		}
		
		$message .= "\n" . $log_text;
		$headers = "From: no-reply@" . $_SERVER['HTTP_SERVER']."\r\nContent-type: text/plain; charset=utf-8 \r\n";
		mail ($to, $subject, $message, $headers);

		exit ($m_orderid . "|error");
	}
}
?>