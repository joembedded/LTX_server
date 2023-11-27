<?php
/* ipush.php - internal immerdiate push 
	http://localhost/ltx/sw/vpnf/ipush.php?s=DDC2FB99207A7E7E&k=S_API_KEY
	http://localhost/ltx/sw/w_php/w_pcp.php?s=DDC2FB99207A7E7E&k=S_API_KEY&cmd=getdata&minid=80

	Version 1.3 - 27.11.2023 - JoWi
	####
	####
	#### MIS gerade in Arbeit -> convert2mis(). IN Zeile 451 dann stoppen!
	####
	####

	ConfCmd: PROTOCOL FORMAT[/DIR] STATIONID  URL PORT USER PW 
	Bsp:     FTPSSL CSV Bach123 s246.goserver.host 21 web28f3 qfile57
	Bsp:     FTPSSL CSV/mydir Bach123 s246.goserver.host 21 web28f3 qfile57

	Protocol:
		FTP: unencrypted FTP (normally Port 21)
		FTPSSL FTP with explizit encryption (normally Port 21)

	Format (optional subformat after '-'): 
		CSV: Basic CSV Format - All lines as CSV (including '<>' meta lines, Separator: ';')
		CSV-0: Only data lines as CSV, else like Basic Format
		ZRXP: Simple standard ZRXP Format
		MIS: Simple MIS Format *##### inArbeit #####*

	Dir: Main directory in FTP, optionally followed Format after '/'
		e.g. CSV-0/mydir

	StationId: 
		String,1-8 characters, used as filename-prefix for upload 
		(e.g. 'Bach123' writes files 'Bach123_20231015181223.txt')
		StationID kann aich .EXT enthalten, siehe 'wildcard2name()'
	
	URL / PORT / USER: FTP credentials
*/

error_reporting(E_ALL);
ini_set("display_errors", true);
include("../conf/api_key.inc.php");
include("../conf/config.inc.php");	// DB Access Param
include("../inc/db_funcs.inc.php"); // Init DB

set_time_limit(600); // 10 Min runtime

define('VERSION', "V1.3 27.11.2023");

// --- Functons --------
function exit_error($err)
{
	global $xlog;
	echo "ERROR: '$err'\n";
	$xlog .= "(ERROR:'$err')";
	add_logfile();
	exit();
}

function add_logfile()
{
	global $xlog, $dbg, $now, $mac;

	$sdata = "../" . S_DATA;
	// Global log
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
		fputs($log, " MAC:$mac $xlog\n");        // evt. add extras
		flock($log, LOCK_UN);
		fclose($log);
	}

	// Local log
	$logpath = $sdata . "/$mac/";
	if (@filesize($logpath . "log.txt") > 50000) {	// Device LOG
		@unlink($logpath . "_log_old.txt");
		rename($logpath . "log.txt", $logpath . "_log_old.txt");
	}

	$log = fopen($logpath . "log.txt", 'a');
	if (!$log) return;
	while (!flock($log, LOCK_EX)) usleep(10000);  // Lock File - Is a MUST
	fputs($log, gmdate("d.m.y H:i:s ", $now) . "UTC");
	fputs($log, " $xlog\n");        // evt. add extras
	flock($log, LOCK_UN);
	fclose($log);
	echo "LOG nach $logpath\n";
}

