<?php
/***********************************************************
 * w_pcp.php - push-cmd-pull worker fuer einzelne Logger LTX
 *
 * Entwickler: Juergen Wickenhaeuser, joembedded@gmail.com
 *
 * Beispielaufrufe (ggfs. $dbg) setzen):
 *
 * Fuer einfache CMDs per URL (z.B. interne Aufrufe): k kann auch S_API_KEY sein!

//Basis-Aufruf / Ausgabe Version
http://localhost/ltx/sw/w_php/w_pcp.php?cmd

// Listet alle Devives zu diesem Key auf
http://localhost/ltx/sw/w_php/w_pcp.php?k=ABC&cmd=list 

// Device Details (device-Eintrag aus DB)
http://localhost/ltx/sw/w_php/w_pcp.php?s=26FEA299F444F836&k=ABC&cmd=details

// Daten Zeilen aus DB ausgeben (opt. limits minid/maxid)
http://localhost/ltx/sw/w_php/w_pcp.php?s=26FEA299F444F836&k=ABC&cmd=getdata&minid=1800 
http://localhost/ltx/sw/w_php/w_pcp.php?s=26FEA299F444F836&k='S_API_KEY'&cmd=getdata&minid=1800

// Parameter 'iparam.lxp' zu diesem Device mit Beschreibung ausgeben
http://localhost/ltx/sw/w_php/w_pcp.php?s=26FEA299F444F836&k=ABC&cmd=iparam
http://localhost/ltx/sw/w_php/w_pcp.php?s=DDC2FB99207A7E7E&k='S_API_KEY'&cmd=iparam

// Parameter 'iparam.lxp' zu diesem Device aendern 
http://localhost/ltx/sw/w_php/w_pcp.php?s=26FEA299F444F836&k=ABC&cmd=iparamchange&iparam[5]=NeuMeriva&iparam[6]=3601

// Pending Parameter zu diesem Device entfernen
http://localhost/ltx/sw/w_php/w_pcp.php?s=26FEA299F444F836&k=ABC&cmd=iparamunpend

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
 * iparamrunpend:	iparam-pendinge-loeschen
 * iparamchange:	Parameter aendern und speichern
 * getdata:	mMAC ausgeben mit opt. minid und/oder maxid
 * 
 * Status-Returns:
 * 0:	OK
 * 100: Keine Tabelle mMAC fuer diese MAC
 * 101: Keine Parameter gefunden fuer diese MAC
 * 102: Unbekanntes Kommando cmd
 * 103: Mehr als 90 Messkanaele nicht moeglich
 * 104,105: Index Error bei 'iparam'
 * 106: Keine geaenderten Parameter gefunden
 * ...
 * 300-699: Wie BlueBlx.cs (siehe checkiparam())
 * ...
 */

define('VERSION', "LTX V1.11 14.01.2024");

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

/************************************************************
 * Falls gewunscht koennten alle Zeilen mit Infos versehen
 * werden. Bei Bedarf bitte Bescheid geben
 * Die Parameter werden ja selten transportiert, daher kann man
 * sich denke ich den Luxus der Beschreibung erlauben
 ************************************************************/
