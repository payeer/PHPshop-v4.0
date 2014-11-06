<?php

if (empty($GLOBALS['SysValue'])) 
{
	exit (header("Location: /"));
}

$m_url = $SysValue['payeer']['merchant_url'];

$m_shop = $SysValue['payeer']['merchant_id'];

$o_req = explode("-", $_POST['ouid']);

$m_orderid = $o_req[0] . $o_req[1];

$m_amount  = $GLOBALS['SysValue']['other']['total'];

$m_curr = $SysValue['payeer']['currency'];

$m_desc = base64_encode('Payment ' . $m_orderid);

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

$disp = "
<form action=$m_url method=GET name=\"pay\">
	<input type=hidden name=m_shop value=$m_shop>
	<input type=hidden name=m_orderid value=$m_orderid>
	<input type=hidden name=m_amount value=$m_amount>
	<input type=hidden name=m_curr value=$m_curr>
	<input type=hidden name=m_desc value=$m_desc>
	<input type=hidden name=m_sign value=$sign>
	<input type=submit name=m_process value='Оплатить'>
</form>";
?>