/* Name evtl. mappen. Baut Namen um.
* Im Namen wird *X ersetzt durch mit X als
*  T oder '' (nichts) : UTC-Zeit in Sekunden, Reverse Bsp.: * oder *T wird zu 20231017165922
*  H: UTC-Zeit in Stunden, Reverse Bsp.: *H wird zu 2023101716
*  D: UTC-Zeit in Tagen, Reverse Bsp.: *D wird zu 20231017
*  M: UTC-Zeit in Monaten, Reverse Bsp.: *M wird zu 202310
*  Y: UTC-Zeit in Jahren, Reverse Bsp.: *Y wird zu 2023
*  N: Geraetename (wi in den Systemoarameter) Bsp.: Dev_*N wird zu Dev_Pegel33
*  #:  16-Stellige MAC. Bsp.: STS_*# wird zu STS_0123456789ABCDEF
*  andere: ignoriert  Bsp.: *k wird k
*
* Es sind auch mehrere Ersetzungen moeglich, z.B. Dev_*N_** wird tz Dev_Pegel33_0123456789ABCDEF
*/
function wildcard2name($wc)
{
	global $mac, $ipar_obj, $now;
	$idx = strpos($wc, '*');
	while ($idx !== false) {
		$t = @$wc[$idx + 1];
		if ($t === '*') $t = "";;
		$wa = substr($wc, 0, $idx);	// Nur Anfang nehmen
		$we = substr($wc, $idx + (strlen($t) ? 2 : 1));
		switch ($t) {
			case '': // Nix
			case 'T':
				$wc = $wa . gmdate("YmdHis", $now) . $we;
				break;
			case 'H':
				$wc = $wa . gmdate("YmdH", $now) . $we;
				break;
			case 'D':
				$wc = $wa . gmdate("Ymd", $now) . $we;
				break;
			case 'M':
				$wc = $wa . gmdate("Ym", $now) . $we;
				break;
			case 'Y':
				$wc = $wa . gmdate("Y", $now) . $we;
				break;
			case 'N': // Name anhaengen
				$wc = $wa . $ipar_obj->overview->name . $we;
				break;
			case '#':	// *#  MAC anhaengen
				$wc = $wa . $mac . $we;
				break;
			default: // Default: ignorieren
				$wc = $wa . $t . $we;
		}
		$idx = strpos($wc, '*');
	}
	return str_replace(array('/', '$', '\\', '@', ':', '?'), '_', $wc);
}

// Transferiert lokales File auf FTP mit Namensaenderung
function transfer_ftp($prot, $local_filename, $rdir, $remote_filename, $ftp_server, $ssl_flag, $ftp_port, $ftp_user_name, $ftp_user_pass)
{
	global $xlog, $dpath;
	$transfer_modus = FTP_BINARY; // Der Transfer-Modus muss entweder FTP_ASCII oder FTP_BINARY sein.
	if ($ssl_flag) $conn_id = ftp_ssl_connect($ftp_server, $ftp_port);
	else $conn_id = ftp_connect($ftp_server, $ftp_port);
	if ($conn_id == false) {
		file_put_contents("$dpath/cmd/okreply.cmd", "$prot:Connection Error");
		exit_error("Connection to '$ftp_server' failed");
	}
	if (ftp_login($conn_id, $ftp_user_name, $ftp_user_pass) == false) {
		file_put_contents("$dpath/cmd/okreply.cmd", "$prot:Login Error");
		exit_error("Login '$ftp_user_name' failed");
	}
	ftp_pasv($conn_id, true); // Passiven Modus wg. Firewall besser

	if (isset($rdir)) {	// Wenn rdir angegeben: ggfs. erzeugen und betreten
		$rdir = wildcard2name($rdir);
		if (strlen($rdir)) {
			if (!@ftp_chdir($conn_id, $rdir)) {
				if (!@ftp_mkdir($conn_id, $rdir)) $xlog .= "(Error: Make Dir '$rdir' failed)";
				else {
					$xlog .= "(Make Dir '$rdir')";
					@ftp_chdir($conn_id, $rdir);
				}
			}
		}
	}

	$loc_filehandle = @fopen($local_filename, "r");
	if ($loc_filehandle == false) {
		file_put_contents("$dpath/cmd/okreply.cmd", "$prot:Read Error");
		exit_error("File '$local_filename' not found");
	}
	$putfilesize = filesize($local_filename);
	$remote_dir = ftp_pwd($conn_id);
	if (ftp_fput($conn_id, $remote_filename, $loc_filehandle, $transfer_modus) == false) {
		file_put_contents("$dpath/cmd/okreply.cmd", "$prot:Put Error");
		exit_error("Put '$remote_dir/$remote_filename' failed");
	}
	fclose($loc_filehandle);
	ftp_close($conn_id);
	$xlog .= "($prot:Put '$remote_dir/$remote_filename', $putfilesize Bytes)"; // 2Slash Haupt, 1/Sub
}

