<?php
/*******************************
 * w_pull_db.php
 *
 * Daten eines Loggers pullen, sofern erlaubt. Wenn erlaubt, kann ueber dieses Script alles
 * abgefragt werden was moeglich ist. Hier erstmal nur eine Minimalversion, Script aehnlich
 * zu w_gdraw_db.php.
 * Infos/Fragen/Wuensche: Jo
 *
 * Stand: V1.0 25.01.2023
 *
 * Bsp.: http://localhost/ltx/sw/w_php/w_pull_db.php?s=A000000002CF8D53&delta&m=1674575118&lim=-1
 * Bsp.: http://localhost/ltx/sw/w_php/w_pull_db.php?s=A000000002CF8D53&k=AccessToken&lim=10
 *
 * s=MAC
 * m=sec		Letztes bekanntes Mod. Datum (#MDATE)
 * delta  	Wenn gesetzt nur Delta-Werte
 * lim=anz	Anzahl der maximal auszugebenden Zeilen, <0: ALLE, >=0 anz (lim per Default=0)
 * k=ACCESSTOKEN	Access Token aus '../$mac/quota_days.dat'
 * 
 * Optionen:
 * lts=1/2 	'line_ts' aus DB: Line Timestamp (Einfuege-Zeitstempel anzeigen
 *                    1:ISO-Format, 2: als UNIX Timestamp) daraus 'm' und '#MDATE'
 * cts=1/2 	 'calc_ts' aus DB: Kalkulierte Zeitstempel (entspricht !xxxx der Zeile, 
 *                    aber separate Spalte in der DB fuer Verwaltung)
 *                    (1:ISO-Format, 2: als UNIX Timestamp)
 */

error_reporting(E_ALL);
$mtmain_t0 = microtime(true);         // for Benchmark 

session_start();
require_once("../conf/config.inc.php");	// DB Access 
require_once("../conf/api_key.inc.php"); // APIs
require_once("../inc/db_funcs.inc.php"); // Init DB

header('Content-Type: text/plain');

try {
	$now = time();
	$tzo = timezone_open('UTC');

	$mac = @$_REQUEST['s'];	// s always MAC (k: API-Key, r: Reason)
	if (!isset($mac)) $mac = "";
	if (strlen($mac) != 16) {
		echo "#ERROR: MAC len\n";
		exit();
	}
	$mac = strtoupper($mac);

	// Check Access-Token for this Device
	$quota = @file("../" . S_DATA . "/$mac/quota_days.dat", FILE_IGNORE_NEW_LINES);
	if (isset($quota[2]) && strlen($quota[2])) {
		$qpar = explode(' ', trim(preg_replace('/\s+/', ' ', $quota[2])));
		if (count($qpar) >= 2) {
			$akey = @$_REQUEST['k'];	// s always MAC (k: API-Key, r: Reason)
			if (!isset($akey) || $akey !== $qpar[1]) {
				echo "#ERROR: No Access\n";
				exit();
			}
		}
	}

	db_init();

	$statement = $pdo->prepare("SELECT * FROM `devices` WHERE mac=?");
	$statement->execute(array($mac));
	$anz = $statement->rowCount(); // No of matches
	if ($anz != 1) {
		echo "#ERROR: MAC unknown";
		exit();
	} // MAC Unknown
	$device = $statement->fetch();	// DEVICE data for MAC

	$cdate = @$_REQUEST['m']; 	// Last known Modification Date
	if (!isset($cdate)) $cdate = "";

	$limit = intval(@$_REQUEST['lim']);	// Limit of Datasets (if set, else: maximum, MUST be set)

	$deltasel = @$_REQUEST['delta'];

	$last_seen = $device['last_seen'];  // Letztes Modifikationsdatum 
	if (!$last_seen) {
		echo "#ERROR: No Data\n";
		exit();
	}

	$mdate = date_create($last_seen)->getTimestamp(); // Unix TS aus Datum erz.
	echo "#MDATE: $mdate\n";	// Delta
	echo "#NOW: $now\n";

	$anz = 0;	// Assume no Change
	if ($mdate != $cdate) { // Unchanged! Send all, else only #MDATE // normally cdate <= mdate!
		// Minimum Header 
		$lts=intval(@$_REQUEST['lts']); //OPt &lts=1/2 Line Timestamp
		$cts=intval(@$_REQUEST['cts']); //OPt &cts=1/2 Calc Timestamp

		$cookie = $device['cookie'];	// Informativ
		echo "#COOKIE: $cookie\n";	// Current Cookie

		echo "<MAC: $mac>\n";
		$dname = @$device['name'];
		if (!isset($dname)) $dname = "(Unknown)";
		if (strlen($dname)) echo "<NAME: $dname>\n";
		$units = @$device['units']; // As String, Space separated
		echo "!U $units\n";

		// Gen SQL  query
		$lmac = strtolower($mac);
		$innersel = "m$lmac";
		if (isset($deltasel)) {
			if ($cdate > 0) $innersel .= " WHERE line_ts >= FROM_UNIXTIME( $cdate ) ";
			else echo "<WARNING: Opt. 'delta' needs 'm'>\n";
		}
		if ($limit >= 0) $innersel = "( SELECT * FROM $innersel ORDER BY id DESC LIMIT $limit )Var1";
		/* Get lines: Limited No, 0 if Table not exists, sort if HK101 ' 101:Travel(sec)' found */
		if (strpos($units, " 101:Travel(sec)")) {	// Packet based Devivce - Packets may arrive in wrong oder! ALways ORDER
			if ($limit >= 0) $sql = "SELECT * FROM $innersel ORDER BY calc_ts,id";
			else $sql = "SELECT * FROM $innersel ORDER BY calc_ts,id";
		} else {	// Logger with Bidirectional connection
			if ($limit >= 0) $sql = "SELECT * FROM $innersel ORDER BY id";
			else $sql = "SELECT * FROM $innersel";
		}
		$statement = $pdo->prepare($sql);
		$statement->execute();
		$anz = $statement->rowCount(); // No of matches
		for ($i = 0; $i < $anz; $i++) {
			$user_row = $statement->fetch();
			if($lts) {		
				$lval = $user_row['line_ts']; // lts=1: Standart
				if($lts>1) $lval = date_create($lval)->getTimestamp(); // lts=2: UNIX TS
				echo $lval.' ';
			}
			if($cts) {
				$cval = $user_row['calc_ts']; // cts=1: Standart
				if($cts>1) {
					$cval = date_create($cval)->getTimestamp(); // cts=2: UNIX TS
					if($cval<0) $cval = 0; 	// Komp. mit UNIX TS
				}
				echo $cval.' ';
			}
			echo $user_row['id'] . ' ' . $user_row['dataline'] . "\n";
		}
		// Optional Info
		$mtrun = round((microtime(true) - $mtmain_t0) * 1000, 4);
		echo '<' . $anz . ' Lines (' . $mtrun . " msec)>\n";
	}
} catch (Exception $e) {
	exit("FATAL ERROR: '" . $e->getMessage() . "'\n");
}
