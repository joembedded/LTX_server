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

// Datei sys_param.lxp zu diesem Device holen (mit Beschreibung der Zeilen. Falls noch eine zu senden Datei aktiv: sysparam_pending:true)
http://localhost/ltx/sw/w_php/w_rad.php?k=ABC&cmd=sysparamget&s=DDC2FB99207A7E7E

// Daten in 'sys_param.lxp' zu diesem Device aendern (nur was geaendert werden mss schicken, hier Port 80 und Protokoll HTTP)
http://localhost/ltx/sw/w_php/w_rad.php?k=ABC&cmd=sysparamchange&s=DDC2FB99207A7E7E&sys_param[10]=80&sys_param[19]=0

// Pending 'sys_param.lxp' loeschen (falls vorhanden)
http://localhost/ltx/sw/w_php/w_rad.php?k=ABC&cmd=sysparamunpend&s=DDC2FB99207A7E7E


 * Parameter - Koennen per POST oder URL uebergeben werden
 * cmd: Kommando
 * k: AccessKey (aus 'quota_days.dat') (opt.)
 * s: MAC(16-Digits) (opt.)
 * 
 * cmd:
 * '':		Version
 * list:	Alle MACs mit Zugriff auflisten (nur 'k' benoetigt)
 * details: Details zu einer MAC (quasi geich wie w_pcp.php)
 * quotaget: quota zu einer MAC holen
 * quotachange: quota (einzelne Eintraege oder alles) zu einer MAC aendern
 * sysparamget: sys_param.lxp holen
 * sysparamchange: sys_param.lxp aendern, ggf. neue Zeilen ergaenzen
 * sysparamunpend: ggfs. pending sys_param.lxp loeschen
 * onboard: Device anlegen und die wichtigsten Files schreiben, nicht aber z.B. iparam.lxp oder sys_param.lxp. 
 *          Es wird nichts ueberschrieben, hoechstens ergaenzt.
 * remove: Alle Daten des Device entfernen, inkl. Daten, Users und Guests
 *
 * *todo* dapikey schreiben *todo*
 *
 * Status-Returns:
 * 0:	OK
 * 100: Keine Tabelle mMAC fuer diese MAC
 * 101: Keine Parameter gefunden fuer diese MAC
 * 102: Unbekanntes Kommando cmd
 * 103: Write Quota
 * 104: Index Error bei quota
 * 105: sys_param not found
 * 106: Index Error bei sys_param.lxp
 * 107: No changes in sys_param.lxp
 * 108: Write Error sys_param.lxp
  * ...
 */

define('VERSION', "RAD V0.13 23.01.2024");

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

// Beschreibung der Parameter damit leichter lesbar
$p200_beschr = array(	// sys_param.lxp
	"*@200_Sys_Param",
	"APN[$41]",
	"Server/VPN[$41]",
	"Script/Id[$41]",
	"API Key[$41]",
	"ConFlags[0..255] (B0:Verbose B1:RoamAllow B4:LOG_FILE (B5:LOG_UART) B7:Debug)",
	"SIM Pin[0..65535] (opt)",
	"APN User[$41]",
	"APN Password[$41]",
	"Max_creg[10..255]",
	"Port[1..65535]",
	"Server_timeout_0[1000..65535]",
	"Server_timeout_run[1000..65535]",
	"Modem Check Reload[60..3600]",
	"Bat. Capacity (mAh)[0..100000]",
	"Bat. Volts 0%[float]",
	"Bat. Volts 100%[float]",
	"Max Ringsize (Bytes)[1000..2e31]",
	"mAmsec/Measure[0..1e9]",
	"Mobile Protocol[0..255] B0:0/1:HTTP/HTTPS B1:PUSH B2,3:TCP/UDPSetup"
);

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

	$dstr = gmdate("d.m.y H:i:s ", $now) . "UTC ";
	$log = @fopen("$fpath/log/pcplog.txt", 'a');
	if ($log) {
		while (!flock($log, LOCK_EX)) usleep(10000);  // Lock File - Is a MUST
		fputs($log,  $dstr . $_SERVER['REMOTE_ADDR'] . " RAD");        // Write file
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
		fputs($log, $dstr . "RAD $xlog\n");        // evt. add extras
		flock($log, LOCK_UN);
		fclose($log);
	}
}

