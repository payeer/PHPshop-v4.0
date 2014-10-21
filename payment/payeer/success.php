<?
if(empty($GLOBALS['SysValue'])) exit(header("Location: /"));

if(isset($_REQUEST['m_orderid']))
{
	$order_metod="payeer";
	$success_function=true; 
	$crc = 0; 
	$my_crc = 0;
	$inv_id = $_POST['m_orderid'];
}
?>
