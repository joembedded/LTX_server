<?PHP
// -------------------------------------------------------------------
// con_view.php - Connection Viewer (filtered)
// 14.12.2024

error_reporting(E_ALL);
include("../sw/conf/api_key.inc.php");
include("mcclist.inc.php");

// ---------------------------- M A I N --------------
session_start();
if (isset($_REQUEST['k'])) {
	$api_key = $_REQUEST['k'];
	$_SESSION['key'] = L_KEY;
} else $api_key = @$_SESSION['key'];
if (!strcmp($api_key, L_KEY)) {
	$dev = 1;	// Dev-Funktionen anzeigen
} else {
	$dev = 0;	// Dev-Funktionen anzeigen
}
if (!$dev) {
	echo "ERROR: Access denied!";
	exit();
}



$mac = $_GET["s"];
$fname = $_GET["f"];

$rfile = S_DATA . "/$mac/$fname";

$ext = substr($fname, strpos($fname, '.'));
if (strcasecmp($ext, ".txt")) {
	header('Content-Type: text/plain');
	echo "<ERROR: Not allowed Format>\n"; // Filter illegal formats (Never display contents of PHP)
} else if (strpos("$mac/$fname", "..") || !file_exists($rfile)) { // prevent upper dirs
	header('Content-Type: text/plain');
	echo "<ERROR: File not found: '$fname' (maybe too old?)>\n";
} else {
	$dataa =  file($rfile,FILE_IGNORE_NEW_LINES);
	$anz = count($dataa);
	echo "<!DOCTYPE HTML><html>	<style>	table, th, td {	  border: 1px solid black;	 border-collapse: collapse;	} </style>	<body>";

	echo "---------------- Connections: '$mac/$fname': $anz Lines: --------------<br>";
	// Scan connected Cells:
	$cpcache=array();
	foreach($dataa as $line){
		$mccx=@strpos($line,"mcc:");
		$netx=@strpos($line,"net:");
		$lacx=@strpos($line,"lac:");
		$cidx=@strpos($line,"cid:");
		$acx=strpos($line,"act:");

		$mcc=intval(substr($line,$mccx+4));
		$net=intval(substr($line,$netx+4));
		$lac=intval(substr($line,$lacx+4));
		$cid=intval(substr($line,$cidx+4));
		$act=($acx>0)?intval(substr($line,$acx+4)):0;
		if($act==2 || $act==3) $act=1;	// Allg. 2G
		$ha="$mcc:$net:$lac:$cid:$act";
		@$cpcache[$ha]++;
	}


	echo "<table>";
	echo "<tr> <th>Cells (".count($cpcache).")</th> <th>Used</th> <th>Pos</th> </tr>";
	$i=1;
	foreach($cpcache as $cell=>$ccnt){
		$ccom=explode(":",$cell);
		$mcc=$ccom[0];
		$net=$ccom[1];
		$lac=$ccom[2];
		$cid=$ccom[3];
		$act=$ccom[4];
		$acts = array("No/unkn.", "2G", "_GPRS", "_EDGE", "LTE_M", "LTE_NB", "_LTE");
		$actn=@$acts[$act];

		$country=@$mcca[$mcc];
		if(!$country) $country=$mcca[intval($mcc/100)]; // Fallback

		$sqs = 'k='.G_API_KEY."&s=$mac&lnk=1&mcc=$mcc&net=$net&lac=$lac&cid=$cid"; // Link
		echo "<tr> <td>$i: $mcc-$net-$lac-$cid ($actn) (<i>$country</i>) </td> <td> $ccnt </td> <td><a target='_blank' href=\"" . CELLOC_SERVER_URL . "?$sqs\">[Here]</a></td> </tr>";
		$i++;
	}
	echo "</table>";


	$lcnt=0;
	$ccnt=1;
	foreach($dataa as $line){
		$lcnt++;
		echo "$lcnt: ";

		$mccx=strpos($line,"mcc:");
		$netx=strpos($line,"net:");
		$lacx=strpos($line,"lac:");
		$cidx=strpos($line,"cid:");
		$tax=strpos($line,"ta:");
		$dbx=strpos($line,"dbm:");
		$acx=strpos($line,"act:");
		$mcc=intval(substr($line,$mccx+4));
		$net=intval(substr($line,$netx+4));
		$lac=intval(substr($line,$lacx+4));
		$cid=intval(substr($line,$cidx+4));

		$dbm=($dbx>0)?intval(substr($line,$dbx+4)):0;
		$act=($acx>0)?intval(substr($line,$acx+4)):0;
		if($act==2 || $act==3) $act=1;	// Allg. 2G
		$ha="$mcc:$net:$lac:$cid:$act";	
		if($cpcache[$ha]>0){	// Jede Zelle nur EINMAL anzeigen
			$utc=substr($line,0,$mccx);
			echo "$utc mcc:$mcc net:$net lac:$lac cid:$cid ";

			if($dbm!=0) echo "dbm:$dbm ";
			if($act){
				$acts = array("No/unkn.", "2G", "_GPRS", "_EDGE", "LTE_M", "LTE_NB", "_LTE");
				$actn=@$acts[$act];
				echo "($actn) ";
			}

			$ta=intval(substr($line,$tax+3));
			if($ta==255) $tar="";
			else $tar= " ca. ".($ta*500+500)."mtr" ;	// in m

			$sqs = 'k='.G_API_KEY."&s=$mac&lnk=1&mcc=$mcc&net=$net&lac=$lac&cid=$cid"; // Link
	

			echo " &nbsp; Cell($ccnt):" . $tar . " arround <a href=\"" . CELLOC_SERVER_URL . "?$sqs\">[Here]</a>";


		/* Ask DB for each line is SLOW 
		$sqs = 'k='.G_API_KEY."&s=$mac&lnk=0&mcc=$mcc&net=$net&lac=$lac&cid=$cid"; // No Link
		$xresp=file_get_contents(CELLOC_SERVER_URL . "?$sqs");
		echo $xresp;
		*/		
			$cpcache[$ha]=-$ccnt;
			$ccnt++;
		}else{
			$ncell=-$cpcache[$ha];
			$utc=substr($line,0,$mccx);
			echo "$utc ";
			if($dbm!=0) echo "dbm:$dbm ";

			$ta=intval(substr($line,$tax+3));
			if($ta!=255) echo " ca. ".($ta*500+500)."mtr" ;	// in m

			echo "(Cell($ncell))";	// Schon gezeigt, nur neue Zeit
		}
		echo "<br>";
	}
	
	echo "-----END ------<br>";
	echo "</body></html>";
}
