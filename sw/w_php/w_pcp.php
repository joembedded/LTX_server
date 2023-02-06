<?php
/***********************************************************
 * w_pcp.php - push-cmd-pull worker fuer einzelne Logger LTX
 *
 * Entwickler: Juergen Wickenhaeuser, joembedded@gmail.com
 *
 * Beispielaufrufe (ggfs. $dbg) setzen):
 *
 * Fuer einfache CMDs per URL:
   http://localhost/ltx/sw/w_php/w_pcp.php?s=26FEA299F444F836&k=ABC&cmd=iparam
   http://localhost/ltx/sw/w_php/w_pcp.php?s=26FEA299F444F836&k=ABC&cmd=details
   http://localhost/ltx/sw/w_php/w_pcp.php?s=26FEA299F444F836&k=ABC&cmd=getdata&minid=1800
 * Um kompliziertere Sachen per JSON zu uebergeben z.B. per Script und jquery:
 * (Geht nat. umstaendlich auch per URL, z.B. ```&jo[lang]="de"&jo[age]=58```)
   http://localhost/wrk/pushpull/call_pcp.php
 *
 * Parameter:
 * cmd: Kommando
 * k: AccessKey (aus 'quota_days.dat') (opt.)
 * s: MAC(16-Digits) (opt.)
 * 
 * cmd:
 * '':		Version
 * list:	Alle MACs mit Zugriff auflisten (nur 'k' benoetigt)
 * details:	Kompletten Record zu dieser MAC aus 'devices'. Enth. noch viel optionale Platzhalter
 * iparam:	Parameter-File 'iparam.lxp' zu diesem Device
 * ***todo***:	iparam-pendinge-loeschen
 * ***todo***:	iparam-save
 * getdata:	mMAC ausgeben mit opt. minid und/oder maxid
 * 
 * Status-Returns:
 * 0:	OK
 * 100: Keine Tabelle mMAC fuer diese MAC
 * 101: Keine Parameter gefunden fuer diese MAC
 * 102: Unbekanntes Kommando cmd
 */

define ('VERSION',"LTX V1.01 06.02.2023"); 

error_reporting(E_ALL);
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

/************************************************************
 * Falls gewunscht koennten alle Zeilen mit Infos versehen
 * werden. Bei Bedarf bitte Bescheid geben
 * Die Parameter werden ja selten transportiert, daher kann man
 * sich denke ich den Luxus der Beschreibung erlauben
 ************************************************************/
// Beschreibung der Parameter damit leichter lesbar
$p100_beschr = array(
	"*@100_System", 
	"*DEVICE_TYP", // WICHTIG: Zeilen mit '*' duerfen NICHT vom User geendert werden
	"*MAX_CHANNELS", // *
	"*HK_FLAGS",     // *
	"*NewCookie [Parameter 10-digit Timestamp.32]", // (*) Bei aenderung neuer Zeitstempel hier eintragen!
	"Device_Name[BLE:$11/total:$41]",
	"Period_sec[10..86400]",	// Mess-Periode
	"Period_Offset_sec[0..(Period_sec-1)]",
	"Period_Alarm_sec[0..Period_sec]",
	"Period_Internet_sec[0..604799]",   // Internet-Uebertragungs-Periode
	"Period_Internet_Alarm_sec[0..Period_Internet_sec]",
	"UTC_Offset_sec[-43200..43200]",
	"Flags (B0:Rec B1:Ring) (0: RecOff)",
	"HK_flags (B0:Bat B1:Temp B2.Hum B3.Perc)",
	"HK_reload[0..255]",
	"Net_Mode (0:Off 1:OnOff 2:On_5min 3:Online)",
	"ErrorPolicy (O:None 1:RetriesForAlarms, 2:RetriesForAll)",
	"MinTemp_oC[-40..10]",
	"Period_Internet_Offset[0..Period_Internet_sec]",
);
$pkan_beschr = array(
	"*@ChanNo",  // (*) Neue Kanaele dazufuegen ist erlaubt, sofer aufsteigend und komplett
	"Action[0..65535] (B0:Meas B1:Cache B2:Alarms)",
	"Physkan_no[0..65535]",
	"Kan_caps_str[$8]",
	"Src_index[0..255]",
	"Unit[$8]",
	"Mem_format[0..255]",
	"DB_id[0..2e31]",
	"Offset[float]",
	"Factor[float]",
	"Alarm_hi[float]",
	"Alarm_lo[float]",
	"Messbits[0..65535]",
	"Xbytes[$32]"
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

	if(!isset($mac)) $mac="UNKNOWN_MAC";
	if ($dbg) $xlog .= "(DBG:$dbg)";

	$log = @fopen("$fpath/log/pcplog.txt", 'a');
	if ($log) {
		while (!flock($log, LOCK_EX)) usleep(10000);  // Lock File - Is a MUST
		fputs($log, gmdate("d.m.y H:i:s ", $now) . "UTC " . $_SERVER['REMOTE_ADDR'] );        // Write file
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
		fputs($log, gmdate("d.m.y H:i:s ", $now) . "UTC");
		fputs($log, " $xlog\n");        // evt. add extras
		flock($log, LOCK_UN);
		fclose($log);
	}
}