function get_pcp($xcmd) // xcmd ohne cmd, aber Parameter URL codiert, e.g. iparam&minid=123
{
	global $mac;
	$script = $_SERVER['PHP_SELF'];	// /xxx.php
	$lp = strpos($script, "sw"); // Path
	$sroot = substr($script, 0, $lp - 1);
	if (HTTPS_SERVER != null) $sec = "https://" . HTTPS_SERVER;
	else $sec = "http://" . $_SERVER['HTTP_HOST'];
	$sqs = $sec . $sroot . "/sw/w_php/w_pcp.php?k=" . S_API_KEY . "&s=$mac&cmd=$xcmd";
	$ch = curl_init($sqs);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	$result = curl_exec($ch);
	if (curl_errno($ch)) exit_error("CurlErrno'" . curl_error($ch) . "'");
	curl_close($ch);
	$obj = @json_decode($result);
	if (!isset($obj->status) || strcmp(substr($obj->status, 0, 4), "0 OK")) exit_error("CurlResult:'" . trim($result) . "'");
	return $obj;
}
//------- Konvertierungen -------------------
// --- CSV Formate ---
function convert2csv($subf)
{
	global $fdata, $xlines; // Input - Output

	$funits = explode(' ', $fdata->overview->units);
	$fuarr = array();
	$frema = array();	// Reverse search

	$xhdr = "TIME(UTC)";
	foreach ($funits as $fkuv) {
		$fka = @explode(':', $fkuv);
		$kan = intval(@$fka[0]);
		$val = @$fka[1];
		$fuarr[$kan] = $val;
		$frema[] = $kan;
		$xhdr .= ";$val($kan)";
	}
	$xlines = array($xhdr . "\n");	// Exportierte Daten

	$danz = $fdata->get_count; // evt. $danz limitieren, Index startet mit 1 $ipar_obj->overview->max_id+1
	for ($i = 0; $i < $danz; $i++) {
		$typ = $fdata->get_data[$i]->type;
		$lcont = $fdata->get_data[$i]->line;
		if ($typ == 'msg' && @$lcont[0] == '<' && $subf !== "0") {
			$xline = $fdata->get_data[$i]->calc_ts . ";" . $lcont;
			$xlines[] = $xline . "\n";
		} else if ($typ == 'val') {
			$xline = $fdata->get_data[$i]->calc_ts;
			$larr = explode(' ', $lcont);
			$pox = 0;
			foreach ($larr as $lcuv) {
				$kerw = $frema[$pox++]; // Erwarteter Kanal hier
				$lka = @explode(':', $lcuv);
				$kist = intval(@$lka[0]); // Was ist (evtl. schon weiter)
				while ($kerw < $kist && $pox < 100) { // Sicherheitsgrenze
					$xline .= ";";
					$kerw = $frema[$pox++];
				}
				$val = @$lka[1];
				$xline .= ";$val";
			}
			$xlines[] = $xline . "\n";
		}
	}
}

