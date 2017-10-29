﻿<?php
	include_once 'includes/config.php'; // загружаем HOST, USER, PASSWORD
	include_once 'counter.php'; // загружаем Counter
	counter_go('1');
	
	header('Access-Control-Allow-Origin: *');
	header('Content-Type: application/json; charset=UTF-8');
	
	// принимаем входящие параметры
	$nickname = $uid = $limit = $options = '';
	
	$nickname = test_input($_GET['nickname']);
	$uid = test_input($_GET['uid']);
	$limit = test_input($_GET['limit']);
	$options = test_input($_GET['options']);
	
	function test_input($data) {
		$data = trim($data);
		$data = stripslashes($data);
		$data = htmlspecialchars($data);
		return $data;
	}
	
	// Параметры по умолчанию
	$defaultoptions = 'uid,nickname,effRating,karma,prestigeBonus,gamePlayed,gameWin,totalAssists,totalBattleTime,totalDeath,totalDmgDone,totalHealingDone,totalKill,totalVpDmgDone,clanName,clanTag';
	
	if (empty($nickname) and empty($uid)) {
		Error('invalid request');
	}
	if (empty($limit) or intval($limit)>300) {
		$limit = '300';
	}
	
	if (empty($options) or $options == 'max' or $options == 'all') {
		$options = $defaultoptions;
	}
	if ($options == 'min') {
		$options = 'uid,nickname';
	}
	$optionslist = explode(",", $options);
	$defaultoptionslist = explode(",", $defaultoptions);
	
	foreach($optionslist as &$value) {
		if (!in_array($value, $defaultoptionslist)) {
			$str = "unidentified options: " . $value;
			Error($str);
			exit();
		}
	}
	
	
	// если uid не указан, узнаем его
	if (empty($uid)) {
		$uid = FindUidFromNickname($nickname);
	}
	
	// определяем начальные данные
	$db_name = 'sc_history_db';
	
	// соединяемся с сервером базы данных
	$connect_to_db = mysql_connect(HOST, USER, PASSWORD)
	or Error("Could not connect: " . mysql_error());
	
	// подключаемс¤ к базе данных
	mysql_select_db($db_name, $connect_to_db)
	or Error("Could not select DB: " . mysql_error());
	
	// выбираем все значения из таблицы
	$db_table_to_show = 'uid_' . $uid;
	$qr_result = mysql_query("SELECT * FROM " . $db_table_to_show . " ORDER BY date DESC LIMIT " . $limit)
	or Error('Could not find: ' . mysql_error());
	
	// закрываем соединение с сервером базы данных
	mysql_close($connect_to_db);
	
	$bigdata = [];
	$result = mysql_num_rows($qr_result);
	while($data = mysql_fetch_array($qr_result)) {
		$date = new DateTime($data['date']);
		$date->modify('-1 day');
		$date = $date->format('Y-m-d');
		$data = array_replace_recursive($data, ['date' => $date, 0 => $date]);
		//$bigdata = array_merge_recursive($bigdata, [$data]);
		//$bigdata = array_merge_recursive($bigdata, [$data['date'] => ['uid' => $data['uid'], 'nickname' => $data['nickname']]]);
		
		
		$buffer = [];
		foreach($optionslist as &$value) {
			$buffer[$value] = $data[$value];
		}
		$bigdata = array_merge_recursive($bigdata, [$data['date'] => $buffer]);
		
	}
	
	// Формируем вывод
	$output = ['result' => $result, 'text' => 'ok', 'bigdata' => $bigdata];
	echo json_encode($output, JSON_FORCE_OBJECT); // и отдаем как json
	
	function Error($text) {
		$output = ['result' => -1, 'text' => $text, 'data' => null];
		echo json_encode($output, JSON_FORCE_OBJECT);
		exit();
	}
	function FindUidFromNickname($nickname)
	{
		$webform = file_get_contents("http://gmt.star-conflict.com/pubapi/v1/userinfo.php?nickname=" . $nickname);
		if(strpos($webform, '"result":"ok"') !== False ) {
			$uidStartIndex = strpos($webform, '"uid":') + strlen('"uid":');
			$uid_n = substr($webform, $uidStartIndex);
			$uidEndIndex = strpos($uid_n, ',');
			
			$uid = substr($uid_n, 0, $uidEndIndex);
			
			$is_uid_fined = true;
		}
		if (!isset($is_uid_fined)) {
				$str = "User not found";
				Error($str);
				exit();
			}
		return $uid;
	}
?>