try {
	// Check Access-Token for this Device
	function checkAccess($lmac, $ckey){
		global $fpath;
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

	//=========== MAIN ==========
	$retResult = array();	
	
	if($dbg>1) print_r($_REQUEST); // Was wollte man DBG (2)
	
	$cmd= @$_REQUEST['cmd'];
	if(!isset($cmd)) $cmd="";
	$ckey = @$_REQUEST['k'];	// s always MAC (k: API-Key, r: Reason)
	$xlog = "(cmd:'$cmd', k:'$ckey')";

	// --- cmd PreStart - CMD vorfiltern ---
	switch($cmd){
	case "":
		$retResult['version'] = VERSION;
		break;

	case "list": // CMD'list' ALLE Devices zu DIESEM PW listen, MAC nicht noetig, Push-URL egal
			db_init(); // Access Ok, erst dann DB oeffnen (init global $pdo)

			$statement = $pdo->prepare("SELECT * FROM devices");
			$qres = $statement->execute();
			if ($qres == false) throw new Exception("DB 'devices'");
			$anz = $statement->rowCount();
			$macarr=array();
			for($i=0;$i<$anz;$i++){
				$ldev = $statement->fetch();
				$lmac = $ldev['mac'];
				if(checkAccess($lmac,$ckey)){
					$macarr[]=$lmac;
				}
			}
			if(!count($macarr)) throw new Exception("No Access");
			$retResult['list_count'] = count($macarr); // Allowed Devices
			$retResult['list_mac'] = $macarr;
			$cmd="";	// CMD Erledigt
			break;

	default: // Alle anderen CMDs sind Geratespezifsich
		// Im Normalfall erstmal feststellen ob Zugriff auf einzelnen Logger erlaubt
		$mac = @$_REQUEST['s'];	// s ist immer die MAC, muss bekannt sein
		if (!isset($mac)) $mac = "";
		if (strlen($mac) != 16) {
			throw new Exception("MAC len");
		}
		$mac = strtoupper($mac);
		if(!checkAccess($mac,$ckey)){
			throw new Exception("No Access");
		}
		db_init(); // Access Ok, erst dann DB oeffnen (init global $pdo)
		
		// Default-Infos fuer diese Teil
		$statement = $pdo->prepare("SELECT * FROM devices WHERE mac = ?");
		$qres = $statement->execute(array($mac));
		if ($qres == false) throw new Exception("MAC $mac not in 'devices'");
		$devres = $statement->fetch(); // $devres[]: 'device(mac)'!

		$ovv=array();	// Overview zu dieser MAC
		$ovv['db_now']= $pdo->query("SELECT NOW() as now")->fetch()['now']; // *JETZT* als Datum UTC - Rein zurInfo

		$statement = $pdo->prepare("SELECT MIN(id) as minid, MAX(id) as maxid FROM m$mac");
		$qres = $statement->execute();
		if ($qres == false){
			$maxid = $minid = -1; // No Data
			$status = "100 No Data for MAC"; // Error 100
		}else{
			$mm = $statement->fetch();
			$maxid = $mm['maxid'];
			$minid = $mm['minid'];
		}
		$ovv['min_id']=$minid;	// Minimale ID in mMAC (kann variiern, da CRON Autodrop alter Werte)
		$ovv['max_id']=$maxid;	// Maximaler ID in mMAC (Differenz+1 normalerweise (ungefaehr!) = 'anz_lines')
		if($cmd!='details'){	// WIchtige Overview-Daten, aber doppelt nicht noetig
			$ovv['last_change']=$devres['last_change'];	// Wann zuletzt Daten/irgendwas geandert
			$ovv['last_seen']=$devres['last_seen'];	// Wann zuletzt Daten angekommen
			$ovv['name']=$devres['name'];	// Name des Geraetes (wie in iparam.lxp)
			$ovv['units']=$devres['units']; // Die aktuellen verwendeten Kanaele/Einheiten 
			$ovv['vals']=$devres['vals'];	// Die letzten Werte aller verwendeten Kanaele
			$ovv['cookie']=$devres['cookie'];	// Cookie der Parameter 'iparam.lxp'
			$ovv['transfer_cnt']=$devres['transfer_cnt'];	// Anzahl der Transfers
			$ovv['lines_cnt']=$devres['lines_cnt'];	// Insgesmt uebertragene Zeilen Daten
			$ovv['warnings_cnt']=$devres['warnings_cnt']; // Aktuell vorh. Warnungen (z.B Innen-Feuchte)
			$ovv['alarms_cnt']=$devres['alarms_cnt'];	// Aktuell vor. Alarme (Grenzwert)
			$ovv['err_cnt']=$devres['err_cnt'];	// Aktuell vorh. Fehler (z.B. Netzfehler)
			$ovv['anz_lines']=$devres['anz_lines'];	// Aktuell vorh. Zeilen
		}
		$retResult['overview']=$ovv;
	} // --- cmd PreEnde ---

	// --- cmd Main Start - CMD auswerten ---
	switch($cmd){
	case 'details':	// Einfach ALLES fuer diese MAC
		$retResult['details']=$devres;
		break;
	case 'iparam': // Parameterfile fuer diese MAC
		$par = @file("$fpath/$mac/put/iparam.lxp", FILE_IGNORE_NEW_LINES); // pending Parameters?
		if ($par != false) {
			$retResult['par_pending'] = true;	// Return - Pending Parameters!
		} else {
			$par = @file("$fpath/$mac/files/iparam.lxp", FILE_IGNORE_NEW_LINES); // No NL, but empty Lines OK
			if ($par == false) {
				$status = "101 No Parameters found for MAC:$mac";
				break;
			}
			$retResult['par_pending'] = false;	// On Dev.
		}
		$vkarr=[];	// Ausgabe der Parameter etwas verzieren fuer leichtere Lesbarkeit
		foreach($par as $p){
			if(strlen($p) && $p[0]=='@'){
				$ktyp=intval(substr($p,1));
				if($ktyp<0 || $ktyp>100) throw new Exception("Parameter 'iparam.lxp' invalid");
				if($ktyp==100) {
					$infoarr=$p100_beschr;
					$lcnt=0;	// Damit MUSS es beginnen
					$erkl="Common";
				}else{
					$infoarr=$pkan_beschr;
					$erkl="Chan $ktyp";
				}
				$ridx=0;
			}
			$info = $infoarr[$ridx]." ($erkl, Line $lcnt)"; // Juer jede Zeile: Erklaere Bedeutung
			$vkarr[] = array('line' => $p,'info' => $info); // Line, Value, Text
			$ridx++;
			$lcnt++;
		}
		$retResult['iparam'] = $vkarr; 
		break;

	case 'getdata':
		// $minid und $maxid noch von oben, evtl. ueberschreiben
		$xlog .= "([";
		if(isset($_REQUEST['minid'])){
			$minid=intval($_REQUEST['minid']);
			$xlog .= $minid;	
		}
		$xlog .= "..";
		if(isset($_REQUEST['maxid'])) {
			$maxid=intval($_REQUEST['maxid']);
			$xlog .= $maxid;	
		}

		$statement = $pdo->prepare("SELECT * FROM m$mac WHERE   ( id >= ? AND id <= ? )");
		$qres = $statement->execute(array($minid,$maxid));
		if ($qres == false) throw new Exception("getdata");
		$anz = $statement->rowCount(); // No of matches
		$valarr =array();
		for ($i = 0; $i < $anz; $i++) {
			$user_row = $statement->fetch();
			$line_ts = $user_row['line_ts'];	// Wann eingepflegt in DB (UTC)
			$calc_ts = $user_row['calc_ts'];	// RTC des Gerates (UTC)
			$id = $user_row['id']; 				// Zeilen ID. *** Achtung: Nach ClearDevice beginnt die wieder bei 1 ***
			if($calc_ts == null) $calc_ts = $line_ts; // Sinnvoller Default falls Geraet ohne Zeit, z.B. nach RESET
			$line = $user_row['dataline'];	// Daten oder Messages - extrem flach organisiert fuer max. Flexibilitaet
			$ltyp="msg";
			try{
				if(strlen($line)> 11 && $line[0]=='!' && is_numeric($line[1])){
					$line = substr($line,strpos($line,' ')+1);
					$ltyp="val";
				}
			} catch (Exception $e){ 
				$ltyp="error";	// Markieren, aber durchreichen
			}	
/************************************************************
 * HIER WERDEN DIE DATEN ERSTMAL ***QUASI ROH** aingepflegt,
 * mit IT klaeren, welche Format GENAU gewuenscht 06.02.2023 JoWI
 ************************************************************/
			$valarr[] = array('id' => $id, 'line_ts' => $line_ts, 'calc_ts' => $calc_ts, 'type' => $ltyp, 'line' => $line);

		}
		$retResult['get_count'] = count($valarr); // Allowed Devices
		$retResult['get_data'] = $valarr;
		$xlog .= "]: ".count($valarr)." Lines)";

		break;

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
	exit("$errm\n");
	$xlog.="($errm)";
}

if(isset($pdo) && strlen($xlog)) add_logfile(); // // Nur ernsthafte Anfragen loggen
// ***