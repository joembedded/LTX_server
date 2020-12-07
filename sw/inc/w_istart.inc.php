<?php
// Starter for Workers w_xx with LOGIN (intern)
// inits mtmain_t0, database and echecks sesion
// Errors <= -1000: LOGOUT

$mtmain_t0 = microtime(true);         // for Benchmark 
$now = time();

error_reporting(E_ALL);

session_start();
require_once("../conf/config.inc.php");	// DB Access 
require_once("../conf/api_key.inc.php"); // APIs
require_once("inc_loglib.php"); // 
require_once("db_funcs.inc.php"); // Init DB

$cmd = @$_REQUEST["cmd"];
if (!isset($cmd)) $cmd = "";

// Check for valid user
$user_id = @$_SESSION["user_id"];
if (!isset($user_id)) {
	echo "{\"status\":\"-1001 Login required\"}";
	exit();
}
db_init();
$statement = $pdo->prepare("SELECT name,loggedin,user_role FROM users WHERE id = ?");
$statement->execute(array(intval($user_id)));
$anz = $statement->rowCount(); // No of matches
if ($anz != 1) {
	echo "{\"status\":\"-1000 Access denied\"}";
	exit();
}
$user_row = $statement->fetch();
if (!$user_row['loggedin']) {
	echo "{\"status\":\"-1002 Logged out\"}";
	exit();
}
$uname = $user_row['name']; // Save for later (mail)
$urole = $user_row['user_role'];
