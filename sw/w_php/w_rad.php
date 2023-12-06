<?php
/***********************************************************
 * w_rad.php - push-cmd-pull worker fuer RemoteADmin 
 *
// ----------- FRAGMENT --------------
// ----------- FRAGMENT --------------
// ----------- FRAGMENT --------------
// ----------- FRAGMENT --------------

 * Entwickler: Juergen Wickenhaeuser, joembedded@gmail.com
 *
 * Beispielaufrufe (ggfs. $dbg) setzen):
 *
 * Fuer einfache CMDs per URL (z.B. interne Aufrufe): k kann auch S_API_KEY sein
 * Es wird die selbe LogDatei wie w_pcp verwendet.

---Die Idee ist, dass ueber dieses Script alles Remote ADmin Aufgaben der LTX-CLoud
geloest werden koennen. Genau Funktionalitaet, z.B. Onboaring, Credits, etc..
ist noch zu klaeren---

//Basis-Aufruf / Ausgabe Version
http://localhost/ltx/sw/w_php/w_rad.php?cmd

// Listet alle Devices zu diesem Key auf
http://localhost/ltx/sw/w_php/w_rad.php?k=ABC&cmd=list 

// Quota lesen (alle Zeilen)
http://localhost/ltx/sw/w_php/w_rad.php?k=ABC&cmd=quotaget&s=DDC2FB99207A7E7E

// Daten in 'quota' zu diesem Device aendern (nur was geaendert werden muss schicken)
http://localhost/ltx/sw/w_php/w_rad.php?s=26FEA299F444F836&k=ABC&cmd=quotachange&quota[2]=neuerserver%20XXX (%20: SPACE)


 * Parameter - Koennen per POST oder URL uebergeben werden
 * cmd: Kommando
 * k: AccessKey (aus 'quota_days.dat') (opt.)
 * s: MAC(16-Digits) (opt.)
 * 
 * cmd:
 * '':		Version
 * list:	Alle MACs mit Zugriff auflisten (nur 'k' benoetigt)
 * quotaget: quota zu einer MAC holen
 * quotachange: quota (einzelne Eintraege oder alles) zu einer MAC aendern
 * 
 * Status-Returns:
 * 0:	OK
 * 100: Keine Tabelle mMAC fuer diese MAC
 * 101: Keine Parameter gefunden fuer diese MAC
 * 102: Unbekanntes Kommando cmd
 * 103: Write Quota
 * 104: Index Error bei quota
 * ...
 */

define('VERSION', "RAD V0.10 06.12.2023");

error_reporting(E_ALL);
ini_set("display_errors", true);

header("Content-type: application/json; charset=utf-8");
header('Access-Control-Allow-Origin: *');	// CORS enabler

$mtmain_t0 = microtime(true);         // fuer Benchmark
$tzo = timezone_open('UTC');
$now = time();

require_once("../conf/config.inc.php");	// DB Access 
require_once("../conf/api_key.inc.php"); // APIs
require_once("../inc/db_funcs.inc.php"); // Init DB

$dbg = 0; // 1:Dbg, 2:Dbg++
$fpath = "../" . S_DATA;	// Globaler Pfad auf Daten
$xlog = ""; // Log-String

