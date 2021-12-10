<?php

$Setting = array(
	'header' => 'Content-Type: application/xml, charset=windows-1251',
	'db_host' => '********',
	'db_base' => 'UTM5',
	'db_user' => '******',
	'db_password' => '******',
	'UTMCore_Login' => 'osmp',
	'UTMCore_Password' => '********',

	'UTMPayment_Method_ID' => 106,
	'UTMPayment_Turn_on_internet' => 1,
);

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT); 
$mysqli = new mysqli($Setting['db_host'], $Setting['db_user'], $Setting['db_password'], $Setting['db_base']);
if ($mysqli->connect_errno) {
	echo "Не удалось подключиться к MySQL: " . $mysqli->connect_error;
}

$LogFile = "/var/log/utm5/payments/osmp_".date("Y_m_d").".log";

//****************************************************** Шаблоны ответов ******************************************************************************************
$header_template = '<?xml version="1.0" encoding="UTF-8" ?><response>';
$footer_template = '</response>';
//2.4. Проверка возможности регистрации платежа
//2.4.1. Запрос
//Для проверки состояния абонента Система генерирует запрос следующего вида:
//https://www.flintnet.ru/api/qiwi/qiwi.php?command=check&txn_id=1234567&account=1&sum=10.45
//2.4.2. Ответ
$possibility_payment_template = '<osmp_txn_id>%osmp_txn_id%</osmp_txn_id><result>%result%</result><result>%comment%</result>'; 
$possibility_payment_template = $header_template . $possibility_payment_template . $footer_template;

//2.5. Регистрация платежа
//2.5.1. Запрос
//Для подтверждения платежа на пополнение лицевого счета Система генерирует запрос следующего вида:
//https://www.flintnet.ru/api/qiwi/qiwi.php?command=pay&txn_id=1234567&txn_date=20090815120133&account=1&sum=10.45
//2.5.2. Ответ
$payment_template = '<osmp_txn_id>%osmp_txn_id%</osmp_txn_id><prv_txn>%prv_txn%</prv_txn><sum>%sum%</sum><result>%result%</result><comment>%comment%</comment>';
$payment_template = $header_template.$payment_template.$footer_template;

$Incomplete_request = '<result>300</result><comment>Incomplete request</comment>';
$Incomplete_request = $header_template.$Incomplete_request.$footer_template;


function MyLog($Title, $Value = "", $Debug = false) {	
	global $LogFile;

	if (is_array($Title))
		$Title = "\n".print_r($Title, true)."\n";

	if (is_array($Value))
		$Value = "\n".print_r($Value, true)."\n";

	$Message = date("Y.m.d H:i:s")." ".$Title.$Value."\r\n";
	if ($Debug)
		echo $Message;
	file_put_contents($LogFile, $Message, FILE_APPEND);	
}

function InitMysqli(&$mysqli) {
	global $Setting;
	include_once('/opt/URFAClient/URFAClient_Config.php');
	$Result = true;
	if (@!$mysqli) {
		mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT); 
		$mysqli = new mysqli($Setting["db_host"], $Setting["db_user"], $Setting["db_password"], $Setting["db_base"]);
		if ($mysqli->connect_errno) {
			MyLog("Не удалось подключиться к MySQL:", $mysqli->connect_error);
			return false;
		}
	} 
//	$mysqli->set_charset("utf8");
	return $Result;
}

function InitUrfa(&$urfa_admin) {
	global $Setting;
	include_once('/opt/URFAClient/URFAClient.php');
	$Result = true;

	$URFAParams = array(
		'login'    => $Setting["UTMCore_Login"],
		'password' => $Setting["UTMCore_Password"],
	);

	if (@!$urfa_admin) {
		try {
			$urfa_admin = URFAClient::init($URFAParams);
		} catch (Exception $exception) { 
			MyLog("URFA error:", $exception->getLine() . "\n" . $exception->getMessage());
			$Result = false;
			return $Result;                                                         
		} 
	}
	return $Result;
}