// Beschreibung der Parameter damit leichter lesbar
$p100beschr = array( // SIZE der gemeinsamen Parameter hat MINIMALE Groesse
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
	"Config0_U31 (B0:OffPer.Inet:On/Off B1,2:BLE:On/Mo/Li/MoLi B3:EnDS B4:CE:Off/On B5:Live:Off/On)",
	"Configuration_Command[$79]",	
);
$pkanbeschr = array( // SIZE eines Kanals ist absolut FIX
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

$parLastChanIdx=-1; 
$parChan0Idx=-1;
$parChanSize=-1;
$parLastChanNo=-1;


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
		fputs($log,  $dstr. $_SERVER['REMOTE_ADDR']. " PCP");        // Write file
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
		fputs($log, $dstr."PCP $xlog\n");        // evt. add extras
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

	function getcurrentiparam()
	{
		global $fpath, $mac, $retResult, $status;
		$par = @file("$fpath/$mac/put/iparam.lxp", FILE_IGNORE_NEW_LINES); // pending Parameters?
		if ($par != false) {
			$retResult['par_pending'] = true;	// Return - Pending Parameters!
		} else {
			$par = @file("$fpath/$mac/files/iparam.lxp", FILE_IGNORE_NEW_LINES); // No NL, but empty Lines OK
			if ($par == false) {
				$status = "101 No Parameters found for MAC:$mac";
			}
			$retResult['par_pending'] = false;	// On Dev.
		}
		return $par;
	}

	// Prueft Zahlenwert auf Grenzen
	function nverify($str, $ilow, $ihigh){
		if(!is_numeric($str)) return true;
		$val = intval($str);
		if($val<$ilow || $val>$ihigh) return true;	// Fehler
		return false;
	}
	function nisfloat($str){	// PHP recht relaxed, alles als Float OK, daher mind. 1 char.
		if(!is_numeric($str)) return true;
		return false;
	}
	// Pruefen einer Parameterdatei - return NULL (OK) oder Status - (wie in BlueShell: BlueBlx.cs)
	function checkiparam($par)
	{
		global $parLastChanIdx, $parLastChanNo, $parChanSize, $pkanbeschr, $parChan0Idx;

		$parLastChanIdx=-1; // unset incompat. to global
		$parChan0Idx=-1;
		// 1. Teil Pruefen der Gemeinsamen Parameter
		if ($par[0] !== '@100') return "301 File Format (No valid 'iparam.lxp', ID must be '@100')";
		for ($i = 1; $i < count($par); $i++) {	// Scan for last parameter in Src
			if (@$par[$i][0] == '@') {
				$parLastChanIdx = $i;
				if($parChan0Idx<0) $parChan0Idx = $i;
			}
		}
		if ($parLastChanIdx<0) return "300 File Size 'iparam.lxp' (too small)";
		$parLastChanNo = intval(substr($par[$parLastChanIdx], 1));
		if ($parLastChanNo<0 || $parLastChanNo > 89) return "399 Invalid Parameters 'iparam.lxp";
		if ($parLastChanNo>= intval($par[2])) return "398 Too many channels";
		$parChanSize = count($par) - $parLastChanIdx;
		if($parChanSize!=count($pkanbeschr)) return "300 File Size 'iparam.lxp' (too small)";
		// Anfangsteil checken 
		if(nverify($par[1],0,9999)) return "302 Illegal DEVICE_TYP";
		if(nverify($par[2],1,90)) return "303 MAX_CHANNELS out of range";
		if(nverify($par[3],0,255)) return "304 HK_FLAGS out of range";
		if(strlen($par[4])!= 10) return "305 Cookie (must be exactly 10 Digits)";
		if(strlen($par[5])>41) return "306 Device Name Len"; // len=0: Use DefaultAdvertising Name
		if(nverify($par[6],10,86400)) return "307 Measure Period out of range";
		if(nverify($par[7],0,intval($par[6])-1)) return "308 Period Offset (must be < than Period)";
		if(nverify($par[8],0,intval($par[6]))) return "309 Alarm Period out of range";
		if(nverify($par[9],0,604799)) return "310 Internet Period out or range";
		if(nverify($par[10],0,intval($par[9]))) return "311 Internet Alarm Period (must be <= than Internet Period)";
		if(nverify($par[11],-43200,43200)) return "312: UTC Offset out or range";

		if(nverify($par[12],0,255)) return "313 Record Flags out of range";
		if(nverify($par[13],0,255)) return "314 HK Flags out of range";
		if(nverify($par[14],0,255)) return "315 HK Reload out of range";
		if(nverify($par[15],0,255)) return "316 Net Mode out of range";
		if(nverify($par[16],0,255)) return "317 Error Policy out of range";
		if(nverify($par[17],-40,10)) return "318 MinTemp oC out of range";
		if(nverify($par[18],0,0x7FFFFFFF)) return "319 U31_Unused";
		if(strlen($par[19])>79) return "320 Configuration Command Len"; 

		$pidx = $parChan0Idx;
		if($pidx< 19 ) return "600: Missing Channel #0 (at least 1 channel required)"; // Min. iparam
		$chan = 0;
		for(;;){
			if (@$par[$pidx][0] != '@' || intval(substr($par[$pidx],1)!=$chan))	return "615 Unexpected Line in Channel #$chan";

			if(nverify($par[$pidx+1],0,255)) return "602 Action for Channel #$chan";
			if(nverify($par[$pidx+2],0,65535)) return "603 PhysChan for Channel #$chan";
			if(strlen($par[$pidx+3])>8 ) return "604 KanCaps Len for Channel #$chan";
			if(nverify($par[$pidx+4],0,255)) return "605 SrcIndex for Channel #$chan";
			if(strlen($par[$pidx+5])>8) return "606 Unit Len for Channel #$chan";
			if(nverify($par[$pidx+6],0,255)) return "607 Number Format  for Channel #$chan";
			if(nverify($par[$pidx+7],0,0x7FFFFFFF)) return "608 DB_ID for Channel #$chan";
			if(nisfloat($par[$pidx+8])) return "609 Offset for Channel #$chan (Format requires Decimal Point)";
			if(nisfloat($par[$pidx+9])) return "610 Factor for Channel #$chan (Format requires Decimal Point)";
			if(nisfloat($par[$pidx+10])) return "611 Alarm_Hi for Channel #$chan (Format requires Decimal Point)";
			if(nisfloat($par[$pidx+11])) return "612 Alarm_Low for Channel #$chan (Format requires Decimal Point)";
			if(nverify($par[$pidx+12],0,65535)) return "613 MeasBits for Channel #$chan";
			if(strlen($par[$pidx+13])>32) return "614 XBytes Len for Channel #$chan";
			if($pidx == $parLastChanIdx ) break;
			$chan++;
			$pidx +=  $parChanSize;
		}
			
		// Kanalteil checken
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

			if ($pdo->query("SHOW TABLES LIKE 'm$mac'")->rowCount() === 0){
				$maxid = $minid = -1; // No Data
				$status = "100 No Data for MAC"; // Error 100
			}else{
				$statement = $pdo->prepare("SELECT MIN(id) as minid, MAX(id) as maxid FROM m$mac");
				$qres = $statement->execute();
				if ($qres == false) throw new Exception("No table'm$mac'");
				$mm = $statement->fetch();
				$maxid = $mm['maxid'];
				$minid = $mm['minid'];
			}
			$ovv['min_id'] = $minid;	// Minimale ID in mMAC (kann variiern, da CRON Autodrop alter Werte)
			$ovv['max_id'] = $maxid;	// Maximaler ID in mMAC (Differenz+1 normalerweise (ungefaehr!) = 'anz_lines')
			if ($cmd != 'details') {	// WIchtige Overview-Daten, aber doppelt nicht noetig
				$ovv['last_change'] = $devres['last_change'];	// Wann zuletzt Daten/irgendwas geandert
				$ovv['last_seen'] = $devres['last_seen'];	// Wann zuletzt Daten angekommen
				$ovv['name'] = $devres['name'];	// Name des Geraetes (wie in iparam.lxp)
				$ovv['units'] = $devres['units']; // Die aktuellen verwendeten Kanaele/Einheiten 
				$ovv['vals'] = $devres['vals'];	// Die letzten Werte aller verwendeten Kanaele
				$ovv['cookie'] = $devres['cookie'];	// Cookie der Parameter 'iparam.lxp' (wie aktuell auf Geraet)
				$ovv['transfer_cnt'] = $devres['transfer_cnt'];	// Anzahl der Transfers
				$ovv['lines_cnt'] = $devres['lines_cnt'];	// Insgesmt uebertragene Zeilen Daten
				$ovv['warnings_cnt'] = $devres['warnings_cnt']; // Aktuell vorh. Warnungen (z.B Innen-Feuchte)
				$ovv['alarms_cnt'] = $devres['alarms_cnt'];	// Aktuell vor. Alarme (Grenzwert)
				$ovv['err_cnt'] = $devres['err_cnt'];	// Aktuell vorh. Fehler (z.B. Netzfehler)
				$ovv['anz_lines'] = $devres['anz_lines'];	// Aktuell vorh. Zeilen
			}
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

		case 'iparam': // Parameterfile fuer diese MAC
			$par = getcurrentiparam();
			if ($par == false) break;
			$chkres = checkiparam($par);
			if($chkres != null) { 
				$status = $chkres;
				break;
			}
			$vkarr = [];	// Ausgabe der Parameter etwas verzieren fuer leichtere Lesbarkeit
			foreach ($par as $p) {
				if (strlen($p) && $p[0] == '@') {
					$ktyp = intval(substr($p, 1));
					if ($ktyp < 0 || $ktyp > 100) throw new Exception("Parameter 'iparam.lxp' invalid");
					if ($ktyp == 100) {
						$infoarr = $p100beschr;
						$lcnt = 0;	// Damit MUSS es beginnen
						$erkl = "Common";
					} else {
						$infoarr = $pkanbeschr;
						$erkl = "Chan $ktyp";
					}
					$ridx = 0;
				}
				$info = $infoarr[$ridx] . " ($erkl, Line $lcnt)"; // Juer jede Zeile: Erklaere Bedeutung
				$vkarr[] = array('line' => $p, 'info' => $info); // Line, Value, Text
				$ridx++;
				$lcnt++;
			}
			$ipov = array(); // Parameter Overview
			$ipov['chan0_idx'] =  $parChan0Idx; // Index Channel 0
			$ipov['chan_anz'] = $parLastChanNo+1; // Anzahl Channels
			$ipov['lines_per_chan'] =  $parChanSize; // Lines per channel

			$retResult['iparam_meta'] = $ipov;

			$retResult['iparam'] = $vkarr;
			break;

		case 'iparamchange': // Parameter changes sorgfaeltig einpflegen. Probleme melden
			$opar = $par = getcurrentiparam();
			if ($par == false) break;
			$chkres = checkiparam($par);
			if($chkres != null) { 
				$status = $chkres;
				break;
			}
			$nparlist = $_REQUEST['iparam'];
			foreach ($nparlist as $npk => $npv) {
				$idx = intval($npk);
				if ($idx < 5 || $idx > 1999) { // 0..3: Header
					$status = "104 Index Error";
					break;
				}

				// Bei Bedarf neue Kanaele erzeugen, solange neuer Idx ausserhalb von existierendem:
				while ($idx > count($par)) { 
					$parLastChanNo++;
					$par[] = '@' . $parLastChanNo;
					$par[] = 0;	// ACTION als 0 vorgeben
					for ($i = 2; $i < $parChanSize; $i++) $par[] = $par[$parLastChanIdx + $i]; // Letzten Kanal duplizieren
					$parLastChanIdx+=$parChanSize;
				}
				if($parLastChanNo>89){
					$status = "103 Too many Channels"; // max. 90 Kanaele
					break;
				}
				if ($idx >= $parLastChanIdx && ($idx - $parLastChanIdx) % $parChanSize == 0) {
					$status = "105 Index Error (Index/Line $idx)"; // Kanalnr. nicht aenderbar '@xx'
					break;
				}

			}
			if (isset($status)) break;
			// Nun alles OK, Alte Werte durch neue ersetzen
			foreach ($nparlist as $npk => $npv) {
				$idx = intval($npk);
				$par[$idx] = $npv;
			}
			// Parameter vom Ende her kompaktieren. 
			while($parLastChanNo>0){
				if(intval($par[$parLastChanIdx+1])) break;	// Action is 0: Wird also nicht verwendet, kann raus
				for($i=0;$i<$parChanSize;$i++) array_pop($par);	// Kanal unbelegt, entfernen
				$parLastChanNo--;
				$parLastChanIdx-=$parChanSize;
			}

			$chkres = checkiparam($par); // Nochmal pruefen
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
					$status = "106 No Changes found"; // Keine Aederungen
					break;
				}
			}

			// Aus Array File erzeugen
			$par[4]=time();	// Neuen Cookie dafuer
			$nparstr = implode("\n", $par) . "\n";
			$ilen = strlen($nparstr);
			@unlink("$fpath/$mac/cmd/iparam.lxp.pmeta");
			if ($ilen > 32)	$slen = file_put_contents("$fpath/$mac/put/iparam.lxp", $nparstr);
			else $slen = -1;
			if ($ilen == $slen) {
				file_put_contents("$fpath/$mac/cmd/iparam.lxp.pmeta", "sent\t0\n");
				$wnpar = @file("$fpath/$mac/put/iparam.lxp", FILE_IGNORE_NEW_LINES); // Set NewName?
				if ($wnpar != false) {
					$snn = $pdo->prepare("UPDATE devices SET name = ? WHERE mac = ?");
					$snn->execute(array(@$par[5], $mac));
					$retResult['overview']['name']=@$par[5];
				}
				$xlog .= "(New Hardware-Parameter 'iparam.lxp':$ilen)";
				$retResult['par_pending'] = true;
			} else {
				$xlog .= "(ERROR: Write 'iparam.lxp':$slen/$ilen Bytes)";
				$status = "107 Write Parameter";
			}
			break;

		case 'iparamunpend':
			@unlink("$fpath/$mac/cmd/iparam.lxp.pmeta");
			@unlink("$fpath/$mac/put/iparam.lxp");
			$par = getcurrentiparam();
			if ($par == false) break;
			$snn = $pdo->prepare("UPDATE devices SET name = ? WHERE mac = ?");
			$snn->execute(array(@$par[5], $mac));
			$retResult['overview']['name']=@$par[5];
			$xlog .= "(Remove pending Hardware-Parameter'iparam.lxp')";
			break;

		case 'getdata':
			if (isset($status)) break;

			// $minid und $maxid noch von oben, evtl. ueberschreiben
			$xlog .= "([";
			if (isset($_REQUEST['minid'])) {
				$minid = intval($_REQUEST['minid']);
				$xlog .= $minid;
			}
			$xlog .= "..";
			if (isset($_REQUEST['maxid'])) {
				$maxid = intval($_REQUEST['maxid']);
				$xlog .= $maxid;
			}

			$statement = $pdo->prepare("SELECT * FROM m$mac WHERE   ( id >= ? AND id <= ? )");
			$qres = $statement->execute(array($minid, $maxid));
			if ($qres == false) throw new Exception("getdata");
			$anz = $statement->rowCount(); // No of matches
			$valarr = array();
			for ($i = 0; $i < $anz; $i++) {
				$user_row = $statement->fetch();
				$line_ts = $user_row['line_ts'];	// Wann eingepflegt in DB (UTC)
				$calc_ts = $user_row['calc_ts'];	// RTC des Gerates (UTC)
				$id = $user_row['id']; 				// Zeilen ID. *** Achtung: Nach ClearDevice beginnt die wieder bei 1 ***
				if ($calc_ts == null) $calc_ts = $line_ts; // Sinnvoller Default falls Geraet ohne Zeit, z.B. nach RESET
				$line = $user_row['dataline'];	// Daten oder Messages - extrem flach organisiert fuer max. Flexibilitaet
				$ltyp = "msg";
				try {
					if (strlen($line) > 11 && $line[0] == '!' && is_numeric($line[1])) {
						$line = substr($line, strpos($line, ' ') + 1);
						$ltyp = "val";
					}
				} catch (Exception $e) {
					$ltyp = "error";	// Markieren, aber durchreichen
				}
				/************************************************************
				 * HIER WERDEN DIE DATEN ERSTMAL ***QUASI ROH** eingepflegt,
				 * mit IT klaeren, welches Format GENAU gewuenscht 07.02.2023 JoWI
				 ************************************************************/
				$valarr[] = array('id' => $id, 'line_ts' => $line_ts, 'calc_ts' => $calc_ts, 'type' => $ltyp, 'line' => $line);
			}
			$retResult['get_count'] = count($valarr); // Allowed Devices
			$retResult['get_data'] = $valarr;
			$xlog .= "]: " . count($valarr) . " Lines)";

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
	echo $errm;
	$xlog .= "($errm)";
}

if (isset($pdo) && strlen($xlog)) add_logfile(); // // Nur ernsthafte Anfragen loggen
// ***