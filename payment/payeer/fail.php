<?
if(isset($_GET['m_orderid']))
{
	$order_metod = 'payeer';
	$success_function = false; 
	$crc = "NoN"; 
	$my_crc = "NoN";
	$inv_id = $_GET['m_orderid'];
	
	header("Location: " . $SysValue['dir']['dir'] . "/users/order.html?orderId=" . $_GET['m_orderid'] . "#PphpshopOrder");
}
?>