try {
	// Check Access-Token for this Device
	function checkAccess($lmac, $ckey)
	{
		global $fpath;
		if ($ckey == S_API_KEY) return true;	// S_API_KEY valid for ALL
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
	function nverify($str, $ilow, $ihigh)
	{
		if (!is_numeric($str)) return true;
		$val = intval($str);
		if ($val < $ilow || $val > $ihigh) return true;	// Fehler
		return false;
	}
	function nisfloat($str)
	{	// PHP recht relaxed, alles als Float OK, daher mind. 1 char. - HELPER
		if (!is_numeric($str)) return true;
		return false;
	}

	// Pruefen ob quota OK (max. 3 Zeilen)
	function checkquota($quota)
	{
		if (!isset($quota) || $quota == false) return "400 'quota_days.dat' not found";
		if (count($quota) > 3) return "401 'quota_days.dat' overdue lines";
		$qd = intval(@$quota[0]);
		if (!$qd || $qd > 365000) return "402 'quota_days.dat' Days";
		$ql = intval(@$quota[1]);
		if ($ql < 100) return "403 'quota_days.dat' Lines<100";
		// Serverzeile ohne Pruefung
		return null;	// OK
	}

	// Pruefen einer Sysparamdatei - return NULL (OK) oder Status - (wie in BlueShell: BlueBlx.cs)
	function checksys_param($par)
	{
		// 1. Teil Pruefen der Gemeinsamen Parameter
		if (!isset($par) || $par == false || count($par)<19 ) return "200 'sys_param.lxp' not found";
		if ($par[0] !== '@200') return "201 File Format (No valid 'sys_param.lxp', ID must be '@200')";
		if (strlen($par[1]) > 41) return "202 APN Len";
		if (strlen($par[2]) > 41) return "203 Server Len";
		if (strlen($par[3]) > 41) return "204 Script Len";
		if (strlen($par[4]) > 41) return "205 API Key Len";
		if (nverify($par[5], 0, 255)) return "206 ConFlags";
		if (nverify($par[6], 0, 65535)) return "207 SIM PIN";
		if (strlen($par[7]) > 41) return "208 User Len";
		if (strlen($par[8]) > 41) return "209 Password Len";
		if (nverify($par[9], 10, 255)) return "210 Max_creg";
		if (nverify($par[10], 1, 65535)) return "211 Port";
		if (nverify($par[11], 1000, 65535)) return "212 Timeout_0";
		if (nverify($par[12], 1000, 65535)) return "213 Timeout_run";
		if (nverify($par[13], 60, 3600)) return "214 Reload";
		if (nverify($par[14], 0, 100000)) return "215 Bat. Capacity";
		if (nisfloat($par[15])) return "216 Bat. Volt 0%";
		if (nisfloat($par[16])) return "217 Bat. Volt 100%";
		if (nverify($par[17], 1000, 0x7FFFFFFF)) return "218 Max Ringsize";
		if (nverify($par[18], 0, 1000000000)) return "219 mAmsec/Measure";
		if (count($par) >19) if (nverify($par[19], 0, 255)) return "220 Mobile Protocol";
		return null;	// OK
	}

	function getcurrentsys_param()
	{
		global $fpath, $mac, $retResult, $status;
		$par = @file("$fpath/$mac/put/sys_param.lxp", FILE_IGNORE_NEW_LINES); // pending Parameters?
		if ($par != false) {
			$retResult['syspar_pending'] = true;	// Return - Pending Parameters!
		} else {
			$par = @file("$fpath/$mac/files/sys_param.lxp", FILE_IGNORE_NEW_LINES); // No NL, but empty Lines OK
			if ($par == false) {
				$status = "105 No 'sys_param.lxp' found for MAC:$mac";
			}
			$retResult['syspar_pending'] = false;	// On Dev.
		}
		return $par;
	}

	// Subfct. - Remove Directory
	function rmrf($dir) {
		foreach (glob($dir) as $file) {
			if (is_dir($file)) { 
				@rmrf("$file/*");
				@rmdir($file);
			} else {
				@unlink($file);
			}
		}
	}

	//=========== MAIN ==========
	$retResult = array();

	if ($dbg > 1) print_r($_REQUEST); // Was wollte man DBG (2)

	$cmd = @$_REQUEST['cmd'];
	if (!isset($cmd)) $cmd = "";
	$ckey = @$_REQUEST['k'];	// s always MAC (k: API-Key, r: Reason)
	if ($ckey == S_API_KEY) $xlog = "(cmd:'$cmd')"; // internal
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
			// Logger muss in 'devices' angelegt sein! Rest (Owner, Files, nur wenn explizit gefragt)
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
			if ($qres == false) throw new Exception("Select MAC $mac in 'devices'");
			$devres = $statement->fetch(); // $devres[]: 'device(mac)'!
			$ovv = array();	// Overview zu dieser MAC
			$ovv['mac'] = $mac;
			$ovv['db_now'] = $pdo->query("SELECT NOW() as now")->fetch()['now']; // *JETZT* als Datum UTC - Rein zurInfo
			$retResult['overview'] = $ovv;

			
	} // --- cmd PreEnde ---

	// --- cmd Main Start - CMD auswerten ---
	switch ($cmd) {
		case '': // VERSION
		case 'list': // Liste schon fertig
			break;

		case 'details':	// Einfach ALLES was wichtig ist fuer diese MAC checken
			$warncnt=0;
			if($devres === false) { // Device MUSS in 'devices' gelistet sein
				$retResult['warning_'.($warncnt++)] = "MAC not in 'devices'";
			}else{
				$retResult['details'] = $devres;
			}
			if (!file_exists("$fpath/$mac")){
				$retResult['warning_'.($warncnt++)] = "No directory for MAC";
			}else{
				if (!file_exists("$fpath/$mac/date0.dat"))
					$retResult['warning_'.($warncnt++)] = "File 'MAC/date.dat' not found";
				if (!file_exists("$fpath/$mac/device_info.dat"))
					$retResult['warning_'.($warncnt++)] = "File 'MAC/device_info.dat' not found";
				if (!file_exists("$fpath/$mac/quota_days.dat"))
					$retResult['warning_'.($warncnt++)] = "File 'MAC/quota_days.dat' not found";
				if (!file_exists("$fpath/$mac/files")){
					$retResult['warning_'.($warncnt++)] = "Directory 'MAC/files' not found";
				}else{
				if (!file_exists("$fpath/$mac/files/iparam.lxp"))
					$retResult['warning_'.($warncnt++)] = "File 'MAC/files/iparam.lxp' not found";
				if (!file_exists("$fpath/$mac/files/sys_param.lxp"))
					$retResult['warning_'.($warncnt++)] = "File 'MAC/files/sys_param.lxp' not found";
				}
			}

			break;

		// Ab hier: device directory muss vorhanden sein, wird nicht extra getestet
		case 'quotaget':
			// Original holen und pruefen
			$quota = @file("$fpath/$mac/quota_days.dat", FILE_IGNORE_NEW_LINES);
			$chkres = checkquota($quota);
			if ($chkres != null) {
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
			if ($chkres != null) {
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

		case 'sysparamget':
			$par = getcurrentsys_param();
			$chkres = checksys_param($par);
			if($chkres != null) { 
				$status = $chkres;
				break;
			}
			$vkarr = [];	// Ausgabe der Parameter etwas verzieren fuer leichtere Lesbarkeit
			$infoarr = $p200_beschr;
			$lcnt=0;
			foreach ($par as $p) {
				$info = @$infoarr[$lcnt];
				if(!isset($info)) $info = "(Undef.)"; // Unkown
				$info .= " (Line $lcnt)"; // Fuer jede Zeile: Erklaere Bedeutung
				$vkarr[] = array('line' => $p, 'info' => $info); // Line, Value, Text
				$lcnt++;
			}
			$ipov = array(); // Parameter Overview
			$retResult['sys_param'] = $vkarr;
			break;

		case 'sysparamchange':
			$opar = $par = getcurrentsys_param();
			$chkres = checksys_param($par);
			if($chkres != null) { 
				$status = $chkres;
				break;
			}
			$nparlist = $_REQUEST['sys_param'];
			foreach ($nparlist as $npk => $npv) {
				$idx = intval($npk);
				if ($idx < 1 || $idx > 30) { // Mehr als 30 Zeilen erstmal nie
					$status = "106 Index Error";
					break;
				}
				// Bei Bedarf neue Linien erzeugen, solange neuer Idx ausserhalb von existierendem:
				while ($idx > count($par)) { 
					$par[] = "";
				}
			}
			if (isset($status)) break;
			// Nun alles OK, Alte Werte durch neue ersetzen
			foreach ($nparlist as $npk => $npv) {
				$idx = intval($npk);
				$par[$idx] = $npv;
			}
			$chkres = checksys_param($par); // Nochmal pruefen
			if($chkres != null) { 
				$status = $chkres;
				break;
			}
			// Auf Delta pruefen 
			if(count($opar)==count($par)){
				for($i=0;$i<count($opar);$i++){
					if($opar[$i]!=$par[$i]) break;
				}
				if($i==count($opar)){
					$status = "107 No Changes found"; // Keine Aederungen
					break;
				}
			}
			// Aus Array File erzeugen
			$nparstr = implode("\n", $par) . "\n";
			$ilen = strlen($nparstr);
			@unlink("$fpath/$mac/cmd/sys_param.lxp.pmeta"); 
			if ($ilen > 32)	$slen = file_put_contents("$fpath/$mac/put/sys_param.lxp", $nparstr); // unter 32 len nicht sinnvoll
			else $slen = -1;
			if ($ilen == $slen) {
				file_put_contents("$fpath/$mac/cmd/sys_param.lxp.pmeta", "sent\t0\n");
				$wnpar = @file("$fpath/$mac/put/sys_param.lxp", FILE_IGNORE_NEW_LINES); // Set NewName?
				$xlog .= "(New Hardware-Parameter 'sys_param.lxp':$ilen)";
				$retResult['syspar_pending'] = true;
			} else {
				$xlog .= "(ERROR: Write 'sys_param.lxp':$slen/$ilen Bytes)";
				$status = "108 Write Parameter";
			}
			break;

			case 'sysparamunpend': // Remove pending sys_param.lxp
				@unlink("$fpath/$mac/cmd/sys_param.lxp.pmeta");
				@unlink("$fpath/$mac/put/sys_param.lxp");
				$par = getcurrentsys_param();
				if ($par == false) break;
				$xlog .= "(Remove pending Hardware-Parameter'sys_param.lxp')";
				break;
	
			case 'onboard': // Init/Add (most important) files for new device
				$infocnt=0;
				if($devres === false) { // wenn nicht in 'devices' gelistet
					$qres = $pdo->exec("INSERT INTO devices ( mac ) VALUES ( '$mac' )");
					$new_id = $pdo->lastInsertId();
					$xlog .= "((Re-)Added in 'devices' (ID:$new_id))";
					$retResult['info_'.($infocnt++)] = "(Re-)Added in 'devices' (ID:$new_id)";
				}

				$newdev = false;
				if (!file_exists("$fpath/$mac")){
					mkdir("$fpath/$mac");  // MainDirectory
					$xlog .= "(MAC directory created)";
					$retResult['info_'.($infocnt++)] = "MAC directory created (cmd: get Dir. and get 'sys_param.lxp')";
					$newdev = true; // Dir neu angelegt
				}
				if (!file_exists("$fpath/$mac/files")){
					mkdir("$fpath/$mac/files");  
					$xlog .= "(Directory 'MAC/files' created)";
					$retResult['info_'.($infocnt++)] = "Directory 'MAC/files' created";
				}
				if (!file_exists("$fpath/$mac/cmd")){
					mkdir("$fpath/$mac/cmd");  
					$xlog .= "(Directory 'MAC/cmd' created)";
					$retResult['info_'.($infocnt++)] = "Directory 'MAC/cmd' created";
					if ($newdev == true) {
						file_put_contents("$fpath/$mac/cmd/getdir.cmd", "123");	// 3 Tries to get Directoy
					}
				}
				if (!file_exists("$fpath/$mac/get")){
					mkdir("$fpath/$mac/get");  
					$xlog .= "(Directory 'MAC/get' created)";
					$retResult['info_'.($infocnt++)] = "Directory 'MAC/get' created";
					if ($newdev == true) {
						file_put_contents("$fpath/$mac/get/sys_param.lxp", "123");	// 3 Tries to get sys_param.lxp
					}
				}
				if (!file_exists("$fpath/$mac/put")){
					mkdir("$fpath/$mac/put");  
					$xlog .= "(Directory 'MAC/put' created)";
					$retResult['info_'.($infocnt++)] = "Directory 'MAC/put' created";
				}

				if (!file_exists("$fpath/$mac/date0.dat")){
					file_put_contents("$fpath/$mac/date0.dat", time()); // Note initial date
					$xlog .= "('MAC/date0.dat' created)";
					$retResult['info_'.($infocnt++)] = "'MAC/date0.dat' created";
				}
				if (!file_exists("$fpath/$mac/quota_days.dat")){
					file_put_contents("$fpath/$mac/quota_days.dat", DB_QUOTA); 
					$xlog .= "('MAC/quota_days.dat' (Default) created)";
					$retResult['info_'.($infocnt++)] = "'MAC/quota_days.dat' (Default) created";
				}
				if (!file_exists("$fpath/$mac/device_info.dat")){
					file_put_contents("$fpath/$mac/device_info.dat", ""); // Write DUMMY
					$xlog .= "(Dummy 'MAC/device_info.dat' created)";
					$retResult['info_'.($infocnt++)] = "Dummy 'MAC/device_info.dat' created";
				}

				// No iparam.lxp, no sys_param.lxp
				break;

			case 'remove': // Remove all device data
				$infocnt=0;
				if($devres !== false) { // wenn in 'devices' gelistet
					$pdo->query("DELETE FROM devices WHERE mac = '$mac'");
					$pdo->query("DELETE FROM guest_devices WHERE mac = '$mac'");
					if($pdo->query("SHOW TABLES LIKE 'm$mac'")->rowCount()>0){
						$pdo->query("DROP TABLE m$mac");
					}
					$xlog.="(Removed device from DB)";
					$retResult['info_'.($infocnt++)] = "Removed device from DB";
				}
				if (file_exists("$fpath/$mac")){
					rmrf("$fpath/$mac");
					$xlog.="(Removed device directory and files)";
					$retResult['info_'.($infocnt++)] = "Removed device directory and files";
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