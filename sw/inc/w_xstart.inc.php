<?php
// Starter for Workers w_xx with EXTERN w_xstart.inc.php
// inits mtmain_t0, database and 

error_reporting(E_ALL);
$mtmain_t0 = microtime(true);         // for Benchmark 

session_start();
require_once("../conf/config.inc.php");	// DB Access 
require_once("../conf/api_key.inc.php"); // APIs
require_once("../inc/db_funcs.inc.php"); // Init DB

$mac = @$_REQUEST['s'];	// s always MAC (k: API-Key, r: Reason)
if(!isset($mac)) $mac="";
if (strlen($mac) != 16) {
	echo "#ERROR: MAC len\n";
	exit();
}

$cdate = @$_REQUEST['m']; 	// Last known Modification Date
if(!isset($cdate)) $cdate="";
$token=@$_REQUEST['k']; 	// optionally if not logged IN as owner
if(!isset($token)) $token="";
$token = strtoupper($token);
$limit = intval(@$_REQUEST['lim']);	// Limit of Datasets (if set, else: maximum, MUST be set)

db_init();

$statement = $pdo->prepare("SELECT * FROM `devices` WHERE mac=?");
$statement->execute(array($mac));
$anz = $statement->rowCount(); // No of matches
if ($anz != 1) {
	echo "#ERROR: MAC unknown";
	exit();
} // MAC Unknown
$device = $statement->fetch();	// DEVICE data for MAC

$cmd = @$_REQUEST["cmd"];
if (!isset($cmd)) $cmd = "";

// Check for valid token or valid user
$user_id = @$_SESSION["user_id"];
if (isset($user_id) && !strlen($token)) {	// User not SET, check Token
	if ($device['owner_id'] != $user_id) {
		$statement = $pdo->prepare("SELECT * FROM `users` WHERE id=?"); // Check for ADMIN?
		$statement->execute(array($user_id));
		$user_row = @$statement->fetch();
		$user_role = @$user_row['user_role'];
		if (!($user_role & 65536)) {
			echo "#ERROR: Not Owner";
			exit();
		} // No Admin: no data!
		$auth = 1001;	// Auth: Admin
	} else $auth = 1000;	// Auth: Owner
} else for (;;) {
	if (strlen($token) != 16) {
		if (!strlen($token))	echo "#ERROR: Logged out";
		else echo "#ERROR: Token len";
		exit();
	}
	if (substr($device['fw_key'], 0, 16) == $token) {
		$auth = -1;
		break;
	}	// Owner-Token but not logged in(!)
	if ($device['token0'] == $token) {
		$auth = 0;
		break;
	}	// Other 4 Tokens - spaeter Role noch uebernehmen
	if ($device['token1'] == $token) {
		$auth = 1;
		break;
	}
	if ($device['token2'] == $token) {
		$auth = 2;
		break;
	}
	if ($device['token3'] == $token) {
		$auth = 3;
		break;
	}
	echo "#ERROR: Access denied";
	exit();
}
