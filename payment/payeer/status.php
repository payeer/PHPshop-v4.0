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
	$err = false;
	$message = '';
	$m_amount = $_POST['m_amount'];
	$m_orderid = $_POST['m_orderid'];
	
	// запись логов

	$log_text = 
	"--------------------------------------------------------\n" .
	"operation id		" . $_POST['m_operation_id'] . "\n" .
	"operation ps		" . $_POST['m_operation_ps'] . "\n" .
	"operation date		" . $_POST['m_operation_date'] . "\n" .
	"operation pay date	" . $_POST['m_operation_pay_date'] . "\n" .
	"shop				" . $_POST['m_shop'] . "\n" .
	"order id			" . $_POST['m_orderid'] . "\n" .
	"amount				" . $_POST['m_amount'] . "\n" .
	"currency			" . $_POST['m_curr'] . "\n" .
	"description		" . base64_decode($_POST['m_desc']) . "\n" .
	"status				" . $_POST['m_status'] . "\n" .
	"sign				" . $_POST['m_sign'] . "\n\n";
	
	$log_file = $SysValue['payeer']['paylog'];
	
	if (!empty($log_file))
	{
		file_put_contents($_SERVER['DOCUMENT_ROOT'] . $log_file, $log_text, FILE_APPEND);
	}

	// проверка цифровой подписи и ip

	$sign_hash = strtoupper(hash('sha256', implode(":", array(
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
		$SysValue['payeer']['secret_key']
	))));
	
	$valid_ip = true;
	$sIP = str_replace(' ', '', $SysValue['payeer']['ipfilter']);
	
	if (!empty($sIP))
	{
		$arrIP = explode('.', $_SERVER['REMOTE_ADDR']);
		if (!preg_match('/(^|,)(' . $arrIP[0] . '|\*{1})(\.)' .
		'(' . $arrIP[1] . '|\*{1})(\.)' .
		'(' . $arrIP[2] . '|\*{1})(\.)' .
		'(' . $arrIP[3] . '|\*{1})($|,)/', $sIP))
		{
			$valid_ip = false;
		}
	}
	
	if (!$valid_ip)
	{
		$message .= " - ip-адрес не является доверенным\n" .
		"   доверенные ip-адреса: " . $sIP . "\n" .
		"   текущий ip-адрес: " . $_SERVER['REMOTE_ADDR'] . "\n";
		$err = true;
	}

	if ($_POST['m_sign'] != $sign_hash)
	{
		$message .= " - не совпадают цифровые подписи\n";
		$err = true;
	}

	if (!$err)
	{
		// загрузка заказа
		
		$new_uid = UpdateNumOrder($_POST["m_orderid"]);
	
		@mysql_connect ($SysValue['connect']['host'], $SysValue['connect']['user_db'],  $SysValue['connect']['pass_db']) or 
			@die("" . PHPSHOP_error(101, $SysValue['my']['error_tracer']) . "");

		@mysql_select_db ($SysValue['connect']['dbase']) or 
			@die("" . PHPSHOP_error(102, $SysValue['my']['error_tracer']) . "");
			
		$sql = "select * from " . $SysValue['base']['table_name1'] . " where uid='$new_uid'";
		$result = mysql_query($sql);
		$row = mysql_fetch_array($result);

		if ($uid == $row['uid'])
		{
			$message .= " - несуществующий ордер\n";
			$err = true;
		}
		else
		{
			$order_curr = ($SysValue['payeer']['currency'] == 'RUR') ? 'RUB' : $SysValue['payeer']['currency'];
			$order_amount = number_format($row['sum'], 2, '.', '');
			
			// проверка суммы и валюты
		
			if ($_POST['m_amount'] != $order_amount)
			{
				$message .= " - неправильная сумма\n";
				$err = true;
			}

			if ($_POST['m_curr'] != $order_curr)
			{
				$message .= " - неправильная валюта\n";
				$err = true;
			}
			
			// проверка статуса
			
			if (!$err)
			{
				switch ($_POST['m_status'])
				{
					case 'success':
						$status_success = $SysValue['payeer']['status_success'];
						$sql = "INSERT INTO " . $SysValue['base']['table_name33'] . " VALUES 
							('$m_orderid','Payeer','$m_amount','" . date("U") . "')";
						
						$result = mysql_query($sql);
						
						$sql = "UPDATE " . $SysValue['base']['table_name1'] . " SET statusi='$status_success' WHERE uid='$new_uid'";
						$result = mysql_query($sql);
						break;
						
					default:
						$status_fail = $SysValue['payeer']['status_fail'];
						$sql = "UPDATE " . $SysValue['base']['table_name1'] . " SET statusi='$status_fail' WHERE uid='$new_uid'";
						$result = mysql_query($sql);
						$message .= " - статус платежа не является success\n";
						$err = true;
						break;
				}
			}
		}
	}
	
	if ($err)
	{
		$to = $SysValue['payeer']['emailerror'];

		if (!empty($to))
		{
			$message = "Не удалось провести платёж через систему Payeer по следующим причинам:\n\n" . $message . "\n" . $log_text;
			$headers = "From: no-reply@" . $_SERVER['HTTP_HOST'] . "\r\n" . 
			"Content-type: text/plain; charset=utf-8 \r\n";
			mail($to, 'Ошибка оплаты', $message, $headers);
		}
		
		exit ($m_orderid . "|error");
	}
	else
	{
		exit ($m_orderid . "|success");
	}
}
?>