<?php

/** w_gdraw_db.php; Get Data from Device-TABLE. User verifiedy by DB (token or session) **
 *
 * Die DB ist im Prinzip extrem simple aufgebaut: 
 * - Pro Logger gibt es einen Eintrag in der Tabelle devices und/oder guest_devices
 * - Die Daten der Logger stehen zeilenweis in der Tabelle mMAC (MAC: 16 Digits)
 *
 * MDATE: Last seen TS
 * NOW: Current TS (only for info)
 * COOKIE: TS of Parameter set 'iparam.lxp'
 * $cdate (TS via m=), (normally) lower than < MDATE
 * lim: >=0: Count, or <0: ALL
 * cmd: here unused
 *
 * 25.01.2023 JoEm
 */

header('Content-Type: text/plain');
require_once("../inc/w_xstart.inc.php");	// INIT everything

try {

	$now = time();
	$dbg = 0;	// Bisher noch ohne Fkt.

	$last_seen = $device['last_seen'];
	if (!$last_seen) {
		echo "#ERROR: No Data\n";
		exit();
	}
	$cookie = $device['cookie'];

	$mdate = strtotime($last_seen); // invers: echo date("Y-m-d H:i:s", $mdate);	
	echo "#MDATE: $mdate\n";	// Delta
	echo "#NOW: $now\n";
	if (isset($_REQUEST['mk'])) {
		echo "#MK: " . MAPKEY . "\n";	// APIKey MAP
		// If current GPS-Position is unknown: Use latest Cell Position 
		if (isset($device['vals']) && ((strpos($device['vals'], "NoGPSValue") > 0 || strpos($device['vals'], "Lat")) == false) && isset($device['last_gps']) && isset($device['last_seen'])) {
			$gps_age =  strtotime($device['last_gps']) - strtotime($device['last_seen']); // OK for NULL
			if ($gps_age < 9 || strlen($device['lat']) < 1 || strlen($device['lng']) < 1) {	// Update GPS-Data required
				$devi = array();
				$lines = file("../" . S_DATA . "/$mac/device_info.dat", FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
				if ($lines !== false) {
					foreach ($lines as $line) {
						$tmp = explode("\t", $line);
						$devi[$tmp[0]] = $tmp[1];
					}
					if (isset($devi['signal'])) {
						$cella = explode(" ", $devi['signal']); // Cell-Infos as Array
						$cell = array(); // KV-Array
						foreach ($cella as $kv) {
							$tmp = explode(":", $kv);
							$cell[$tmp[0]] = $tmp[1];
						}
						// Now Call Cellserver
						$sqs = CELLOC_SERVER_URL . '?k=' . G_API_KEY . "&s=$mac&mcc=" . $cell['mcc'] . "&net=" . $cell['net'] . "&lac=" . $cell['lac'] . "&cid=" . $cell['cid'];
						$ch = curl_init($sqs);
						curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
						$result = curl_exec($ch);
						if (curl_errno($ch)) {
							echo "#INFO: CELLOC: '$sqs'\n"; // Display Query String on Failure
							echo "#ERROR(internal): curl:'" . curl_error($ch) . "'\n";
						}
						curl_close($ch);
						$obj = @json_decode($result);
						if (strcmp($obj->code, "OK")) {
							echo "#INFO: CELLOC: '$sqs'\n";
							echo "#ERROR(internal): CELLOC: " . $obj->code . "," . $obj->info . "\n";
						} else {
							$nlat = $obj->lat;
							$nlon = $obj->lon;
							$nrad = $obj->accuracy;
							// Use new Data and update DB
							$device['lat'] = $nlat;
							$device['lng'] = $nlon;
							$device['rad'] = $nrad;
							$insert_sql = "UPDATE devices SET lat = $nlat, lng = $nlon, rad = $nrad, last_gps=NOW(), last_change=NOW() WHERE mac ='$mac'";
							$qres = $pdo->exec($insert_sql); // return 0 for no match
							if ($qres === false) {
								echo "#ERROR(internal): " . $pdo->errorInfo()[2] . "\n";
							}
						}
					}
				}
			}
			echo "#LAT: " . $device['lat'] . "\n"; // Last Cell Position, set by trigger/main via device_info.dat -> signal
			echo "#LNG: " . $device['lng'] . "\n";
			echo "#RAD: " . $device['rad'] . "\n";
		}
	}

	$anz = 0;	// Assume no Change
	//echo "MDATE: $mdate  CDATE: $cdate\n";
	if ($mdate != $cdate) { // Unchanged! Send all, else only #MDATE // normally cdate <= mdate!

		// Minimum Header 
		$lts = intval(@$_REQUEST['lts']); //OPt &lts=1/2 Line Timestamp
		$cts = intval(@$_REQUEST['cts']); //OPt &cts=1/2 Calc Timestamp

		$deltasel = @$_REQUEST['delta'];
		//echo "#COOKIE: $cookie\n";	// Current Cookie (not req.)

		echo "<MAC: $mac>\n";
		$dname = @$device['name'];
		if (!isset($dname)) $dname = "(Unknown)";
		if (strlen($dname)) echo "<NAME: $dname>\n";
		$units = @$device['units']; // As String, Space separated
		echo "!U $units\n";

		// Gen SQL 
		$innersel = "m$mac";
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

		//echo "<SQL: '$sql'>\n";
		$tzo = timezone_open('UTC');

		$statement = $pdo->prepare($sql);
		$statement->execute();
		$anz = $statement->rowCount(); // No of matches
		for ($i = 0; $i < $anz; $i++) {
			$user_row = $statement->fetch();
			if ($lts) {
				$lval = $user_row['line_ts']; // lts=1: Standart
				if ($lts > 1) $lval = date_create($lval)->getTimestamp(); // lts=2: UNIX TS
				echo $lval . ' ';
			}
			if ($cts) {
				$cval = $user_row['calc_ts']; // cts=1: Standart
				if ($cts > 1) {
					$cval = date_create($cval)->getTimestamp(); // cts=2: UNIX TS
					if ($cval < 0) $cval = 0; 	// Komp. mit UNIX TS
				}
				echo $cval . ' ';
			}

			echo $user_row['id'] . ' ' . $user_row['dataline'] . "\n";
		}
		if ($auth < 0) echo "<WARNING: External Use with Owner Token>\n";
		// Optional Info
		$mtrun = round((microtime(true) - $mtmain_t0) * 1000, 4);
		echo '<' . $anz . ' Lines (' . $mtrun . " msec)>\n";
	}
} catch (Exception $e) {
	exit("FATAL ERROR: '" . $e->getMessage() . "'\n");
}
