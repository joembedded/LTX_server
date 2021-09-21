<?php
/*************************************************************
 * db_service for LTrax V1.xx
 * 12.09.2021
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
set_time_limit(600); // 10 Min runtime

// For Local access: 
if (!isset($_SERVER['SERVER_NAME'])) $_SERVER['SERVER_NAME'] ="joembedded.de";
if (!isset($_SERVER['REMOTE_ADDR'])) $_SERVER['REMOTE_ADDR'] ="joembedded.de";

include("../conf/api_key.inc.php");
include("../conf/config.inc.php");	// DB Access Param
include("../inc/db_funcs.inc.php"); // Init DB

// ----------------Functions----------------
function exit_error($err){
	global $xlog;
	echo "ERROR: '$err'\n";
	$xlog .= "(ERROR:'$err')";
	add_logfile();
	exit();
}

function add_logfile(){
	global $xlog, $dbg,  $now;

	$sdata = "../".S_DATA;
	$logpath = $sdata . "/log/";
	if (@filesize($logpath . "log.txt") > 100000) {	// Main LOG
		@unlink($logpath . "_log_old.txt");
		rename($logpath . "log.txt", $logpath . "_log_old.txt");
		$xlog .= " (Main 'log.txt' -> '_log_old.txt')";
	}

	if ($dbg) $xlog .= "(DBG:$dbg)";

	$log = @fopen($sdata . "/log/log.txt", 'a');
	if ($log) {
		while (!flock($log, LOCK_EX)) usleep(10000);  // Lock File - Is a MUST
		fputs($log, gmdate("d.m.y H:i:s ", $now) . "UTC " . $_SERVER['REMOTE_ADDR'] . ' ' . $_SERVER['PHP_SELF']);        // Write file
		fputs($log, " $xlog\n");        // evt. add extras
		flock($log, LOCK_UN);
		fclose($log);
	}
}

function send_mail($mail, $cont, $subj, $from)
{
	global $dbg;
	$host = $_SERVER['SERVER_NAME'];
	$mail_text = "Notification via '$host':\n";
	$mail_text .= "$subj\n$cont\n";
	//$mail_text .= "(This Email was sent automatically. If received unintentionally, please ignore it. Contact Service: " . SERVICEMAIL . ")";
	$header = "From: $from <" . AUTOMAIL . ">\r\n" .
		// 'Reply-To: webmaster@example.com' . "\r\n" .
		'X-Mailer: PHP/' . phpversion();

	if ($dbg) {
		echo "(DEBUG)<pre>MAIL: =$mail=\nSUBJ: =$subj=\nHEADER: =$header=\nTEXT:\n=$mail_text=\n</pre>";
		$res = true;	// Patch
	} else {
		$res = @mail($mail, $subj, $mail_text, $header);
	}
	return $res; // OK: true
}


// Check Mac Tables - Show Empty / Overaged Devices
function check_macs($rep){ 	
	global $pdo,$now,$qday;
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
			$xlog.="(IllegalTable:$row)";
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
			$age_d = round(($now - $res['x'])/86400,2);
			$quota = @file("../".S_DATA ."/$mac/quota_days.dat");
			$quota_days = intval(@$quota[0]);
			if ($quota_days < 1) $quota_days = $qday;	// 1 Day minimum, if unknown assume 1 year
			if($age_d>$quota_days){
				$mark.="(OverAge:".($quota_days-$age_d).")"; // <-- Remove this Device
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
	global $vis,$xlog,$admin_mail;
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
			$admin_mail=$row['email'];	// Save (LAST) Admin as Mail-Contact
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

		$age_d = round(($now - $row['x'])/86400,2);
		if($owncnt==0 && $guestcnt == 0 && $age_d>1 && !($role&65536)){ // Only keep ADMIN
			$mark.="(NoDevs/Timeout)"; // <-- Remove this User
			if($rep){
				$pdo->query("DELETE FROM users WHERE id = '$uid'");
				$xlog.="(Del.User:Id:$uid mail:".$row['email'].")";
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
	global $pdo,$now,$qday;
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

		$age_d = round(($now - $row['x'])/86400,2);
		$quota = @file("../".S_DATA ."/$mac/quota_days.dat");
		$quota_days = intval(@$quota[0]);
		if ($quota_days < 1) $quota_days = $qday;	// 1 Day minimum, if unknown assume 1 year
		if($age_d>$quota_days){
			$mark.="(OverAge:".($quota_days-$age_d).")"; // <--- Delete Entry, Remove Table
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
	global $now,$qday;
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
			$age_d = round($dt/86400,2);
			$quota = @file("$dir/$mac/quota_days.dat");
			$quota_days = intval(@$quota[0]);
			if ($quota_days < 1) $quota_days = $qday;	// 1 Day minimum, if unknown assume 1 year
			if($age_d>$quota_days){
				$mark.="(OverAge:".($quota_days-$age_d).")"; // <-- Remove this Device
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
$admin_mail = SERVICEMAIL;	// Default

echo $xlog;

if($dbg) echo "*** DBG:$dbg ***<br>";

if (@file_exists(S_DATA . "/$mac/cmd/dbg.cmd")) $dbg = 1; // Allow Individual Debug

// Check Key before loading data
//echo "API-KEY: '$api_key'<br>\n"; // TEST
if ($vis && !$dbg && strcmp($api_key, L_KEY)) {
	exit_error("Option 'v' only with API Key");
}

$qday = intval(DB_QUOTA);	// Use Default
//$qday=5;	// Only latest! For TESTS

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

case '':	// Nothing
case 'service':
	$status = check_macs(true);
	$status .= " * ".check_users(true);
	$status .= " * ".check_devices(true);
	$status .= " * ".check_guest_devices(true);
	$status .= " * ".check_legacy(true);
	break;

default:
	$status = "ERROR: CMD '$cmd'";
}

$mtrun = round((microtime(true) - $mttr_t0) * 1000, 4);
$xlog .= "(Run:$mtrun msec)"; // Script Runtime
if (!isset($status)) $status = "0 OK";

$cont = str_replace(")(",")\n(",$xlog);
echo "*** Result: ***<pre>",$cont,"</pre>";
echo "*** Mailed to: '$admin_mail' **<br>";
echo "*** Service: '$status' ***<br>\n"; // Always
add_logfile();

send_mail($admin_mail, $cont."\n\n".$status,"LTX Service", "LTX Service (PHP)");

//***
