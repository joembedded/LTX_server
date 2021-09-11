<?php

/*************************************************************
 * db_service for LTrax V1.xx
 * 10.09.2021
 *
 * Service-Functions - WORK
 * Call with k=Legcay-Key
 * - Checks Ref. Integrity of Database
 *
 * Help: 
 * CMD: mysql -uroot   -> use ltx_1u1 ->
 * SHOW TABLES;
 *
 * Notes:
 * All Logger Tables start with 'm'
 * Other Tables: devices, guest_devices, users
 * Assume Basic DB structure is OK
 ***************************************************************/

error_reporting(E_ALL);

ignore_user_abort(true);
set_time_limit(240); // 4 Min runtime

include("../conf/api_key.inc.php");
include("../conf/config.inc.php");	// DB Access Param
include("../inc/db_funcs.inc.php"); // Init DB
include("../lxu_loglib.php");

// ----------------Functions----------------
// Check Mac Tables - Show Empty / Overaged Devices
function check_macs($rep){ 	
	global $pdo,$now;
	global $vis,$xlog;
	$statement = $pdo->prepare("SHOW TABLES");
	$statement->execute(); // Get ALL Tables!
	if($vis) echo "---MAC-Tables Info---<br>\n";
	$anz_devices=0;
	$total_lines=0;
	while($rowa = $statement->fetch()){
		$row=array_pop($rowa);
		if($row=='devices' || $row=='guest_devices' || $row=='users'){
			continue; // Ignore Unknown Tables
		}
		if($row[0]!=='m') {
			if($vis) echo "(IllegalTable)  Table:$row<br>\n";
			continue;
		}
		// All Devices start with 'm'
		$mark="";
		$anz_devices++;
		$mac=substr($row,1);
		$statement2 = $pdo->prepare("SELECT COUNT(*) AS x FROM m$mac");
		$statement2->execute();  
		$lines = $statement2->fetch()['x'];
		if($lines == 0) {
			$mark="(TableLen:0)";  // <-- Strange! No Data! No Remove
		}
		$total_lines+=$lines;
		$statement2 = $pdo->prepare("SELECT UNIX_TIMESTAMP(last_change) as x FROM devices WHERE mac = '$mac'");
		$statement2->execute();  
		$res = $statement2->fetch();
		if($res == false){
			if($vis) echo "(RefInt-NotInDevices)  Mac:$mac<br>\n"; // <-- Not in Devices - Referentielle Integritaet!
			if($rep){
				$pdo->query("DELETE FROM devices WHERE mac = '$mac'");
				$pdo->query("DELETE FROM guest_devices WHERE mac = '$mac'");
				$pdo->query("DROP TABLE m$mac");
				$xlog.="(RefInt:Del. devices.MAC:$mac)";
			}
		}else{
			$age_d = intval(($now - $res['x'])/86400);
			$quota = @file("../".S_DATA ."/$mac/quota_days.dat");
			$quota_days = intval(@$quota[0]);
			if ($quota_days < 1) $quota_days = 366;	// 1 Day minimum, if unknown assume 1 year
			if($age_d>$quota_days){
				$mark.="(OverAge:".$quota_days-$age_d.")"; // <-- Remove this Device
				if($rep){
					$pdo->query("DELETE FROM devices WHERE mac = '$mac'");
					$pdo->query("DELETE FROM guest_devices WHERE mac = '$mac'");
					if($pdo->query("SHOW TABLES LIKE 'm$mac'")->rowCount()>0){
						$pdo->query("DROP TABLE m$mac");
					}
					$xlog.="(OAge:Del. MAC:$mac)";
				}
			}
			if($vis) echo "$mark  Mac:$mac - Lines:$lines - AgeDays:$age_d<br>\n";
		}
	}
	$status = "0 Devices:$anz_devices TotalLines:$total_lines";
	if($vis) echo "=> $status<br>\n<br>\n";
	return $status;
}
// Check Users 
function check_users($rep){ 	
	global $pdo,$now;
	global $vis,$xlog;
	$statement = $pdo->prepare("SELECT *,UNIX_TIMESTAMP(last_seen) as x  FROM users");
	$statement->execute(); // Get ALL Entries!
	if($vis) echo "---Users Info---<br>\n";
	$anz_users=0;
	while($row = $statement->fetch()){
		$anz_users++;
		$mark="";
		$role=$row['user_role'];
		if($role&65536){
			$mark="(Admin)";
		}
		if(!($role&32768)){
			$mark.="(Demo)";
		}
		$uid=$row['id'];
		$crypt=$row['password'];
		$plain=simple_crypt($crypt,1); // 1:Decrypt

		$statement2 = $pdo->prepare("SELECT COUNT(*) AS x FROM devices WHERE owner_id = '$uid'");
		$statement2->execute();  
		$owncnt = $statement2->fetch()['x'];
		$statement2 = $pdo->prepare("SELECT COUNT(*) AS x FROM guest_devices WHERE guest_id = '$uid'");
		$statement2->execute();  
		$guestcnt = $statement2->fetch()['x'];

		$age_d = intval(($now - $row['x'])/86400);
		if($owncnt==0 && $guestcnt == 0 && $age_d>1 && !($role&65536)){ // Only keep ADMIN
			$mark.="(NoDevs/Timeout)"; // <-- Remove this User
			if($rep){
				$pdo->query("DELETE FROM users WHERE id = '$uid'");
				$xlog.="(To:Del. MAC:$uid)";
			}
		}

		if($vis) echo "$mark  Id:$uid Name:'".$row['name']."' PW:'".$plain."' Mailto:".$row['email']." Own:$owncnt Guests:$guestcnt<br>\n";
	}
	$status = "0 Users:$anz_users";
	if($vis) echo "=> $status<br>\n<br>\n";
	return $status;
}

