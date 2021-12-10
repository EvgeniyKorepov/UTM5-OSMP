<?php

include_once("config.php");

@ini_set("display_errors", "1"); error_reporting(E_ALL);
//@ini_set("display_errors", "0"); error_reporting(0);

function MyExit($text) {
	global $Setting;
	header($Setting['header']);
	exit($text);
}

//Формат: YYYYMMDDHHMMSS
function GetDateText() {
	return date('YmdHis');
}

function AccountExists($account) {
	global $mysqli;
	$account = $mysqli->real_escape_string($account);
	$query = "
		SELECT 
			users.basic_account 
		FROM 
			users 
		WHERE 
			users.is_deleted = 0 AND 
			users.basic_account = '$account'
	";
	$mysql_res = $mysqli->query($query);
	$row = $mysql_res->fetch_array();
	if (!isset($row['basic_account'])) {
		return false;
	} else {
		return true;
	}
}

function PaymentExists($account, $TransactionID) {
	global $mysqli, $Setting;
	$account = $mysqli->real_escape_string($account);
	$TransactionID = $mysqli->real_escape_string($TransactionID);
	$UTMPaymentMethodID = $Setting['UTMPayment_Method_ID'];
	$query = "
		SELECT
			id, 
			payment_absolute 
		FROM 
			payment_transactions 
		WHERE 
			method = $UTMPaymentMethodID AND 
			payment_ext_number = '$TransactionID' AND 
			account_id = '$account'
	";
	$mysql_res = $mysqli->query($query);
	$row = $mysql_res->fetch_array(MYSQLI_ASSOC);
	if (isset($row['payment_absolute'])) // Платёж уже внесен, возвращаем отсутсиве ошибки, выходим.
		return $row['id'];
	else
		return false;
}

function CheckPossibilityPayment() {
	global $possibility_payment_template, $Incomplete_request, $Setting, $mysqli;
	if (!isset($_GET['txn_id'])) {
		MyExit($Incomplete_request);
	} else 
		$TransactionID	= $_GET['txn_id'];
	if (!isset($_GET['account'])) {
		MyExit($Incomplete_request);
	} else 
		$account	= $_GET['account'];
	if (!isset($_GET['sum'])) {
		MyExit($Incomplete_request);
	} else 
		$sum	= $_GET['sum'];

	$possibility_payment_template = str_replace("%osmp_txn_id%", $TransactionID, $possibility_payment_template);

	if (preg_match('/^\d+$/', $account)) {
	} else {
		$result = 4; // Идентификатор абонента не найден
		$possibility_payment_template = str_replace("%result%", $result, $possibility_payment_template);
		$possibility_payment_template = str_replace("%comment%", "Лицевой счет $account не найден", $possibility_payment_template);
		MyExit($possibility_payment_template);
	}

	if (AccountExists($account)) {
		$result = 0; // ОК
		$possibility_payment_template = str_replace("%result%", $result, $possibility_payment_template);
		$possibility_payment_template = str_replace("%comment%", "Лицевой счет $account найден, платеж возможен", $possibility_payment_template);
	} else {
		$result = 5; // Идентификатор абонента не найден
		$possibility_payment_template = str_replace("%result%", $result, $possibility_payment_template);
		$possibility_payment_template = str_replace("%comment%", "Лицевой счет $account не найден", $possibility_payment_template);
	}
	MyExit($possibility_payment_template);
}

function PaymentRegistration() {
	global $payment_template, $Incomplete_request, $Setting;

	if (!isset($_GET['txn_id'])) {
		MyExit($Incomplete_request);
	} else 
		$TransactionID	= $_GET['txn_id'];

	if (!isset($_GET['account'])) {
		MyExit($Incomplete_request);
	} else 
		$account	= $_GET['account'];

	if (!isset($_GET['sum'])) {
		MyExit($Incomplete_request);
	} else 
		$sum	= $_GET['sum'];

	$txn_date = '';
	if (isset($_GET['txn_date'])) 
		$txn_date	= $_GET['txn_date'];

	$Comment = 'QIWI, номер транзакции '.$TransactionID;

	$Date = strtotime($txn_date);
	$payment_time = $Date;
	$burn_date = 0;

	$payment_template = str_replace("%osmp_txn_id%", $TransactionID, $payment_template);
	$payment_template = str_replace("%sum%", $sum, $payment_template);

	if (!AccountExists($account)) {
		$result = 5; // Идентификатор абонента не найден
		$payment_template =  str_replace("%prv_txn%", 0, $payment_template);
		$payment_template = str_replace("%result%", $result, $payment_template);
		$payment_template = str_replace("%comment%", "Лицевой счет $account не найден", $payment_template);
		MyExit($payment_template);
	}

	$UTM5TransactionID = PaymentExists($account, $TransactionID);
	if ($UTM5TransactionID !== false) { // Платёж уже внесен, возвращаем отсутсиве ошибки, выходим.
		$result = 0; 
		$payment_template =  str_replace("%result%", $result, $payment_template);
		$payment_template =  str_replace("%prv_txn%", $UTM5TransactionID, $payment_template);
		$payment_template =  str_replace("%comment%", "Платёж уже внесен", $payment_template);
		MyExit($payment_template);
	}

	$URFAParams =	array (
	  'account_id' => $account,
	  'payment' => $sum,
	  'payment_date' => time(),
	  'burn_date' => 0,
	  'payment_method' => $Setting["UTMPayment_Method_ID"],
		'admin_comment' => $Comment,
	  'comment' => $Comment,
	  'payment_ext_number' => $TransactionID,
	  'turn_on_inet' => 1,
	);	

	InitUrfa($urfa_admin);
	$PaymentResult = $urfa_admin->rpcf_add_payment_for_account($URFAParams);
  MyLog("PaymentResult:", $PaymentResult);
	if (isset($PaymentResult['payment_transaction_id'])) {
    $result = 0; // OK
    $prv_txn = $TransactionID;
    $payment_template =  str_replace("%result%", $result, $payment_template);
    $payment_template =  str_replace("%prv_txn%", $prv_txn, $payment_template);
    $payment_template =  str_replace("%comment%", "Платеж успешно проведен", $payment_template);
//    SendPaymentMessageToUser($account, $sum, 'QIWI');
    MyLog("Account = $account\tSumma = $sum\tID_trans = $TransactionID");
    MyExit($payment_template);
  } else {
    $result = 1; // Временная ошибка. Повторите запрос позже
    $prv_txn = 0;
    $payment_template =  str_replace("%result%", $result, $payment_template);
    $payment_template =  str_replace("%prv_txn%", $prv_txn, $payment_template);
    $payment_template =  str_replace("%comment%", "Сервис временно не доступен", $payment_template);
    MyExit($payment_template);
  }
}