// ------ Write LogFile (carefully and out-of-try()/catch()) -------- (similar to lxu_xxx.php)
function add_logfile()
{
	global $xlog, $dbg, $mac, $now, $fpath;
	if (@filesize("$fpath/log/pcplog.txt") > 100000) {	// Main LOG
		@unlink("$fpath/log/_pcplog_old.txt");
		rename("$fpath/log/pcplog.txt", "$fpath/log/_pcplog_old.txt");
		$xlog .= " (Main 'pcplog.txt' -> '_pcplog_old.txt')";
	}

	if (!isset($mac)) $mac = "UNKNOWN_MAC";
	if ($dbg) $xlog .= "(DBG:$dbg)";

	$dstr=gmdate("d.m.y H:i:s ", $now) . "UTC ";
	$log = @fopen("$fpath/log/pcplog.txt", 'a');
	if ($log) {
		while (!flock($log, LOCK_EX)) usleep(10000);  // Lock File - Is a MUST
		fputs($log,  $dstr. $_SERVER['REMOTE_ADDR']. " RAD");        // Write file
		if (strlen($mac)) fputs($log, " MAC:$mac"); // mac only for global lock
		fputs($log, " $xlog\n");        // evt. add extras
		flock($log, LOCK_UN);
		fclose($log);
	}
	// User Logfile - Text
	if (strlen($mac) == 16 && file_exists("$fpath/$mac")) {
		if (@filesize("$fpath/$mac/pcplog.txt") > 50000) {	// Device LOG
			@unlink("$fpath/$mac/_pcplog_old.txt");
			rename("$fpath/$mac/pcplog.txt", "$fpath/$mac/_pcplog_old.txt");
		}

		$log = fopen("$fpath/$mac/pcplog.txt", 'a');
		if (!$log) return;
		while (!flock($log, LOCK_EX)) usleep(10000);  // Lock File - Is a MUST
		fputs($log, $dstr."RAD $xlog\n");        // evt. add extras
		flock($log, LOCK_UN);
		fclose($log);
	}
}