// Check Devices
function check_devices($rep){ 	
	global $pdo,$now;
	global $vis,$xlog;
	$statement = $pdo->prepare("SELECT *,UNIX_TIMESTAMP(last_change) as x  FROM devices");
	$statement->execute(); // Get ALL entries
	if($vis) echo "---Devices Info---<br>\n";
	$anz_devices=0;
	while($row = $statement->fetch()){
		$anz_devices++;
		$mark="";
		$mac = $row['mac'];

		$qres = $pdo->query("SHOW TABLES LIKE 'm$mac'")->rowCount();
		if ($qres === 0) {	// No Table for this Device 
			$lines="?";
			$mark="(TableMissing)"; // <--- Delete Entry in Devices
			if($rep){
				$pdo->query("DELETE FROM devices WHERE mac = '$mac'");
				$pdo->query("DELETE FROM guest_devices WHERE mac = '$mac'");
				$xlog.="(NoTable:Del. MAC:$mac)";
			}
		}else{
			$lines = $pdo->query("SELECT COUNT(*) AS x FROM m$mac")->fetch()['x'];
		}

		$age_d = intval(($now - $row['x'])/86400);
		$quota = @file("../".S_DATA ."/$mac/quota_days.dat");
		$quota_days = intval(@$quota[0]);
		if ($quota_days < 1) $quota_days = 366;	// 1 Day minimum, if unknown assume 1 year
		if($age_d>$quota_days){
			$mark.="(OverAge:".$quota_days-$age_d.")"; // <--- Delete Entry, Remove Table
			if($rep){
				$pdo->query("DELETE FROM devices WHERE mac = '$mac'");
				$pdo->query("DELETE FROM guest_devices WHERE mac = '$mac'");
				if($pdo->query("SHOW TABLES LIKE 'm$mac'")->rowCount()>0){
					$pdo->query("DROP TABLE m$mac");
				}
				$xlog.="(OAge:Del. MAC:$mac)";
			}
		}

		if($vis) echo "$mark  Mac:$mac - Lines:$lines - AgeDays:$age_d<br>\n";
	}
	$status = "0 Devices:$anz_devices";
	if($vis) echo "=> $status<br>\n<br>\n";
	return $status;
}

