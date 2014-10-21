<?
function WriteLog($m_shop, $m_amount, $m_orderid, $sign)
{
	global $REQUEST_URI, $REMOTE_ADDR;
	$handle = fopen("../paymentlog.log", "a+");

	$str="
	  Payeer Payment Start ------------------
	  date=".date('F j, Y, g:i a')."
	  m_shop=$m_shop
	  m_amount=$m_amount
	  m_orderid=$m_orderid
	  sign=$sign
	  IP=$REMOTE_ADDR
	  Payeer Payment End --------------------
	  ";
	fwrite($handle, $str);
	fclose($handle);
}

function UpdateNumOrder($uid)
{
	$last_num = substr($uid, -2);
	$total=strlen($uid);
	$ferst_num = substr($uid,0,($total-2));
	return $ferst_num."-".$last_num;
}

$SysValue=parse_ini_file("../../phpshop/inc/config.ini",1);

while(list($section,$array)=each($SysValue))
{
	while(list($key,$value)=each($array))
	{
		$SysValue['other'][chr(73).chr(110).chr(105).ucfirst(strtolower($section)).ucfirst(strtolower($key))]=$value;
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
	
	if ($_POST["m_sign"] == $sign_hash && $_POST['m_status'] == "success")
	{
		@mysql_connect ($SysValue['connect']['host'], $SysValue['connect']['user_db'],  $SysValue['connect']['pass_db']) or 
		@die("".PHPSHOP_error(101,$SysValue['my']['error_tracer'])."");
		mysql_select_db($SysValue['connect']['dbase'])or 
		@die("".PHPSHOP_error(102,$SysValue['my']['error_tracer'])."");

		$new_uid=UpdateNumOrder($_POST["m_orderid"]);

		$sql="select uid from ".$SysValue['base']['table_name1']." where uid='$new_uid'";
		$result=mysql_query($sql);
		$row=mysql_fetch_array($result);
		$uid=$row['uid'];

		if($uid == $new_uid)
		{
			$sql="INSERT INTO ".$SysValue['base']['table_name33']." VALUES 
			('$m_orderid','Payeer','$m_amount','".date("U")."')";
			$result=mysql_query($sql);
			
			$sql = "UPDATE " . $SysValue['base']['table_name1'] . " SET statusi='$status_success' WHERE uid='$new_uid'";
            $result = mysql_query($sql);
			
			if ($SysValue['payeer']['paylog'] == 1)
			{
				WriteLog($m_shop,$m_amount,$m_orderid,$m_sign);
			}
			
			echo $m_orderid . "|success";
		}
		else 
		{
			$sql = "UPDATE " . $SysValue['base']['table_name1'] . " SET statusi='$status_fail' WHERE uid='$new_uid'";
            $result = mysql_query($sql);
			
			if ($SysValue['payeer']['paylog'] == 1)
			{
				WriteLog($m_shop,$m_amount,$m_orderid,$m_sign);
			}
			
			echo $m_orderid . "|error";
			exit;
		}
		
		exit;
	}

	$sql = "UPDATE " . $SysValue['base']['table_name1'] . " SET statusi='$status_fail' WHERE uid='$new_uid'";
	$result = mysql_query($sql);
	
	$log_text = 
		"--------------------------------------------------------\n".
		"operation id		".$_POST["m_operation_id"]."\n".
		"operation ps		".$_POST["m_operation_ps"]."\n".
		"operation date		".$_POST["m_operation_date"]."\n".
		"operation pay date	".$_POST["m_operation_pay_date"]."\n".
		"shop				".$_POST["m_shop"]."\n".
		"order id			".$_POST["m_orderid"]."\n".
		"amount				".$_POST["m_amount"]."\n".
		"currency			".$_POST["m_curr"]."\n".
		"description		".base64_decode($_POST["m_desc"])."\n".
		"status				".$_POST["m_status"]."\n".
		"sign				".$_POST["m_sign"]."\n\n";
	
	$to = $email_error;
	$subject = "Error payment";
	$message = "Failed to make the payment through the system Payeer for the following reasons:\n\n";
	
	if ($_POST["m_sign"] != $sign_hash)
	{
		$message.=" - Do not match the digital signature\n";
	}
	
	if ($_POST['m_status'] != "success")
	{
		$message.=" - The payment status is not success\n";
	}
	
	$message.="\n".$log_text;
	$headers = "From: no-reply@".$_SERVER['HTTP_SERVER']."\r\nContent-type: text/plain; charset=utf-8 \r\n";
	mail($to, $subject, $message, $headers);

	if ($SysValue['payeer']['paylog'] == 1)
	{
		WriteLog($m_shop,$m_amount,$m_orderid,$m_sign);
	}
	
	echo $m_orderid . "|error";
}
?>