try {
	// Check Access-Token for this Device
	function checkAccess($lmac, $ckey)
	{
		global $fpath;
		if($ckey == S_API_KEY) return true;	// S_API_KEY valid for ALL
		$quota = @file("$fpath/$lmac/quota_days.dat", FILE_IGNORE_NEW_LINES);
		if (isset($quota[2]) && strlen($quota[2])) {
			$qpar = explode(' ', trim(preg_replace('/\s+/', ' ', $quota[2])));
			if (count($qpar) >= 2) {
				$akey = $ckey;
			}
		}
		if (!isset($akey) || $akey !== $qpar[1]) {
			return false;
		}
		return true;
	}

	// Prueft Zahlenwert auf Grenzen - HELPER
	function nverify($str, $ilow, $ihigh){
		if(!is_numeric($str)) return true;
		$val = intval($str);
		if($val<$ilow || $val>$ihigh) return true;	// Fehler
		return false;
	}
	function nisfloat($str){	// PHP recht relaxed, alles als Float OK, daher mind. 1 char. - HELPER
		if(!is_numeric($str)) return true;
		return false;
	}

	// Pruefen ob quota OK (max. 3 Zeilen)
	function checkquota($quota){
		if(!isset($quota) || $quota == false) return "400 'quota_days.dat' not found";
		if(count($quota)>3) return "401 'quota_days.dat' overdue lines";
		$qd = intval(@$quota[0]);
		if(!$qd || $qd>365000) return "402 'quota_days.dat' Days";
		$ql = intval(@$quota[1]);
		if($ql<100 ) return "403 'quota_days.dat' Lines<100";
		// Serverzeile ohne Pruefung
		return null;	// OK
	}

	//=========== MAIN ==========
	$retResult = array();

	if ($dbg > 1) print_r($_REQUEST); // Was wollte man DBG (2)

	$cmd = @$_REQUEST['cmd'];
	if (!isset($cmd)) $cmd = "";
	$ckey = @$_REQUEST['k'];	// s always MAC (k: API-Key, r: Reason)
	if($ckey == S_API_KEY) $xlog = "(cmd:'$cmd')"; // internal
	else $xlog = "(cmd:'$cmd', k:'$ckey')";
	

	// --- cmd PreStart - CMD vorfiltern ---
	switch ($cmd) {
		case "":
			$retResult['version'] = VERSION;
			break;

		case "list": // CMD'list' ALLE Devices zu DIESEM PW listen, MAC nicht noetig, Push-URL egal
			db_init(); // Access Ok, erst dann DB oeffnen (init global $pdo)

			$statement = $pdo->prepare("SELECT * FROM devices");
			$qres = $statement->execute();
			if ($qres == false) throw new Exception("DB 'devices'");
			$anz = $statement->rowCount();
			$macarr = array();
			for ($i = 0; $i < $anz; $i++) {
				$ldev = $statement->fetch();
				$lmac = $ldev['mac'];
				if (checkAccess($lmac, $ckey)) {
					$macarr[] = $lmac;
				}
			}
			if (!count($macarr)) throw new Exception("No Access");
			$retResult['list_count'] = count($macarr); // Allowed Devices
			$retResult['list_mac'] = $macarr;
			break;

		default: // Alle anderen CMDs sind Geratespezifsich
			// Im Normalfall erstmal feststellen ob Zugriff auf einzelnen Logger erlaubt
			$mac = @$_REQUEST['s'];	// s ist immer die MAC, muss bekannt sein
			if (!isset($mac)) $mac = "";
			if (strlen($mac) != 16) {
				throw new Exception("MAC len");
			}
			$mac = strtoupper($mac);
			if (!checkAccess($mac, $ckey)) {
				throw new Exception("No Access");
			}
			db_init(); // Access Ok, erst dann DB oeffnen (init global $pdo)

			// Default-Infos fuer diese Teil
			$statement = $pdo->prepare("SELECT * FROM devices WHERE mac = ?");
			$qres = $statement->execute(array($mac));
			if ($qres == false) throw new Exception("MAC $mac not in 'devices'");
			$devres = $statement->fetch(); // $devres[]: 'device(mac)'!

			$ovv = array();	// Overview zu dieser MAC
			$ovv['mac']=$mac;   
			$ovv['db_now'] = $pdo->query("SELECT NOW() as now")->fetch()['now']; // *JETZT* als Datum UTC - Rein zurInfo

			$retResult['overview'] = $ovv;
	} // --- cmd PreEnde ---

	// --- cmd Main Start - CMD auswerten ---
	switch ($cmd) {
		case '': // VERSION
		case 'list': // Liste schon fertig
			break;	

		case 'details':	// Einfach ALLES fuer diese MAC
			$retResult['details'] = $devres;
			break;

		case 'quotaget':
			// Original holen und pruefen
			$quota = @file("$fpath/$mac/quota_days.dat", FILE_IGNORE_NEW_LINES);
			$chkres = checkquota($quota);
			if($chkres != null) { 
				$status = $chkres;
				break;
			}
			$retResult['quota'] = $quota;
			break;

		case 'quotachange':
			// Erstmal Original holen und pruefen
			$quota = @file("$fpath/$mac/quota_days.dat", FILE_IGNORE_NEW_LINES);

			$nqlist = $_REQUEST['quota'];
			foreach ($nqlist as $npk => $npv) {
				$idx = intval($npk);
				if ($idx < 0 || $idx > 2) { 
					$status = "104 Index Error";
					break;
				}
			}
			if (isset($status)) break;
			// Nun alles OK, Alte Werte durch neue ersetzen
			foreach ($nqlist as $npk => $npv) {
				$idx = intval($npk);
				$quota[$idx] = $npv;
			}
			// geaenderte Quota vor Schreiben pruefen
			$chkres = checkquota($quota);
			if($chkres != null) { 
				$status = $chkres;
				break;
			}
			// Schreiben
			$nqstr = implode("\n", $quota) . "\n";
			$ilen = strlen($nqstr);
			$slen = file_put_contents("$fpath/$mac/quota_days.dat", $nqstr);
			if ($ilen == $slen) {
				$xlog .= "('quota_days.dat' changed)";
			} else {
				$xlog .= "(ERROR: Write 'quota_days.dat':$slen/$ilen Bytes)";
				$status = "103 Write 'quota_days.dat'";
			}
			break;
	
/****************************************************************
* AB hier weitere eigene CMDS *todo* 
****************************************************************/

		default:
			$status = "102 Unknown Cmd";
	} // --- cmd Main Ende ---

	// Benchmark am Ende
	$mtrun = round((microtime(true) - $mtmain_t0) * 1000, 4);
	if (!isset($status)) $status = "0 OK";	// Im Normalfall Status '0 OK'
	$retResult['status'] = $status . " ($mtrun msec)";	// plus Time

	$ares = json_encode($retResult); // assoc array always as object
	if (!strlen($ares))  throw new Exception("json_encode()");
	if ($dbg) var_export($retResult);
	else echo $ares;
} catch (Exception $e) {
	$errm = "#ERROR: '" . $e->getMessage() . "'";
	echo $errm;
	$xlog .= "($errm)";
}

if (isset($pdo) && strlen($xlog)) add_logfile(); // // Nur ernsthafte Anfragen loggen
// ***