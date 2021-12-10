<?php

@ini_set("display_errors", "1"); error_reporting(E_ALL);
//@ini_set("display_errors", "0"); error_reporting(0);

include_once('config.php');
include_once("helpers.php");

MyLog("====================================================================================================================================================================");
MyLog('$_GET:', $_GET);

/*
if ($_GET['LOGIN']!=$LOGIN or $_GET['PASS']!=$PASS) {
	$payment_error_template = str_replace("%RESULTCODE%", '401', $payment_error_template);
	$payment_error_template = str_replace("%RESULTMESSAGE%", UTF8ToWindows1251('Не авторизован'), $payment_error_template);
	$payment_error_template = str_replace("%DATE%", GetDateText(), $payment_error_template);
	exit($payment_error_template);
}
*/
if (!isset($_GET['command'])) 
	MyExit($Incomplete_request);

$Command = $_GET['command'];

switch ($Command) {
	case 'check': // 'check' – запрос на проверку возможности регистрации платежа;
    CheckPossibilityPayment($possibility_payment_template, $Incomplete_request, $Setting, $mysqli);
    break;
	case 'pay': // 2 - запрос на регистрацию платежа.
		PaymentRegistration($payment_template, $Incomplete_request, $Setting, $mysqli);
    break;
}


?>