// Check GuestDevices (Contains only MACs)
function check_guest_devices($rep){ 	
	global $pdo,$now;
	global $vis,$xlog;
	$statement = $pdo->prepare("SELECT *  FROM guest_devices");
	$statement->execute(); // Get ALL entries
	if($vis) echo "---Guest Devices Info---<br>\n";
	$anz_devices=0;
	while($row = $statement->fetch()){
		$anz_devices++;
		$mark="";
		$mac = $row['mac'];

		$qres = $pdo->query("SHOW TABLES LIKE 'm$mac'")->rowCount();
		if ($qres === 0) {	// No Table for this Device 
			$lines="?";
			$mark="(TableMissing)"; // <--- Delete Entry in GuestDevices
			if($rep){
				$pdo->query("DELETE FROM guest_devices WHERE mac = '$mac'");
				$xlog.="(NoTable2:Del. MAC:$mac)";
			}
		}else{
			$lines = $pdo->query("SELECT COUNT(*) AS x FROM m$mac")->fetch()['x'];
		}

		if($vis) echo "$mark  Mac:$mac - Lines:$lines<br>\n";
	}
	$status = "0 Guest Devices:$anz_devices";
	if($vis) echo "=> $status<br>\n<br>\n";
	return $status;
}
// Subfct.
function rmrf($dir) {
    foreach (glob($dir) as $file) {
        if (is_dir($file)) { 
            rmrf("$file/*");
            rmdir($file);
        } else {
            unlink($file);
        }
    }
}
// Check legacy - independant from Database!
function check_legacy($rep){
	global $now;
	global $vis,$xlog;
	$dir = "../".S_DATA;
	if($vis) echo "---Legacy Info---<br>\n";

	$list = scandir($dir);
	$anz = 0;
	foreach ($list as $file) {
		if ($file == '.' || $file == '..') continue;
		if ($file == 'log' || $file == 'stemp') continue;	// log and stemp not listed
		if (!is_dir("./$dir/$file")) continue;	// Should not be, but..
		$mac=$file;
		$mark="";
		$anz++;
		if (@file_exists("$dir/$file/device_info.dat")) {
			$dt = $now - filemtime("$dir/$file/device_info.dat");
			$age_d = intval($dt/86400);
			$quota = @file("$dir/$mac/quota_days.dat");
			$quota_days = intval(@$quota[0]);
			if ($quota_days < 1) $quota_days = 366;	// 1 Day minimum, if unknown assume 1 year
			if($age_d>$quota_days){
				$mark.="(OverAge:".$quota_days-$age_d.")"; // <-- Remove this Device
				if($rep){	// Remove DIR
					$xlog.="(Del.Legacy MAC:$mac)";
					rmrf("$dir/$mac");
				}
			}
		}else{
			$mark="(UnknownAge)";
			$xlog.="(UnknownAge MAC:$mac)";
		}

		if($vis) echo "$mark  MAC:$mac AgeDays:$age_d<br>\n";		
	}
	$status = "0 Legacy Devices:$anz";
	if($vis) echo "=> $status<br>\n<br>\n";
	return $status;
	
}

// ----------------MAIN----------------
$dbg = 0;	// Debug-Level if >0, see docu
//header('Content-Type: text/plain');

$api_key = @$_GET['k'];				// max. 41 Chars KEY
$cmd = @$_GET['cmd'];				// Command
$vis = @$_GET['v'];					// Visibility

$now = time();						// one timestamp for complete run
$mttr_t0 = microtime(true);           // Benchmark trigger
$xlog = "(Service:'$cmd')";

if($dbg) echo "*** DBG:$dbg ***<br>";

if (@file_exists(S_DATA . "/$mac/cmd/dbg.cmd")) $dbg = 1; // Allow Individual Debug

// Check Key before loading data
//echo "API-KEY: '$api_key'<br>\n"; // TEST
if ($vis && !$dbg && strcmp($api_key, L_KEY)) {
	exit_error("Option 'v' only with API Key");
}

db_init(); // --- Connect to DB ---
switch ($cmd) {
case 'scan_macs':
	$status = check_macs(false);
	break;
case 'scan_users':
	$status = check_users(false);
	break;
case 'scan_devices':
	$status = check_devices(false);
	break;
case 'scan_guest_devices':
	$status = check_guest_devices(false);
	break;
case 'scan_legacy':
	$status = check_legacy(false);
	break;

case 'scan':
	$status = check_macs(false);
	$status .= " * ".check_users(false);
	$status .= " * ".check_devices(false);
	$status .= " * ".check_guest_devices(false);
	$status .= " * ".check_legacy(false);
	break;

case 'service_macs':
	$status = check_macs(true);
	break;
case 'service_users':
	$status = check_users(true);
	break;
case 'service_devices':
	$status = check_devices(true);
	break;
case 'service_guest_devices':
	$status = check_guest_devices(true);
	break;
case 'service_legacy':
	$status = check_legacy(true);
	break;


case 'service':
default:
	$status = check_macs(true);
	$status .= " * ".check_users(true);
	$status .= " * ".check_devices(true);
	$status .= " * ".check_guest_devices(true);
	$status .= " * ".check_legacy(true);
	break;
}

$mtrun = round((microtime(true) - $mttr_t0) * 1000, 4);
$xlog .= "(Run:$mtrun msec)"; // Script Runtime
if (!isset($status)) $status = "0 OK";

echo "*Service: '$status'*<br>\n"; // Always
echo "*Log: '$xlog'*<br>\n";
add_logfile();