// --- ZXRP Format ---
function convert2zxrp()
{
	global $fdata, $xlines; // Input - Output
	global $station; // Als Serial
	global $tzo;	// Timezone

	$dsno = $station;	// Destination Serial No (**NOCH FIX**, steht im File und im Dateinamen???)
	$chans = explode(' ', $fdata->overview->units);
	$anz_kans = count($chans);
	$anz_lines = count($fdata->get_data);
	$xlines = array();
	$xlines[] = "#TZUTC0|*|\n";	// Timezone UTC
	for ($kan = 0; $kan < $anz_kans; $kan++) {
		$kex = explode(':', $chans[$kan]);
		$kno = $kex[0];	// Kanal-Nummer
		$kunit = $kex[1];	// Kanal-Unit
		$klcnt = 0;
		for ($i = 0; $i < $anz_lines; $i++) {
			$lobj = $fdata->get_data[$i];
			if ($lobj->type != 'val') continue;	// Ignore Messages, etc..
			$lex = explode(' ', $lobj->line); // Line in KAN:VAL - Array
			for ($ik = 0; $ik < count($lex); $ik++) {
				$lik = explode(':', $lex[$ik]);
				if (!strcmp($lik[0], $kno)) {
					if (!$klcnt) { // Header wenn neu
						$xlines[] = "\n";
						$xlines[] = "#REXCHANGE$dsno" . "_KANAL$kno|*|\n";
						$xlines[] = "##CCHANNEL_KANAL$kno|*|CCHANNELNO$kno|*|CUNIT$kunit|*|\n";
						$klcnt = 3;
					}
					$dtsec = date_create($lobj->calc_ts, $tzo)->getTimestamp();
					$ldtcomp = gmdate("YmdHis", $dtsec);
					$xlines[] = $ldtcomp . "\t" . $lik[1] . "\n";
					$klcnt++;
					break;
				}
			}
		}
	}
}

// ---  MIS Format ---
function convert2mis()
{
	global $fdata, $xlines; // Input - Output
	global $station; // Als Serial
	global $tzo;	// Timezone

	$dsno = $station;	// Destination Serial No (**NOCH FIX**, steht im File und im Dateinamen???)
	$chans = explode(' ', $fdata->overview->units);
	$anz_kans = count($chans);
	$anz_lines = count($fdata->get_data);

	$xlines = array();
	$xlines[] = "Ueberschrift: Station $dsno\n";	// z.B. Station oder Timezone UTC

	for ($kan = 0; $kan < $anz_kans; $kan++) { // Fuer jeden Kanal die Liste durchchecken, wie ZXRP
		$kex = explode(':', $chans[$kan]);
		$kno = $kex[0];	// Kanal-Nummer
		$kunit = $kex[1];	// Kanal-Unit
		$klcnt = 0;
		for ($i = 0; $i < $anz_lines; $i++) {
			$lobj = $fdata->get_data[$i];
			if ($lobj->type != 'val') continue;	// Ignore Messages, etc..
			$lex = explode(' ', $lobj->line); // Line in KAN:VAL - Array
			for ($ik = 0; $ik < count($lex); $ik++) {
				$lik = explode(':', $lex[$ik]);
				if (!strcmp($lik[0], $kno)) {
					if (!$klcnt) { // Header wenn neu
						$xlines[] = "HeaderFuerKanal $kno:$kunit\n";
						$klcnt = 1;
					}
					$dtsec = date_create($lobj->calc_ts, $tzo)->getTimestamp();
					$ldtcomp = gmdate("YmdHis", $dtsec);
					$xlines[] = $ldtcomp . "\t" . $lik[1] . "\n";  // Datum Irgendwelche Werte einbauen
					$klcnt++;
					break;
				}
			}
		}
	}

}

//------------- MAIN ---------------
header("Content-Type: text/plain; charset=UTF-8");

$dbg = 0;
$xlog = "(ipush)";
$tzo = timezone_open('UTC'); 		// Oder LOKAL - Wie ists mit Sommer-Winterzeit?
$now = time();						// one timestamp for complete run

$mtmain_t0 = microtime(true);         // for Benchmark 

$mac = @$_REQUEST['s'];
if (!isset($mac) || strlen($mac) != 16) exit_error("MAC Len");
$api_key = @$_GET['k'];				// max. 41 Chars KEY

$dpath = "../" . S_DATA . "/$mac";	// Device Path
if (@file_exists("$dpath/cmd/dbg.cmd")) {
	if (!$dbg) $dbg = 1;
}

// Check Key before loading data
if (!$dbg && (!isset($api_key) || strcmp($api_key, S_API_KEY))) {
	exit_error("API Key");
}
if ($dbg) {
	$xlog .= $_SERVER['REQUEST_URI'] . ' ';
	echo "*** ipush.php " . VERSION . " ***\n";
}

