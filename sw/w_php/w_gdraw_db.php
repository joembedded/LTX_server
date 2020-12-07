<?php
//** w_gdraw_db.php; Get Data from Device-TABLE. User verifiedy by DB (token or session) **

header('Content-Type: text/plain');
require_once("../inc/w_xstart.inc.php");	// INIT everything

/* Just for Info/Debug - Enable in g_draw.js
	$ajt=@$_REQUEST['ajt'];	// Miliseconds since 1970
	$aid=@$_REQUEST['aid']; // Counts up each call
	*/

$now = time();
$dbg = 0;	// Biser noch ohne Fkt.

$last_seen = $device['last_seen'];
if (!$last_seen) {
	echo "#ERROR: No Data\n";
	exit();
}
$mdate = strtotime($last_seen); // invers: echo date("Y-m-d H:i:s", $mdate);	
echo "#MDATE: $mdate\n";	// Delta
echo "#NOW: $now\n";
if (isset($_REQUEST['mk'])) {
	echo "#MK: " . MAPKEY . "\n";	// APIKey MAP

	// If current GPS-Position is unknown: Use latest Cell Position
	if (strpos($device['vals'], "NoGPSValue") > 0 || strpos($device['vals'], "Lat") == false) {
		$gps_age =  strtotime($device['last_gps']) - strtotime($device['last_seen']); // OK for NULL
		if ($gps_age < 9 || strlen($device['lat']) < 1 || strlen($device['lng']) < 1) {	// Update GPS-Data required
			$devi = array();
			$lines = file("../" . S_DATA . "/$mac/device_info.dat", FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
			if ($lines !== false) {
				foreach ($lines as $line) {
					$tmp = explode("\t", $line);
					$devi[$tmp[0]] = $tmp[1];
				}
				$cella = explode(" ", @$devi['signal']); // Cell-Infos as Array
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
					echo "#ERROR(internal): curl:'" . curl_error($ch) . "'\n";
				}
				curl_close($ch);
				$obj = @json_decode($result);
				if (strcmp($obj->code, "OK")) {
					echo "#ERROR(internal): CELLOC: " . $obj->code . "," . $cres . $obj->info . "\n";
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
		echo "#LAT: " . $device['lat'] . "\n"; // Last Cell Position, set by trigger/main via device_info.dat -> signal
		echo "#LNG: " . $device['lng'] . "\n";
		echo "#RAD: " . $device['rad'] . "\n";
	}
}

$anz = 0;	// Assume no Change
if ($mdate != $cdate) { // Unchanged! Send all, else only #MDATE
	//echo "#Sag Hallo\n"; // Option for Info...(eg. Alarm etc???=
	// Minimum Header 
	echo "<MAC: $mac>\n";
	$dname = $device['name'];
	if (strlen($dname)) echo "<NAME: $dname>\n";
	$units = @$device['units']; // As String, Space separated
	echo "!U $units\n";

	/* Get lines: Limited No, 0 if Table not exists */
	if ($limit >= 0) $sql = "SELECT id,dataline FROM (  SELECT * FROM m$mac ORDER BY id DESC LIMIT $limit )Var1  ORDER BY id";
	else $sql = "SELECT id,dataline FROM m$mac";
	$statement = $pdo->prepare($sql);
	$statement->execute();
	$anz = $statement->rowCount(); // No of matches
	for ($i = 0; $i < $anz; $i++) {
		$user_row = $statement->fetch();
		echo $user_row['id'] . ' ' . $user_row['dataline'] . "\n";
	}

	if ($auth < 0) echo "<WARNING: External Use with Owner Token>\n";

	// Optional Info
	$mtrun = round((microtime(true) - $mtmain_t0) * 1000, 4);
	echo '<' . $anz . ' Lines (' . $mtrun . " msec)>\n";
}
	//sleep(5); // Timeout-Test

	/*
	$log=@fopen("intern/log.txt",'a');
	if($log){                
		// UNIX-Daytime *1k
		$ajtrel=($now*1000-$ajt);
		$lc=0;
		while(!flock($log,LOCK_EX)) {
			usleep(10000);  // Lock File - Is a MUST
			$lc++;
		}
		fputs($log,gmdate("d.m.y H:i:s ",$now).$_SERVER['REMOTE_ADDR']." ");        // Write file
		fwrite($log,"$mac [$aid/$ajtrel/$lc]->$anz\n");
		fclose($log);
	}else{
		echo "<ERROR: Logfile?>\n";
	}
	*/
