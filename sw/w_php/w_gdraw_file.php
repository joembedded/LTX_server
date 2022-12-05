<?php
//** w_gdraw_file.php; Get Data from Device-FILE. User verifiedy by DB (token or session) **
//* Used by g_draw.js and gps_view.js

header('Content-Type: text/plain');
require_once("../inc/w_xstart.inc.php");	// INIT everything

/* Just for Info/Debug - Enable in g_draw.js
	$ajt=@$_REQUEST['ajt'];	// Miliseconds since 1970
	$aid=@$_REQUEST['aid']; // Counts up each call
	*/

$fname = @$_REQUEST['file'];	// Filename (opt.)
if (!strlen($fname)) $fname = "data.edt";	// Use Default
$now = time();
$dbg = 0;	// Biser noch ohne Fkt.

$filename = "../" . S_DATA . "/$mac/files/$fname";
if (!file_exists($filename)) {
	echo "#ERROR: File does not exist\n";
	exit();
}

$mdate = filemtime($filename);
echo "#MDATE: $mdate\n";	// Delta
echo "#NOW: $now\n";
if (isset($_REQUEST['mk'])) {
	echo "#MK: " . MAPKEY . "\n";	// APIKey MAP
}

$anz = 0;	// Assume no Change
if ($mdate != $cdate) { // Unchanged! Send all, else only #MDATE
	//echo "#Sag Hallo\n"; // Option for Info...(eg. Alarm etc???=
	echo "<MAC: $mac>\n";
	echo "<NAME: '$fname'>\n";
	$lines = file($filename, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
	$anz = count($lines);
	$x0 = 0;
	if ($limit >= 0) {
		if ($anz > $limit) {
			$x0 = $anz - $limit;
			$anz = $limit;
		}
	}
	for ($i = 0; $i < $anz; $i++) {
		echo $lines[$i + $x0] . "\n";
	}

	if ($auth < 0) echo "<WARNING: External Use with Owner Token>\n";

	// Optional Info
	$mtrun = round((microtime(true) - $mtmain_t0) * 1000, 4);
	echo '<' . $anz . ' Lines (' . $mtrun . " msec)>\n";
}

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
