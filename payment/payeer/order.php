<?php

if(empty($GLOBALS['SysValue'])) exit(header("Location: /"));

$m_url = $SysValue['payeer']['merchant_url'];

$m_shop = $SysValue['payeer']['merchant_id'];

$o_req = explode("-", $_POST['ouid']);

$m_orderid = $o_req[0]."".$o_req[1];

$m_amount  = $GLOBALS['SysValue']['other']['total'];

$m_curr = $SysValue['payeer']['currency'];

$m_desc = base64_encode('Payment order No. ' . $_POST['ouid']);

$m_key = $SysValue['payeer']['secret_key']; 

$arHash = array(
	$m_shop,
	$m_orderid,
	$m_amount,
	$m_curr,
	$m_desc,
	$m_key
);
$sign = strtoupper(hash('sha256', implode(":", $arHash)));

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

if ($valid_ip)
{
	$disp = "
	<form action=$m_url method=GET name=\"pay\">
		<input type=hidden name=m_shop value=$m_shop>
		<input type=hidden name=m_orderid value=$m_orderid>
		<input type=hidden name=m_amount value=$m_amount>
		<input type=hidden name=m_curr value=$m_curr>
		<input type=hidden name=m_desc value=$m_desc>
		<input type=hidden name=m_sign value=$sign>
		<input type=submit name=m_process value='Pay'>
	</form>";
}
else
{
	$status_fail = $SysValue['payeer']['status_fail'];
	
	$email_error = $SysValue['payeer']['emailerror'];
	
	$order_code = $_POST['ouid'];
	
	$log_text = 
		"--------------------------------------------------------\n".
		"shop				".$m_shop."\n".
		"order id			".$m_orderid."\n".
		"amount				".$m_amount."\n".
		"currency			".$m_curr."\n".
		"sign				".$sign."\n\n";

	@mysql_connect ($SysValue['connect']['host'], $SysValue['connect']['user_db'],  $SysValue['connect']['pass_db']) or 
	@die("".PHPSHOP_error(101,$SysValue['my']['error_tracer'])."");
	mysql_select_db($SysValue['connect']['dbase'])or 
	@die("".PHPSHOP_error(102,$SysValue['my']['error_tracer'])."");

	$sql = "UPDATE " . $SysValue['base']['table_name1'] . " SET statusi='$status_fail' WHERE uid='$order_code'";
	$result = mysql_query($sql);
	
	$to = $email_error;
	$subject = "Error payment";
	$message = "Failed to make the payment through the system Payeer for the following reasons:\n\n";
	$message.=" - the ip address of the server is not trusted\n";
	$message.="   trusted ip: " . $SysValue['payeer']['ipfilter'] . "\n";
	$message.="   ip of the current server: ".$_SERVER['REMOTE_ADDR']."\n";
	$message.="\n".$log_text;
	$headers = "From: no-reply@".$_SERVER['HTTP_SERVER']."\r\nContent-type: text/plain; charset=utf-8 \r\n";
	mail($to, $subject, $message, $headers);
				
	$disp = "Your order is cancelled, the information is sent to support";
}
				

?>