// --- START ---
$tempfile  = '../' . S_DATA . "/stemp/$mac.ftp"; // unique_string - working file
$ipar_obj = get_pcp("iparam"); // No Return on Error
if ($ipar_obj->iparam_meta->chan0_idx < 20) exit_error("No ConfigCommand in iparam");
$okreply = "OK";
$configCmd = trim($ipar_obj->iparam[19]->line);
$pdevi = @file($dpath . "/ppinfo.dat", FILE_IGNORE_NEW_LINES);
$minid = intval(@$pdevi[0]);
if (!$minid) $minid = 1;	// Index statet bei 1

if ($dbg) {
	echo "ConfigCmd: '$configCmd' minid:$minid\n";
	$xlog .= "(ConfigCmd:'$configCmd' minid:$minid)";
}

$prot = strtok($configCmd, " ");
if ($prot !== false) {
	if ($prot !== "FTP" && $prot !== "FTPSSL") {
		file_put_contents("$dpath/cmd/okreply.cmd", "Error:Unkn.Protocol");
		exit_error("Unkn.Protocol('$prot')");
	}

	// format: FULLFORMAT/dir - FULLFORMAT: CSV CSV
	$formatarr = explode('/', strtok(" "));
	$station = wildcard2name(strtok(" "));
	$fhost = strtok(" ");
	$fport = intval(strtok(" "));
	$fuser = strtok(" ");
	$fpassword = strtok(" ");

	$fullformat = @$formatarr[0];
	$sdir = @$formatarr[1]; // NULL if not set.
	$format = strtok($fullformat, '-'); // Main Format
	$subformat = strtok('-'); // 'false' if not set.
	// 1. Format/Subformat -  Nur pruefen
	switch ($format) {
		case 'CSV':	// OK: CSV and CSV-0
			$defext = "csv";
			if ($subformat !== false && $subformat !== '0') unset($format);
			break;
		case 'ZRXP':	// Legacy ZRXP
			$defext = "zrxp";
			if ($subformat !== false) unset($format); // Keine Subformate
			break;
		case 'MIS':	// Legacy MIS
			$defext = "mis";
			if ($subformat !== false) unset($format); // Keine Subformate
			break;
		default:
			unset($format);
	}
	if (!isset($format)) {
		file_put_contents("$dpath/cmd/okreply.cmd", "Error:Unkn.Format");
		exit_error("Unkn.Format('$fullformat')");
	}

	//echo "Protocol: '$prot' Format: '$format'/'$subformat' Station: '$station' Host:$fhost:$fport $fuser/$fpassword\n";

	$fdata = get_pcp("getdata&minid=$minid");
	// 2. Konvertieren
	switch ($format) {
		case 'CSV':
			convert2csv($subformat);
			break;
		case 'ZRXP':	// Legacy ZRXP
			convert2zxrp();
			break;
		case 'MIS':	// Legacy MIS
			convert2mis();
			break;
	}

	$tanz = count($xlines);
	$xlog .= "($tanz Data Lines)";

	//print_r($xlines);  die("*#####DBG END#####*"); // #### DBG: Output & Stop ####

	file_put_contents($tempfile, $xlines); // Fkt OK for array

	$sslflag = ($prot == "FTPSSL");
	if (strpos($station, '.') == false) $station .= '.' . $defext;

	transfer_ftp($prot, $tempfile, $sdir, $station, $fhost, $sslflag, $fport, $fuser, $fpassword);
	@unlink($tempfile);
	$okreply = "$prot:OK";
	$minid = $minid + $fdata->get_count;
} else {
	$minid = $ipar_obj->overview->max_id + 1;	// Ignore
}

file_put_contents("$dpath/cmd/okreply.cmd", $okreply); // Hat funktioniert
file_put_contents($dpath . "/ppinfo.dat", $minid);

// --- END ---
$mtrun = round((microtime(true) - $mtmain_t0) * 1000, 4);
$xlog .= "(Run:$mtrun msec)"; // Script Runtime

echo "*IPUSH(DBG:$dbg) RES: ('$xlog')*\n"; // Always
add_logfile($xlog); // Regular exit, entry in logfile should be first
//